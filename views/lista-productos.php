<?php
// views/lista-productos.php

// 1. Control e instancias seguras del catálogo
require_once __DIR__ . '/../controllers/ProductoController.php';
$controller = new ProductoController();
$modeloInterno = new ProductoModelo();

// 2. Captura y purificación de filtros desde la URL (GET)
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$categoria_filtro_id = isset($_GET['categoria_id']) ? intval($_GET['categoria_id']) : 0;

// 3. MATEMÁTICA DE PAGINACIÓN INDUSTRIAL
$por_pagina = 10; // Cantidad fija de pizzas/bebidas a renderizar por pantalla en la tablet
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
if ($pagina_actual < 1) $pagina_actual = 1;

// Obtenemos los totales según lo que escriba el usuario para saber cuántas páginas dibujar
$total_registros = $modeloInterno->contarProductosFiltrados($buscar, $categoria_filtro_id);
$total_paginas = ceil($total_registros / $por_pagina);
if ($total_paginas < 1) $total_paginas = 1;
if ($pagina_actual > $total_paginas) $pagina_actual = $total_paginas;

// Cálculo del Offset: Indica a MySQL a partir de qué fila empezar a leer
$offset = ($pagina_actual - 1) * $por_pagina;

// Ejecutamos la consulta con límites estrictos de rendimiento
$lista_productos = $modeloInterno->listarProductosPaginados($buscar, $categoria_filtro_id, $por_pagina, $offset);
$todas_categorias = $controller->obtenerCategorias(); // Para rellenar el combo del filtro

// 4. Sincronización automática de URL_BASE (Local y Hostinger)
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
    <title>Catálogo Maestro - Jungle Pizza</title>
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/productos.css?v=4">

</head>

