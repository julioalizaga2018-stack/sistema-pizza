<?php
// views/tomar_pedido.php (Parte 1 de 4 - Arquitectura Jungle POS)

// 1. Validaciones e instancias de los datos del pedido actual
require_once __DIR__ . '/../models/PedidoModelo.php';
require_once __DIR__ . '/../controllers/ProductoController.php';

$pedido_id = intval($_GET['pedido_id'] ?? 0);
$db = (new Conexion())->conectar();

// Amarre relacional para extraer el mesero y la mesa física actual
$stmtPed = $db->prepare("SELECT p.*, m.numero_mesa, a.nombre as nombre_area, u.nombre as nombre_mesero 
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
$categoriasMenu = $prodController->obtenerCategorias();
$productosMenu = $prodController->listar(); 

// 3. Extraemos el detalle actual de lo que ya se ha sumado a esta comanda (Fiel a tus dos tablas de mermas)
$stmtDet = $db->prepare("SELECT pd.id, pd.pedido_id, pd.producto_id, pd.cantidad, pd.precio_unitario, pd.subtotal, pd.estado, pd.es_mixta, p.nombre as nombre_producto, p.imagen
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
       🚀 CORE LAYOUT: EXPANSIÓN VERTICAL MÁXIMA DE LA PANTALLA TÁCTIL
       ========================================================================== */
    .pos-grid-container {
        display: grid !important;
        grid-template-columns: 1fr;
        gap: 20px;
        margin-top: 15px;
        width: 100%;
        height: calc(100vh - 140px) !important; /* 🌟 Corrección del operador menos (-) */
        min-height: 650px;
    }
    .pos-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(27, 67, 50, 0.05);
        padding: 20px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        height: 100% !important; /* 🌟 Alto elástico hasta abajo */
        box-sizing: border-box;
    }
    
    /* ==========================================================================
       📁 SECCIÓN IZQUIERDA: CATÁLOGO DE PRODUCTOS EXPANSIBLE
       ========================================================================== */
    .category-select-wrapper {
        margin-bottom: 15px;
        width: 100%;
    }
    .menu-products-layout {
        display: grid !important;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)) !important; /* Tarjetas robustas */
        gap: 15px;
        overflow-y: auto;
        flex: 1;
        padding-right: 5px;
    }
    .product-item-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        cursor: pointer;
        transition: transform 0.1s, border-color 0.2s;
        position: relative;
        height: 240px !important; /* 🌟 Mayor elevación vertical */
    }
    .product-item-card:active {
        transform: scale(0.96);
    }
    .product-item-card img {
        width: 100%;
        height: 130px !important; /* Imagen de gaseosa/pizza imponente */
        object-fit: contain !important;
        background: #ffffff;
        padding: 5px;
        box-sizing: border-box;
    }
    .product-body-desc {
        padding: 10px;
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .product-item-title {
        font-size: 14px !important;
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
        font-size: 15px !important;
        font-weight: 800;
        color: var(--naranja-pizza, #e67e22);
        margin-top: auto;
    }

    /* ==========================================================================
       📋 SECCIÓN DERECHA: DESGLOSE DE TICKET (COMANDA INDUSTRIAL)
       ========================================================================== */
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
    .ticket-rows-scroll {
        flex: 1 !important;
        overflow-y: auto !important;
        padding-right: 5px;
        max-height: calc(100% - 180px) !important; /* Scroll interno inteligente */
    }
    .ticket-item-row {
        display: flex;
        justify-content: space-between;
        align-items: start;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
    }
    .action-row-buttons {
        display: flex;
        gap: 5px;
        margin-top: 6px;
    }
    .btn-mini-pos {
        border: none;
        padding: 4px 8px;
        font-size: 11px;
        font-weight: bold;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
    }
    .btn-mini-extra { background: #fff3cd; color: #856404; }
    .btn-mini-delete { background: #ffe3e3; color: #c92a2a; }
    
    .ticket-summary-totals {
        background: #f8fafc;
        padding: 14px;
        border-radius: 8px;
        margin-top: auto; /* Anclado abajo */
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
        font-size: 18px !important;
        font-weight: 800;
        color: var(--verde-oscuro, #1b4332);
        margin-bottom: 0;
    }
    .alert { padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; font-weight: 500; }
    .alert-error { background: #ffe3e3; color: #c92a2a; }
    .alert-success { background: #ebfbee; color: #2b8a3e; }
    .form-control { width: 100% !important; padding: 10px !important; border: 2px solid #e2e8f0 !important; border-radius: 8px !important; box-sizing: border-box !important; }

    @media (min-width: 992px) {
        .pos-grid-container { grid-template-columns: 1fr 380px !important; } /* Reparto de anchos */
    }
    </style>
</head>
<body>
    <header class="mobile-header">
        <button class="hamburger-btn" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <div class="mobile-logo">🍕 Jungle POS</div>
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
                    <!-- 🎛️ SELECTOR DESPLEGABLE DE CATEGORÍAS (MÁXIMO AHORRO DE ESPACIO TÁCTIL) -->
                    <div class="category-select-wrapper">
                        <label style="display: block; margin-bottom: 6px; font-weight: 700; font-size: 13px; color: var(--verde-oscuro);">
                            📁 Seleccionar Categoría:
                        </label>
                        <?php
                        // 🌟 MAPA DE PRIORIDADES GASTRONÓMICAS IDEAL JUNGLE POS
                        $ordenIdealGastro = ['entrada', 'pizza', 'almuerzo', 'rapida', 'bebida', 'empaque'];

                        // Ordenamos el arreglo original de tu menú usando la función de comparación de PHP
                        usort($categoriasMenu, function ($a, $b) use ($ordenIdealGastro) {
                            $nombreA = strtolower($a['nombre']);
                            $nombreB = strtolower($b['nombre']);
                            $posA = false; $posB = false;

                            foreach ($ordenIdealGastro as $index => $keyword) {
                                if (strpos($nombreA, $keyword) !== false) { $posA = $index; break; }
                            }
                            foreach ($ordenIdealGastro as $index => $keyword) {
                                if (strpos($nombreB, $keyword) !== false) { $posB = $index; break; }
                            }

                            $posA = ($posA === false) ? 99 : $posA;
                            $posB = ($posB === false) ? 99 : $posB;
                            return $posA <=> $posB;
                        });
                        ?>

                        <!-- El evento onchange envía el value seleccionado directo a tu función JS -->
                        <select class="form-control" style="width: 100%; padding: 12px; font-size: 15px; font-weight: 700; border: 2px solid var(--verde-oscuro); border-radius: 8px; color: #1e293b; background-color: #ffffff; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.02);"
                                onchange="filtrarCatalogoArea(this.value, this)">
                            <option value="0">🌍 Mostrar Todo el Menú</option>
                            <?php
                            foreach ($categoriasMenu as $cat):
                                $id_limpio = (int)$cat['id'];
                                $nombre_minuscula = strtolower($cat['nombre']);
                                
                                // Asignación automatizada de iconos visuales
                                $icono = '🍽️ ';
                                if (strpos($nombre_minuscula, 'entrada') !== false) $icono = '🥗 ';
                                elseif (strpos($nombre_minuscula, 'pizza') !== false) $icono = '🍕 ';
                                elseif (strpos($nombre_minuscula, 'almuerzo') !== false) $icono = '🍲 ';
                                elseif (strpos($nombre_minuscula, 'rapida') !== false) $icono = '🍔 ';
                                elseif (strpos($nombre_minuscula, 'bebida') !== false || $id_limpio === 5) $icono = '🍹 ';
                                elseif (strpos($nombre_minuscula, 'empaque') !== false) $icono = '📦 ';
                            ?>
                                <option value="<?php echo $id_limpio; ?>">
                                    <?php echo $icono . htmlspecialchars($cat['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 🍕 CUADRÍCULA ELÁSTICA DE PLATOS CON FOTOGRAFÍA -->
                    <div class="menu-products-layout">
                        <!-- 🍕 CONFIGURADOR MIXTO FIJO EN PRIMERA POSICIÓN -->
                        <div class="product-item-card" data-cat-id="2" style="background: linear-gradient(135deg, #fff3cd, #ffe8cc); border: 2px dashed #e67e22;" onclick="abrirModalPizzaMixta()">
                            <div style="width:100%; height:95px; background:rgba(230, 126, 34, 0.1); display:flex; align-items:center; justify-content:center; font-size:32px;">🍕</div>
                            <div class="product-body-desc" style="justify-content: center; text-align: center;">
                                <span class="product-item-title" style="color: #b55d05; font-size: 14px; margin: 0;">★ Armar Pizza Mixta</span>
                                <span style="font-size: 11px; font-weight: bold; color: #e67e22;">Mitad y Mitad</span>
                            </div>
                        </div>

                        <!-- ITERACIÓN AUTOMÁTICA DE PRODUCTOS CON IMAGEN -->
                        <?php if (!empty($productosMenu)): ?>
                            <?php foreach ($productosMenu as $p): ?>
                                <div class="product-item-card" data-cat-id="<?php echo $p['categoria_id']; ?>"
                                     onclick="agregarProductoFila(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['nombre']); ?>', <?php echo $p['precio_base']; ?>)">
                                    <?php if (!empty($p['imagen']) && file_exists(__DIR__ . '/../public/uploads/productos/' . $p['imagen'])): ?>
                                        <img src="<?php echo URL_BASE; ?>public/uploads/productos/<?php echo $p['imagen']; ?>" alt="Foto Menu">
                                    <?php else: ?>
                                        <div style="width:100%; height:95px; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-size:24px;">🍽️</div>
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

                <!-- FORMULARIO OCULTO INSERCIÓN RÁPIDA (Mantiene la sincronización POST ordinaria) -->
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
                    <!-- CABECERA DEL TICKET (Muestra identificadores y operador del turno) -->
                    <div class="ticket-header-info">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span style="font-weight:800; color:var(--verde-oscuro); font-size: 14px;">📋 TICKET #<?php echo $pedido_id; ?></span>
                            <span style="background: #e2e8f0; color: #334155; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;">
                                👤 <?php echo htmlspecialchars($pedidoInfo['nombre_mesero'] ?? 'Sistema'); ?>
                            </span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 8px 12px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 6px;">
                            <span style="color:#475569; font-weight: 600;">Modalidad: <strong style="color: #e67e22;"><?php echo strtoupper($pedidoInfo['tipo_pedido']); ?></strong></span>
                            
                            <!-- CONTADOR INTERACTIVO DE COMENSALES (AJAX silencioso) -->
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 12px; color: #64748b; font-weight: 700;">Clientes:</span>
                                <button type="button" onclick="ajustarComensales(-1)" style="width: 26px; height: 26px; border-radius: 4px; border: 1px solid #cbd5e1; background: #fff; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center;">-</button>
                                <span id="label-num-personas" style="font-weight: 800; color: var(--verde-oscuro); font-size: 14px; min-width: 15px; text-align: center;"><?php echo intval($pedidoInfo['num_personas'] ?? 1); ?></span>
                                <button type="button" onclick="ajustarComensales(1)" style="width: 26px; height: 26px; border-radius: 4px; border: 1px solid #cbd5e1; background: #fff; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center;">+</button>
                            </div>
                        </div>
                        
                        <?php if ($pedidoInfo['tipo_pedido'] === 'local'): ?>
                            <span style="color:#666; display: block; font-size: 12px;">
                                📍 Ubicación: <strong><?php echo htmlspecialchars($pedidoInfo['nombre_area'] ?? 'Salón'); ?> / <?php echo htmlspecialchars($pedidoInfo['numero_mesa'] ?? 'Barra'); ?></strong>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- ÁREA DE ELEMENTOS DE CUENTA CON SCROLL INTERNO INDEPENDIENTE -->
                    <div class="ticket-rows-scroll">
                        <?php
                        $subtotal_acumulado = 0;
                        if (empty($itemsComanda)):
                        ?>
                            <div style="text-align:center; color:#94a3b8; padding:40px 10px; font-size:13px; font-style:italic;">
                                Comanda vacía. Toca los productos de la izquierda para sumarlos a la cuenta de la mesa.
                            </div>
                        <?php 
                        else: 
                            foreach ($itemsComanda as $item):
                                $subtotal_acumulado += floatval($item['subtotal']);
                                $id_detalle = (int)$item['id'];
                                
                                // 1. EXTRAER EXTRAS DESDE TU TABLA RELACIONAL EN ESPAÑOL
                                $stmtExt = $db->prepare("SELECT pde.*, p.nombre FROM pedido_detalle_extras pde INNER JOIN productos p ON pde.producto_id = p.id WHERE pde.pedido_detalle_id = :det_id");
                                $stmtExt->execute(['det_id' => $id_detalle]);
                                $extrasItem = $stmtExt->fetchAll();

                                // 2. EXTRAER MITADES (Para pizzas mixtas combinadas de sabores)
                                $saboresMixtosText = "";
                                if (isset($item['es_mixta']) && (int)$item['es_mixta'] === 1) {
                                    $stmtSab = $db->prepare("SELECT p.nombre FROM pedido_detalle_sabores pds INNER JOIN productos p ON pds.producto_id = p.id WHERE pds.pedido_detalle_id = :det_id");
                                    $stmtSab->execute(['det_id' => $id_detalle]);
                                    $saboresMitades = $stmtSab->fetchAll(PDO::FETCH_COLUMN);
                                    if (!empty($saboresMitades)) {
                                        $saboresMixtosText = "Combinación: " . implode(" / ", $saboresMitades);
                                    }
                                }
                        ?>
                               <?php
// ==========================================================================
// 🎨 MÁQUINA DE ESTADOS FINITA (FSM) - ESQUEMA HOMOLOGADO JUNGLE POS
// ==========================================================================
$estado_actual_item = trim($item['estado'] ?? 'solicitado');

// 🌟 DETECTOR DE ESTACIÓN: Extraemos el destino KDS real para pintar el texto exacto
$stmtEstacion = $db->prepare("SELECT area_produccion FROM productos WHERE id = :prod_id LIMIT 1");
$stmtEstacion->execute(['prod_id' => intval($item['producto_id'])]);
$area_res = $stmtEstacion->fetch(PDO::FETCH_ASSOC);
$estacion_destino = strtoupper($area_res['area_produccion'] ?? 'Cocina');

// Valores base por defecto: Verde Jungle para los nuevos borradores
$estilo_fondo = 'background: #ffffff; border-left: 5px solid #2b8a3e;'; 
$badge_html = '<span style="font-size: 10px; font-weight: 800; background: #ebfbee; color: #2b8a3e; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.3px;">✨ Nuevo Borrador</span>';
$bloquear_borrado_ordinario = false; 

if ($estado_actual_item === 'pendiente') {
    // 🚀 HOMOLOGACIÓN DE DESTINOS: El letrero te dice exactamente a qué monitor viajó
    $estilo_fondo = 'background: #fafafa; border-left: 5px solid #cbd5e1; opacity: 0.85;'; // Gris resguardo
    $badge_html = '<span style="font-size: 10px; font-weight: 800; background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.3px;">📦 Comandado ' . $estacion_destino . '</span>';
    $bloquear_borrado_ordinario = true; 
} elseif ($estado_actual_item === 'preparando') {
    $estilo_fondo = 'background: #fdfaf6; border-left: 5px solid #d9480f; opacity: 0.85;'; // Naranja estricto solo para lo que está en fuego
    $badge_html = '<span style="font-size: 10px; font-weight: 800; background: #fff4e6; color: #d9480f; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.3px;">🔥 En Fuego</span>';
    $bloquear_borrado_ordinario = true;
} elseif ($estado_actual_item === 'servido') {
    $estilo_fondo = 'background: #f4f8fa; border-left: 5px solid #0d47a1; opacity: 0.70;'; // Azul para Entregado a la Mesa
    $badge_html = '<span style="font-size: 10px; font-weight: 800; background: #e3f2fd; color: #0d47a1; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.3px;">✅ Servido</span>';
    $bloquear_borrado_ordinario = true;
}
?>
<!-- Fila de la comanda con inyección de paletas elásticas CSS (Mantiene tu maquetación intacta) -->
<div class="ticket-item-row" id="fila-detalle-<?php echo $id_detalle; ?>" style="display: flex; justify-content: space-between; align-items: start; padding: 12px 10px; border-bottom: 1px solid #edf2f7; border-radius: 8px; margin-bottom: 6px; box-sizing: border-box; <?php echo $estilo_fondo; ?>">
    <div style="flex:1; padding-right:10px;">
        <span style="font-weight:700; color:#1e293b;"><?php echo (int)$item['cantidad']; ?>x</span>
        <span style="font-weight: 600; color: #1b4332;">
            <?php echo ((int)$item['es_mixta'] === 1) ? "Pizza Mixta Combinada" : htmlspecialchars($item['nombre_producto']); ?>
        </span>

        <!-- Visualización de las mitades combinadas (Mapeo simétrico al KDS) -->
        <?php if (!empty($saboresMixtosText)): ?>
            <div style="font-size: 12px; color: #e67e22; font-weight: bold; background: #fff4e6; padding: 4px 8px; border-radius: 4px; margin-top: 3px; display: inline-block;">
                <?php echo htmlspecialchars($saboresMixtosText); ?>
            </div>
        <?php endif; ?>

        <!-- 🌟 INYECCIÓN DE LA NUEVA INSIGNIA DE ESTACIÓN INTELIGENTE -->
        <div style="margin-top: 5px; display: flex; gap: 4px; align-items: center; margin-bottom: 4px;">
            <?php echo $badge_html; ?>
        </div>

        <!-- Renderizado del recuadro de adicionales con inyección de precios -->
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

        <!-- BOTONERA TÁCTIL INTELIGENTE CON CANDADOS DE RONDAS -->
        <div class="action-row-buttons" style="margin-top: 8px; display: flex; gap: 6px;">
            <button type="button" class="btn-mini-pos btn-mini-extra" onclick="abrirModalModificadores(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['nombre_producto']); ?>')">🧀 + Extra</button>
            
            <?php if ($bloquear_borrado_ordinario === false): ?>
                <!-- Caso A: Es un borrador libre de la ronda nueva. Borrado instantáneo permitido -->
                <button type="button" class="btn-mini-pos btn-mini-delete" onclick="solicitarBajaItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['nombre_producto']); ?>')">❌ Quitar</button>
            <?php else: ?>
                <!-- Caso B: El plato ya está en producción. Bloqueo ordinario e insignia protectora -->
                <span style="font-size: 11px; color: #94a3b8; font-style: italic; padding-left: 5px; display: inline-flex; align-items: center; gap: 3px;">🔒 Comandado</span>
            <?php endif; ?>
        </div>
    </div>

    <div style="font-weight:800; text-align:right; color:#334155; min-width:70px; padding-top: 2px;">
        C$ <?php echo number_format($item['subtotal'], 2); ?>
    </div>
</div>
<?php
    endforeach; 
endif; 
?>
</div>



                    <!-- TOTALES DEL TICKET CON AJUSTES EN CALIENTE -->
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
                        <!-- 🚀 INYECTA ESTE BOTÓN AZÚL REPOSICIONADO ADENTRO DE TU TICKET DERECHO EN views/tomar_pedido.php: -->
<div style="margin-top: 10px; width: 100%;">
    <!-- Al dar clic abre la precuenta térmica en una pestaña flotante limpia lista para la tiquetera -->
    <a href="index.php?v=precuenta&pedido_id=<?php echo $pedido_id; ?>" target="_blank" style="width: 100%; background: #0284c7; color: #ffffff; border: none; padding: 12px; border-radius: 8px; font-weight: 800; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; box-sizing: border-box; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.15); margin-bottom: 8px; text-transform: uppercase;">
        🧾 Imprimir Precuenta (Mesa)
    </a>
</div>


                        <!-- 🌟 EL BOTÓN VERDE PURIFICADO CON LA RUTA DE TU CONTROLADOR REAL -->
                        <div style="margin-top: 15px; width: 100%;">
                            <form action="<?php echo URL_BASE; ?>controllers/PedidoController.php" method="POST">
                                <input type="hidden" name="accion" value="comandar_orden_kds">
                                <input type="hidden" name="pedido_id" value="<?php echo (int)$_GET['pedido_id']; ?>">
                                <button type="submit" style="width: 100%; background: #2b8a3e; color: #ffffff; border: none; padding: 14px; border-radius: 8px; font-weight: 800; font-size: 15px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px rgba(43, 138, 62, 0.2); transition: background 0.2s;">
                                    🚀 Enviar Orden a Producción
                                </button>
                            </form>
                        </div>
                    </div>
                </div> <!-- Cierre de la clase .pos-card de la comanda -->
            </div> <!-- Cierre de la clase .pos-grid-container maestro -->
        </main>
    </div>
    <!-- 📦 CONFIGURADOR FLOTANTE MAESTRO PARA PIZZAS MIXTAS -->
    <div id="modal-pizza-mixta-wrapper" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999999; align-items: center; justify-content: center; padding: 15px; box-sizing: border-box;">
        <div style="background: #ffffff; width: 100%; max-width: 460px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); overflow: hidden; border-top: 5px solid #e67e22;">
            <div style="padding: 18px; background: #fafbfc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #1b4332; font-size: 1.15rem;">🍕 Configurar Especialidad Mixta</h3>
                <button type="button" onclick="cerrarModalPizzaMixta()" style="background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer; font-weight: bold;">&times;</button>
            </div>
            <form action="<?php echo URL_BASE; ?>controllers/PedidoController.php" method="POST" style="padding: 20px; margin: 0;">
                <input type="hidden" name="accion" value="agregar_mixta">
                <input type="hidden" name="pedido_id" value="<?php echo $pedido_id; ?>">
                <input type="hidden" name="cantidad" value="1">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #1b4332;">Seleccione el Primer Sabor (1/2)</label>
                    <select name="sabor_1_id" class="form-control" required>
                        <option value="">-- Escoger Mitad A --</option>
                        <?php foreach ($productosMenu as $prod): ?>
                            <?php if ((int)$prod['es_sabor_pizza'] === 1): ?>
                                <option value="<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['nombre']); ?> (C$ <?php echo number_format($prod['precio_base'], 2); ?>)</option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #1b4332;">Seleccione el Segundo Sabor (1/2)</label>
                    <select name="sabor_2_id" class="form-control" required>
                        <option value="">-- Escoger Mitad B --</option>
                        <?php foreach ($productosMenu as $prod): ?>
                            <?php if ((int)$prod['es_sabor_pizza'] === 1): ?>
                                <option value="<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['nombre']); ?> (C$ <?php echo number_format($prod['precio_base'], 2); ?>)</option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="button" onclick="cerrarModalPizzaMixta()" style="flex: 1; padding: 12px; border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 8px; font-weight: bold; color: #475569; cursor: pointer;">Cancelar</button>
                    <button type="submit" style="flex: 1; padding: 12px; border: none; background: #e67e22; color: #ffffff; border-radius: 8px; font-weight: bold; cursor: pointer;">➕ Agregar al Ticket</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 📦 NUEVO MODAL TÁCTIL PARA SELECCIÓN DE INGREDIENTES EXTRAS -->
    <div id="modal-agregar-extra-wrapper" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999999; align-items: center; justify-content: center; padding: 15px; box-sizing: border-box;">
        <div style="background: #ffffff; width: 100%; max-width: 420px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); overflow: hidden; border-top: 5px solid #b58105;">
            <div style="padding: 18px; background: #fafbfc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #856404; font-size: 1.1rem;" id="titulo-modal-extra-dinamico">🧀 Cargar Extra</h3>
                <button type="button" onclick="cerrarModalExtras()" style="background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer; font-weight: bold;">&times;</button>
            </div>
            <form action="<?php echo URL_BASE; ?>controllers/PedidoController.php" method="POST" style="padding: 20px; margin: 0;">
                <input type="hidden" name="accion" value="agregar_extra">
                <input type="hidden" name="pedido_id" value="<?php echo $pedido_id; ?>">
                <input type="hidden" name="pedido_detalle_id" id="modal-extra-detalle-id">
                <input type="hidden" name="precio_cobrado" id="modal-extra-precio-hidden">
                <input type="hidden" name="cantidad" value="1">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #1b4332;">Seleccione el Adicional / Modificador</label>
                    <select id="select-ingrediente-extra" name="producto_id" class="form-control" required onchange="actualizarPrecioExtraVisual(this)">
                        <option value="">-- Seleccionar Adicional --</option>
                        <?php foreach ($productosMenu as $prod): ?>
                            <?php
                            $nombre_minuscula = strtolower($prod['nombre']);
                            if (strpos($nombre_minuscula, 'extra') !== false || strpos($nombre_minuscula, 'borde') !== false):
                            ?>
                                <option value="<?php echo $prod['id']; ?>" data-precio="<?php echo $prod['precio_base']; ?>">
                                    <?php echo htmlspecialchars($prod['nombre']); ?> (+ C$ <?php echo number_format($prod['precio_base'], 2); ?>)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="background: #fffbeb; padding: 10px; border-radius: 6px; border: 1px solid #ffeeba; margin-bottom: 20px; font-size: 13px; color: #856404; font-weight: bold; text-align: right;">
                    Cargo Extra: <span id="label-precio-extra-dinamico">C$ 0.00</span>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="cerrarModalExtras()" style="flex: 1; padding: 12px; border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 8px; font-weight: bold; color: #475569; cursor: pointer;">Cancelar</button>
                    <button type="submit" style="flex: 1; padding: 12px; border: none; background: #b58105; color: #ffffff; border-radius: 8px; font-weight: bold; cursor: pointer;">🧀 Sumar Extra</button>
                </div>
            </form>
        </div>
    </div>
    <!-- 🧠🧠 SCRIPTS OPERATIVOS DEL PUNTO DE VENTA -->
    <script>
    /**
     * 📁 FILTRADO TÁCTIL: Muestra u oculta los platos de la cuadrícula de forma instantánea
     */
    function filtrarCatalogoArea(categoriaId, elemento) {
        const catId = parseInt(categoriaId);
        
        // Solo alteramos estilos activos si el elemento tocado es un botón (evita romper el select)
        if (elemento && elemento.tagName === 'BUTTON') {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('tab-active'));
            elemento.classList.add('tab-active');
        }

        // Buscador universal elástico en la cuadrícula de tu tablet
        const tarjetasProductos = document.querySelectorAll('.product-item-card, [data-cat-id]');
        tarjetasProductos.forEach(card => {
            const tarjetaCat = parseInt(card.getAttribute('data-cat-id'));
            
            // Si el combo marca 0 o el ID coincide con la categoría elegida, renderiza al instante
            if (catId === 0 || tarjetaCat === catId) {
                card.style.setProperty('display', 'flex', 'important'); 
            } else {
                card.style.setProperty('display', 'none', 'important'); 
            }
        });
    }

    /**
     * 📦 INSERCIÓN RÁPIDA: Inyecta los datos en el formulario oculto y dispara el POST
     */
    function agregarProductoFila(productoId, nombre, precio) {
        const form = document.getElementById('form-add-item-hidden');
        const inputId = document.getElementById('hidden-prod-id');
        const inputPrice = document.getElementById('hidden-prod-price');
        const inputMixta = document.querySelector('input[name="es_mixta"]') || document.getElementsByName('es_mixta')[0];

        // 🌟 NUEVO CANDADO DE MEMORIA PERSISTENTE: Guarda la categoría activa del mesero
        const selectCategoria = document.querySelector('.category-select-wrapper select');
        if (selectCategoria) {
            localStorage.setItem('jungle_pizza_categoria_activa', selectCategoria.value);
        }

        if (form && inputId && inputPrice) {
            inputId.value = productoId;
            inputPrice.value = precio;
            if (inputMixta) {
                inputMixta.value = "0";
            }
            form.submit(); 
        }
    }

    /**
     * 🚚 ESTIMADOR DE DELIVERY: Recalcula el gran total sumando el motorizado en vivo
     */
    function recalcularGranTotalDelivery(montoEnvio) {
        const subtotalElement = document.getElementById('resumen-subtotal-neto');
        const totalElement = document.getElementById('resumen-total-final');
        if (!subtotalElement || !totalElement) return;

        const subtotalNeto = parseFloat(subtotalElement.getAttribute('data-neto')) || 0;
        const envioNum = parseFloat(montoEnvio) || 0;
        totalElement.innerText = "C$ " + (subtotalNeto + envioNum).toFixed(2);
    }

    /**
     * 🧀 MODAL EXTRAS: Abre la solapa e indexa el ID de la fila de detalles de la comanda
     */
    function abrirModalModificadores(detalleId, nombreProducto) {
        const modal = document.getElementById('modal-agregar-extra-wrapper');
        const inputDetail = document.getElementById('modal-extra-detalle-id');
        const titulo = document.getElementById('titulo-modal-extra-dinamico');
        const select = document.getElementById('select-ingrediente-extra');
        const labelPrecio = document.getElementById('label-precio-extra-dinamico');
        const inputPrecioHidden = document.getElementById('modal-extra-precio-hidden');

        if (modal && inputDetail) {
            inputDetail.value = detalleId;
            if (titulo) titulo.innerText = `🧀 Extras para: ${nombreProducto}`;
            if (select) select.selectedIndex = 0;
            if (labelPrecio) labelPrecio.innerText = "C$ 0.00";
            if (inputPrecioHidden) inputPrecioHidden.value = "0.00";
            modal.style.display = 'flex';
        }
    }

    function cerrarModalExtras() {
        const modal = document.getElementById('modal-agregar-extra-wrapper');
        if (modal) { modal.style.display = 'none'; }
    }

    function actualizarPrecioExtraVisual(elementoSelect) {
        const opcionSeleccionada = elementoSelect.options[elementoSelect.selectedIndex];
        const labelPrecio = document.getElementById('label-precio-extra-dinamico');
        const inputPrecioHidden = document.getElementById('modal-extra-precio-hidden');

        if (opcionSeleccionada && elementoSelect.value !== "") {
            const precioBase = parseFloat(opcionSeleccionada.getAttribute('data-precio')) || 0;
            if (labelPrecio) labelPrecio.innerText = "C$ " + precioBase.toFixed(2);
            if (inputPrecioHidden) inputPrecioHidden.value = precioBase.toFixed(2);
        } else {
            if (labelPrecio) labelPrecio.innerText = "C$ 0.00";
            if (inputPrecioHidden) inputPrecioHidden.value = "0.00";
        }
    }

    /**
     * ❌ SOLICITAR BAJA: Activa el prompt de merma obligatorio para productos comandados
     */
    function solicitarBajaItem(detalleId, nombreProducto) {
        const motivo = prompt(`❌ REMOVER DE LA COMANDA: ${nombreProducto}\nEscriba el motivo obligatorio para autorizar la remoción:`);
        if (motivo) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo URL_BASE; ?>controllers/PedidoController.php';
            form.innerHTML = `
                <input type="hidden" name="accion" value="quitar_item">
                <input type="hidden" name="pedido_id" value="<?php echo $pedido_id; ?>">
                <input type="hidden" name="pedido_detalle_id" value="${detalleId}">
                <input type="hidden" name="motivo_quitar" value="${motivo}">
                <input type="hidden" name="fue_servido" value="1">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    // 🚀 INYECTA ESTAS DOS FUNCIONES AL FINAL DE TU BLOQUE DE SCRIPT EN views/tomar_pedido.php:

/**
 * 🍕 CONTROL TÁCTIL MODAL MIXTO: Unifica los identificadores de pantalla 
 * para forzar al navegador a desplegar el configurador de mitades combinadas.
 */
function abrirModalPizzaMixta() {
    // Apuntamos con precisión quirúrgica al ID real de tu contenedor físico de la Parte 4.A
    const modalMixto = document.getElementById('modal-pizza-mixta-wrapper');
    
    if (modalMixto) {
        // Cambiamos el display de 'none' a 'flex' para centrarlo estéticamente en la tablet
        modalMixto.style.setProperty('display', 'flex', 'important');
    } else {
        console.error("🚨 ERROR JUNGLE POS: No se encontró el contenedor 'modal-pizza-mixta-wrapper' en el DOM.");
    }
}

function cerrarModalPizzaMixta() {
    const modalMixto = document.getElementById('modal-pizza-mixta-wrapper');
    if (modalMixto) {
        modalMixto.style.setProperty('display', 'none', 'important');
    }
}


    /**
     * 👥 SINCRO COMENSALES: Suma o resta personas enviando un microsegundo AJAX en segundo plano
     */
    function ajustarComensales(cambio) {
        const label = document.getElementById('label-num-personas');
        if (!label) return;

        let valorActual = parseInt(label.innerText) || 1;
        let nuevoValor = valorActual + cambio;
        if (nuevoValor < 1) nuevoValor = 1;

        label.innerText = nuevoValor;

        const formData = new FormData();
        formData.append('accion', 'actualizar_comensales_ajax');
        formData.append('pedido_id', '<?php echo $pedido_id; ?>');
        formData.append('num_personas', nuevoValor);

        fetch('<?php echo URL_BASE; ?>controllers/PedidoController.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                console.error("Error al sincronizar comensales con la base de datos.");
            }
        })
        .catch(error => console.error("Error de red comensales:", error));
    }

    /**
     * 🌟 REINYECTOR DE FILTRO: Lee la memoria interna de la tablet al reencender la pantalla
     */
    document.addEventListener("DOMContentLoaded", function() {
        const categoriaGuardada = localStorage.getItem('jungle_pizza_categoria_activa');
        const selectCategoria = document.querySelector('.category-select-wrapper select');

        if (categoriaGuardada !== null && selectCategoria) {
            const catId = parseInt(categoriaGuardada);
            selectCategoria.value = catId;
            filtrarCatalogoArea(catId, selectCategoria);
        }
    });
    </script>
    <script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>
</html>
