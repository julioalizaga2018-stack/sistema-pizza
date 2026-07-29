<?php
// models/CobranzaModelo.php
require_once __DIR__ . '/../config/conexion.php';

class CobranzaModelo extends Conexion {
    private $db;

    public function __construct() {
        $this->db = $this->conectar();
    }

          // 1. LISTAR PENDIENTES CORREGIDO: Trae el Área y la Mesa de forma relacional limpia
    public function listarPedidosPendientesDeCobro() {
        $sql = "SELECT p.*, m.numero_mesa as numero_mesa, a.nombre as nombre_area
                FROM pedidos p 
                LEFT JOIN mesas m ON p.mesa_id = m.id 
                LEFT JOIN areas a ON m.area_id = a.id
                WHERE p.estado IN ('pendiente', 'cocina', 'horno', 'bar')
                ORDER BY p.id DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }



    // 2. DETALLE DE CUENTA: Obtiene los productos consumidos para el desglose en caja
    public function obtenerDesglosePedido($pedido_id) {
        $sql = "SELECT pd.*, prod.nombre as producto_nombre 
                FROM pedido_detalles pd
                INNER JOIN productos prod ON pd.producto_id = prod.id
                WHERE pd.pedido_id = :pedido_id AND pd.estado != 'quitado_antes'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['pedido_id' => $pedido_id]);
        return $stmt->fetchAll();
    }
    // 3. TRANSACCIÓN MAESTRA DE COBRO MIXTO CON REBAJA DE KARDEX Y LIBERACIÓN DE MESA
    public function procesarPagoPedido($pedido_id, $caja_turno_id, $propina, $descuento, $pagos, $total_final) {
        try {
            $this->db->beginTransaction();

            // A. Actualizar el pedido con los montos definitivos y cambiar estado a entregado
            $sqlPedido = "UPDATE pedidos 
                          SET monto_propina = :propina, 
                              monto_descuento = :descuento, 
                              total = :total_final, 
                              estado = 'entregado',
                              caja_turno_id = :caja_turno_id
                          WHERE id = :pedido_id";
            $stmtP = $this->db->prepare($sqlPedido);
            $stmtP->execute([
                'propina'       => $propina,
                'descuento'     => $descuento,
                'total_final'   => $total_final,
                'caja_turno_id' => $caja_turno_id,
                'pedido_id'     => $pedido_id
            ]);

            // B. Registrar de forma desglosada cada método del pago mixto en pedido_pagos
            $sqlPago = "INSERT INTO pedido_pagos (pedido_id, caja_turno_id, metodo_pago, banco_id, monto, referencia) 
                        VALUES (:pedido_id, :caja_turno_id, :metodo_pago, :banco_id, :monto, :referencia)";
            $stmtPagos = $this->db->prepare($sqlPago);

            foreach ($pagos as $pago) {
                if (floatval($pago['monto']) > 0) {
                    $stmtPagos->execute([
                        'pedido_id'     => $pedido_id,
                        'caja_turno_id' => $caja_turno_id,
                        'metodo_pago'   => $pago['metodo_pago'],
                        'banco_id'      => !empty($pago['banco_id']) ? $pago['banco_id'] : null,
                        'monto'         => $pago['monto'],
                        'referencia'    => !empty($pago['referencia']) ? $pago['referencia'] : null
                    ]);
                }
            }

            // C. Descuento automatizado de existencias en el Kardex
            // 🌟 REEMPLÁZALO POR ESTA VERSIÓN BLINDADA CON CANAL COMPARTIDO:
            require_once __DIR__ . '/InventarioModelo.php';
            $inventarioModel = new InventarioModelo();
            $inventarioModel->establecerConexionCompartida($this->db); // 🔥 Comparte el canal PDO de la venta
            
            $stmtItems = $this->db->prepare("SELECT producto_id, cantidad FROM pedido_detalles WHERE pedido_id = :pedido_id AND estado NOT IN ('quitado_antes', 'quitado_despues')");
            $stmtItems->execute(['pedido_id' => $pedido_id]);
            $itemsVandidos = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            foreach ($itemsVandidos as $item) {
                $producto_id     = (int)$item['producto_id'];
                $cantidad_venda  = floatval($item['cantidad']);
                $usuario_cajero  = $_SESSION['usuario_id'] ?? null;

                $inventarioModel->registrarMovimiento(
                    $producto_id, 
                    'venta_factura', 
                    $cantidad_venda, 
                    "Salida por Venta - Factura #{$pedido_id}", 
                    $pedido_id, 
                    $usuario_cajero
                );
            }

            // 🚀 D. INYECCIÓN QUIRÚRGICA: Liberación automática de la mesa bloqueada
            // Primero consultamos si el pedido tiene una mesa asociada (para saltar este paso si es Delivery o Retiro)
            $sqlMesaId = "SELECT mesa_id FROM pedidos WHERE id = :pedido_id LIMIT 1";
            $stmtM = $this->db->prepare($sqlMesaId);
            $stmtM->execute(['pedido_id' => $pedido_id]);
            $resMesa = $stmtM->fetch(PDO::FETCH_ASSOC);

            if ($resMesa && !empty($resMesa['mesa_id'])) {
                $mesa_id = (int)$resMesa['mesa_id'];
                
                // Actualizamos el estado en la tabla mesas usando tu ENUM nativo 'disponible'
                $sqlLiberarMesa = "UPDATE mesas SET estado = 'disponible' WHERE id = :mesa_id";
                $stmtLiberar = $this->db->prepare($sqlLiberarMesa);
                $stmtLiberar->execute(['mesa_id' => $mesa_id]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
 // 🚀 4. HISTORIAL: Contar facturas cobradas con filtros de fecha y número (¡Esta es la que faltaba!)
    public function contarFacturasHistorial($desde = '', $hasta = '', $num_pedido = 0) {
        $sql = "SELECT COUNT(*) as total FROM pedidos WHERE estado = 'entregado'";
        $params = [];

        if ($num_pedido > 0) {
            $sql .= " AND id = :num_pedido";
            $params['num_pedido'] = $num_pedido;
        } else {
            if (!empty($desde)) {
                $sql .= " AND DATE(created_at) >= :desde";
                $params['desde'] = $desde;
            }
            if (!empty($hasta)) {
                $sql .= " AND DATE(created_at) <= :hasta";
                $params['hasta'] = $hasta;
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch();
        return (int)$fila['total'];
    }

    // 🚀 5. HISTORIAL: Listar las facturas de forma paginada y optimizada para el rendimiento (¡Esta también faltaba!)
    public function listarFacturasHistorial($desde = '', $hasta = '', $num_pedido = 0, $limite = 15, $offset = 0) {
        $sql = "SELECT p.*, m.numero_mesa 
                FROM pedidos p 
                LEFT JOIN mesas m ON p.mesa_id = m.id 
                WHERE p.estado = 'entregado'";
        $params = [];

        if ($num_pedido > 0) {
            $sql .= " AND p.id = :num_pedido";
            $params['num_pedido'] = $num_pedido;
        } else {
            if (!empty($desde)) {
                $sql .= " AND DATE(p.created_at) >= :desde";
                $params['desde'] = $desde;
            }
            if (!empty($hasta)) {
                $sql .= " AND DATE(p.created_at) <= :hasta";
                $params['hasta'] = $hasta;
            }
        }

        $sql .= " ORDER BY p.id DESC LIMIT :limite OFFSET :offset";
        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }
        // 6. ANALÍTICA: Contar cuántos productos únicos registran ventas en un rango de fechas
    public function contarProductosVendidosHistorial($desde = '', $hasta = '') {
        $sql = "SELECT COUNT(DISTINCT pd.producto_id) as total 
                FROM pedido_detalles pd
                INNER JOIN pedidos p ON pd.pedido_id = p.id
                WHERE p.estado = 'entregado' AND pd.estado NOT IN ('quitado_antes', 'quitado_despues')";
        $params = [];

        if (!empty($desde)) {
            $sql .= " AND DATE(p.created_at) >= :desde";
            $params['desde'] = $desde;
        }
        if (!empty($hasta)) {
            $sql .= " AND DATE(p.created_at) <= :hasta";
            $params['hasta'] = $hasta;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch();
        return (int)$fila['total'];
    }

    // 7. ANALÍTICA: Listar el ranking de productos vendidos de forma paginada (Límite de 10)
    public function listarProductosVendidosHistorial($desde = '', $hasta = '', $limite = 10, $offset = 0) {
        // Hacemos un SUM de cantidades y subtotales agrupando por producto_id
        $sql = "SELECT 
                    prod.id as producto_id,
                    prod.nombre as producto_nombre,
                    prod.unidad_medida,
                    SUM(pd.cantidad) as total_unidades,
                    SUM(pd.subtotal) as total_recaudado
                FROM pedido_detalles pd
                INNER JOIN pedidos p ON pd.pedido_id = p.id
                INNER JOIN productos prod ON pd.producto_id = prod.id
                WHERE p.estado = 'entregado' AND pd.estado NOT IN ('quitado_antes', 'quitado_despues')";
        
        $params = [];

        if (!empty($desde)) {
            $sql .= " AND DATE(p.created_at) >= :desde";
            $params['desde'] = $desde;
        }
        if (!empty($hasta)) {
            $sql .= " AND DATE(p.created_at) <= :hasta";
            $params['hasta'] = $hasta;
        }

        // Agrupamos por el ID del alimento y ordenamos de mayor a menor venta
        $sql .= " GROUP BY prod.id, prod.nombre, prod.unidad_medida 
                  ORDER BY total_unidades DESC 
                  LIMIT :limite OFFSET :offset";
                  
        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }


}
?>
