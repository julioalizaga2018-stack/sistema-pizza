<?php
// index.php (Raíz del proyecto - Ecosistema Jungle Pizza)

// En la línea 2 de tu index.php antes de cualquier otra cosa
date_default_timezone_set('America/Managua');

// 1. Forzar a PHP a mostrar cualquier error oculto en pantalla para depuración limpia
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Iniciar la sesión de forma global para todo el restaurante
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Importar los controladores y utilidades necesarias de forma absoluta
require_once __DIR__ . '/controllers/UsuarioController.php';

// 4. Capturar la acción de vista que solicita el usuario (por defecto va al login)
$vista = $_GET['v'] ?? 'login';
// ============================================================================
// 🖨️ INTERCEPTOR EXCLUSIVO PARA IMPRESIONES Y CONSULTAS ASÍNCRONAS AJAX (CORREGIDO)
// ============================================================================
if (isset($_GET['v']) && ($_GET['v'] === 'imprimir_ticket' || $_GET['v'] === 'imprimir_cierre' || $_GET['v'] === 'api_detalle_compra' || $_GET['v'] === 'api_receta_detalle' || $_GET['v'] === 'imprimir_compra')) {
    
    // Filtro de seguridad unificado para impresiones y consumo de APIs de auditoría
    if (!isset($_SESSION['usuario_id'])) { 
        if ($_GET['v'] === 'api_detalle_compra') {
            echo json_encode(['status' => 'error', 'msg' => 'Sesión inválida o expirada.']);
        } else {
            header('Location: index.php?v=login'); 
        }
        exit; 
    }
    
    // Enrutamiento modular limpio e independiente
    if ($_GET['v'] === 'imprimir_ticket') {
        require_once __DIR__ . '/views/imprimir_ticket.php';
    }
     elseif ($_GET['v'] === 'imprimir_cierre') {
        require_once __DIR__ . '/views/imprimir_cierre.php';
        
    } 
    // 🌟 NUEVA COMPUERTA: Invoca el renderizado térmico de la factura de abasto
    elseif ($_GET['v'] === 'imprimir_compra') {
        require_once __DIR__ . '/views/imprimir_compra.php';
        exit;
    }
    elseif ($_GET['v'] === 'api_detalle_compra') {
        // Al estar incluido en el IF principal, este bloque se ejecutará limpiamente
        require_once __DIR__ . '/controllers/CompraController.php';
        $apiController = new CompraController();
        $apiController->obtenerDetalleCompraAjax(); 
    }
            // 🌟 API EN CALIENTE TOTALMENTE CONTROLADA: Forzar salida limpia libre de espacios basura
    elseif ($_GET['v'] === 'api_receta_detalle') {
        // Desactivamos la inyección automática de errores en formato HTML para que no rompan el parseo de JS
        ini_set('display_errors', 0); 
        
        // Limpiamos y destruimos CUALQUIER texto residual acumulado en memoria por XAMPP
        while (ob_get_level()) { ob_end_clean(); }
        
        // Forzamos cabeceras HTTP de formato estrictas
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        
        require_once __DIR__ . '/models/RecetaModelo.php';
        
        $p_id = filter_var($_GET['plato_id'] ?? 0, FILTER_VALIDATE_INT);
        
        try {
            $recetaModel = new RecetaModelo();
            $data = $recetaModel->obtenerIngredientesDePlato($p_id);
            
            // Retornamos de forma explícita y forzada
            echo json_encode(['status' => 'success', 'data' => $data]);
            exit; // Detiene el servidor en el acto para evitar inyecciones posteriores
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            exit;
        }
    }


    exit; 
}






// 5. Instanciar el controlador de usuarios para verificar el estado del sistema
$usuarioCtrl = new UsuarioController();

