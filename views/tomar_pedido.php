<?php
// views/tomar_pedido.php (Parte 1 de 8 - Arquitectura Jungle POS Ultra)
// 1. Validaciones e instancias de los datos del pedido actual
require_once __DIR__ . '/../models/PedidoModelo.php';
require_once __DIR__ . '/../controllers/ProductoController.php';

$pedido_id = intval($_GET['pedido_id'] ?? 0);
$db = (new Conexion())->conectar();

// views/tomar_pedido.php (Modificación en la cabecera superior)
$stmtPed = $db->prepare("SELECT p.*, m.numero_mesa, a.nombre as nombre_area, u.nombre as nombre_mesero, p.cliente_nombre -- 👈 Agregado de forma segura aquí
                         FROM pedidos p 
                         LEFT JOIN mesas m ON p.mesa_id = m.id
                         LEFT JOIN areas a ON m.area_id = a.id
                         INNER JOIN usuarios u ON p.usuario_id = u.id
                         WHERE p.id = :id LIMIT 1");

$stmtPed->execute(['id' => $pedido_id]);
$pedidoInfo = $stmtPed->fetch();

if (!$pedidoInfo) {
    header("Location: index.php?v=mesas&error=" . urlencode("El pedido solicitado no existe."));
    exit;
}

// 2. Cargamos el catálogo de productos disponibles y sus categorías para las pestañas
$prodController = new ProductoController();
$categoriasMenu = $prodController->obtenerCategoriasPedido();
$productosMenu = $prodController->listarParaMesero();

// 3. Extraemos el detalle actual agregando p.categoria_id para agrupar las bebidas de forma exacta
$stmtDet = $db->prepare("SELECT pd.id, pd.pedido_id, pd.producto_id, pd.cantidad, pd.precio_unitario, pd.subtotal, pd.estado, pd.es_mixta, p.nombre as nombre_producto, p.imagen, p.categoria_id
                         FROM pedido_detalles pd 
                         INNER JOIN productos p ON pd.producto_id = p.id
                         WHERE pd.pedido_id = :pedido_id AND pd.estado NOT IN ('quitado_antes', 'quitado_despues')");
$stmtDet->execute(['pedido_id' => $pedido_id]);
$itemsComanda = $stmtDet->fetchAll();

// 4. Sincronización automática de URL_BASE
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
if (!defined('URL_BASE')) {
    define('URL_BASE', ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/");
}
$msg_error = $_GET['error'] ?? null;
$msg_success = $_GET['success'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Punto de Venta Táctil - Jungle Pizza</title>
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    <style>
        /* ==========================================================================
   🚀🚀 CORE LAYOUT: DISEÑO ELÁSTICO ADAPTATIVO JUNGLE POS ULTRA
   ========================================================================== */
/* ==========================================================================
   🚀🚀 CORE LAYOUT: DISEÑO ELÁSTICO ADAPTATIVO JUNGLE POS ULTRA (Limpio)
   ========================================================================== */
.pos-grid-container {
    display: grid !important;
    grid-template-columns: 1fr; /* 1 columna por defecto en smartphones */
    gap: 15px;
    margin-top: 15px;
    width: 100%;
    height: calc(100vh - 140px) !important;
    min-height: 550px;
    box-sizing: border-box;
}

.pos-card {
    background: #ffffff;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(27, 67, 50, 0.06);
    padding: 18px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    height: 100% !important;
    box-sizing: border-box;
    border: 1px solid #e2e8f0;
}

.category-select-wrapper {
    margin-bottom: 12px;
    width: 100%;
}

.category-select-wrapper select {
    padding: 14px !important;
    font-size: 16px !important;
    border-radius: 10px !important;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03) !important;
}

.menu-products-layout {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    overflow-y: auto;
    flex: 1;
    padding-right: 5px;
}

.product-item-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    cursor: pointer;
    transition: transform 0.1s ease, border-color 0.2s, box-shadow 0.2s;
    position: relative;
    height: 210px !important;
}

.product-item-card:active {
    transform: scale(0.96);
    background: #f1f5f9;
}

.product-item-card img {
    width: 100%;
    height: 110px !important;
    object-fit: contain !important;
    background: #ffffff;
    padding: 6px;
    box-sizing: border-box;
    border-bottom: 1px solid #edf2f7;
}

.product-body-desc {
    padding: 10px;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.product-item-title {
    font-size: 13px !important;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.3;
    margin-bottom: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-item-price {
    font-size: 14px !important;
    font-weight: 800;
    color: #e67e22;
    margin-top: auto;
}

.comanda-ticket-wrapper {
    display: flex;
    flex-direction: column;
    height: 100% !important;
    justify-content: space-between;
}

.ticket-header-info {
    padding-bottom: 12px;
    border-bottom: 1px dashed #cbd5e1;
    margin-bottom: 12px;
    font-size: 13px;
}

/* ==========================================================================
   ⚙️ ENGINE REESTRUCTURADO: MÁXIMA AMPLITUD PARA EL PLATO Y BEBIDAS
   ========================================================================== */
.ticket-rows-scroll {
    flex: 1 !important;
    overflow-y: auto !important;
    padding-right: 5px;
    /* 🔥 CLAVE DE AMPLITUD: Cambiamos el porcentaje fijo por cálculo elástico dinámico de viewport */
    min-height: 200px !important;
    max-height: calc(100vh - 410px) !important; 
    margin-bottom: 12px;
    border: 1px solid #f1f5f9;
    border-radius: 8px;
    background: #fafbfc;
    padding: 8px;
    box-sizing: border-box;
}

/* Filas elásticas y alineadas arriba */
.ticket-item-row {
    display: flex !important;
    justify-content: space-between !important;
    align-items: start !important;
    padding: 10px 8px !important;
    border-bottom: 1px solid #edf2f7 !important;
    border-radius: 8px !important;
    margin-bottom: 6px !important;
    box-sizing: border-box !important;
    font-size: 14px !important;
    line-height: 1.35 !important;
}

/* Fila interna de acciones */
.action-row-buttons {
    display: flex !important;
    gap: 6px !important;
    margin-top: 6px !important;
    align-items: center !important;
    flex-wrap: wrap !important;
}

/* 🔥 BOTONES MINI BLINDADOS: Evitan el estiramiento vertical desproporcionado */
.btn-mini-pos {
    border: none !important;
    padding: 4px 10px !important; 
    font-size: 11px !important;
    font-weight: 800 !important;
    border-radius: 5px !important;
    cursor: pointer !important;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    height: 25px !important; /* Altura fija milimétrica táctil */
    box-sizing: border-box !important;
    text-transform: uppercase !important;
}

.btn-mini-extra {
    background: #fff3cd !important;
    color: #856404 !important;
    border: 1px solid #ffeeba !important;
}

.btn-mini-delete {
    background: #ffe3e3 !important;
    color: #c92a2a !important;
    border: 1px solid #fdbfbf !important;
    width: auto !important;
    min-width: 75px !important;
}

.ticket-summary-totals {
    background: #f8fafc;
    padding: 14px;
    border-radius: 10px;
    margin-top: auto;
    border: 1px solid #e2e8f0;
    font-size: 13px;
}

.summary-flex-line {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
}

.summary-total-bold {
    border-top: 2px dashed #cbd5e1;
    padding-top: 8px;
    font-size: 19px !important;
    font-weight: 800;
    color: #1b4332;
    margin-bottom: 0;
}

.alert {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 13px;
    font-weight: 500;
}

.alert-error {
    background: #ffe3e3;
    color: #c92a2a;
    border: 1px solid #fdbfbf;
}

.alert-success {
    background: #ebfbee;
    color: #2b8a3e;
    border: 1px solid #c3e6cb;
}

.form-control {
    width: 100% !important;
    padding: 12px !important;
    border: 2px solid #e2e8f0 !important;
    border-radius: 8px !important;
    box-sizing: border-box !important;
}

/* ==========================================================================
   📱 RESPONSIVE MEDIA QUERIES: OPTIMIZACIÓN INDUSTRIAL MULTI-PANTALLA
   ========================================================================== */
@media (min-width: 600px) and (max-width: 1024px) {
    .pos-grid-container {
        grid-template-columns: 1fr 340px !important;
        gap: 12px;
    }
    .menu-products-layout {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)) !important;
    }
    .product-item-card {
        height: 190px !important;
    }
}

@media (min-width: 1025px) {
    .pos-grid-container {
        grid-template-columns: 1fr 420px !important;
        gap: 20px;
    }
    .menu-products-layout {
        grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)) !important;
    }
    .product-item-card:hover {
        border-color: #27AE60;
        box-shadow: 0 6px 15px rgba(39, 174, 96, 0.12);
        transform: translateY(-2px);
    }
    .mobile-pos-nav {
        display: none !important;
    }
}

