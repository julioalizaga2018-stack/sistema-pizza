<?php
require_once __DIR__ . '/../config/conexion.php';

class KdsModelo {
    private $db;

    public function __construct() {
        $this->db = (new Conexion())->conectar();
    }

    public function obtenerComandasPorEstacion($area_produccion) {
        try {
            $sql = "SELECT pd.id as detalle_id, pd.pedido_id, pd.cantidad, pd.precio_unitario, pd.estado as item_estado, pd.es_mixta,
                           p.tipo_pedido, p.created_at as hora_pedido,
                           prod.nombre as nombre_producto,
                           m.numero_mesa as nombre_mesa,
                           a.nombre as nombre_area,
                           u.nombre as nombre_mesero
                    FROM pedido_detalles pd
                    INNER JOIN pedidos p ON pd.pedido_id = p.id
                    INNER JOIN productos prod ON pd.producto_id = prod.id
                    LEFT JOIN mesas m ON p.mesa_id = m.id
                    LEFT JOIN areas a ON m.area_id = a.id
                    LEFT JOIN usuarios u ON p.usuario_id = u.id
                    WHERE prod.area_produccion = :area_prod
                      AND pd.estado IN ('pendiente', 'preparando')
                      AND p.estado = 'pendiente'
                    ORDER BY p.id ASC, pd.id ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['area_prod' => trim($area_produccion)]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $index => $item) {
                $detalle_id = intval($item['detalle_id']);
                $items[$index]['extras'] = [];
                $items[$index]['sabores'] = [];

                if ((int)$item['es_mixta'] === 1) {
                    $items[$index]['nombre_producto'] = 'Pizza Mixta Combinada';
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
            error_log('Error en KdsModelo::obtenerComandasPorEstacion -> ' . $e->getMessage());
            return [];
        }
    }

    public function actualizarEstadoItemKds($detalle_id, $nuevo_estado) {
        try {
            $estado_real = 'pendiente';
            if ($nuevo_estado === 'preparando') {
                $estado_real = 'preparando';
            } elseif ($nuevo_estado === 'listo') {
                $estado_real = 'servido';
            }

            $sql = 'UPDATE pedido_detalles SET estado = :estado WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'estado' => $estado_real,
                'id' => intval($detalle_id)
            ]);
        } catch (PDOException $e) {
            error_log('Error en KdsModelo::actualizarEstadoItemKds -> ' . $e->getMessage());
            return false;
        }
    }

    public function rechazarItemPorFaltaInsumo($detalle_id, $pedido_id, $motivo) {
        try {
            $this->db->beginTransaction();

            $sqlCancel = "UPDATE pedido_detalles
                          SET estado = 'quitado_despues',
                              motivo_quitar = :motivo
                          WHERE id = :id
                            AND pedido_id = :pedido_id
                            AND estado IN ('pendiente', 'preparando')";
            $stmtCancel = $this->db->prepare($sqlCancel);
            $stmtCancel->execute([
                'motivo' => trim($motivo),
                'id' => intval($detalle_id),
                'pedido_id' => intval($pedido_id)
            ]);

            if ($stmtCancel->rowCount() === 0) {
                throw new Exception('No se modificó ningún detalle con id=' . intval($detalle_id) . ' y pedido_id=' . intval($pedido_id));
            }

            $sqlRecalculate = "UPDATE pedidos p
                               SET p.total = COALESCE((SELECT SUM(subtotal)
                                                       FROM pedido_detalles
                                                       WHERE pedido_id = :pedido_id_inner
                                                         AND estado NOT IN ('quitado_antes', 'quitado_despues')), 0)
                               WHERE p.id = :pedido_id";
            $stmtRecalc = $this->db->prepare($sqlRecalculate);
            $stmtRecalc->execute([
                'pedido_id_inner' => intval($pedido_id),
                'pedido_id' => intval($pedido_id)
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error en KdsModelo::rechazarItemPorFaltaInsumo -> ' . $e->getMessage());
            return false;
        }
    }
}
?>
