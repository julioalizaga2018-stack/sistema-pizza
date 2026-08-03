<?php
// models/PedidoModelo.php (Parte 1 de 2 - Jungle POS Engine)
require_once __DIR__ . '/../config/conexion.php';

class PedidoModelo extends Conexion {
    private $db;

    public function __construct() {
        $this->db = $this->conectar();
    }

    /**
     * 🍕 APERTURA: Inserta la cabecera del pedido calculando el tipo de canal
     */
    public function abrirNuevaComanda($usuario_id, $caja_turno_id, $mesa_id = null, $tipo_pedido = 'local', $monto_envio = 0.00) {
        try {
            $this->db->beginTransaction();
            $id_mesa = ($tipo_pedido === 'local') ? $mesa_id : null;
            
            $sql = "INSERT INTO pedidos (usuario_id, caja_turno_id, mesa_id, tipo_pedido, monto_envio, estado, total) 
                    VALUES (:usuario_id, :caja_turno_id, :mesa_id, :tipo_pedido, :monto_envio, 'pendiente', 0.00)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'usuario_id' => $usuario_id,
                'caja_turno_id' => $caja_turno_id,
                'mesa_id' => $id_mesa,
                'tipo_pedido' => $tipo_pedido,
                'monto_envio' => $monto_envio
            ]);
            $pedido_id = $this->db->lastInsertId();

            if ($tipo_pedido === 'local' && $id_mesa > 0) {
                $sqlMesa = "UPDATE mesas SET estado = 'ocupada' WHERE id = :mesa_id";
                $stmtMesa = $this->db->prepare($sqlMesa);
                $stmtMesa->execute(['mesa_id' => $id_mesa]);
            }
            
