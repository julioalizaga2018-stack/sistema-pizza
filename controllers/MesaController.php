<?php
// controllers/MesaController.php
require_once __DIR__ . '/../models/MesaModelo.php';

class MesaController {
    private $modelo;

    public function __construct() {
        $this->modelo = new MesaModelo();
    }

    public function listar() {
        return $this->modelo->listarMesasOperativas();
    }
}

// --- 🚀 DISPARADOR DE ACCIONES URL (Captura el clic de Liberar Mesa) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'liberar_mesa') {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    // Candado de seguridad: Solo Superadmin (1), Admin (2) o Supervisor (3) pueden liberar mesas
    $rolSesion = isset($_SESSION['rol_id']) ? (int)$_SESSION['rol_id'] : 0;
    if ($rolSesion === 1 || $rolSesion === 2 || $rolSesion === 3) {
        
        // 🚀 REEMPLÁZALA EXACTAMENTE POR ESTA (Acepta ambas variables por seguridad):
$mesa_id = isset($_GET['id']) ? intval($_GET['id']) : intval($_GET['del_id'] ?? 0);
        $modeloMesa = new MesaModelo();
        
        if ($mesa_id > 0 && $modeloMesa->liberarMesaForzado($mesa_id)) {
            $resultado = ['status' => 'success', 'msg' => 'Mesa liberada de forma segura y cuenta limpia.'];
        } else {
            $resultado = ['status' => 'error', 'msg' => 'No se pudo procesar la liberación de la mesa.'];
        }
    } else {
        $resultado = ['status' => 'error', 'msg' => 'No tienes permisos para liberar mesas forzadamente.'];
    }

    // Redirección inmediata al mapa de mesas del salón
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $url_base = ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/";
    $tipo = ($resultado['status'] === 'success') ? 'success' : 'error';
    
    header("Location: " . $url_base . "index.php?v=mesas&" . $tipo . "=" . urlencode($resultado['msg']));
    exit;
}
?>