@media (max-width: 599px) {
    body::after {
        content: "";
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 65px;
        background: #ffffff;
        box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.06);
        z-index: 99998;
    }
    .pos-grid-container {
        grid-template-columns: 1fr !important;
        height: calc(100vh - 150px) !important;
        margin-bottom: 75px;
    }
    .pos-grid-container .pos-card:last-child {
        display: none !important;
    }
    body.ver-ticket-activo .pos-grid-container .pos-card:first-child {
        display: none !important;
    }
    body.ver-ticket-activo .pos-grid-container .pos-card:last-child {
        display: flex !important;
        height: 100% !important;
        overflow-y: auto !important;
    }
    body.ver-ticket-activo .comanda-ticket-wrapper {
        height: auto !important;
        display: block !important;
    }
    /* 🔥 EN CELULARES EL TICKET TOMA SU ANCHO FLUIDO NATURAL */
    .ticket-rows-scroll {
        flex: none !important;
        max-height: none !important;
        overflow-y: visible !important;
        margin-bottom: 15px;
    }
    .ticket-summary-totals {
        margin-top: 25px !important;
        position: relative !important;
        z-index: 10;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }
    .action-row-buttons {
        gap: 12px !important;
        margin-top: 10px;
    }
    .btn-mini-pos {
        padding: 6px 12px !important;
        height: 28px !important; /* Rango táctil ideal en smartphones */
    }
    .mobile-pos-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 65px;
        display: flex !important;
        z-index: 99999;
        background: #ffffff;
        border-top: 1px solid #edf2f7;
    }
    .pos-tab-trigger {
        flex: 1;
        border: none;
        background: none;
        font-weight: 800;
        font-size: 11px;
        text-transform: uppercase;
        color: #94a3b8;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        cursor: pointer;
        transition: background 0.2s;
    }
    .pos-tab-trigger.active-tab {
        color: #27AE60;
        border-top: 3px solid #27AE60;
        background: #f8fafc;
    }
}

    </style>
</head>

