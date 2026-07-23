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
       // Dentro del switch ($vista) de tu index.php:

    // Caso 1: La nueva tabla paginada con buscadores
    case 'mantenimiento_productos':
        if (!isset($_SESSION['usuario_id']) || ((int)$_SESSION['rol_id'] !== 1 && (int)$_SESSION['rol_id'] !== 2)) {
            header('Location: index.php?v=dashboard'); exit;
        }
        require_once __DIR__ . '/views/lista-productos.php'; // <-- Apunta a la nueva lista
        break;

    // Caso 2: El formulario limpio (que antes tenías a la izquierda) ahora en pantalla completa
    case 'mantenimiento_productos_nuevo':
        if (!isset($_SESSION['usuario_id']) || ((int)$_SESSION['rol_id'] !== 1 && (int)$_SESSION['rol_id'] !== 2)) {
            header('Location: index.php?v=dashboard'); exit;
        }
        require_once __DIR__ . '/views/productos.php'; // <-- Apunta al formulario
        break;
// ============================================================================
    // 🗂️ NUEVA RUTA: MANTENIMIENTO DE CATEGORÍAS DEL MENÚ
    // ============================================================================
    case 'mantenimiento_categorias':
        // 1. Verificación de sesión: Si no está logueado, lo rebota al login ordinario
        if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol_id'])) {
            header('Location: index.php?v=login');
            exit;
        }

        // 2. Filtro de jerarquía: Solo Superadmin (1) o Admin (2) gestionan las categorías
        $rolSesion = (int)$_SESSION['rol_id'];
        if ($rolSesion !== 1 && $rolSesion !== 2) {
            header('Location: index.php?v=dashboard');
            exit;
        }

        // 3. Cargamos de forma segura el archivo físico de la vista
        require_once __DIR__ . '/views/categorias.php';
        break;
            // Dentro del switch ($vista) de tu index.php:
    
    case 'mantenimiento_areas':
        if (!isset($_SESSION['usuario_id']) || ((int)$_SESSION['rol_id'] !== 1 && (int)$_SESSION['rol_id'] !== 2)) {
            header('Location: index.php?v=dashboard'); exit;
        }
        require_once __DIR__ . '/views/areas.php';
        break;

    case 'mantenimiento_mesas':
        if (!isset($_SESSION['usuario_id']) || ((int)$_SESSION['rol_id'] !== 1 && (int)$_SESSION['rol_id'] !== 2)) {
            header('Location: index.php?v=dashboard'); exit;
        }
        require_once __DIR__ . '/views/mesas.php';
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
  // REEMPLAZA ESOS DOS CASOS EN TU index.php POR ESTA CONFIGURACIÓN LIMPIA:

    // 🪑 1. EL MAPA DEL SALÓN (Para los meseros: botones de colores, comandas y liberación)
    case 'mesas':
        if (!isset($_SESSION['usuario_id'])) { 
            header('Location: index.php?v=login'); 
            exit; 
        }
        // Apunta exclusivamente al plano gráfico táctil
        require_once __DIR__ . '/views/mesas_salon.php';
        break;
        // ============================================================================
    // 🍕 RUTA CENTRAL DE VENTAS: CATÁLOGO TÁCTIL Y LEVANTAMIENTO DE PEDIDOS
    // ============================================================================
    case 'tomar_pedido':
        // 1. Verificación de sesión ordinaria: Si no está logueado, lo rebota al login
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: index.php?v=login');
            exit;
        }

        // 2. Filtro de seguridad por ID de Comanda: Evita entrar a pantallas de facturación vacías
        $pedido_id = isset($_GET['pedido_id']) ? intval($_GET['pedido_id']) : 0;
        if ($pedido_id <= 0) {
            header('Location: index.php?v=mesas&error=' . urlencode('Seleccione una mesa o abra una comanda válida.'));
            exit;
        }

        // 3. Cargamos de forma segura el archivo físico de la vista
        require_once __DIR__ . '/views/tomar_pedido.php';
        break;
       // Busca el 'case abrir_comanda' en tu index.php y reemplázalo por esta lógica directa:

    case 'abrir_comanda':
        if (!isset($_SESSION['usuario_id'])) { 
            header('Location: index.php?v=login'); 
            exit; 
        }

        // Captura directa de datos desde el mapa de mesas o botones superiores
        $mesa_id     = isset($_GET['mesa_id']) ? intval($_GET['mesa_id']) : 0;
        $tipo_pedido = isset($_GET['tipo_pedido']) ? trim($_GET['tipo_pedido']) : 'local';
        $monto_envio = isset($_GET['monto_envio']) ? floatval($_GET['monto_envio']) : 0.00;
        $usuario_id  = (int)$_SESSION['usuario_id'];
        $caja_turno_id = isset($_SESSION['caja_turno_id']) ? (int)$_SESSION['caja_turno_id'] : null;

        // Invocamos al modelo para insertar la comanda en silencio en MySQL
        require_once __DIR__ . '/models/PedidoModelo.php';
        $pedidoModelo = new PedidoModelo();
        
        $pedido_id = $pedidoModelo->abrirNuevaComanda($usuario_id, $caja_turno_id, $mesa_id, $tipo_pedido, $monto_envio);
        
        if ($pedido_id) {
            // 🚀 REDIRECCIÓN INSTANTÁNEA: Entra directo a levantar el pedido al Punto de Venta
            header("Location: index.php?v=tomar_pedido&pedido_id=" . $pedido_id);
            exit;
        } else {
            header("Location: index.php?v=mesas&error=" . urlencode("No se pudo aperturar la cuenta. Verifique su caja."));
            exit;
        }
        break;


   


    default:
        http_response_code(404);
        echo "<h1>404 - Página no encontrada en Jungle Pizza</h1>";
        echo "<a href='index.php'>Volver al inicio</a>";
        break;
}
?>