// 6. Enrutamiento lógico centralizado por bloques con discriminación de roles
switch ($vista) {

    // Cambia esto en tu index.php principal:
    case 'login':
        if (isset($_SESSION['usuario_id']) && !$usuarioCtrl->esSistemaNuevo()) {
            // CORREGIDO: Cambiamos 'catalogo' por 'dashboard'
            header('Location: index.php?v=dashboard');
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
    // ============================================================================
    // ⚙️ MÓDULOS CRÍTICOS DEL NÚCLEO: ACCESO EXCLUSIVO PARA SUPERADMIN (1) Y ADMIN (2)
    // El Supervisor (Rol 3) es rebotado automáticamente con alerta destructiva.
    // ============================================================================

    case 'config_empresa':
    case 'configuracion':
        if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol_id'])) {
            header('Location: index.php?v=login');
            exit;
        }
        
        // 🌟 CANDADO INDUSTRIAL: Si el rol es igual o mayor a 3 (Supervisor, Mesero, etc.), bloqueamos
        if ((int)$_SESSION['rol_id'] >= 3) {
            header('Location: index.php?v=dashboard&error=' . urlencode('Acceso Restringido: El rol de Supervisor no posee privilegios para modificar la configuración del sistema.'));
            exit;
        }
        
        $vista_archivo = ($vista === 'config_empresa') ? 'config_empresa.php' : 'configuracion.php';
        require_once __DIR__ . '/views/' . $vista_archivo;
        break;

    case 'usuarios':
    case 'gestion_usuarios':
        if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol_id'])) {
            header('Location: index.php?v=login');
            exit;
        }
        
        // El personal de soporte y el admin administran usuarios, el supervisor queda fuera por seguridad
        if ((int)$_SESSION['rol_id'] >= 3) {
            header('Location: index.php?v=dashboard&error=' . urlencode('Acceso Restringido: Gestión de personal exclusiva para Administradores.'));
            exit;
        }
        
        $vista_usuario = ($vista === 'usuarios') ? 'usuarios.php' : 'gestion_usuarios.php';
        require_once __DIR__ . '/views/usuarios.php';
        break;

      



                  // ============================================================================
    // 🍕 MÓDULOS OPERATIVOS: ACCESO COMBINADO PARA SUPERADMIN (1), ADMIN (2) Y SUPERVISOR (3)
    // ============================================================================

    // 🅰️ GRUPO A: Módulos Administrativos y de Configuración (Libres de candado de apertura de caja)
    case 'mantenimiento_productos':
    case 'mantenimiento_productos_nuevo':
    case 'mantenimiento_categorias':
    case 'mantenimiento_areas':
    case 'mantenimiento_mesas': // 🌟 Dejado aquí para el mantenimiento técnico de ID de mesas
    case 'proveedores': 
    case 'inventario_ajustes': 
    case 'gestion_caja': 
    case 'cobranza_historial': 
    case 'ventas_productos': 
    case 'compras_lista':     
    case 'compras_registrar': 
    case 'reportes_mensuales': 
    case 'recetas_lista': 
    
        if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol_id'])) {
            header('Location: index.php?v=login');
            exit;
        }

        $rolSesion = (int)$_SESSION['rol_id'];
        if ($rolSesion !== 1 && $rolSesion !== 2 && $rolSesion !== 3) {
            header('Location: index.php?v=dashboard');
            exit;
        }

        // Renderizado directo de vistas administrativas (Sincronizado con tus archivos reales)
        if ($vista === 'mantenimiento_productos') require_once __DIR__ . '/views/lista-productos.php';
        elseif ($vista === 'mantenimiento_productos_nuevo') require_once __DIR__ . '/views/productos.php';
        elseif ($vista === 'mantenimiento_categories' || $vista === 'mantenimiento_categorias') require_once __DIR__ . '/views/categorias.php';
        elseif ($vista === 'mantenimiento_areas') require_once __DIR__ . '/views/areas.php';
        elseif ($vista === 'mantenimiento_mesas') require_once __DIR__ . '/views/mesas.php'; // CRUD de configuración
        elseif ($vista === 'proveedores') require_once __DIR__ . '/views/proveedores.php'; 
        elseif ($vista === 'inventario_ajustes') require_once __DIR__ . '/views/inventario_ajustes.php'; 
        elseif ($vista === 'gestion_caja') require_once __DIR__ . '/views/gestion_caja.php'; 
        elseif ($vista === 'cobranza_historial') require_once __DIR__ . '/views/cobranza_historial.php'; 
        elseif ($vista === 'ventas_productos') require_once __DIR__ . '/views/ventas_productos.php'; 
        elseif ($vista === 'compras_lista') require_once __DIR__ . '/views/compras_lista.php';         
        elseif ($vista === 'compras_registrar') require_once __DIR__ . '/views/compras_registrar.php'; 
        elseif ($vista === 'reportes_mensuales') require_once __DIR__ . '/views/reportes_mensuales.php'; 
        elseif ($vista === 'recetas_lista') require_once __DIR__ . '/views/recetas_lista.php'; 
    break;

   // ============================================================================
