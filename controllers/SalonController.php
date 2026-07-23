<?php
// controllers/SalonController.php
require_once __DIR__ . '/../models/SalonModelo.php';

class SalonController {
    private $modelo;

    public function __construct() {
        $this->modelo = new SalonModelo();
    }

    public function procesarPeticiones() {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $rolSesion = isset($_SESSION['rol_id']) ? (int)$_SESSION['rol_id'] : 0;
        if ($rolSesion !== 1 && $rolSesion !== 2) {
            return ['status' => 'error', 'msg' => 'Acción no autorizada.', 'origen' => 'dashboard'];
        }

        // --- MANEJO DE ACCIONES GET (BAJAS LOGICAS) ---
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
            $action = $_GET['action'];
            $id = intval($_GET['del_id'] ?? 0);

            if ($action === 'eliminar_area' && $id > 0) {
                if ($this->modelo->eliminarAreaLogico($id)) {
                    return ['status' => 'success', 'msg' => 'Zona del local eliminada con éxito.', 'origen' => 'areas'];
                }
                return ['status' => 'error', 'msg' => 'No se pudo eliminar el área.', 'origen' => 'areas'];
            }

            if ($action === 'eliminar_mesa' && $id > 0) {
                if ($this->modelo->eliminarMesaLogico($id)) {
                    return ['status' => 'success', 'msg' => 'Mesa removida del mapa con éxito.', 'origen' => 'mesas'];
                }
                return ['status' => 'error', 'msg' => 'No se pudo eliminar la mesa.', 'origen' => 'mesas'];
            }
        }

        // --- MANEJO DE REGISTROS POST ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accion = $_POST['accion'] ?? '';

            if ($accion === 'crear_area') {
                $nombre = trim($_POST['nombre'] ?? '');
                $descripcion = trim($_POST['descripcion'] ?? '');
                if (empty($nombre)) return ['status' => 'error', 'msg' => 'El nombre del área es obligatorio.', 'origen' => 'areas'];
                
                if ($this->modelo->registrarArea($nombre, $descripcion)) {
                    return ['status' => 'success', 'msg' => 'Nueva área agregada al local.', 'origen' => 'areas'];
                }
                return ['status' => 'error', 'msg' => 'Hubo un error al registrar la zona.', 'origen' => 'areas'];
            }

            if ($accion === 'crear_mesa') {
                $area_id = intval($_POST['area_id'] ?? 0);
                $numero_mesa = trim($_POST['numero_mesa'] ?? '');
                $capacidad = intval($_POST['capacidad'] ?? 4);

                if (empty($area_id) || empty($numero_mesa)) {
                    return ['status' => 'error', 'msg' => 'Rellene los campos obligatorios de la mesa.', 'origen' => 'mesas'];
                }

                if ($this->modelo->registrarMesa($area_id, $numero_mesa, $capacidad)) {
                    return ['status' => 'success', 'msg' => 'Mesa incorporada al plano comercial.', 'origen' => 'mesas'];
                }
                return ['status' => 'error', 'msg' => 'El número de mesa ya existe en esa misma área.', 'origen' => 'mesas'];
            }
        }
        return null;
    }
}

// Disparador del controlador
if ($_SERVER['REQUEST_METHOD'] === 'POST' || ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']))) {
    $controller = new SalonController();
    $resultado = $controller->procesarPeticiones();

    if ($resultado) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $url_base = ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/";
        $tipo = ($resultado['status'] === 'success') ? 'success' : 'error';
        
        if ($resultado['origen'] === 'areas') {
            header("Location: " . $url_base . "index.php?v=mantenimiento_areas&" . $tipo . "=" . urlencode($resultado['msg']));
        } elseif ($resultado['origen'] === 'mesas') {
            header("Location: " . $url_base . "index.php?v=mantenimiento_mesas&" . $tipo . "=" . urlencode($resultado['msg']));
        } else {
            header("Location: " . $url_base . "index.php?v=dashboard");
        }
        exit;
    }
}
?>