            $this->db->commit();
            return $pedido_id;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * 🍕 CARGAR ÍTEM: Cada producto nuevo en la tablet nace de forma estricta en 'solicitado'
     */
    public function agregarItemAPedido($pedido_id, $producto_id, $cantidad, $precio_unitario, $es_mixta = 0) {
        $subtotal = $cantidad * $precio_unitario;
        $flag_mixta = (int)$es_mixta;
        
        $sql = "INSERT INTO pedido_detalles (pedido_id, producto_id, cantidad, precio_unitario, subtotal, es_mixta, estado) 
                VALUES (:pedido_id, :producto_id, :cantidad, :precio_unitario, :subtotal, :es_mixta, 'solicitado')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'pedido_id' => $pedido_id,
            'producto_id' => $producto_id,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio_unitario,
            'subtotal' => $subtotal,
            'es_mixta' => $flag_mixta
        ]);
        return $this->db->lastInsertId();
    }

    /**
     * 🧀 AMARRAR EXTRA: Vincula un ingrediente adicional a un renglón del pedido
     */
    public function agregarExtraAItem($pedido_detalle_id, $producto_id, $cantidad, $precio_cobrado) {
        $sql = "INSERT INTO pedido_detalle_extras (pedido_detalle_id, producto_id, cantidad, precio_cobrado) 
                VALUES (:pedido_detalle_id, :producto_id, :cantidad, :precio_cobrado)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'pedido_detalle_id' => $pedido_detalle_id,
            'producto_id' => $producto_id,
            'cantidad' => $cantidad,
            'precio_cobrado' => $precio_cobrado
        ]);
    }
    /**
     * 🔄 RECALCULAR TOTALES: Suma los productos activos y calcula propinas locales del 10%
     */
    public function actualizarTotalesPedido($pedido_id) {
        $sqlItems = "SELECT SUM(subtotal) as sub_total FROM pedido_detalles 
                     WHERE pedido_id = :pedido_id AND estado NOT IN ('quitado_antes', 'quitado_despues')";
        $stmtItems = $this->db->prepare($sqlItems);
        $stmtItems->execute(['pedido_id' => $pedido_id]);
        $subtotal_items = floatval($stmtItems->fetch()['sub_total'] ?? 0.00);

        $sqlExtras = "SELECT SUM(pde.cantidad * pde.precio_cobrado) as sub_extras 
                      FROM pedido_detalle_extras pde
                      INNER JOIN pedido_detalles pd ON pde.pedido_detalle_id = pd.id
                      WHERE pd.pedido_id = :pedido_id AND pd.estado NOT IN ('quitado_antes', 'quitado_despues')";
        $stmtExtras = $this->db->prepare($sqlExtras);
        $stmtExtras->execute(['pedido_id' => $pedido_id]);
        $subtotal_extras = floatval($stmtExtras->fetch()['sub_extras'] ?? 0.00);

        $subtotal_neto = $subtotal_items + $subtotal_extras;

        $sqlInfo = "SELECT tipo_pedido, monto_envio FROM pedidos WHERE id = :pedido_id LIMIT 1";
        $stmtInfo = $this->db->prepare($sqlInfo);
        $stmtInfo->execute(['pedido_id' => $pedido_id]);
        $pedidoInfo = $stmtInfo->fetch();
        
        $tipo = $pedidoInfo['tipo_pedido'];
        $monto_envio = floatval($pedidoInfo['monto_envio']);
        $monto_propina = ($tipo === 'local') ? ($subtotal_neto * 0.10) : 0.00;
        $total_final = $subtotal_neto + $monto_envio + $monto_propina;

        $sqlUpdate = "UPDATE pedidos SET total = :total, monto_propina = :monto_propina WHERE id = :pedido_id";
        $stmtUpdate = $this->db->prepare($sqlUpdate);
        return $stmtUpdate->execute([
            'total' => $total_final,
            'monto_propina' => $monto_propina,
            'pedido_id' => $pedido_id
        ]);
    }

    /**
     * 🛠 AUDITORÍA DE ÍTEMS QUITADOS: Registra el descarte en la comanda
     */
    public function modificarEstadoItem($detalle_id, $nuevo_estado, $motivo) {
        $sql = "UPDATE pedido_detalles SET estado = :estado, motivo_quitar = :motivo WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'estado' => $nuevo_estado,
            'motivo' => $motivo,
            'id' => $detalle_id
        ]);
    }

    /**
     * 🍕 PIZZAS MIXTAS: Registra las mitades en la tabla relacional de sabores
     */
    public function agregarMitadesAPizzaMixta($pedido_detalle_id, $sabor_1_id, $sabor_2_id) {
        $sql = "INSERT INTO pedido_detalle_sabores (pedido_detalle_id, producto_id) VALUES (:pedido_detalle_id, :producto_id)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['pedido_detalle_id' => $pedido_detalle_id, 'producto_id' => $sabor_1_id]);
        return $stmt->execute(['pedido_detalle_id' => $pedido_detalle_id, 'producto_id' => $sabor_2_id]);
    }

    /**
     * 🚀 ENVIAR ORDEN A PRODUCCIÓN: Transiciona de 'solicitado' a 'pendiente' de forma masiva e indestructible
     */
    public function enviarPedidoAProduccion($pedido_id) {
        try {
            $id_limpio = intval($pedido_id);
            $this->db->beginTransaction();

            $sqlItems = "UPDATE pedido_detalles SET estado = 'pendiente' 
                         WHERE pedido_id = :pedido_id AND estado = 'solicitado'";
            $stmtItems = $this->db->prepare($sqlItems);
            $stmtItems->execute(['pedido_id' => $id_limpio]);

            $sqlHeader = "UPDATE pedidos SET updated_at = NOW() WHERE id = :pedido_id";
            $stmtHeader = $this->db->prepare($sqlHeader);
            $stmtHeader->execute(['pedido_id' => $id_limpio]);

            $this->db->commit();
            $this->actualizarTotalesPedido($id_limpio);
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * 🔄🔄🔄 OPTIMIZACIÓN MVC: TRANSFERENCIA DE MESA INDESTRUCTIBLE 🔄🔄🔄
     * Este método gestiona el cambio físico del pedido en el salón de forma 100% segura
     */
    public function transferirPedidoDeMesa($pedido_id, $mesa_origen_id, $mesa_destino_id) {
        try {
            // Iniciamos la transacción utilizando el pool del motor nativo ($this->db)
            $this->db->beginTransaction();

            // 1. Reasignar la cabecera del pedido hacia el ID de la nueva mesa seleccionada
            $sqlMover = "UPDATE pedidos SET mesa_id = :destino WHERE id = :pedido_id";
            $stmtMover = $this->db->prepare($sqlMover);
            $stmtMover->execute(['destino' => $mesa_destino_id, 'pedido_id' => $pedido_id]);

            // 2. Bloquear la mesa de destino seleccionada pasándola a estado 'ocupada'
            $sqlBloquear = "UPDATE mesas SET estado = 'ocupada' WHERE id = :destino";
            $stmtBloquear = $this->db->prepare($sqlBloquear);
            $stmtBloquear->execute(['destino' => $mesa_destino_id]);

            // 3. Liberar la mesa de origen anterior pasándola a estado 'disponible' (si aplica)
            if ($mesa_origen_id > 0) {
                $sqlLiberar = "UPDATE mesas SET estado = 'disponible' WHERE id = :origen";
                $stmtLiberar = $this->db->prepare($sqlLiberar);
                $stmtLiberar->execute(['origen' => $mesa_origen_id]);
            }

            // Consolidamos la operación en la base de datos de manera atómica
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            // Si hay un corte intermitente, revertimos los cambios para resguardar la consistencia
            $this->db->rollBack();
            return false;
        }
    }
}
?>