// 🅱️ GRUPO B: Módulos Operativos de Salón y Caja (Acceso Libre por Enrutador)
// ============================================================================
case 'mesas':         
case 'tomar_pedido':    
case 'cobranza_lista':    
case 'cobranza_facturar': 

    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol_id'])) {
        header('Location: index.php?v=login');
        exit;
    }

    // Renderizado Directo Sin Interrupciones
    if ($vista === 'mesas') require_once __DIR__ . '/views/mesas_salon.php'; 
    elseif ($vista === 'tomar_pedido') require_once __DIR__ . '/views/tomar_pedido.php'; 
    elseif ($vista === 'cobranza_lista') require_once __DIR__ . '/views/cobranza_lista.php';       
    elseif ($vista === 'cobranza_facturar') require_once __DIR__ . '/views/cobranza_facturar.php'; 
break;



    // ============================================================================
    // 🍳 PANTALLAS DE PRODUCCIÓN KDS: ACCESO LIBRE SEGÚN ESTACIÓN
    // ============================================================================

    case 'cocina':
    case 'horno':
    case 'bar':
        if (!isset($_SESSION['usuario_id'])) { 
            header('Location: index.php?v=login'); 
            exit; 
        }
        
        if ($vista === 'cocina') require_once __DIR__ . '/views/cocina.php';
        elseif ($vista === 'horno') require_once __DIR__ . '/views/horno.php';
        elseif ($vista === 'bar') require_once __DIR__ . '/views/bar.php';
        break;


    // ============================================================================
    // 🪑 FLUX DE SALÓN Y VENTAS: EL MAPA DEL SALÓN Y EL CIRCUITO DE REAPERTURA
    // ============================================================================

    case 'mesas':
        if (!isset($_SESSION['usuario_id'])) { 
            header('Location: index.php?v=login'); 
            exit; 
        }
        // Apunta exclusivamente al plano gráfico táctil de los salones
        require_once __DIR__ . '/views/mesas_salon.php';
        break;

    case 'tomar_pedido':
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: index.php?v=login');
            exit;
        }

        // Filtro de seguridad por ID de Comanda: Evita entrar a pantallas vacías
        $pedido_id = isset($_GET['pedido_id']) ? intval($_GET['pedido_id']) : 0;
        if ($pedido_id <= 0) {
            header('Location: index.php?v=mesas&error=' . urlencode('Seleccione una mesa o abra una comanda válida.'));
            exit;
        }

        require_once __DIR__ . '/views/tomar_pedido.php';
        break;
    case 'reporte_ventas':
        require_once 'views/reporte_ventas.php';
        break;


    case 'abrir_comanda':
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: index.php?v=login');
            exit;
        }

        $mesa_id       = isset($_GET['mesa_id']) ? intval($_GET['mesa_id']) : 0;
        $tipo_pedido   = isset($_GET['tipo_pedido']) ? trim($_GET['tipo_pedido']) : 'local';
        $monto_envio   = isset($_GET['monto_envio']) ? floatval($_GET['monto_envio']) : 0.00;
        $usuario_id    = (int)$_SESSION['usuario_id'];
        $caja_turno_id = isset($_SESSION['caja_turno_id']) ? (int)$_SESSION['caja_turno_id'] : null;

        // 🌟 CANDADO DE RECUPERACIÓN AUTOMÁTICA: Si es Consumo Local y la mesa física ya está ocupada
        if ($tipo_pedido === 'local' && $mesa_id > 0) {
            $db_check = (new Conexion())->conectar();
            
            // Buscamos si posee una comanda abierta en estado borrador/pendiente
            $stmtCheck = $db_check->prepare("SELECT id FROM pedidos WHERE mesa_id = :mesa_id AND estado = 'pendiente' LIMIT 1");
            $stmtCheck->execute(['mesa_id' => $mesa_id]);
            $pedidoExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($pedidoExistente) {
                $id_pedido_activo = (int)$pedidoExistente['id'];
                
                // Forzamos la persistencia del estado en el plano
                $db_check->prepare("UPDATE mesas SET estado = 'ocupada' WHERE id = :id")->execute(['id' => $mesa_id]);
                
                header("Location: index.php?v=tomar_pedido&pedido_id=" . $id_pedido_activo);
                exit;
            }
        }

        // ➕ CREACIÓN LIMPIA: Si es una mesa libre o un despacho express
        require_once __DIR__ . '/models/PedidoModelo.php';
        $pedidoModelo = new PedidoModelo();
        
        $pedido_id = $pedidoModelo->abrirNuevaComanda($usuario_id, $caja_turno_id, $mesa_id, $tipo_pedido, $monto_envio);
        
        if ($pedido_id) {
            header("Location: index.php?v=tomar_pedido&pedido_id=" . $pedido_id);
            exit;
        } else {
            header("Location: index.php?v=mesas&error=" . urlencode("No se pudo aperturar la cuenta. Verifique su caja."));
            exit;
        }
        break;
    case 'kds_monitor':
        if (!isset($_SESSION['usuario_id'])) { header('Location: index.php?v=login'); exit; }
        require_once __DIR__ . '/views/kds_monitor.php';
        break;
        // ============================================================================
        // 🧾 IMPRESIÓN DE PRECUENTA: Vista térmica optimizada para tiquetera del salón
        // ============================================================================
        case 'precuenta':
            if (!isset($_SESSION['usuario_id'])) {
                header('Location: index.php?v=login');
                exit;
            }
            
            $pedido_id = isset($_GET['pedido_id']) ? intval($_GET['pedido_id']) : 0;
            if ($pedido_id <= 0) {
                header('Location: index.php?v=mesas&error=' . urlencode('ID de comanda inválido para precuenta.'));
                exit;
            }
            
            // Invoca directamente al archivo físico de impresión térmica
            require_once __DIR__ . '/views/precuenta_ticket.php';
            break;


    // ============================================================================
    // 🔕 CIERRE DEL ENRUTADOR: MANEJO DE EXCEPCIONES 404
    // ============================================================================

    default:
        http_response_code(404);
        echo "<div style='font-family:sans-serif; text-align:center; padding:50px; color:#1b4332;'>";
        echo "<h1 style='font-size:40px; margin-bottom:10px;'>🦁 404 - Página no encontrada</h1>";
        echo "<p style='color:#666; margin-bottom:20px;'>La sección solicitada no existe en el ecosistema de Jungle Pizza.</p>";
        echo "<a href='index.php?v=mesas' style='background:#1b4332; color:#fff; padding:10px 20px; border-radius:6px; text-decoration:none; font-weight:bold;'>Volver al Plano de Salones</a>";
        echo "</div>";
        break;
}
?>
