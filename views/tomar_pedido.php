<?php
// views/tomar_pedido.php

// 1. Validaciones e instancias de los datos del pedido actual
require_once __DIR__ . '/../models/PedidoModelo.php';
require_once __DIR__ . '/../controllers/ProductoController.php';

$pedido_id = intval($_GET['pedido_id'] ?? 0);
$db = (new Conexion())->conectar();

// 🚀 REEMPLÁZALA EXACTAMENTE POR ESTA (Añadiendo el amarre de usuarios):
$stmtPed = $db->prepare("SELECT p.*, m.numero_mesa, a.nombre as nombre_area, u.nombre as nombre_mesero 
                         FROM pedidos p 
                         LEFT JOIN mesas m ON p.mesa_id = m.id 
                         LEFT JOIN areas a ON m.area_id = a.id 
                         INNER JOIN usuarios u ON p.usuario_id = u.id -- Amarre indestructible al empleado
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
$productosMenu  = $prodController->listar(); // Trae todo el catálogo activo

// 🚀 REEMPLAZE EL PASO 3 DE TU ARCHIVO POR ESTE BLOQUE DE ALTA PRECISIÓN:

// 3. Extraemos el detalle actual de lo que ya se ha sumado a esta comanda (Forzando la columna es_mixta)
$stmtDet = $db->prepare("SELECT 
                            pd.id, 
                            pd.pedido_id, 
                            pd.producto_id, 
                            pd.cantidad, 
                            pd.precio_unitario, 
                            pd.subtotal, 
                            pd.estado, 
                            pd.es_mixta, -- 🌟 Forzamos la extracción limpia del flag 0 o 1
                            p.nombre as nombre_producto, 
                            p.imagen 
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
    <!-- 🎨 MAQUETACIÓN EXCLUSIVA PARA EL ENTORNO TÁCTIL DE VENTAS -->
    <style>
        /* ==========================================================================
   🚀 CORE LAYOUT: EXPANSIÓN VERTICAL MÁXIMA DE LA PANTALLA TÁCTIL
   ========================================================================== */

        /* Contenedor principal: Corregimos la sintaxis y fijamos el alto total de la tablet */
        .pos-grid-container {
            display: grid !important;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-top: 15px;
            width: 100%;
            /* 🌟 SOLUCIÓN: Añadimos el signo menos (-) para que descuente la barra superior */
            height: calc(100vh - 140px) !important;
            min-height: 650px;
        }

        /* Tarjetas base de las dos grandes columnas */
        .pos-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(27, 67, 50, 0.05);
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            /* 🌟 ALTO FIJO: Obliga a ambas columnas a estirarse idénticamente hasta abajo */
            height: 100% !important;
            box-sizing: border-box;
        }

        /* ==========================================================================
   📁 SECCIÓN IZQUIERDA: CATÁLOGO DE PRODUCTOS EXPANSIBLE
   ========================================================================== */

        /* Barra de selección desplegable de categorías */
        .category-select-wrapper {
            margin-bottom: 15px;
            width: 100%;
        }

        /* 🌟 CUADRÍCULA ELÁSTICA AMPLIADA: Elevamos las medidas de las tarjetas de pizzas/bebidas */
        .menu-products-layout {
            display: grid !important;
            /* Tarjetas más anchas para que no se vean comprimidas */
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)) !important;
            gap: 15px;
            overflow-y: auto;
            /* Scroll interno independiente */
            flex: 1;
            padding-right: 5px;
        }

        /* La tarjeta del producto individual: Más alta e imponente */
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
            /* 🌟 ELEVACIÓN VERTICAL: Pasamos de 175px a 240px de alto total */
            height: 240px !important;
        }

        .product-item-card:active {
            transform: scale(0.96);
        }

        /* La foto de la Coca Cola o Fanta gana mucha más presencia física */
        .product-item-card img {
            width: 100%;
            /* 🌟 MÁS ALTO: Elevamos la foto a 130px de alto con ajuste proporcional */
            height: 130px !important;
            object-fit: contain !important;
            background: #ffffff;
            /* Fondo blanco limpio para resaltar botellas */
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
            /* Texto un punto más grande */
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

        /* El contenedor maestro de la comanda */
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

        /* 🌟 ÁREA DE ELEMENTOS DE CUENTA: Absorbe de forma inteligente todo el alto del centro */
        .ticket-rows-scroll {
            flex: 1 !important;
            overflow-y: auto !important;
            /* Genera scroll en cuentas largas de forma fluida */
            padding-right: 5px;
            /* Ponemos un límite visual saludable para que no empuje hacia abajo a los totales */
            max-height: calc(100% - 180px) !important;
        }

        .ticket-item-row {
            display: flex;
            justify-content: space-between;
            align-items: start;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        /* Botonera de Modificadores Rápidos */
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

        .btn-mini-extra {
            background: #fff3cd;
            color: #856404;
        }

        .btn-mini-delete {
            background: #ffe3e3;
            color: #c92a2a;
        }

        /* 🌟 TOTALES DEL TICKET CONGELADOS EN LA BASE:
   Quedarán perfectamente plantados abajo al lado de tu botón de envío */
        .ticket-summary-totals {
            background: #f8fafc;
            padding: 14px;
            border-radius: 8px;
            margin-top: auto;
            /* Empuja de forma automática la caja hacia el fondo */
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
            font-size: 18px;
            !important;
            /* Total Final imponente */
            font-weight: 800;
            color: var(--verde-oscuro, #1b4332);
            margin-bottom: 0;
        }

        /* Alertas de Notificación */
        .alert {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13px;
            font-weight: 500;
        }

        .alert-error {
            background: #ffe3e3;
            color: #c92a2a;
        }

        .alert-success {
            background: #ebfbee;
            color: #2b8a3e;
        }

        .form-control {
            width: 100% !important;
            padding: 10px !important;
            border: 2px solid #e2e8f0 !important;
            border-radius: 8px !important;
            box-sizing: border-box !important;
        }

        /* ==========================================================================
   📱 RESPONSIVE MEDIA QUERIES (Sincronización de Pantallas Grandes)
   ========================================================================== */
        @media (min-width: 992px) {
            .pos-grid-container {
                /* Mantenemos tu distribución horizontal perfecta: el catálogo toma el resto y el ticket mide 380px fijo */
                grid-template-columns: 1fr 380px !important;
            }
        }
    </style>

</head>

<body>
    <!-- Cabecera Mobile original intacta -->
    <header class="mobile-header">
        <button class="hamburger-btn" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <div class="mobile-logo">🍕 Jungle POS</div>
    </header>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="dashboard-layout">
        <!-- Inclusión nativa de tu barra lateral fija -->
        <?php include 'sidebar.php'; ?>

        <main class="main-content" style="padding-bottom: 5px;">
            <!-- Notificaciones URL del Controlador -->
            <?php if ($msg_error): ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
            <?php if ($msg_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div><?php endif; ?>

            <div class="pos-grid-container">

                <!-- COLUMNA IZQUIERDA: EL CATÁLOGO VISUAL SELECCIONABLE -->
                <div class="pos-card">
                    <!-- 🎛️ SELECTOR DESPLEGABLE DE CATEGORÍAS (MÁXIMO AHORRO DE ESPACIO TÁCTIL) -->
                    <div class="category-select-wrapper" style="margin-bottom: 15px; width: 100%;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 700; font-size: 13px; color: var(--verde-oscuro);">
                            📁 Seleccionar Categoría:
                        </label>

                        <?php
                        // 🌟 MAPA DE PRIORIDADES GASTRONÓMICAS IDEAL
                        $ordenIdealGastro = ['entrada', 'pizza', 'almuerzo', 'rapida', 'bebida', 'empaque'];

                        // Ordenamos el arreglo original de tu menú usando la función de comparación de PHP
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
                            <div style="width:100%; height:95px; background:rgba(230, 126, 34, 0.1); display:flex; align-items:center; justify-content:center; font-size:32px;">🍕🌓</div>
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
                                        <div style="width:100%; height:95px; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-size:24px;">🍕</div>
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

                <!-- FORMULARIO OCULTO INSERCIÓN RÁPIDA -->
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
                    <!-- 🚀 REEMPLAZA LA CABECERA DEL TICKET EN views/tomar_pedido.php POR ESTA VERSION CON CONTADOR: -->
                    <div class="ticket-header-info" style="padding-bottom: 12px; border-bottom: 1px dashed #cbd5e1; margin-bottom: 12px; font-size: 13px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span style="font-weight:800; color:var(--verde-oscuro); font-size: 14px;">📋 TICKET #<?php echo $pedido_id; ?></span>
                            <span style="background: #e2e8f0; color: #334155; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;">
                                👤 <?php echo htmlspecialchars($pedidoInfo['nombre_mesero'] ?? 'Sistema'); ?>
                            </span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 8px 12px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 6px;">
                            <span style="color:#475569; font-weight: 600;">Modalidad: <strong style="color: #e67e22;"><?php echo strtoupper($pedidoInfo['tipo_pedido']); ?></strong></span>

                            <!-- 👥 CONTADOR INTERACTIVO DE COMENSALES -->
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 12px; color: #64748b; font-weight: 700;">👥 Clientes:</span>
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



                    <div class="ticket-rows-scroll">
                        <?php
                        // Inicialización obligatoria para evitar Warnings
                        $subtotal_acumulado = 0;

                        if (empty($itemsComanda)):
                        ?>
                            <div style="text-align:center; color:#94a3b8; padding:40px 10px; font-size:13px; font-style:italic;">
                                Comanda vacía. Toca los productos de la izquierda para sumarlos a la cuenta de la mesa.
                            </div>
                        <?php else: ?>
                            < <?php
                                foreach ($itemsComanda as $item):
                                    $subtotal_acumulado += floatval($item['subtotal']);

                                    // 1. EXTRAER EXTRAS: Buscamos si el renglón tiene ingredientes adicionales
                                    $stmtExt = $db->prepare("SELECT pde.*, p.nombre FROM pedido_detalle_extras pde 
                                                             INNER JOIN productos p ON pde.producto_id = p.id 
                                                             WHERE pde.pedido_detalle_id = :det_id");
                                    $stmtExt->execute(['det_id' => $item['id']]);
                                    $extrasItem = $stmtExt->fetchAll();

                                    // 🌟 2. EXTRAER MITADES (Para pizzas mixtas): Buscamos los sabores relacionales
                                    $saboresMixtosText = "";

                                    // Blindamos la condición validando que exista la columna y que sea estrictamente igual a 1
                                    if (isset($item['es_mixta']) && (int)$item['es_mixta'] === 1) {
                                        $stmtSab = $db->prepare("SELECT p.nombre FROM pedido_detalle_sabores pds
                                                                 INNER JOIN productos p ON pds.producto_id = p.id
                                                                 WHERE pds.pedido_detalle_id = :det_id");
                                        $stmtSab->execute(['det_id' => $item['id']]);
                                        $saboresMitades = $stmtSab->fetchAll(PDO::FETCH_COLUMN);

                                        if (!empty($saboresMitades)) {
                                            // Se adapta de forma dinámica si eligen 2, 3 o 4 sabores
                                            $saboresMixtosText = "🌓 Combinación: " . implode(" / ", $saboresMitades);
                                        }
                                    }

                                ?>
                                <div class="ticket-item-row">
                                <div style="flex:1; padding-right:10px;">
                                    <!-- Cantidad y Nombre del producto raíz -->
                                    <span style="font-weight:700; color:#1e293b;"><?php echo (int)$item['cantidad']; ?>x</span>
                                    <span style="font-weight: 600; color: #1b4332;">
                                        <?php echo ((int)$item['es_mixta'] === 1) ? "Pizza Mixta Combinada" : htmlspecialchars($item['nombre_producto']); ?>
                                    </span>

                                    <!-- 🌟 VISUALIZACIÓN DE LOS DOS SABORES REALES DE LA BASE DE DATOS -->
                                    <?php if (!empty($saboresMixtosText)): ?>
                                        <div style="font-size: 12px; color: #e67e22; font-weight: bold; background: #fff4e6; padding: 4px 8px; border-radius: 4px; margin-top: 3px; display: inline-block;">
                                            <?php echo htmlspecialchars($saboresMixtosText); ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- 🚀 REEMPLAZA EL CONTENEDOR DE EXTRAS EN LA FILA POR ESTE (Añade el precio individual): -->
                                    <?php if (!empty($extrasItem)): ?>
                                        <div style="font-size:11px; color:#b58105; background:#fffbeb; padding:6px 8px; border-radius:4px; margin-top:5px; font-weight:600; line-height: 1.4;">
                                            <?php foreach ($extrasItem as $ex):
                                                $subtotal_acumulado += (floatval($ex['cantidad']) * floatval($ex['precio_cobrado']));
                                                $costo_total_este_extra = floatval($ex['cantidad']) * floatval($ex['precio_cobrado']);
                                            ?>
                                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                                    <span>✦ +<?php echo (int)$ex['cantidad']; ?> <?php echo htmlspecialchars($ex['nombre']); ?></span>
                                                    <!-- 💵 Detalle financiero en la misma fila del modificador -->
                                                    <span style="color: #856404; font-weight: 700;">+ C$ <?php echo number_format($costo_total_este_extra, 2); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>


                                    <div class="action-row-buttons">
                                        <button type="button" class="btn-mini-pos btn-mini-extra" onclick="abrirModalModificadores(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['nombre_producto']); ?>')">🧀 + Extra</button>
                                        <button type="button" class="btn-mini-pos btn-mini-delete" onclick="solicitarBajaItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['nombre_producto']); ?>')">❌ Quitar</button>
                                    </div>
                                </div>
                                <!-- Precio cobrado (el de la mitad más cara) -->
                                <div style="font-weight:800; text-align:right; color:#334155; min-width:70px;">
                                    C$ <?php echo number_format($item['subtotal'], 2); ?>
                                </div>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>
                </div>

                <!-- 📊 TOTALES DEL TICKET CON AJUSTES EN CALIENTE -->
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
                            <input type="number" id="input-costo-envio-dinamico" name="monto_envio_dinamico"
                                style="width: 90px; padding: 4px 8px; border: 2px solid #e67e22; border-radius: 6px; font-weight: bold; text-align: right;"
                                min="0" step="1" value="<?php echo floatval($pedidoInfo['monto_envio']); ?>"
                                oninput="recalcularGranTotalDelivery(this.value)">
                        </div>
                    <?php endif; ?>

                    <div class="summary-flex-line summary-total-bold">
                        <span>TOTAL FINAL:</span>
                        <span id="resumen-total-final">C$ <?php echo number_format(floatval($pedidoInfo['total']), 2); ?></span>
                    </div>
                </div>

                <!-- 🚀 REEMPLAZE EL BOTÓN VERDE ORIGINAL AL FONDO DE TU TICKET POR ESTA ESTRUCTURA DIRECTA EN PHP: -->
<div style="margin-top: 15px; width: 100%;">
    <form action="" method="POST">
        <!-- Enviamos las variables de control directo al controlador -->
        <input type="hidden" name="accion" value="comandar_orden_kds">
        <input type="hidden" name="pedido_id" value="<?php echo (int)$_GET['pedido_id']; ?>">
        
        <button type="submit" style="width: 100%; background: #2b8a3e; color: #ffffff; border: none; padding: 14px; border-radius: 8px; font-weight: 800; font-size: 15px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px rgba(43, 138, 62, 0.2); transition: background 0.2s;">
            🚀 Enviar Orden a Producción
        </button>
    </form>
</div>


            </div>
    </div>

    </div>
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
                <!-- Campos dinámicos que inyectará JavaScript al tocar la fila -->
                <input type="hidden" name="pedido_detalle_id" id="modal-extra-detalle-id">
                <input type="hidden" name="precio_cobrado" id="modal-extra-precio-hidden">
                <input type="hidden" name="cantidad" value="1">

                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #1b4332;">Seleccione el Adicional / Modificador</label>
                    <!-- Al cambiar la opción del combo, JavaScript lee el precio data-precio de inmediato -->
                    <select id="select-ingrediente-extra" name="producto_id" class="form-control" required onchange="actualizarPrecioExtraVisual(this)">
                        <option value="">-- Seleccionar Adicional --</option>
                        <?php foreach ($productosMenu as $prod): ?>
                            <?php
                            // 🌟 FILTRO CLÍNICO: Excluimos la Categoría 1 (si 1 es entradas) y validamos que el nombre contenga la palabra "extra" o "borde"
                            // Nota: Si tienes un ID de categoría específico para puros extras (ej: categoría_id == 6), cambia la condición a: (int)$prod['categoria_id'] === 6
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

                <!-- Resumen Informativo en Pantalla -->
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


    <!-- 🧠 SCRIPTS OPERATIVOS DEL PUNTO DE VENTA -->
    <script>
        function abrirModalPizzaMixta() {
            const modal = document.getElementById('modal-pizza-mixta-wrapper');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function cerrarModalPizzaMixta() {
            const modal = document.getElementById('modal-pizza-mixta-wrapper');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // 🚀 REEMPLAZE LA FUNCIÓN DE FILTRADO EN LA PÁGINA 1 POR ESTA VERSIÓN INTEGRADA:

        function filtrarCatalogoArea(categoriaId, elemento) {
            // Convertimos a entero el ID seleccionado por seguridad (0 = Mostrar Todo)
            const catId = parseInt(categoriaId);

            // 🌟 BLINDAJE A: Solo alteramos estilos si el elemento tocado es un botón (evita romper el select)
            if (elemento && elemento.tagName === 'BUTTON') {
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('tab-active'));
                elemento.classList.add('tab-active');
            }

            // 🌟 BLINDAJE B: Selector universal. Busca tanto por la clase vieja como por las clases reales de tus tarjetas
            const tarjetasProductos = document.querySelectorAll('.producto-card, .producto-card-item, [data-cat-id]');

            tarjetasProductos.forEach(card => {
                const tarjetaCat = parseInt(card.getAttribute('data-cat-id'));

                // Si el combo marca 0 o el ID de la tarjeta coincide con la categoría elegida:
                if (catId === 0 || tarjetaCat === catId) {
                    card.style.setProperty('display', 'flex', 'important'); // 🚀 Renderiza la pizza al instante
                } else {
                    card.style.setProperty('display', 'none', 'important'); // 🚀 Oculta el plato
                }
            });
        }


        function agregarProductoFila(productoId, nombre, precio) {
            const form = document.getElementById('form-add-item-hidden');
            const inputId = document.getElementById('hidden-prod-id');
            const inputPrice = document.getElementById('hidden-prod-price');

            // Buscamos el input oculto de 'es_mixta' de la cuadrícula normal
            const inputMixta = document.getElementsByName('es_mixta')[0] || document.querySelector('input[name="es_mixta"]');

            // 🌟 NUEVO CANDADO DE MEMORIA: Buscamos qué categoría está seleccionada en tu combo desplegable justo ahora
            const selectCategoria = document.querySelector('.category-select-wrapper select');
            if (selectCategoria) {
                // Guardamos el número de la categoría en la memoria interna de la tablet para que no se borre al recargar
                localStorage.setItem('jungle_pizza_categoria_activa', selectCategoria.value);
            }

            if (form && inputId && inputPrice) {
                inputId.value = productoId;
                inputPrice.value = precio;

                if (inputMixta) {
                    inputMixta.value = "0";
                }

                form.submit(); // Dispara la inserción y recarga la página de forma normal
            }
        }


        function recalcularGranTotalDelivery(montoEnvio) {
            const subtotalElement = document.getElementById('resumen-subtotal-neto');
            const totalElement = document.getElementById('resumen-total-final');
            if (!subtotalElement || !totalElement) return;

            const subtotalNeto = parseFloat(subtotalElement.getAttribute('data-neto')) || 0;
            const envioNum = parseFloat(montoEnvio) || 0;
            totalElement.innerText = "C$ " + (subtotalNeto + envioNum).toFixed(2);
        }

        function confirmarOrdenHaciaKds() {
            const inputEnvio = document.getElementById('input-costo-envio-dinamico');
            const formFinal = document.createElement('form');
            formFinal.method = 'POST';
            formFinal.action = '<?php echo URL_BASE; ?>controllers/PedidoController.php';

            const envioValor = inputEnvio ? (parseFloat(inputEnvio.value) || 0.00) : 0.00;
            formFinal.innerHTML = `
                <input type="hidden" name="accion" value="confirmar_y_enviar_kds">
                <input type="hidden" name="pedido_id" value="<?php echo $pedido_id; ?>">
                <input type="hidden" name="monto_envio_actualizado" value="${envioValor}">
            `;
            document.body.appendChild(formFinal);
            formFinal.submit();
        }

        // 🚀 REEMPLAZA LA ANTIGUA FUNCIÓN DE EXTRAS POR ESTAS EN TU BLOQUE DE SCRIPTS:

        function abrirModalModificadores(detalleId, nombreProducto) {
            const modal = document.getElementById('modal-agregar-extra-wrapper');
            const inputDetalle = document.getElementById('modal-extra-detalle-id');
            const titulo = document.getElementById('titulo-modal-extra-dinamico');
            const select = document.getElementById('select-ingrediente-extra');
            const labelPrecio = document.getElementById('label-precio-extra-dinamico');
            const inputPrecioHidden = document.getElementById('modal-extra-precio-hidden');

            if (modal && inputDetalle) {
                // Seteamos los datos de la fila de la pizza elegida
                inputDetalle.value = detalleId;
                if (titulo) titulo.innerText = `🧀 Extras para: ${nombreProducto}`;

                // Limpiamos selecciones previas del combo al abrir
                if (select) select.selectedIndex = 0;
                if (labelPrecio) labelPrecio.innerText = "C$ 0.00";
                if (inputPrecioHidden) inputPrecioHidden.value = "0.00";

                modal.style.display = 'flex';
            }
        }

        function cerrarModalExtras() {
            const modal = document.getElementById('modal-agregar-extra-wrapper');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // LECTURA DINÁMICA: Atrapa el precio registrado en la base de datos al mover el combo
        function actualizarPrecioExtraVisual(elementoSelect) {
            const opcionSeleccionada = elementoSelect.options[elementoSelect.selectedIndex];
            const labelPrecio = document.getElementById('label-precio-extra-dinamico');
            const inputPrecioHidden = document.getElementById('modal-extra-precio-hidden');

            if (opcionSeleccionada && elementoSelect.value !== "") {
                // Extraemos el atributo data-precio que inyectó PHP desde la base de datos
                const precioBase = parseFloat(opcionSeleccionada.getAttribute('data-precio')) || 0;

                // Sincronizamos la pantalla y el campo oculto POST
                if (labelPrecio) labelPrecio.innerText = "C$ " + precioBase.toFixed(2);
                if (inputPrecioHidden) inputPrecioHidden.value = precioBase.toFixed(2);
            } else {
                if (labelPrecio) labelPrecio.innerText = "C$ 0.00";
                if (inputPrecioHidden) inputPrecioHidden.value = "0.00";
            }
        }


        function solicitarBajaItem(detalleId, nombreProducto) {
            const motivo = prompt(`❌ REMOVER DE LA COMANDA: ${nombreProducto}\nEscriba el motivo obligatorio para autorizar la remoción:`);
            if (motivo) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?php echo URL_BASE; ?>controllers/PedidoController.php';
                form.innerHTML = `<input type="hidden" name="accion" value="quitar_item"><input type="hidden" name="pedido_id" value="<?php echo $pedido_id; ?>"><input type="hidden" name="pedido_detalle_id" value="${detalleId}"><input type="hidden" name="motivo_quitar" value="${motivo}"><input type="hidden" name="fue_servido" value="0">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
        // Inyecta esta función dentro de la etiqueta <script> al final de views/tomar_pedido.php:

        function ajustarComensales(cambio) {
            const label = document.getElementById('label-num-personas');
            if (!label) return;

            let valorActual = parseInt(label.innerText) || 1;
            let nuevoValor = valorActual + cambio;

            // Validación de cortesía: mínimo debe haber 1 persona sentada a la mesa
            if (nuevoValor < 1) nuevoValor = 1;

            // Actualizamos el número visualmente en la pantalla de la tablet al instante
            label.innerText = nuevoValor;

            // Enviamos la actualización al backend en segundo plano sin refrescar la vista
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
                .catch(error => console.error("Error de red:", error));

        }



        document.addEventListener("DOMContentLoaded", function() {
            // Leemos si el mesero tenía una categoría seleccionada antes de que parpadeara la página
            const categoriaGuardada = localStorage.getItem('jungle_pizza_categoria_activa');

            // Localizamos el combo desplegable de tu menú
            const selectCategoria = document.querySelector('.category-select-wrapper select');

            if (categoriaGuardada !== null && selectCategoria) {
                const catId = parseInt(categoriaGuardada);

                // Colocamos el desplegable exactamente donde lo dejó el mesero
                selectCategoria.value = catId;

                // Ejecutamos tu función nativa de filtrado para que la cuadrícula oculte lo demás
                filtrarCatalogoArea(catId, selectCategoria);
            }
        });
    </script>
    <script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>

</html>