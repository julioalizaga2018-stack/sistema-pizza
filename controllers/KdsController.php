<?php
// controllers/KdsController.php

require_once __DIR__ . '/../models/KdsModelo.php';

class KdsController {
    private $modelo;

    public function __construct() {
        $this->modelo = new KdsModelo();
    }

    /**
     * Captura las interacciones asíncronas de los operarios de producción
     */
    public function procesarAccionesKds() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accion = $_POST['accion'] ?? '';
            header('Content-Type: application/json');

            // 🔄 ACCIÓN A: Cambiar estado (De 'solicitado' a 'preparando' o de 'preparando' a 'listo')
            if ($accion === 'cambiar_estado_item') {
                $detalle_id   = intval($_POST['detalle_id'] ?? 0);
                $nuevo_estado = trim($_POST['nuevo_estado'] ?? '');

                if ($detalle_id > 0 && in_array($nuevo_estado, ['preparando', 'listo'])) {
                    $exito = $this->modelo->actualizarEstadoItemKds($detalle_id, $nuevo_estado);
                    if ($exito) {
                        echo json_encode(['status' => 'success', 'msg' => 'Estado actualizado en KDS']);
                        exit;
                    }
                }
                echo json_encode(['status' => 'error', 'msg' => 'No se pudo cambiar el estado del ítem.']);
                exit;
            }

            // ❌ ACCIÓN B: Rechazar ítem por quiebre de stock o falta de ingredientes
            if ($accion === 'rechazar_item_stock') {
                $detalle_id = intval($_POST['detalle_id'] ?? 0);
                $pedido_id  = intval($_POST['pedido_id'] ?? 0);

                if ($detalle_id > 0 && $pedido_id > 0) {
                    $exito = $this->modelo->eliminarItemPorFaltaInsumo($detalle_id, $pedido_id);
                    if ($exito) {
                        echo json_encode(['status' => 'success', 'msg' => 'Ítem rechazado y totales recalculados.']);
                        exit;
                    }
                }
                echo json_encode(['status' => 'error', 'msg' => 'No se pudo eliminar el ítem de producción.']);
                exit;
            }

            // 🔄 ACCIÓN C: API de auto-refresco en segundo plano para las pantallas de cocina
            if ($accion === 'consultar_cola_refresco') {
                $area_produccion = trim($_POST['area_produccion'] ?? 'cocina');
                $comandas = $this->modelo->obtenerComandasPorEstacion($area_produccion);
                
                echo json_encode(['status' => 'success', 'data' => $comandas]);
                exit;
            }
        }
        return null;
    }
}
?>
