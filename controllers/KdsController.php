<?php
require_once __DIR__ . '/../models/KdsModelo.php';
require_once __DIR__ . '/../models/PedidoModelo.php';

class KdsController {
    private $modelo;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->modelo = new KdsModelo();
    }

    public function procesarAccionesKds() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return null;
        }

        $accion = trim($_POST['accion'] ?? '');

        switch ($accion) {
            case 'cambiar_estado_item':
                return $this->procesarCambioEstado();
            case 'rechazar_item_stock':
                return $this->procesarRechazoItem();
            case 'consultar_cola_refresco':
                return $this->consultarColaRefresco();
            default:
                return null;
        }
    }

    private function procesarCambioEstado() {
        // Asegurarnos de que no hay salida previa que rompa el JSON
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');

        $detalle_id = intval($_POST['detalle_id'] ?? 0);
        $nuevo_estado = trim($_POST['nuevo_estado'] ?? '');

        if ($detalle_id <= 0 || !in_array($nuevo_estado, ['preparando', 'listo'], true)) {
            echo json_encode(['status' => 'error', 'msg' => 'Parámetros inválidos.']);
            exit;
        }

        $exito = $this->modelo->actualizarEstadoItemKds($detalle_id, $nuevo_estado);
        if ($exito) {
            echo json_encode(['status' => 'success', 'msg' => 'Estado actualizado en KDS']);
            exit;
        }

        echo json_encode(['status' => 'error', 'msg' => 'No se pudo cambiar el estado del ítem.']);
        exit;
    }

    private function procesarRechazoItem() {
        $detalle_id = intval($_POST['detalle_id'] ?? 0);
        $pedido_id = intval($_POST['pedido_id'] ?? 0);
        $motivo = trim($_POST['motivo_quitar'] ?? '');

        if ($detalle_id <= 0 || $pedido_id <= 0 || $motivo === '') {
            $this->redirigirConError('Parámetros de rechazo incompletos.');
        }

        $exito = $this->modelo->rechazarItemPorFaltaInsumo($detalle_id, $pedido_id, $motivo);
        if ($exito) {
            (new PedidoModelo())->actualizarTotalesPedido($pedido_id);
            $this->redirigirConSuccess('Ítem removido por falta de insumos.');
        }

        $this->redirigirConError('No se pudo procesar la baja del ítem.');
    }

    private function consultarColaRefresco() {
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');

        $area_produccion = trim($_POST['area_produccion'] ?? 'cocina');
        $comandas = $this->modelo->obtenerComandasPorEstacion($area_produccion);
        echo json_encode(['status' => 'success', 'data' => $comandas]);
        exit;
    }

    private function redirigirConError(string $mensaje) {
        $estacion_actual = $_GET['estacion'] ?? 'cocina';
        header('Location: index.php?v=kds_monitor&estacion=' . urlencode($estacion_actual) . '&error=' . urlencode($mensaje));
        exit;
    }

    private function redirigirConSuccess(string $mensaje) {
        $estacion_actual = $_GET['estacion'] ?? 'cocina';
        header('Location: index.php?v=kds_monitor&estacion=' . urlencode($estacion_actual) . '&success=' . urlencode($mensaje));
        exit;
    }
}
?>
