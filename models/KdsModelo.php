<?php
// models/KdsModelo.php

class KdsModelo {
    private $db;

    public function __construct() {
        $this->db = (new Conexion())->conectar();
    }

       public function obtenerComandasPorEstacion($area_produccion) {
        try {
            // 🌟 CORRECCIÓN MAESTRA: Cambiamos p.usuario_id por u.nombre para jalar el texto real del mesero
            // También cambiamos p.mesa_id por m.numero_mesa para jalar el nombre/número real de la mesa física
           // 🔍 REEMPLAZE ÚNICAMENTE LA SENTENCIA $sql ADENTRO DE obtenerComandasPorEstacion EN models/KdsModelo.php:

            // 🌟 EXTRACTOR RELACIONAL: Acoplamos m.area_id con a.id para jalar el nombre del sector físico
            $sql = "SELECT pd.id as detalle_id, pd.pedido_id, pd.cantidad, pd.precio_unitario, pd.estado as item_estado, pd.es_mixta,
                           p.tipo_pedido, p.created_at as hora_pedido,
                           prod.nombre as nombre_producto,
                           m.numero_mesa as nombre_mesa, 
                           a.nombre as nombre_area,       -- 🚀 CAPTURA DEL NOMBRE DESDE TU TABLA MAESTRO DE ÁREAS
                           u.nombre as nombre_mesero
                    FROM pedido_detalles pd
                    INNER JOIN pedidos p ON pd.pedido_id = p.id
                    INNER JOIN productos prod ON pd.producto_id = prod.id
                    LEFT JOIN mesas m ON p.mesa_id = m.id
                    LEFT JOIN areas a ON m.area_id = a.id -- 🚀 CONEXIÓN FIJA A TU TABLA DE PHPMYADMIN
                    LEFT JOIN usuarios u ON p.usuario_id = u.id
                    WHERE prod.area_produccion = :area_prod
                      AND pd.estado IN ('pendiente', 'preparando')
                      AND p.estado = 'pendiente'
                    ORDER BY p.id ASC, pd.id ASC";


            $stmt = $this->db->prepare($sql);
            $stmt->execute(['area_prod' => trim($area_produccion)]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Bucle relacional de Extras y Sabores (Se mantiene intacto y perfecto)
            foreach ($items as $index => $item) {
                $detalle_id = intval($item['detalle_id']);
                $items[$index]['extras'] = [];
                $items[$index]['sabores'] = [];
                // 🌟 REGLA SEMÁNTICA JUNGLE PIZZA: Si es mixta, forzamos el nombre corporativo
        if ((int)$item['es_mixta'] === 1) {
            $items[$index]['nombre_producto'] = "Pizza Mixta Combinada"; // 🚀 Homologación con el Punto de Venta
        }

                $sqlExtras = "SELECT prod_ex.nombre as nombre_extra, pde.cantidad as cant_extra
                              FROM pedido_detalle_extras pde
                              INNER JOIN productos prod_ex ON pde.producto_id = prod_ex.id
                              WHERE pde.pedido_detalle_id = :detalle_id";
                $stmtEx = $this->db->prepare($sqlExtras);
                $stmtEx->execute(['detalle_id' => $detalle_id]);
                $items[$index]['extras'] = $stmtEx->fetchAll(PDO::FETCH_ASSOC);

                if ((int)$item['es_mixta'] === 1) {
                    $sqlSabores = "SELECT prod_sab.nombre as nombre_sabor
                                   FROM pedido_detalle_sabores pds
                                   INNER JOIN productos prod_sab ON pds.producto_id = prod_sab.id
                                   WHERE pds.pedido_detalle_id = :detalle_id";
                    $stmtSab = $this->db->prepare($sqlSabores);
                    $stmtSab->execute(['detalle_id' => $detalle_id]);
                    $items[$index]['sabores'] = $stmtSab->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            return $items;
        } catch (PDOException $e) {
            error_log("Error en KdsModelo::obtenerComandasPorEstacion -> " . $e->getMessage());
            return [];
        }
    }


    /**
     * 🔄 TRANSICIÓN KDS: Al dar clic en "Cocinar" pasa a 'preparando'. Al dar "Liberar" pasa a 'servido'.
     */
    public function actualizarEstadoItemKds($detalle_id, $nuevo_estado) {
        try {
            // Sincronización estricta de tus strings con la botonera táctil
            $estado_real = 'pendiente';
            if ($nuevo_estado === 'preparando') $estado_real = 'preparando';
            elseif ($nuevo_estado === 'listo')   $estado_real = 'servido'; // 🌟 Tu estado nativo

            $sql = "UPDATE pedido_detalles SET estado = :estado WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'estado' => $estado_real,
                'id'     => intval($detalle_id)
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * ❌ RECHAZAR MONITOR (CON MERMA): Clava de forma fija tu estado 'quitado_despues'
     */
    public function eliminarItemPorFaltaInsumo($detalle_id, $pedido_id, $motivo) {
        try {
            $this->db->beginTransaction();

            $sqlCancel = "UPDATE pedido_detalles 
                          SET estado = 'quitado_despues', 
                              motivo_quitar = :motivo 
                          WHERE id = :id";
            $stmtCancel = $this->db->prepare($sqlCancel);
            $stmtCancel->execute([
                'motivo' => trim($motivo),
                'id'     => intval($detalle_id)
            ]);

            $sqlRecalculate = "UPDATE pedidos p 
                               SET p.total = COALESCE((SELECT SUM(subtotal) FROM pedido_detalles WHERE pedido_id = :pedido_id AND estado NOT IN ('quitado_antes', 'quitado_despues')), 0)
                               WHERE p.id = :pedido_id";
            $stmtRecalc = $this->db->prepare($sqlRecalculate);
            $stmtRecalc->execute(['pedido_id' => intval($pedido_id)]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
}
?>

