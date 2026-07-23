<?php
// controllers/CategoriaController.php
require_once __DIR__ . '/../models/CategoriaModelo.php';

class CategoriaController {
    private $modelo;

    public function __construct() {
        $this->modelo = new CategoriaModelo();
    }

    // Provee la lista completa sin paginar para alimentar el combo select de productos
    public function obtenerCategorias() {
        return $this->modelo->listarCategoriasTodas();
    }

    public function procesarPeticiones() {
        // --- 🛠️ MANEJO DE ELIMINACIÓN LÓGICA POR MÉTODO GET ---
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'eliminar_categoria') {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            
            // Filtro estricto de seguridad: Solo Superadmin (1) o Admin (2)
            $rolSesion = isset($_SESSION['rol_id']) ? (int)$_SESSION['rol_id'] : 0;
            if ($rolSesion !== 1 && $rolSesion !== 2) {
                return ['status' => 'error', 'msg' => 'Acción no autorizada.', 'origen' => 'categorias'];
            }

            $id = intval($_GET['del_id'] ?? 0);
            
            // Regla de negocio: Impedir que se eliminen las categorías base por error de dedo
            if ($id <= 6 && $id > 0) {
                return ['status' => 'error', 'msg' => 'Esta es una categoría del sistema y no puede eliminarse.', 'origen' => 'categorias'];
            }

            if ($id > 0) {
                if ($this->modelo->eliminarCategoriaLogico($id)) {
                    return ['status' => 'success', 'msg' => 'Categoría dada de baja correctamente.', 'origen' => 'categorias'];
                }
            }
            return ['status' => 'error', 'msg' => 'No se pudo procesar la baja de la categoría.', 'origen' => 'categorias'];
        }

        // --- MANEJO DE FORMULARIOS POR MÉTODO POST (CREAR / EDITAR) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accion = $_POST['accion'] ?? '';

            if ($accion === 'crear_categoria' || $accion === 'editar_categoria') {
                $id          = intval($_POST['id'] ?? 0);
                $nombre      = trim($_POST['nombre'] ?? '');
                $descripcion = trim($_POST['descripcion'] ?? '');

                // Validación de campos obligatorios
                if (empty($nombre)) {
                    return ['status' => 'error', 'msg' => 'El nombre de la categoría es obligatorio.', 'origen' => 'categorias'];
                }

                if ($accion === 'crear_categoria') {
                    if ($this->modelo->registrarCategoria($nombre, $descripcion)) {
                        return ['status' => 'success', 'msg' => 'Nueva categoría registrada con éxito.', 'origen' => 'categorias'];
                    }
                } else if ($accion === 'editar_categoria' && $id > 0) {
                    if ($this->modelo->actualizarCategoria($id, $nombre, $descripcion)) {
                        return ['status' => 'success', 'msg' => 'Categoría actualizada con éxito.', 'origen' => 'categorias'];
                    }
                }
                return ['status' => 'error', 'msg' => 'Hubo un error al intentar guardar los datos.', 'origen' => 'categorias'];
            }
        }
        return null;
    }
}
// --- 🚀 DISPARADOR INTEGRADO DE RUTAS DE CATEGORÍAS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' || ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']))) {
    $controller = new CategoriaController();
    $resultado = $controller->procesarPeticiones();

    if ($resultado) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $url_base = ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/";
        
        $tipo = ($resultado['status'] === 'success') ? 'success' : 'error';
        
        // Redirección hacia tu variable del enrutador central de mantenimiento
        if ($resultado['origen'] === 'categorias') {
            header("Location: " . $url_base . "index.php?v=mantenimiento_categorias&" . $tipo . "=" . urlencode($resultado['msg']));
        } else {
            header("Location: " . $url_base . "index.php?v=login&" . $tipo . "=" . urlencode($resultado['msg']));
        }
        exit;
    }
}
?>
