<?php
// models/PedidoModelo.php
require_once __DIR__ . '/../config/conexion.php';

class PedidoModelo extends Conexion {
    private $db;

    public function __construct() {
        $this->db = $this->conectar();
    }

    // 🍕 APERTURA: Inserta la cabecera del pedido calculando el tipo de canal
    public function abrirNuevaComanda($usuario_id, $caja_turno_id, $mesa_id = null, $tipo_pedido = 'local', $monto_envio = 0.00) {
        try {
            $this->db->beginTransaction();

            // Si es delivery o retiro, la mesa es NULL forzadamente
            $id_mesa = ($tipo_pedido === 'local') ? $mesa_id : null;

            $sql = "INSERT INTO pedidos (usuario_id, caja_turno_id, mesa_id, tipo_pedido, monto_envio, estado, total) 
                    VALUES (:usuario_id, :caja_turno_id, :mesa_id, :tipo_pedido, :monto_envio, 'pendiente', 0.00)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'usuario_id'    => $usuario_id,
                'caja_turno_id' => $caja_turno_id,
                'mesa_id'       => $id_mesa,
                'tipo_pedido'   => $tipo_pedido,
                'monto_envio'   => $monto_envio
            ]);
            
            $pedido_id = $this->db->lastInsertId();

            // Si el pedido es local, congelamos la mesa cambiándola a 'ocupada' (Color Rojo)
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

