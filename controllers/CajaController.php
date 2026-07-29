<?php
// controllers/CajaController.php
require_once __DIR__ . '/../models/CajaModelo.php';

class CajaController {
    private $modelo;

    public function __construct() {
        $this->modelo = new CajaModelo();
    }

    public function verificarCajaUsuario($usuario_id) {
        return $this->modelo->obtenerTurnoActivo($usuario_id);
    }

    public function procesarOperacionesCaja() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }

            $usuario_id = $_SESSION['usuario_id'] ?? null;
            if (!$usuario_id) {
                return ['status' => 'error', 'msg' => 'Sesión expirada. Reinicie sesión.', 'origen' => 'login'];
            }

            $accion = $_POST['accion'] ?? '';

            // APERTURA DE TURNO
            if ($accion === 'abrir_caja') {
                $monto_inicial = floatval($_POST['monto_inicial'] ?? 0);
                if ($monto_inicial < 0) {
                    return ['status' => 'error', 'msg' => 'Monto inicial inválido.', 'origen' => 'caja'];
                }

                if ($this->modelo->abrirCaja($usuario_id, $monto_inicial)) {
                    return ['status' => 'success', 'msg' => '¡Turno de caja abierto correctamente!', 'origen' => 'caja'];
                }
                return ['status' => 'error', 'msg' => 'Ya posees un turno activo.', 'origen' => 'caja'];
            }

            // REGISTRAR MOVIMIENTO MANUAL (VALE / INGRESO DE CAMBIO)
            if ($accion === 'registrar_movimiento') {
                $turno_id = filter_var($_POST['turno_id'] ?? null, FILTER_VALIDATE_INT);
                $tipo     = trim($_POST['tipo_movimiento_manual'] ?? '');
                $monto    = floatval($_POST['monto_movimiento'] ?? 0);
                $motivo   = trim($_POST['motivo_movimiento'] ?? '');

                if (!$turno_id || empty($tipo) || $monto <= 0 || empty($motivo)) {
                    return ['status' => 'error', 'msg' => 'Por favor, rellene todos los campos correctamente.', 'origen' => 'caja'];
                }

                if ($this->modelo->registrarMovimientoCaja($turno_id, $tipo, $monto, $motivo)) {
                    return ['status' => 'success', 'msg' => 'Movimiento grabado con éxito en la bitácora.', 'origen' => 'caja'];
                }
                return ['status' => 'error', 'msg' => 'Error al insertar el registro en la base de datos.', 'origen' => 'caja'];
            }

            // CIERRE Y ARQUEO FINAL
            if ($accion === 'cerrar_caja') {
                $turno_id = filter_var($_POST['turno_id'] ?? null, FILTER_VALIDATE_INT);
                $efectivo_entregado      = floatval($_POST['efectivo_real'] ?? 0);
                $tarjeta_entregado       = floatval($_POST['tarjeta_real'] ?? 0);
                $transferencia_entregado = floatval($_POST['transferencia_real'] ?? 0);
                $observaciones           = trim($_POST['observaciones'] ?? '');

                if (!$turno_id) {
                    return ['status' => 'error', 'msg' => 'ID de turno de caja inválido para proceder al cierre.', 'origen' => 'caja'];
                }

                if ($this->modelo->cerrarCaja($turno_id, $efectivo_entregado, $tarjeta_entregado, $transferencia_entregado, $observaciones)) {
                    return ['status' => 'success', 'msg' => '¡Turno de caja clausurado y guardado en historial con éxito!', 'origen' => 'caja'];
                }
                return ['status' => 'error', 'msg' => 'Error al clausurar el turno en el servidor.', 'origen' => 'caja'];
            }
        }
        return null;
    }
}

// --- MODIFICACIÓN QUIRÚRGICA EN EL DISPARADOR DE POST DE controllers/CajaController.php ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new CajaController();
    $resultado  = $controller->procesarOperacionesCaja();

    if ($resultado) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host     = $_SERVER['HTTP_HOST'];
        $url_base = ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/";
        $tipo     = ($resultado['status'] === 'success') ? 'success' : 'error';
        $origen   = $resultado['origen'] ?? 'caja';

        if ($origen === 'caja') {
            $url_redireccion = $url_base . "index.php?v=gestion_caja&" . $tipo . "=" . urlencode($resultado['msg']);
            
            // 🚀 INYECCIÓN: Si el cajero cerró con éxito, mandamos el ID del turno para auto-disparar el ticket
            if ($resultado['status'] === 'success' && $_POST['accion'] === 'cerrar_caja') {
                $url_redireccion .= "&imprimir_cierre_id=" . intval($_POST['turno_id']);
            }
            
            header("Location: " . $url_redireccion);
        } else {
            header("Location: " . $url_base . "index.php?v=login&" . $tipo . "=" . urlencode($resultado['msg']));
        }
        exit;
    }
}
?>