<body>
    <header class="mobile-header">
        <button class="hamburger-btn" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <div class="mobile-logo">🍕🍕 Jungle Dash</div>
    </header>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>

        <main class="main-content">
            <h2>Catálogo General de Productos</h2>
            <p style="color: #666; margin-bottom: 20px;">Supervisa, busca y administra todos los insumos y alimentos del local.</p>

            <?php if ($msg_error): ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
            <?php if ($msg_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div><?php endif; ?>

            <!-- 🛠️ BARRA DE HERRAMIENTAS SUPERIOR: BUSCADOR Y FILTROS TÁCTILES -->
            <div class="table-toolbar">
                <!-- Formulario GET para inyectar los filtros directo en la URL de forma limpia -->
                <form action="index.php" method="GET" class="filter-group">
                    <input type="hidden" name="v" value="mantenimiento_productos">

                    <input type="text" name="buscar" class="form-control" style="max-width: 300px;" value="<?php echo htmlspecialchars($buscar); ?>" placeholder="Buscar por nombre...">

                    <select name="categoria_id" class="form-control" style="max-width: 220px;" onchange="this.form.submit()">
                        <option value="0">-- Todas las Categorías --</option>
                        <?php foreach ($todas_categorias as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($categoria_filtro_id === (int)$c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn-action" style="background: var(--verde-oscuro);">🔍 Filtrar</button>
                    <?php if (!empty($buscar) || $categoria_filtro_id > 0): ?>
                        <a href="index.php?v=mantenimiento_productos" class="btn-action" style="background: #666; text-decoration: none; display: inline-flex; align-items: center;">❌ Limpiar</a>
                    <?php endif; ?>
                </form>

                <!-- Botón Flotante para Redirigir al Formulario de Inserción -->
                <a href="index.php?v=mantenimiento_productos_nuevo" class="btn-nuevo-prod">
                    ➕ Nuevo Producto
                </a>
            </div>
            <!-- 📋 TABLA MAESTRA DEL CATÁLOGO CON RENDIMIENTO PAGINADO -->
            <div class="product-card" style="margin-top: 10px;">
                <div class="table-responsive">
                    <table class="jungle-table">
                        <thead>
                            <tr>
                                <th>Imagen</th>
                                <th>Detalles Producto</th>
                                <th>Costo / PVP</th>
                                <th>Estado Stock</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lista_productos)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #999; padding: 25px;">
                                        No se encontraron productos con los criterios de búsqueda seleccionados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lista_productos as $p): ?>
                                    <tr>
                                        <!-- Miniatura Visual de la Pizza, Entrada o Bebida -->
                                        <td>
                                            <?php if (!empty($p['imagen']) && file_exists(__DIR__ . '/../public/uploads/productos/' . $p['imagen'])): ?>
                                                <img src="<?php echo URL_BASE; ?>public/uploads/productos/<?php echo $p['imagen']; ?>" class="img-menu-thumbnail" style="width:50px; height:50px; margin:0;" alt="Foto">
                                            <?php else: ?>
                                                <div style="width:50px; height:50px; background:#e2e8f0; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:18px;">🍕🍕</div>
                                            <?php endif; ?>
                                        </td>

                                       <!-- 🚀 REEMPLÁZALA EXACTAMENTE POR ESTA MAQUETA CON LOS BADGES DE PRODUCCIÓN: -->
<td>
    <strong style="color:var(--verde-oscuro); display:block; font-size:15px; margin-bottom: 5px;"><?php echo htmlspecialchars($p['nombre']); ?></strong>
    
    <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
        <!-- Badge 1: Clasificación Comercial (Tu código original intacto) -->
        <span style="font-size:11px; text-transform:uppercase; font-weight:bold; color:#777; background:#f1f5f9; padding:2px 6px; border-radius:4px;">
            <?php echo htmlspecialchars($p['nombre_categoria']); ?>
        </span>

        <!-- 🌟 Badge 2: Área de Producción Directa (Destino KDS) -->
        <?php 
        // Inicializamos variables de cortesía con cocina por defecto para proteger el historial
        $badge_color = "#2b8a3e"; 
        $badge_bg = "#ebfbee"; 
        $badge_text = "🍳 Cocina";
        
        if (isset($p['area_produccion']) && !empty(trim($p['area_produccion']))) {
            switch (trim($p['area_produccion'])) {
                case 'horno':
                    $badge_bg = "#fff4e6"; $badge_color = "#d9480f"; $badge_text = "🔥 Horno";
                    break;
                case 'cocina':
                    $badge_bg = "#ebfbee"; $badge_color = "#2b8a3e"; $badge_text = "🍳 Cocina";
                    break;
                case 'bar':
                    $badge_bg = "#e3f2fd"; $badge_color = "#0d47a1"; $badge_text = "🍹 Bar";
                    break;
                case 'despacho':
                    $badge_bg = "#f3f0ff"; $badge_color = "#5f3dc4"; $badge_text = "📦 Despacho";
                    break;
            }
        }
        ?>
        <span style="font-size:11px; text-transform:uppercase; font-weight:bold; color: <?php echo $badge_color; ?>; background: <?php echo $badge_bg; ?>; padding:2px 6px; border-radius:4px; display: inline-flex; align-items: center;">
            <?php echo $badge_text; ?>
        </span>
    </div>

                                        <!-- Desglose de Precios (Costo vs Venta) -->
                                        <td>
                                            <span style="font-size:12px; color:#777; display:block;">Costo: C$ <?php echo number_format($p['precio_costo'], 2); ?></span>
                                            <strong style="color:var(--naranja-pizza); font-size:14px;">Venta: C$ <?php echo number_format($p['precio_base'], 2); ?></strong>
                                        </td>

                                        <!-- Estado Físico del Inventario en Cocina -->
                                        <td>
                                            <?php if ((int)$p['maneja_stock'] === 0): ?>
                                                <span class="stock-badge stock-infinito">⏰ Al Instante</span>
                                            <?php else: ?>
                                                <?php if (floatval($p['stock_actual']) <= floatval($p['stock_minimo'])): ?>
                                                    <span class="stock-badge stock-bajo">⚠️ <?php echo floatval($p['stock_actual']); ?> <?php echo htmlspecialchars($p['unidad_medida']); ?></span>
                                                <?php else: ?>
                                                    <span class="stock-badge stock-ok">✅ <?php echo floatval($p['stock_actual']); ?> <?php echo htmlspecialchars($p['unidad_medida']); ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <!-- Indicadores Operativos Complementarios -->
                                            <?php if ((int)$p['es_extra'] === 1): ?><span style="display:inline-block; font-size:9px; background:#fff3cd; color:#856404; padding:1px 4px; border-radius:3px; margin-left:2px; font-weight:bold;">EXTRA</span><?php endif; ?>
                                            <?php if ((int)$p['es_sabor_pizza'] === 1): ?><span style="display:inline-block; font-size:9px; background:#e3f2fd; color:#0d47a1; padding:1px 4px; border-radius:3px; margin-left:2px; font-weight:bold;">MIXTO</span><?php endif; ?>
                                        </td>

                                        <!-- REEMPLAZA TUS DOS BOTONES EN views/lista-productos.php POR ESTOS: -->
                                        <td style="white-space: nowrap;">
                                            <!-- Botón Editar limpio (Saca los estilos inline al CSS) -->
                                            <a href="index.php?v=mantenimiento_productos_nuevo&edit_id=<?php echo $p['id']; ?>" class="btn-action btn-edit">Editar</a>

                                            <!-- MODIFICADO: Se inyecta la clase 'btn-delete' y se remueven los estilos inline que te forzaban las letras azules -->
                                            <a href="<?php echo URL_BASE; ?>controllers/ProductoController.php?action=eliminar_producto&del_id=<?php echo $p['id']; ?>"
                                                class="btn-action btn-delete"
                                                onclick="return confirm('¿Estás seguro de eliminar este producto del menú?');">
                                                Eliminar
                                            </a>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 🌟 BOTONERA DINÁMICA DE PAGINACIÓN DIGITAL -->
                <?php if ($total_paginas > 1): ?>
                    <div class="pagination-container">
                        <!-- Botón de Página Anterior -->
                        <?php if ($pagina_actual > 1): ?>
                            <a href="index.php?v=mantenimiento_productos&pagina=<?php echo $pagina_actual - 1; ?>&buscar=<?php echo urlencode($buscar); ?>&categoria_id=<?php echo $categoria_filtro_id; ?>" class="page-number-link">&laquo;</a>
                        <?php endif; ?>

                        <!-- Iteración de Botones Numéricos -->
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <a href="index.php?v=mantenimiento_productos&pagina=<?php echo $i; ?>&buscar=<?php echo urlencode($buscar); ?>&categoria_id=<?php echo $categoria_filtro_id; ?>"
                                class="page-number-link <?php echo ($pagina_actual === $i) ? 'page-active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Botón de Página Siguiente -->
                        <?php if ($pagina_actual < $total_paginas): ?>
                            <a href="index.php?v=mantenimiento_productos&pagina=<?php echo $pagina_actual + 1; ?>&buscar=<?php echo urlencode($buscar); ?>&categoria_id=<?php echo $categoria_filtro_id; ?>" class="page-number-link">&raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>

</html>