<?php
// index.php (Raíz del proyecto)

// 1. Forzar a PHP a mostrar cualquier error oculto en pantalla
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Iniciar la sesión de forma global para todo el sistema
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Importar los controladores necesarios de forma absoluta
require_once __DIR__ . '/controllers/UsuarioController.php';

// 4. Capturar la acción que solicita el usuario (por defecto va al login)
$vista = $_GET['v'] ?? 'login';

// 5. Instanciar el controlador de usuarios para verificar el estado del sistema
$usuarioCtrl = new UsuarioController();

// 6. Enrutamiento lógico del sistema
switch ($vista) {
    case 'login':
        if (isset($_SESSION['usuario_id']) && !$usuarioCtrl->esSistemaNuevo()) {
            header('Location: index.php?v=catalogo');
            exit;
        }
        require_once __DIR__ . '/views/login.php';
        break;

    case 'catalogo':
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: index.php?v=login');
            exit;
        }
        require_once __DIR__ . '/views/catalogo.php';
        break;

    case 'logout':
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header('Location: index.php?v=login');
        exit;

    case 'dashboard':
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: index.php?v=login');
            exit;
        }
        require_once __DIR__ . '/views/dashboard.php';
        break;
            case 'config_empresa':
        // Protección extra por código: rebotar si no es Superadmin (1)
        if (!isset($_SESSION['usuario_id']) || (int)$_SESSION['rol_id'] !== 1) {
            header('Location: index.php?v=dashboard');
            exit;
        }
        require_once __DIR__ . '/views/config_empresa.php';
        break;


    case 'usuarios':
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: index.php?v=login');
            exit;
        }
        // USAR __DIR__ AQUÍ ES CRUCIAL PARA REQUERIR LA VISTA DESDE LA RAÍZ
        require_once __DIR__ . '/views/usuarios.php';
        break;
        // Dentro del switch ($vista) de tu index.php:
case 'gestion_usuarios':
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: index.php?v=login');
        exit;
    }
    // Carga tu archivo físico de la vista
    require_once __DIR__ . '/views/usuarios.php'; 
    break;

         // Añade estos nuevos casos dentro del switch de tu index.php central:
    case 'cocina':
        if (!isset($_SESSION['usuario_id'])) { header('Location: index.php?v=login'); exit; }
        require_once __DIR__ . '/views/cocina.php';
        break;
    case 'horno':
        if (!isset($_SESSION['usuario_id'])) { header('Location: index.php?v=login'); exit; }
        require_once __DIR__ . '/views/horno.php';
        break;
    case 'bar':
        if (!isset($_SESSION['usuario_id'])) { header('Location: index.php?v=login'); exit; }
        require_once __DIR__ . '/views/bar.php';
        break;
    case 'mesas':
        if (!isset($_SESSION['usuario_id'])) { header('Location: index.php?v=login'); exit; }
        require_once __DIR__ . '/views/mesas.php';
        break;

    default:
        http_response_code(404);
        echo "<h1>404 - Página no encontrada en Jungle Pizza</h1>";
        echo "<a href='index.php'>Volver al inicio</a>";
        break;
}
?>