           public function agregarItemAPedido($pedido_id, $producto_id, $cantidad, $precio_unitario, $es_mixta = 0) {
        $subtotal = $cantidad * $precio_unitario;
        $flag_mixta = (int)$es_mixta;

        $sql = "INSERT INTO pedido_detalles (pedido_id, producto_id, cantidad, precio_unitario, subtotal, es_mixta, estado) 
                VALUES (:pedido_id, :producto_id, :cantidad, :precio_unitario, :subtotal, :es_mixta, 'solicitado')";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'pedido_id'       => $pedido_id,
            'producto_id'     => $producto_id,
            'cantidad'        => $cantidad,
            'precio_unitario' => $precio_unitario,
            'subtotal'        => $subtotal,
            'es_mixta'        => $flag_mixta
        ]);

        // 🌟 CORRECCIÓN CRÍTICA: Devolvemos el ID real generado en MySQL en vez de un "true"
        return $this->db->lastInsertId();
    }



    // 🧀 AMARRAR EXTRA: Vincula un ingrediente adicional a una pizza o producto específico
    public function agregarExtraAItem($pedido_detalle_id, $producto_id, $cantidad, $precio_cobrado) {
        $sql = "INSERT INTO pedido_detalle_extras (pedido_detalle_id, producto_id, cantidad, precio_cobrado) 
                VALUES (:pedido_detalle_id, :producto_id, :cantidad, :precio_cobrado)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'pedido_detalle_id' => $pedido_detalle_id,
            'producto_id'       => $producto_id,
            'cantidad'          => $cantidad,
            'precio_cobrado'    => $precio_cobrado
        ]);
    }

    // 🔄 RECALCULAR TOTALES: Suma los productos activos y calcula propinas o envíos de forma automática
    public function actualizarTotalesPedido($pedido_id) {
        // 1. Sumamos los subtotales de productos que NO estén quitados
        $sqlItems = "SELECT SUM(subtotal) as sub_total FROM pedido_detalles 
                     WHERE pedido_id = :pedido_id AND estado NOT IN ('quitado_antes', 'quitado_despues')";
        $stmtItems = $this->db->prepare($sqlItems);
        $stmtItems->execute(['pedido_id' => $pedido_id]);
        $subtotal_items = floatval($stmtItems->fetch()['sub_total'] ?? 0.00);

        // 2. Sumamos los extras cobrados de esos ítems activos
        $sqlExtras = "SELECT SUM(pde.cantidad * pde.precio_cobrado) as sub_extras 
                      FROM pedido_detalle_extras pde
                      INNER JOIN pedido_detalles pd ON pde.pedido_detalle_id = pd.id
                      WHERE pd.pedido_id = :pedido_id AND pd.estado NOT IN ('quitado_antes', 'quitado_despues')";
        $stmtExtras = $this->db->prepare($sqlExtras);
        $stmtExtras->execute(['pedido_id' => $pedido_id]);
        $subtotal_extras = floatval($stmtExtras->fetch()['sub_extras'] ?? 0.00);

        $subtotal_neto = $subtotal_items + $subtotal_extras;

        // 3. Extraemos el tipo de pedido y el cargo de envío actual
        $sqlInfo = "SELECT tipo_pedido, monto_envio FROM pedidos WHERE id = :pedido_id LIMIT 1";
        $stmtInfo = $this->db->prepare($sqlInfo);
        $stmtInfo->execute(['pedido_id' => $pedido_id]);
        $pedidoInfo = $stmtInfo->fetch();

        $tipo = $pedidoInfo['tipo_pedido'];
        $monto_envio = floatval($pedidoInfo['monto_envio']);
        $monto_propina = 0.00;

        // Regla de negocio: Si es consumo local, calcula el 10% automático de propina
        if ($tipo === 'local') {
            $monto_propina = $subtotal_neto * 0.10;
        }

        $total_final = $subtotal_neto + $monto_envio + $monto_propina;

        // 4. Guardamos los cálculos financieros finales en la cabecera
        $sqlUpdate = "UPDATE pedidos SET total = :total, monto_propina = :monto_propina WHERE id = :pedido_id";
        $stmtUpdate = $this->db->prepare($sqlUpdate);
        return $stmtUpdate->execute([
            'total'         => $total_final,
            'monto_propina' => $monto_propina,
            'pedido_id'     => $pedido_id
        ]);
    }

    // 🛠️ AUDITORÍA DE ÍTEMS QUITADOS: Quita un plato evaluando si ya se sirvió para registrar la merma
    public function modificarEstadoItem($detalle_id, $nuevo_estado, $motivo) {
        $sql = "UPDATE pedido_detalles SET estado = :estado, motivo_quitar = :motivo WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'estado' => $nuevo_estado,
            'motivo' => $motivo,
            'id'     => $detalle_id
        ]);
    }
           // 🍕 PIZZAS MIXTAS: Registra las mitades insertando dos filas en la columna única 'producto_id'
    public function agregarMitadesAPizzaMixta($pedido_detalle_id, $sabor_1_id, $sabor_2_id) {
        $sql = "INSERT INTO pedido_detalle_sabores (pedido_detalle_id, producto_id) 
                VALUES (:pedido_detalle_id, :producto_id)";
        $stmt = $this->db->prepare($sql);

        // Inserción de la Mitad A
        $stmt->execute([
            'pedido_detalle_id' => $pedido_detalle_id,
            'producto_id'       => $sabor_1_id
        ]);

        // Inserción de la Mitad B
        return $stmt->execute([
            'pedido_detalle_id' => $pedido_detalle_id,
            'producto_id'       => $sabor_2_id
        ]);
    }
                /**
     * 🚀 ENVIAR ORDEN (MESERO): Mueve los borradores de 'solicitado' a 'pendiente' (Cola KDS)
     */
    public function enviarPedidoAProduccion($pedido_id) {
        try {
            $this->db->beginTransaction();

            // Pasamos masivamente de borrador a la cola de espera de cocina
            $sqlItems = "UPDATE pedido_detalles 
                         SET estado = 'pendiente' 
                         WHERE pedido_id = :pedido_id AND estado = 'solicitado'";
            
            $stmtItems = $this->db->prepare($sqlItems);
            $stmtItems->execute(['pedido_id' => intval($pedido_id)]);

            // Congelamos las finanzas en la cabecera ignorando las cancelaciones
            $sqlHeader = "UPDATE pedidos 
                          SET total = COALESCE((SELECT SUM(subtotal) FROM pedido_detalles WHERE pedido_id = :pedido_id AND estado NOT IN ('quitado_antes', 'quitado_despues')), 0),
                              updated_at = NOW() 
                          WHERE id = :pedido_id";
            $stmtHeader = $this->db->prepare($sqlHeader);
            $stmtHeader->execute(['pedido_id' => intval($pedido_id)]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }


}
?>
