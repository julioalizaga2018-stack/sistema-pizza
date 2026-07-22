<?php
// controllers/UsuarioController.php
require_once __DIR__ . '/../models/UsuarioModelo.php';

class UsuarioController {
    private $modelo;

    public function __construct() {
        $this->modelo = new UsuarioModelo();
    }

    public function esSistemaNuevo() {
        return $this->modelo->contarUsuarios() === 0;
    }

    // Provee la lista de usuarios operativos a la vista
    public function listar() {
        return $this->modelo->listarUsuarios();
    }

    // Provee la lista de roles operativos al combo select de la vista
    public function obtenerRoles() {
        return $this->modelo->obtenerRolesOperativos();
    }

    public function procesarPeticiones() {
        // --- 🛠️ MANEJO DE ELIMINACIÓN LOGICA POR MÉTODO GET ---
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'eliminar_usuario') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Filtro estricto: Solo Superadmin (1) o Admin (2) pueden dar de baja personal
            $rolSesion = isset($_SESSION['rol_id']) ? (int)$_SESSION['rol_id'] : 0;
            if ($rolSesion !== 1 && $rolSesion !== 2) {
                return ['status' => 'error', 'msg' => 'Acción no autorizada.', 'origen' => 'usuarios'];
            }

            $id = intval($_GET['del_id'] ?? 0);
            if ($id > 0) {
                if ($this->modelo->eliminarUsuarioLogico($id)) {
                    return ['status' => 'success', 'msg' => 'Empleado dado de baja con éxito.', 'origen' => 'usuarios'];
                }
            }
            return ['status' => 'error', 'msg' => 'No se pudo procesar la baja del empleado.', 'origen' => 'usuarios'];
        }

        // --- MANEJO DE FORMULARIOS POR MÉTODO POST ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accion = $_POST['accion'] ?? '';

            if ($accion === 'registrar_primer_admin' && $this->esSistemaNuevo()) {
                $nombre   = trim($_POST['nombre']);
                $apellido = trim($_POST['apellido']);
                $usuario  = trim($_POST['usuario']);
                $password = $_POST['password'];

                if (!empty($nombre) && !empty($apellido) && !empty($usuario) && !empty($password)) {
                    if ($this->modelo->registrarPrimerAdmin($nombre, $apellido, $usuario, $password)) {
                        return ['status' => 'success', 'msg' => '¡Superadmin creado! Inicia sesión.', 'origen' => 'login'];
                    }
                }
                return ['status' => 'error', 'msg' => 'Por favor, rellena todos los campos.', 'origen' => 'login'];
            }

            if ($accion === 'login_regular') {
                $usuario  = trim($_POST['usuario']);
                $password = $_POST['password'];

                $datosUsuario = $this->modelo->buscarPorUsuario($usuario);
                if ($datosUsuario && password_verify($password, $datosUsuario['password'])) {
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $_SESSION['usuario_id'] = $datosUsuario['id'];
                    $_SESSION['rol_id']     = $datosUsuario['rol_id'];
                    $_SESSION['nombre']     = $datosUsuario['nombre'] . ' ' . $datosUsuario['apellido'];
                    
                    return ['status' => 'success', 'msg' => 'Ok', 'origen' => 'dashboard'];
                }
                return ['status' => 'error', 'msg' => 'Credenciales incorrectas.', 'origen' => 'login'];
            }
            if ($accion === 'crear_usuario') {
                $rol_id   = intval($_POST['rol_id'] ?? 0);
                $nombre   = trim($_POST['nombre']);
                $apellido = trim($_POST['apellido']);
                $usuario  = trim($_POST['usuario']);
                $password = $_POST['password'];

                if (!empty($rol_id) && !empty($nombre) && !empty($apellido) && !empty($usuario) && !empty($password)) {
                    if ($rol_id === 1) {
                        return ['status' => 'error', 'msg' => 'Acción no permitida.', 'origen' => 'usuarios'];
                    }

                    if ($this->modelo->buscarPorUsuario($usuario)) {
                        return ['status' => 'error', 'msg' => 'El nombre de usuario ya existe.', 'origen' => 'usuarios'];
                    }

                    if ($this->modelo->registrarUsuarioComun($rol_id, $nombre, $apellido, $usuario, $password)) {
                        return ['status' => 'success', 'msg' => 'Empleado registrado con éxito.', 'origen' => 'usuarios'];
                    }
                }
                return ['status' => 'error', 'msg' => 'No se pudo registrar al empleado.', 'origen' => 'usuarios'];
            }

            if ($accion === 'editar_usuario') {
                $id       = intval($_POST['id']);
                $rol_id   = intval($_POST['rol_id'] ?? 0);
                $nombre   = trim($_POST['nombre']);
                $apellido = trim($_POST['apellido']);
                $usuario  = trim($_POST['usuario']);
                $password = $_POST['password'] ?? '';

                if ($id > 0 && !empty($rol_id) && !empty($nombre) && !empty($apellido) && !empty($usuario)) {
                    if ($rol_id === 1) {
                        return ['status' => 'error', 'msg' => 'Acción no permitida.', 'origen' => 'usuarios'];
                    }

                    if ($this->modelo->actualizarUsuario($id, $rol_id, $nombre, $apellido, $usuario, $password)) {
                        return ['status' => 'success', 'msg' => 'Usuario modificado con éxito.', 'origen' => 'usuarios'];
                    }
                }
                return ['status' => 'error', 'msg' => 'Error al actualizar datos.', 'origen' => 'usuarios'];
            }
        }
        return null;
    }
}

// --- 🚀 DISPARADOR INTEGRADO DE PETICIONES (Intercepta de manera unificada POST y GET de baja) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' || ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']))) {
    $controller = new UsuarioController();
    $resultado = $controller->procesarPeticiones();

    if ($resultado) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $url_base = ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/";
        
        $tipo = ($resultado['status'] === 'success') ? 'success' : 'error';
        
        if ($resultado['origen'] === 'dashboard') {
            header("Location: " . $url_base . "index.php?v=dashboard");
        } elseif ($resultado['origen'] === 'usuarios') {
            header("Location: " . $url_base . "index.php?v=gestion_usuarios&" . $tipo . "=" . urlencode($resultado['msg']));
        } else {
            header("Location: " . $url_base . "index.php?v=login&" . $tipo . "=" . urlencode($resultado['msg']));
        }
        exit;
    }
}
?>
