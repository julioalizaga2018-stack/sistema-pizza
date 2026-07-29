<?php
// controllers/InventarioController.php
require_once __DIR__ . '/../models/InventarioModelo.php';

class InventarioController {
    private $modelo;

    public function __construct() {
        $this->modelo = new InventarioModelo();
    }

    // 🚀 REVISIÓN QUIRÚRGICA: Fuerza el casteo a enteros estrictos para evitar fallas en PDO bindValue
    public function obtenerHistorial($limite = 20, $offset = 0) {
        $limite_estricto = intval($limite) > 0 ? intval($limite) : 20;
        $offset_estricto = intval($offset) >= 0 ? intval($offset) : 0;
        
        return $this->modelo->obtenerHistorialKardex($limite_estricto, $offset_estricto);
    }

    // 🚀 NUEVO: Conecta la vista con el método contador que agregamos a tu InventarioModelo
    public function obtenerTotalRegistros() {
        return $this->modelo->contarRegistrosKardex();
    }

    public function procesarAjusteManual() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }

            $rolSesion = isset($_SESSION['rol_id']) ? (int)$_SESSION['rol_id'] : 0;
            if ($rolSesion !== 1 && $rolSesion !== 2 && $rolSesion !== 3) {
                return ['status' => 'error', 'msg' => 'Acción no autorizada para su nivel de usuario.'];
            }

            $producto_id     = filter_var($_POST['producto_id'] ?? null, FILTER_VALIDATE_INT);
            $tipo_movimiento = trim($_POST['tipo_movimiento_manual'] ?? $_POST['tipo_movimiento'] ?? '');
            $cantidad        = floatval($_POST['cantidad'] ?? 0);
            $motivo          = trim($_POST['motivo'] ?? '');
            $usuario_id      = $_SESSION['usuario_id'] ?? null;

            if (!$producto_id || empty($tipo_movimiento) || $cantidad <= 0 || empty($motivo)) {
                return ['status' => 'error', 'msg' => 'Por favor, rellene todos los campos con valores válidos.'];
            }

            if ($tipo_movimiento !== 'entrada_ajuste' && $tipo_movimiento !== 'salida_ajuste') {
                return ['status' => 'error', 'msg' => 'Tipo de movimiento de inventario no válido.'];
            }

            $resultado = $this->modelo->registrarMovimiento($producto_id, $tipo_movimiento, $cantidad, $motivo, null, $usuario_id);

            if ($resultado) {
                return ['status' => 'success', 'msg' => 'Ajuste de inventario aplicado correctamente.'];
            } else {
                return ['status' => 'error', 'msg' => 'Error crítico al procesar el ajuste en la base de datos.'];
            }
        }
        return null;
    }
}

// --- DISPARADOR INTEGRADO DE RUTAS DE INVENTARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['producto_id'])) {
    $controller = new InventarioController();
    $resultado  = $controller->procesarAjusteManual();

    if ($resultado) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host     = $_SERVER['HTTP_HOST'];
        $url_base = ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/";
        $tipo     = ($resultado['status'] === 'success') ? 'success' : 'error';

        header("Location: " . $url_base . "index.php?v=inventario_ajustes&" . $tipo . "=" . urlencode($resultado['msg']));
        exit;
    }
}
?>