<body>
    <header class="mobile-header">
        <button class="hamburger-btn" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <div class="mobile-logo">🍕🍕🍕🍕 Jungle POS</div>
    </header>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>
        <main class="main-content" style="padding-bottom: 5px;">
            <?php if ($msg_error): ?><div class="alert alert-error">⚠ <?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
            <?php if ($msg_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div><?php endif; ?>
            <div class="pos-grid-container">

                <!-- COLUMNA IZQUIERDA: EL CATÁLOGO VISUAL SELECCIONABLE -->
                <div class="pos-card">
                    <div class="category-select-wrapper">
                        <label style="display: block; margin-bottom: 6px; font-weight: 700; font-size: 13px; color: #1b4332;">📁📁📁📁 Seleccionar Categoría:</label>
                        <?php
                        $ordenIdealGastro = ['entrada', 'pizza', 'almuerzo', 'rapida', 'bebida', 'empaque'];
                        usort($categoriasMenu, function ($a, $b) use ($ordenIdealGastro) {
                            $nombreA = strtolower($a['nombre']);
                            $nombreB = strtolower($b['nombre']);
                            $posA = false;
                            $posB = false;
                            foreach ($ordenIdealGastro as $index => $keyword) {
                                if (strpos($nombreA, $keyword) !== false) {
                                    $posA = $index;
                                    break;
                                }
                            }
                            foreach ($ordenIdealGastro as $index => $keyword) {
                                if (strpos($nombreB, $keyword) !== false) {
                                    $posB = $index;
                                    break;
                                }
                            }
                            $posA = ($posA === false) ? 99 : $posA;
                            $posB = ($posB === false) ? 99 : $posB;
                            return $posA <=> $posB;
                        });
                        ?>
                        <select class="form-control" id="select-categoria-pos" onchange="filtrarCatalogoArea(this.value, this)">
                            <option value="0">🌍🌍🌍🌍 Mostrar Todo el Menú</option>
                            <?php
                            foreach ($categoriasMenu as $cat):
                                $id_limpio = (int)$cat['id'];
                                $nombre_minuscula = strtolower($cat['nombre']);
                                $icono = '🍽 ';
                                if (strpos($nombre_minuscula, 'entrada') !== false) $icono = '🥗 ';
                                elseif (strpos($nombre_minuscula, 'pizza') !== false) $icono = '🍕 ';
                                elseif (strpos($nombre_minuscula, 'almuerzo') !== false) $icono = '🍲 ';
                                elseif (strpos($nombre_minuscula, 'rapida') !== false) $icono = '🍔 ';
                                elseif (strpos($nombre_minuscula, 'bebida') !== false || $id_limpio === 5) $icono = '🍹 ';
                                elseif (strpos($nombre_minuscula, 'empaque') !== false) $icono = '📦 ';
                            ?>
                                <option value="<?php echo $id_limpio; ?>"><?php echo $icono . htmlspecialchars($cat['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <!-- 🔍 CUADRO DE BÚSQUEDA RÁPIDA DE ALTA EFICIENCIA EN TIEMPO REAL -->
                        <div style="margin-top: 10px;">
                            <input type="text" id="buscador-productos-pos" class="form-control"
                                placeholder="🔍 Buscar por nombre... (Ej: Toña, Pepperoni, Coca)"
                                onkeyup="ejecutarBusquedaRapidaItem(this.value)"
                                style="background: #f8fafc; border: 2px solid #cbd5e1 !important; font-weight: 600;">
                        </div>
                    </div>
                    <div class="menu-products-layout">
                        <!-- CONFIGURADOR MIXTO FIJO EN PRIMERA POSICIÓN -->
                        <div class="product-item-card" data-cat-id="2"
                            style="background: linear-gradient(135deg, #fff3cd, #ffe8cc); border: 2px dashed #e67e22;" onclick="abrirModalPizzaMixta()">
                            <div style="width:100%; height:95px; background:rgba(230, 126, 34, 0.1); display:flex; align-items:center; justify-content:center; font-size:32px;">🍕🍕🍕🍕</div>
                            <div class="product-body-desc" style="justify-content: center; text-align: center;">
                                <span class="product-item-title" style="color: #b55d05; font-size: 14px; margin: 0;">★ Armar Pizza Mixta</span>
                                <span style="font-size: 11px; font-weight: bold; color: #e67e22;">Mitad y Mitad</span>
                            </div>
                        </div>

                        <!-- ITERACIÓN AUTOMÁTICA DE PRODUCTOS -->
                        <?php if (!empty($productosMenu)): ?>
                            <?php foreach ($productosMenu as $p): ?>
                                <div class="product-item-card" data-cat-id="<?php echo $p['categoria_id']; ?>" onclick="agregarProductoFila(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['nombre']); ?>', <?php echo $p['precio_base']; ?>)">
                                    <?php if (!empty($p['imagen']) && file_exists(__DIR__ . '/../public/uploads/productos/' . $p['imagen'])): ?>
                                        <img src="<?php echo URL_BASE; ?>public/uploads/productos/<?php echo $p['imagen']; ?>" alt="Foto Menu">
                                    <?php else: ?>
                                        <div style="width:100%; height:95px; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-size:24px;">🍽🍽🍽🍽</div>
                                    <?php endif; ?>
                                    <div class="product-body-desc">
                                        <span class="product-item-title"><?php echo htmlspecialchars($p['nombre']); ?></span>
                                        <span class="product-item-price">C$ <?php echo number_format($p['precio_base'], 2); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <form id="form-add-item-hidden" action="<?php echo URL_BASE; ?>controllers/PedidoController.php" method="POST" style="display:none;">
                    <input type="hidden" name="accion" value="agregar_item">
                    <input type="hidden" name="pedido_id" value="<?php echo $pedido_id; ?>">
                    <input type="hidden" name="producto_id" id="hidden-prod-id">
                    <input type="hidden" name="cantidad" value="1">
                    <input type="hidden" name="precio_unitario" id="hidden-prod-price">
                    <input type="hidden" name="es_mixta" value="0">
                </form>

                <!-- COLUMNA DERECHA: DESGLOSE DE LA COMANDA ACTIVA -->
                <div class="pos-card">
                    <div class="comanda-ticket-wrapper">
                        <div class="ticket-header-info">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span style="font-weight:800; color:#1b4332; font-size: 14px;">📋📋📋📋 TICKET #<?php echo $pedido_id; ?></span>
                                <span style="background: #e2e8f0; color: #334155; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;">
                                    👤👤👤👤 <?php echo htmlspecialchars($pedidoInfo['nombre_mesero'] ?? 'Sistema'); ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 8px 12px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 6px;">
                                <span style="color:#475569; font-weight: 600;">Modalidad: <strong style="color: #e67e22;"><?php echo strtoupper($pedidoInfo['tipo_pedido']); ?></strong></span>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 12px; color: #64748b; font-weight: 700;">Clientes:</span>
                                    <button type="button" onclick="ajustarComensales(-1)" style="width: 26px; height: 26px; border-radius: 4px; border: 1px solid #cbd5e1; background: #fff; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center;">-</button>
                                    <span id="label-num-personas" style="font-weight: 800; color: #1b4332; font-size: 14px; min-width: 15px; text-align: center;"><?php echo intval($pedidoInfo['num_personas'] ?? 1); ?></span>
                                    <button type="button" onclick="ajustarComensales(1)" style="width: 26px; height: 26px; border-radius: 4px; border: 1px solid #cbd5e1; background: #fff; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center;">+</button>
                                </div>
                            </div>
                            <?php if ($pedidoInfo['tipo_pedido'] === 'local' && !empty($pedidoInfo['mesa_id'])): ?>
                                <span style="color:#666; display: block; font-size: 12px; margin-bottom: 8px;">
                                    📍📍📍📍 Ubicación: <strong><?php echo htmlspecialchars($pedidoInfo['nombre_area'] ?? 'Salón'); ?> / <?php echo htmlspecialchars($pedidoInfo['numero_mesa'] ?? 'Barra'); ?></strong>
                                    <!-- 🔄 GATILLO VISUAL: Cambio de Mesa encapsulado -->
                                    <button type="button" onclick="abrirModalCambioMesa()" style="margin-left: 8px; background: #e67e22; color: #fff; border: none; padding: 3px 8px; font-size: 11px; font-weight: bold; border-radius: 4px; cursor: pointer; box-shadow: 0 2px 4px rgba(230,126,34,0.15);">🔄 Cambiar Mesa</button>
                                    <!-- 🌟 INYECTADO: Gatillo visual para disparar la separación transaccional de productos -->
                                    <button type="button" onclick="abrirModalDividirCuenta()" style="margin-left: 5px; background: #0284c7; color: #fff; border: none; padding: 3px 8px; font-size: 11px; font-weight: bold; border-radius: 4px; cursor: pointer; box-shadow: 0 2px 4px rgba(2,132,199,0.15);">👥 Dividir Cuenta</button>
                                </span>
                            <?php endif; ?>
                            <!-- Cuadro de texto dinámico para capturar el Nombre del Cliente en el POS -->
                            <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px; width: 100%;">
                                <span style="font-size: 12px; color: #64748b; font-weight: 700; min-width: 55px;">Cliente:</span>
                                <input type="text" id="input-cliente-nombre-pos" class="form-control"
                                    placeholder="👤 Nombre del cliente (Ej: Carlos / Pareja ventana)"
                                    value="<?php echo htmlspecialchars($pedidoInfo['cliente_nombre'] ?? ''); ?>"
                                    onchange="guardarNombreClienteDinamico(this.value)"
                                    style="padding: 6px 10px !important; font-size: 13px !important; border: 2px solid #cbd5e1 !important; border-radius: 6px !important; background: #ffffff; font-weight: 600; height: auto !important; flex: 1;">
                            </div>
                            <!-- views/tomar_pedido.php (Página 20 - Conector de Subcuentas) -->
<?php 
if (!empty($pedidoInfo['mesa_id']) && $pedidoInfo['tipo_pedido'] === 'local'): 
    // Buscamos si existen otros pedidos activos y sin cobrar amarrados a esta misma mesa física
    $stmtHermanos = $db->prepare("SELECT id, cliente_nombre FROM pedidos 
                                  WHERE mesa_id = :mesa_id AND estado = 'pendiente' AND id <> :id_actual");
    $stmtHermanos->execute([
        'mesa_id' => $pedidoInfo['mesa_id'],
        'id_actual' => $pedido_id
    ]);
    $cuentasHermanas = $stmtHermanos->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($cuentasHermanas)): 
?>
        <div style="margin-top: 10px; background: #e0f2fe; border: 1px solid #bae6fd; padding: 8px; border-radius: 6px; font-size: 12px; color: #0369a1;">
            <strong style="display: block; margin-bottom: 4px;">👥 Otras cuentas en esta mesa:</strong>
            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                <?php foreach ($cuentasHermanas as $hermano): ?>
                    <a href="index.php?v=tomar_pedido&pedido_id=<?php echo $hermano['id']; ?>" 
                       style="background: #ffffff; color: #0284c7; padding: 2px 6px; border-radius: 4px; text-decoration: none; border: 1px solid #0284c7; font-weight: bold; font-family: monospace;">
                       #<?php echo $hermano['id']; ?> (<?php echo !empty($hermano['cliente_nombre']) ? htmlspecialchars($hermano['cliente_nombre']) : 'Sin nombre'; ?>)
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
<?php 
    endif;
endif; 
?>


                        </div>
                        <div class="ticket-rows-scroll" id="contenedor-render-comanda-ajax">
                            <?php
                            // views/tomar_pedido.php (Parte 5 de 8)
                            $subtotal_acumulado = 0;
                            if (empty($itemsComanda)):
                            ?>
                                <div style="text-align:center; color:#94a3b8; padding:40px 10px; font-size:13px; font-style:italic;">
                                    Comanda vacía. Toca los productos de la izquierda para sumarlos.
                                </div>
                                <?php
                            else:
                                $comida_lista = [];
                                $bebidas_agrupadas = [];
                                $ID_CATEGORIA_BEBIDAS = 5; // ID nativo de la categoría Bebidas

                                // 🔄 PASO A: SEPARACIÓN Y AGRUPACIÓN CONTABLE POR ID Y ESTADO EN CALIENTE
                                foreach ($itemsComanda as $item) {
                                    $idProducto = (int)$item['producto_id'];
                                    $idCategoria = isset($item['categoria_id']) ? (int)$item['categoria_id'] : 0;
                                    $estadoItem = trim($item['estado'] ?? 'solicitado');

                                    if ($idCategoria === $ID_CATEGORIA_BEBIDAS) {
                                        // Llave única compuesta: Unifica ID de producto y su estado KDS real
                                        $llaveBebida = $idProducto . '_' . $estadoItem;

                                        if (isset($bebidas_agrupadas[$llaveBebida])) {
                                            $bebidas_agrupadas[$llaveBebida]['cantidad'] += intval($item['cantidad']);
                                            $bebidas_agrupadas[$llaveBebida]['subtotal'] += floatval($item['subtotal']);
                                        } else {
                                            $bebidas_agrupadas[$llaveBebida] = $item;
                                        }
                                    } else {
                                        $comida_lista[] = $item;
                                    }
                                }

                                // 🔄 PASO B: RENDERIZAR LA COMIDA REGISTRO POR REGISTRO (CON SUS EXTRAS)
                                foreach ($comida_lista as $item):
                                    $subtotal_acumulado += floatval($item['subtotal']);
                                    $id_detalle = (int)$item['id'];

                                    $stmtExt = $db->prepare("SELECT pde.*, p.nombre FROM pedido_detalle_extras pde INNER JOIN productos p ON pde.producto_id = p.id WHERE pde.pedido_detalle_id = :det_id");
                                    $stmtExt->execute(['det_id' => $id_detalle]);
                                    $extrasItem = $stmtExt->fetchAll();

                                    $saboresMixtosText = "";
                                    if (isset($item['es_mixta']) && (int)$item['es_mixta'] === 1) {
                                        $stmtSab = $db->prepare("SELECT p.nombre FROM pedido_detalle_sabores pds INNER JOIN productos p ON pds.producto_id = p.id WHERE pds.pedido_detalle_id = :det_id");
                                        $stmtSab->execute(['det_id' => $id_detalle]);
                                        $saboresMitades = $stmtSab->fetchAll(PDO::FETCH_COLUMN);
                                        if (!empty($saboresMitades)) {
                                            $saboresMixtosText = "Combinación: " . implode(" / ", $saboresMitades);
                                        }
                                    }

                                    $estado_actual_item = trim($item['estado'] ?? 'solicitado');
                                    $stmtEstacion = $db->prepare("SELECT area_produccion FROM productos WHERE id = :prod_id LIMIT 1");
                                    $stmtEstacion->execute(['prod_id' => intval($item['producto_id'])]);
                                    $area_res = $stmtEstacion->fetch(PDO::FETCH_ASSOC);
                                    $estacion_destino = strtoupper($area_res['area_produccion'] ?? 'Cocina');

                                    $estilo_fondo = 'background: #ffffff; border-left: 5px solid #2b8a3e;';
                                    $badge_html = '<span style="font-size: 10px; font-weight: 800; background: #ebfbee; color: #2b8a3e; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">✨ Nuevo Borrador</span>';
                                    $bloquear_borrado_ordinario = false;

                                    if ($estado_actual_item === 'pendiente') {
                                        $estilo_fondo = 'background: #fafafa; border-left: 5px solid #cbd5e1; opacity: 0.85;';
                                        $badge_html = '<span style="font-size: 10px; font-weight: 800; background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">📦 Comandado ' . $estacion_destino . '</span>';
                                        $bloquear_borrado_ordinario = true;
                                    } elseif ($estado_actual_item === 'preparando') {
                                        $estilo_fondo = 'background: #fdfaf6; border-left: 5px solid #d9480f; opacity: 0.85;';
                                        $badge_html = '<span style="font-size: 10px; font-weight: 800; background: #fff4e6; color: #d9480f; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">🔥🔥🔥🔥 En Fuego</span>';
                                        $bloquear_borrado_ordinario = true;
                                    } elseif ($estado_actual_item === 'servido') {
                                        $estilo_fondo = 'background: #f4f8fa; border-left: 5px solid #0d47a1; opacity: 0.70;';
                                        $badge_html = '<span style="font-size: 10px; font-weight: 800; background: #e3f2fd; color: #0d47a1; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">✅ Servido</span>';
                                        $bloquear_borrado_ordinario = true;
                                    }
                                ?>
                                    <div class="ticket-item-row" id="fila-detalle-<?php echo $id_detalle; ?>" style="display: flex; justify-content: space-between; align-items: start; padding: 12px 10px; border-bottom: 1px solid #edf2f7; border-radius: 8px; margin-bottom: 6px; box-sizing: border-box; <?php echo $estilo_fondo; ?>">
                                        <div style="flex:1; padding-right:10px;">
                                            <span style="font-weight:700; color:#1e293b;"><?php echo (int)$item['cantidad']; ?>x</span>
                                            <span style="font-weight: 600; color: #1b4332;"><?php echo ((int)$item['es_mixta'] === 1) ? "Pizza Mixta Combinada" : htmlspecialchars($item['nombre_producto']); ?></span>
                                            <?php if (!empty($saboresMixtosText)): ?>
                                                <div style="font-size: 12px; color: #e67e22; font-weight: bold; background: #fff4e6; padding: 4px 8px; border-radius: 4px; margin-top: 3px; display: inline-block;"><?php echo htmlspecialchars($saboresMixtosText); ?></div>
                                            <?php endif; ?>
                                            <div style="margin-top: 5px; display: flex; gap: 4px; align-items: center; margin-bottom: 4px;"><?php echo $badge_html; ?></div>
                                            <?php if (!empty($extrasItem)): ?>
                                                <div style="font-size:11px; color:#b58105; background:#fffbeb; padding:6px 8px; border-radius:4px; margin-top:5px; font-weight:600; line-height: 1.4;">
                                                    <?php foreach ($extrasItem as $ex):
                                                        $subtotal_acumulado += (floatval($ex['cantidad']) * floatval($ex['precio_cobrado']));
                                                        $costo_total_este_extra = floatval($ex['cantidad']) * floatval($ex['precio_cobrado']);
                                                    ?>
                                                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1px 0;">
                                                            <span>✦ +<?php echo (int)$ex['cantidad']; ?> <?php echo htmlspecialchars($ex['nombre']); ?></span>
                                                            <span style="color: #856404; font-weight: 700;">+ C$ <?php echo number_format($costo_total_este_extra, 2); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="action-row-buttons">
                                                <button type="button" class="btn-mini-pos btn-mini-extra" onclick="abrirModalModificadores(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['nombre_producto']); ?>')">🧀 + Extra</button>
                                                <?php if ($bloquear_borrado_ordinario === false): ?>
                                                    <button type="button" class="btn-mini-pos btn-mini-delete" onclick="solicitarBajaItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['nombre_producto']); ?>')">❌ Quitar</button>
                                                <?php else: ?>
                                                    <span style="font-size: 11px; color: #94a3b8; font-style: italic; padding-left: 5px; display: inline-flex; align-items: center; gap: 3px;">🔒 Comandado</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div style="font-weight:800; text-align:right; color:#334155; min-width:70px; padding-top: 2px;">C$ <?php echo number_format($item['subtotal'], 2); ?></div>
                                    </div>
                                <?php endforeach; ?>
                                <?php
                                // views/tomar_pedido.php (Parte 6 de 8)
                                // 🥤 2.B. RENDERIZAR GRUPO DE BEBIDAS EXCLUSIVAS CON SU ESTADO KDS REAL
                                if (!empty($bebidas_agrupadas)): ?>
                                    <div style="text-align: center; font-size: 11px; font-weight: bold; background: #e2e8f0; color:#334155; padding: 5px; margin: 10px 0; border-radius: 6px; letter-spacing: 0.5px;">--- 🥤 CONSOLIDADO DE BEBIDAS ---</div>
                                    <?php foreach ($bebidas_agrupadas as $bebida):
                                        $subtotal_acumulado += floatval($bebida['subtotal']);
                                        $estado_actual_bebida = trim($bebida['estado'] ?? 'solicitado');

                                        // Consultamos la estación de producción real (Generalmente BAR)
                                        $stmtEstacionB = $db->prepare("SELECT area_produccion FROM productos WHERE id = :prod_id LIMIT 1");
                                        $stmtEstacionB->execute(['prod_id' => intval($bebida['producto_id'])]);
                                        $area_res_b = $stmtEstacionB->fetch(PDO::FETCH_ASSOC);
                                        $estacion_destino_b = strtoupper($area_res_b['area_produccion'] ?? 'Bar');

                                        // Inicializamos las etiquetas visuales respetando tus mismos colores del POS
                                        $badge_bebida_html = '<span style="font-size: 10px; font-weight: 800; background: #ebfbee; color: #2b8a3e; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">✨ Nuevo Borrador</span>';
                                        $bloquear_quitar_bebida = false;

                                        if ($estado_actual_bebida === 'pendiente') {
                                            $badge_bebida_html = '<span style="font-size: 10px; font-weight: 800; background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">📦 Comandado ' . $estacion_destino_b . '</span>';
                                            $bloquear_quitar_bebida = true;
                                        } elseif ($estado_actual_bebida === 'preparando') {
                                            $badge_bebida_html = '<span style="font-size: 10px; font-weight: 800; background: #fff4e6; color: #d9480f; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">🍹 En Preparación</span>';
                                            $bloquear_quitar_bebida = true;
                                        } elseif ($estado_actual_bebida === 'servido') {
                                            $badge_bebida_html = '<span style="font-size: 10px; font-weight: 800; background: #e3f2fd; color: #0d47a1; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">✅ Entregado</span>';
                                            $bloquear_quitar_bebida = true;
                                        }
                                    ?>
                                        <div class="ticket-item-row" style="display: flex; justify-content: space-between; align-items: start; padding: 12px 10px; border-bottom: 1px solid #edf2f7; background: #f8fafc; border-left: 5px solid #0284c7; border-radius: 8px; margin-bottom: 6px; box-sizing: border-box; <?php echo ($bloquear_quitar_bebida) ? 'opacity: 0.85;' : ''; ?>">
                                            <div style="flex: 1; padding-right: 10px;">
                                                <span style="font-weight:800; color:#0284c7; font-size:15px;"><?php echo (int)$bebida['cantidad']; ?>x</span>
                                                <span style="font-weight:700; color:#1e293b;"><?php echo htmlspecialchars($bebida['nombre_producto']); ?></span>

                                                <!-- Inyección del indicador de estado en tiempo real -->
                                                <div style="margin-top: 5px; display: flex; gap: 4px; align-items: center; margin-bottom: 4px;">
                                                    <?php echo $badge_bebida_html; ?>
                                                </div>

                                                <!-- Botón de Quitar Condicional según el estado de la bebida -->
                                                <div class="action-row-buttons">
                                                    <?php if ($bloquear_quitar_bebida === false): ?>
                                                        <button type="button" class="btn-mini-pos btn-mini-delete" style="padding: 4px 10px; font-size: 11px; max-width: 80px;"
                                                            onclick="solicitarBajaBebida(<?php echo $bebida['producto_id']; ?>, '<?php echo htmlspecialchars($bebida['nombre_producto']); ?>')">
                                                            ❌ Quitar 1
                                                        </button>
                                                    <?php else: ?>
                                                        <span style="font-size: 11px; color: #94a3b8; font-style: italic; display: inline-flex; align-items: center; gap: 3px;">🔒 Comandado</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div style="font-weight:800; color:#334155; min-width: 70px; text-align: right; padding-top: 2px;">C$ <?php echo number_format($bebida['subtotal'], 2); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div> <!-- Cierre controlado de ticket-rows-scroll -->

                        <!-- SECCIÓN DE TOTALES DEL PIE DE PÁGINA DEL TICKET -->
                        <div class="ticket-summary-totals">
                            <div class="summary-flex-line">
                                <span>Consumo Neto:</span>
                                <strong id="resumen-subtotal-neto" data-neto="<?php echo $subtotal_acumulado; ?>">C$ <?php echo number_format($subtotal_acumulado, 2); ?></strong>
                            </div>
                            <?php if ($pedidoInfo['tipo_pedido'] === 'local'): ?>
                                <div class="summary-flex-line" style="color: #2563eb;">
                                    <span>Propina Sugerida (10%):</span>
                                    <span>C$ <?php echo number_format($subtotal_acumulado * 0.10, 2); ?></span>
                                </div>
                            <?php elseif ($pedidoInfo['tipo_pedido'] === 'delivery'): ?>
                                <div class="summary-flex-line" style="color: #e67e22; align-items: center; gap: 10px;">
                                    <span>Monto Envío (C$):</span>
                                    <input type="number" id="input-costo-envio-dinamico" name="monto_envio_dinamico" style="width: 90px; padding: 4px 8px; border: 2px solid #e67e22; border-radius: 6px; font-weight: bold; text-align: right;" min="0" step="1" value="<?php echo floatval($pedidoInfo['monto_envio']); ?>" oninput="recalcularGranTotalDelivery(this.value)">
                                </div>
                            <?php endif; ?>
                            <div class="summary-flex-line summary-total-bold">
                                <span>TOTAL FINAL:</span>
                                <span id="resumen-total-final">C$ <?php echo number_format(floatval($pedidoInfo['total']), 2); ?></span>
                            </div>
                            <div style="margin-top: 10px; width: 100%;">
                                <a href="index.php?v=precuenta&pedido_id=<?php echo $pedido_id; ?>" target="_blank" style="width: 100%; background: #0284c7; color: #ffffff; border: none; padding: 12px; border-radius: 8px; font-weight: 800; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; box-sizing: border-box; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.15); margin-bottom: 8px; text-transform: uppercase;"> 🧾🧾 Imprimir Precuenta</a>
                            </div>
                            <div style="margin-top: 5px; width: 100%;">
                                <form action="<?php echo URL_BASE; ?>controllers/PedidoController.php" method="POST">
                                    <input type="hidden" name="accion" value="comandar_orden_kds">
                                    <input type="hidden" name="pedido_id" value="<?php echo (int)$_GET['pedido_id']; ?>">
                                    <button type="submit" style="width: 100%; background: #2b8a3e; color: #ffffff; border: none; padding: 14px; border-radius: 8px; font-weight: 800; font-size: 15px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px rgba(43, 138, 62, 0.2);">🚀🚀🚀🚀 Enviar Orden a Producción</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <!-- MODAL DE PIZZAS MIXTAS (SABORES COMBINADOS) -->
    <div id="modal-pizza-mixta-wrapper" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999999; align-items: center; justify-content: center; padding: 15px; box-sizing: border-box;">
        <div style="background: #ffffff; width: 100%; max-width: 460px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); overflow: hidden; border-top: 5px solid #e67e22;">
            <div style="padding: 18px; background: #fafbfc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #1b4332; font-size: 1.15rem;">🍕🍕🍕🍕 Configurar Especialidad Mixta</h3>
                <button type="button" onclick="cerrarModalPizzaMixta()" style="background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer; font-weight: bold;">&times;</button>
            </div>
            <form action="<?php echo URL_BASE; ?>controllers/PedidoController.php" method="POST" style="padding: 20px; margin: 0;">
                <input type="hidden" name="accion" value="agregar_mixta">
                <input type="hidden" name="pedido_id" value="<?php echo $pedido_id; ?>">
                <input type="hidden" name="cantidad" value="1">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #1b4332;">Primer Sabor (1/2)</label>
                    <select name="sabor_1_id" class="form-control" required>
                        <option value="">-- Escoger Mitad A --</option>
                        <?php foreach ($productosMenu as $prod): if ((int)$prod['es_sabor_pizza'] === 1): ?>
                                <option value="<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['nombre']); ?> (C$ <?php echo number_format($prod['precio_base'], 2); ?>)</option>
                        <?php endif;
                        endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #1b4332;">Segundo Sabor (1/2)</label>
                    <select name="sabor_2_id" class="form-control" required>
                        <option value="">-- Escoger Mitad B --</option>
                        <?php foreach ($productosMenu as $prod): if ((int)$prod['es_sabor_pizza'] === 1): ?>
                                <option value="<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['nombre']); ?> (C$ <?php echo number_format($prod['precio_base'], 2); ?>)</option>
                        <?php endif;
                        endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="button" onclick="cerrarModalPizzaMixta()" style="flex: 1; padding: 12px; border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 8px; font-weight: bold; color: #475569; cursor: pointer;">Cancelar</button>
                    <button type="submit" style="flex: 1; padding: 12px; border: none; background: #e67e22; color: #ffffff; border-radius: 8px; font-weight: bold; cursor: pointer;">➕ Agregar al Ticket</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL PARA AGREGAR ADICIONALES O EXTRAS -->
    <div id="modal-agregar-extra-wrapper" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999999; align-items: center; justify-content: center; padding: 15px; box-sizing: border-box;">
        <div style="background: #ffffff; width: 100%; max-width: 420px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); overflow: hidden; border-top: 5px solid #b58105;">
            <div style="padding: 18px; background: #fafbfc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #856404; font-size: 1.1rem;" id="titulo-modal-extra-dinamico">🧀🧀🧀 Cargar Extra</h3>
                <button type="button" onclick="cerrarModalExtras()" style="background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer; font-weight: bold;">&times;</button>
            </div>
            <form action="<?php echo URL_BASE; ?>controllers/PedidoController.php" method="POST" style="padding: 20px; margin: 0;">
                <input type="hidden" name="accion" value="agregar_extra">
                <input type="hidden" name="pedido_id" value="<?php echo $pedido_id; ?>">
                <input type="hidden" name="pedido_detalle_id" id="modal-extra-detalle-id">
                <input type="hidden" name="precio_cobrado" id="modal-extra-precio-hidden">
                <input type="hidden" name="cantidad" value="1">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #1b4332;">Seleccione el Adicional</label>
                    <select id="select-ingrediente-extra" name="producto_id" class="form-control" required onchange="actualizarPrecioExtraVisual(this)">
                        <option value="">-- Seleccionar Adicional --</option>
                        <?php foreach ($productosMenu as $prod): $nombre_minuscula = strtolower($prod['nombre']);
                            if (strpos($nombre_minuscula, 'extra') !== false || strpos($nombre_minuscula, 'borde') !== false): ?>
                                <option value="<?php echo $prod['id']; ?>" data-precio="<?php echo $prod['precio_base']; ?>"><?php echo htmlspecialchars($prod['nombre']); ?> (+ C$ <?php echo number_format($prod['precio_base'], 2); ?>)</option>
                        <?php endif;
                        endforeach; ?>
                    </select>
                </div>
                <div style="background: #fffbeb; padding: 10px; border-radius: 6px; border: 1px solid #ffeeba; margin-bottom: 20px; font-size: 13px; color: #856404; font-weight: bold; text-align: right;">Cargo Extra: <span id="label-precio-extra-dinamico">C$ 0.00</span></div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="cerrarModalExtras()" style="flex: 1; padding: 12px; border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 8px; font-weight: bold; color: #475569; cursor: pointer;">Cancelar</button>
                    <button type="submit" style="flex: 1; padding: 12px; border: none; background: #b58105; color: #ffffff; border-radius: 8px; font-weight: bold; cursor: pointer;">🧀🧀🧀🧀 Sumar Extra</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 🔄 MODAL EXCLUSIVO: TRANSFERENCIA / CAMBIO DE MESA EN CALIENTE (🔒 CON CANDADO MVC) -->
    <div id="modal-cambio-mesa-wrapper" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999999; align-items: center; justify-content: center; padding: 15px; box-sizing: border-box;">
        <div style="background: #ffffff; width: 100%; max-width: 440px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); overflow: hidden; border-top: 5px solid #e67e22;">
            <div style="padding: 18px; background: #fafbfc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #1b4332; font-size: 1.15rem;">🔄 Transferir Pedido a Otra Mesa</h3>
                <button type="button" onclick="cerrarModalCambioMesa()" style="background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer; font-weight: bold;">&times;</button>
            </div>
            <form action="<?php echo URL_BASE; ?>controllers/PedidoController.php" method="POST" style="padding: 20px; margin: 0;" onsubmit="this.querySelector('button[type=submit]').disabled=true;">
                <input type="hidden" name="accion" value="cambiar_mesa_pedido">
                <input type="hidden" name="pedido_id" value="<?php echo $pedido_id; ?>">
                <input type="hidden" name="mesa_origen_id" value="<?php echo $pedidoInfo['mesa_id']; ?>">
                <div style="background: #fff4e6; padding: 10px; border-radius: 6px; border: 1px solid #ffe8cc; margin-bottom: 15px; font-size: 13px; color: #d9480f; font-weight: 600;">Mesa de Origen: <?php echo htmlspecialchars($pedidoInfo['nombre_area'] . ' - ' . $pedidoInfo['numero_mesa']); ?></div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #1b4332;">Seleccione la Mesa Destino (Sólo Libres)</label>
                    <select name="mesa_destino_id" class="form-control" required style="width:100% !important; padding:12px !important; border:2px solid #e2e8f0 !important; border-radius:8px !important;">
                        <option value="">-- Seleccionar Mesa Vacía --</option>
                        <?php
                        $stmtLibres = $db->query("SELECT m.*, a.nombre as nombre_area FROM mesas m INNER JOIN areas a ON m.area_id = a.id WHERE m.estado = 'disponible' AND m.deleted_at IS NULL ORDER BY a.nombre ASC, m.numero_mesa ASC");
                        $mesasDisponibles = $stmtLibres->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($mesasDisponibles as $mLibre):
                        ?>
                            <option value="<?php echo $mLibre['id']; ?>"><?php echo htmlspecialchars($mLibre['nombre_area'] . ' - ' . $mLibre['numero_mesa']); ?> (Max: <?php echo $mLibre['capacidad']; ?> p.)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="button" onclick="cerrarModalCambioMesa()" style="flex: 1; padding: 12px; border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 8px; font-weight: bold; color: #475569; cursor: pointer;">Cancelar</button>
                    <button type="submit" style="flex: 1; padding: 12px; border: none; background: #e67e22; color: #ffffff; border-radius: 8px; font-weight: bold; cursor: pointer;">🔄 Confirmar Cambio</button>
                </div>
            </form>
        </div>
    </div>
    <!-- ==========================================================================
     👥 MODAL INTERACTIVO: SEPARACIÓN Y DIVISIÓN DE CUENTAS (SPLIT BILL)
     ========================================================================== -->
    <!-- ==========================================================================
     👥 MODAL INTERACTIVO: SEPARACIÓN Y DIVISIÓN DE CUENTAS EN TIEMPO REAL (SPLIT BILL)
     ========================================================================== -->
    <div id="modal-dividir-cuenta-wrapper" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999999; align-items: center; justify-content: center; padding: 15px; box-sizing: border-box;">
        <div style="background: #ffffff; width: 100%; max-width: 480px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); overflow: hidden; border-top: 5px solid #0284c7;">
            <!-- Cabecera del Panel Táctil -->
            <div style="padding: 18px; background: #fafbfc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #0f172a; font-size: 1.15rem; font-weight: 800;">👥 Separar Productos a Cuenta Nueva</h3>
                <button type="button" onclick="cerrarModalDividirCuenta()" style="background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer; font-weight: bold;">&times;</button>
            </div>

            <!-- Formulario que despacha el arreglo de casillas hacia el controlador -->
            <form action="<?php echo URL_BASE; ?>controllers/PedidoController.php" method="POST" style="padding: 20px; margin: 0;">
                <input type="hidden" name="accion" value="dividir_cuenta_pedido">
                <input type="hidden" name="pedido_id" value="<?php echo $pedido_id; ?>">

                <p style="font-size: 12.5px; color: #64748b; margin-top: 0; margin-bottom: 15px; font-weight: 600; line-height: 1.4;">
                    Marque las casillas de los artículos (alimentos o bebidas) que desea remover de este ticket para mandarlos a una sub-cuenta por separado:
                </p>

                <!-- Contenedor con Scroll de Alta Densidad para el Desglose de Productos -->
                <div style="max-height: 260px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; padding: 10px; box-sizing: border-box;">
                    <?php
                    if (empty($itemsComanda)):
                    ?>
                        <div style="text-align:center; color:#94a3b8; padding:15px; font-size:12px; font-style:italic;">No hay productos en borrador para separar.</div>
                        <?php
                    else:
                        // Recorremos la comanda desde el arreglo raíz original para jalar absolutamente todo
                        foreach ($itemsComanda as $cItem):
                            // 🔒 REGLA DE AUDITORÍA: Filtramos solo los ítems activos en borrador 'solicitado'
                            if (trim($cItem['estado']) !== 'solicitado') continue;

                            // Clasificamos visualmente si el ítem es un líquido (Categoría 5) para colocarle un identificador
                            $es_bebida_liquida = ((int)$cItem['categoria_id'] === 5);
                            $icono_item = $es_bebida_liquida ? '🥤' : '🍕';
                        ?>
                            <label style="display: flex; align-items: center; gap: 12px; padding: 10px; border-bottom: 1px solid #edf2f7; background: #ffffff; margin-bottom: 5px; border-radius: 8px; cursor: pointer; font-size: 13.5px; font-weight: 700; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: background 0.1s;">
                                <!-- Casilla Checkbox con realce de color institucional azul -->
                                <input type="checkbox" name="items_dividir[]" value="<?php echo $cItem['id']; ?>" style="width: 19px; height: 19px; cursor: pointer; accent-color: #0284c7; flex-shrink: 0;">
                                <span style="flex: 1;">
                                    <strong style="color: #0284c7; font-size: 14px;"><?php echo (int)$cItem['cantidad']; ?>x</strong>
                                    <?php echo $icono_item; ?> <?php echo htmlspecialchars($cItem['nombre_producto']); ?>
                                    <small style="color: #475569; display: block; font-weight: 800; font-family: system-ui, monospace; margin-top: 2px; font-size: 12px;">C$ <?php echo number_format($cItem['subtotal'], 2); ?></small>
                                </span>
                            </label>
                    <?php
                        endforeach;
                    endif;
                    ?>
                </div>

                <!-- Botonera de Acción de la Tablet -->
                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="button" onclick="cerrarModalDividirCuenta()" style="flex: 1; padding: 12px; border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 8px; font-weight: bold; color: #475569; cursor: pointer; font-size: 13px;">Cancelar</button>
                    <button type="submit" style="flex: 1; padding: 12px; border: none; background: #0284c7; color: #ffffff; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 13px;">👥 Crear Sub-Cuenta</button>
                </div>
            </form>
        </div>
    </div>


    <div class="mobile-pos-nav">
        <button type="button" class="pos-tab-trigger active-tab" id="btn-tab-menu" onclick="cambiarVistaPos('menu')"><span style="font-size: 18px;">🍕🍕</span> Menú Platos</button>
        <button type="button" class="pos-tab-trigger" id="btn-tab-ticket" onclick="cambiarVistaPos('ticket')"><span style="font-size: 18px;">🛒🛒</span> Mi Comanda (<span id="badge-contador-movil"><?php echo count($itemsComanda); ?></span>)</button>
    </div>
    <script>
        // views/tomar_pedido.php (JavaScript - Parte 1 de 2)

        // 🌟 1. BUSCADOR EN TIEMPO REAL (REACTIVO MULTI-FILTRO PERSISTENTE)
        function ejecutarBusquedaRapidaItem(texto) {
            const query = texto.toLowerCase().trim();
            const tarjetas = document.querySelectorAll('.product-item-card');
            const categoriaActiva = parseInt(document.getElementById('select-categoria-pos').value) || 0;

            tarjetas.forEach(card => {
                if (card.getAttribute('onclick') && card.getAttribute('onclick').includes('abrirModalPizzaMixta')) return;
                const nombre = card.querySelector('.product-item-title').innerText.toLowerCase();
                const catId = parseInt(card.getAttribute('data-cat-id')) || 0;
                const coincideCat = (categoriaActiva === 0 || catId === categoriaActiva);
                const coincideTexto = (nombre.includes(query));

                if (coincideCat && coincideTexto) {
                    card.style.setProperty('display', 'flex', 'important');
                } else {
                    card.style.setProperty('display', 'none', 'important');
                }
            });
        }

        // 🌟 2. ADICIÓN ASÍNCRONA (RETENCION DE TEXTO EN EL INPUT Y CONGELACIÓN DE SCROLL)
        function agregarProductoFila(productoId, nombre, precio) {
            const form = document.getElementById('form-add-item-hidden');
            if (!form) return;

            const formData = new FormData();
            formData.append('accion', 'agregar_item');
            formData.append('pedido_id', form.querySelector('input[name="pedido_id"]').value);
            formData.append('producto_id', productoId);
            formData.append('cantidad', '1');
            formData.append('precio_unitario', precio);
            formData.append('es_mixta', '0');

            const tarjetaPresionada = window.event?.currentTarget;
            if (tarjetaPresionada) tarjetaPresionada.style.pointerEvents = 'none';

            // Despacho asíncrono controlado en segundo plano sin romper el foco
            fetch(form.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    // Almacenamos los estados de scroll, la categoría del combo y la palabra exacta buscada
                    const scrollMenu = document.querySelector('.menu-products-layout')?.scrollTop || 0;
                    const scrollTicket = document.querySelector('.ticket-rows-scroll')?.scrollTop || 0;
                    const textoBuscador = document.getElementById('buscador-productos-pos').value || "";
                    const categoriaActual = document.getElementById('select-categoria-pos').value || "0";

                    localStorage.setItem('pos_scroll_menu', scrollMenu);
                    localStorage.setItem('pos_scroll_ticket', scrollTicket);
                    localStorage.setItem('pos_texto_busqueda', textoBuscador); // Guardamos el texto ingresado
                    localStorage.setItem('jungle_pizza_categoria_activa', categoriaActual); // Fijamos la categoría

                    location.reload();
                })
                .catch(error => {
                    console.error("Error en pasarela POS:", error);
                    if (tarjetaPresionada) tarjetaPresionada.style.pointerEvents = 'auto';
                });
        }

        // Envía el nombre del cliente al servidor automáticamente al quitar el cursor del cuadro
        function guardarNombreClienteDinamico(nombre) {
            const formData = new FormData();
            formData.append('accion', 'actualizar_nombre_cliente_ajax');
            formData.append('pedido_id', '<?php echo $pedido_id; ?>');
            formData.append('cliente_nombre', nombre);
            fetch('<?php echo URL_BASE; ?>controllers/PedidoController.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log("Nombre del cliente sincronizado en Jungle POS:", data);
                })
                .catch(error => console.error("Error al guardar el cliente:", error));
        }

        // 🌟 3. ACCIONES DE CONTROL EXCLUSIVAS DEL NUEVO MODAL DE CAMBIO DE MESA
        function abrirModalCambioMesa() {
            document.getElementById('modal-cambio-mesa-wrapper').style.setProperty('display', 'flex', 'important');
        }

        function cerrarModalCambioMesa() {
            document.getElementById('modal-cambio-mesa-wrapper').style.setProperty('display', 'none', 'important');
        }

        // 🌟 4. NUEVA FUNCIÓN: ELIMINACIÓN DE BEBIDAS CON CONSERVACIÓN DE TEXTO BUSCADO
        function solicitarBajaBebida(productoId, nombreBebida) {
            const motivo = prompt(`❌ REMOVER 1 UNIDAD DE BEBIDA:\n${nombreBebida}\nMotivo obligatorio de la modificación:`);
            if (motivo) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?php echo URL_BASE; ?>controllers/PedidoController.php';
                form.innerHTML = `
            <input type="hidden" name="accion" value="quitar_bebida_por_id">
            <input type="hidden" name="pedido_id" value="<?php echo $pedido_id; ?>">
            <input type="hidden" name="producto_id" value="${productoId}">
            <input type="hidden" name="motivo_quitar" value="${motivo}">
        `;
                document.body.appendChild(form);

                // Retenemos el texto actual también para la acción de remoción de líquidos
                const textoBuscador = document.getElementById('buscador-productos-pos').value || "";
                const categoriaActual = document.getElementById('select-categoria-pos').value || "0";
                localStorage.setItem('pos_texto_busqueda', textoBuscador);
                localStorage.setItem('jungle_pizza_categoria_activa', categoriaActual);

                form.submit();
            }
        }
        // views/tomar_pedido.php (JavaScript - Parte 2 de 2)

        // 🌟 5. COMPORTAMIENTOS COMPLEMENTARIOS REPARADOS
        function cambiarVistaPos(vista) {
            const btnMenu = document.getElementById('btn-tab-menu');
            const btnTicket = document.getElementById('btn-tab-ticket');
            if (vista === 'menu') {
                document.body.classList.remove('ver-ticket-activo');
                btnMenu.classList.add('active-tab');
                btnTicket.classList.remove('active-tab');
            } else {
                document.body.classList.add('ver-ticket-activo');
                btnTicket.classList.add('active-tab');
                btnMenu.classList.remove('active-tab');
            }
        }

        // 🔥 CORRECCIÓN CRÍTICA: Eliminamos el .value = "" que saboteaba el foco del mesero
        function filtrarCatalogoArea(categoriaId, elemento) {
            const catId = parseInt(categoriaId);

            // Guardamos la última categoría seleccionada físicamente por el operario
            localStorage.setItem('jungle_pizza_categoria_activa', catId);

            if (elemento && elemento.tagName === 'BUTTON') {
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('tab-active'));
                elemento.classList.add('tab-active');
            }

            const tarjetasProductos = document.querySelectorAll('.product-item-card');
            const query = (document.getElementById('buscador-productos-pos').value || "").toLowerCase().trim();

            tarjetasProductos.forEach(card => {
                if (card.getAttribute('onclick') && card.getAttribute('onclick').includes('abrirModalPizzaMixta')) return;

                const tarjetaCat = parseInt(card.getAttribute('data-cat-id'));
                const nombre = card.querySelector('.product-item-title').innerText.toLowerCase();

                // Ejecuta un filtro cruzado en caliente combinando de forma estricta ambos inputs
                const coincideCat = (catId === 0 || tarjetaCat === catId);
                const coincideTexto = (query === "" || nombre.includes(query));

                if (coincideCat && coincideTexto) {
                    card.style.setProperty('display', 'flex', 'important');
                } else {
                    card.style.setProperty('display', 'none', 'important');
                }
            });
        }

        function recalcularGranTotalDelivery(montoEnvio) {
            const subtotalElement = document.getElementById('resumen-subtotal-neto');
            const totalElement = document.getElementById('resumen-total-final');
            if (!subtotalElement || !totalElement) return;
            const subtotalNeto = parseFloat(subtotalElement.getAttribute('data-neto')) || 0;
            const envioNum = parseFloat(montoEnvio) || 0;
            totalElement.innerText = "C$ " + (subtotalNeto + envioNum).toFixed(2);
        }

        function abrirModalModificadores(detalleId, nombreProducto) {
            const modal = document.getElementById('modal-agregar-extra-wrapper');
            const inputDetail = document.getElementById('modal-extra-detalle-id');
            const titulo = document.getElementById('titulo-modal-extra-dinamico');
            if (modal && inputDetail) {
                inputDetail.value = detalleId;
                if (titulo) titulo.innerText = `Extras para: ${nombreProducto}`;
                modal.style.display = 'flex';
            }
        }

        function cerrarModalExtras() {
            document.getElementById('modal-agregar-extra-wrapper').style.display = 'none';
        }

        function actualizarPrecioExtraVisual(sel) {
            const opt = sel.options[sel.selectedIndex];
            const lbl = document.getElementById('label-precio-extra-dinamico');
            const hid = document.getElementById('modal-extra-precio-hidden');
            if (opt && sel.value !== "") {
                const p = parseFloat(opt.getAttribute('data-precio')) || 0;
                if (lbl) lbl.innerText = "C$ " + p.toFixed(2);
                if (hid) hid.value = p.toFixed(2);
            } else {
                if (lbl) lbl.innerText = "C$ 0.00";
                if (hid) hid.value = "0.00";
            }
        }

        function solicitarBajaItem(detalleId, nombreProducto) {
            const motivo = prompt(`❌ REMOVER DE LA COMANDA:\n${nombreProducto}\nMotivo obligatorio:`);
            if (motivo) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?php echo URL_BASE; ?>controllers/PedidoController.php';
                form.innerHTML = `<input type="hidden" name="accion" value="quitar_item"><input type="hidden" name="pedido_id" value="<?php echo $pedido_id; ?>"><input type="hidden" name="pedido_detalle_id" value="${detalleId}"><input type="hidden" name="motivo_quitar" value="${motivo}"><input type="hidden" name="fue_servido" value="1">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
        // Funciones de control de capas para la ventana de Split Bill (Cuentas Separadas)
        function abrirModalDividirCuenta() {
            document.getElementById('modal-dividir-cuenta-wrapper').style.setProperty('display', 'flex', 'important');
        }

        function cerrarModalDividirCuenta() {
            document.getElementById('modal-dividir-cuenta-wrapper').style.setProperty('display', 'none', 'important');
        }


        function abrirModalPizzaMixta() {
            const m = document.getElementById('modal-pizza-mixta-wrapper');
            if (m) m.style.setProperty('display', 'flex', 'important');
        }

        function cerrarModalPizzaMixta() {
            document.getElementById('modal-pizza-mixta-wrapper').style.setProperty('none', 'important');
        }

        function ajustarComensales(cambio) {
            const label = document.getElementById('label-num-personas');
            if (!label) return;
            let v = (parseInt(label.innerText) || 1) + cambio;
            if (v < 1) v = 1;
            label.innerText = v;
            const formData = new FormData();
            formData.append('accion', 'actualizar_comensales_ajax');
            formData.append('pedido_id', '<?php echo $pedido_id; ?>');
            formData.append('num_personas', v);
            fetch('<?php echo URL_BASE; ?>controllers/PedidoController.php', {
                method: 'POST',
                body: formData
            });
        }

        // 🌟 6. DOM READY MOTOR DE PERSISTENCIA SIN CONFLICTOS INTERNOS
        document.addEventListener("DOMContentLoaded", function() {
            const catG = localStorage.getItem('jungle_pizza_categoria_activa');
            const selC = document.getElementById('select-categoria-pos');

            if (catG !== null && selC) {
                const catIdGuardada = parseInt(catG);

                // 🔒 CLAVE: Seteamos el valor físico del combo select inmediatamente
                selC.value = catIdGuardada;

                // Ejecutamos el filtro cruzado inicial pasándole la categoría en memoria
                filtrarCatalogoArea(catIdGuardada, selC);
            } else {
                filtrarCatalogoArea(0, selC);
            }

            if (localStorage.getItem('pos_vista_activa') === 'ticket' && window.innerWidth < 600) {
                cambiarVistaPos('ticket');
            }

            // 🔍 RESTAURACIÓN DE TEXTO BUSCADO: Recupera la palabra clave y re-fija el puntero del teclado
            const textoGuardado = localStorage.getItem('pos_texto_busqueda');
            if (textoGuardado) {
                const inputBuscador = document.getElementById('buscador-productos-pos');
                if (inputBuscador) {
                    inputBuscador.value = textoGuardado;
                    // Forzamos el re-filtrado combinando el texto de vuelta con la categoría ya anclada
                    ejecutarBusquedaRapidaItem(textoGuardado);
                    inputBuscador.focus(); // Clava el cursor de vuelta en el input para seguir digitando
                }
                localStorage.removeItem('pos_texto_busqueda'); // Limpieza higiénica de caché
            }

            // Restauración exacta de posiciones de scroll (Congelación total)
            const sMenu = localStorage.getItem('pos_scroll_menu');
            const sTicket = localStorage.getItem('pos_scroll_ticket');
            if (sMenu && document.querySelector('.menu-products-layout')) document.querySelector('.menu-products-layout').scrollTop = parseInt(sMenu);
            if (sTicket && document.querySelector('.ticket-rows-scroll')) document.querySelector('.ticket-rows-scroll').scrollTop = parseInt(sTicket);
        });
    </script>
    <script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>

</html>