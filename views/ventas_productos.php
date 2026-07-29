<?php
// views/ventas_productos.php (Parte 1 de 2)
// 1. Requerimos las instancias del modelo para procesar la analítica de cocina
require_once __DIR__ . '/../models/CobranzaModelo.php';
$cobranzaModel = new CobranzaModelo();

// 2. Captura y sanitización de rangos de fechas por URL (GET)
$fecha_desde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';

// 3. Configuración matemática de paginación industrial (Bloques estrictos de 10)
$por_pagina = 10; 
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
if ($pagina_actual < 1) $pagina_actual = 1;

// Obtenemos los totales basados en los filtros aplicados para la botonera numérica
$total_registros = $cobranzaModel->contarProductosVendidosHistorial($fecha_desde, $fecha_hasta);
$total_paginas   = ceil($total_registros / $por_pagina);
if ($total_paginas < 1) $total_paginas = 1;
if ($pagina_actual > $total_paginas) $pagina_actual = $total_paginas;

$offset = ($pagina_actual - 1) * $por_pagina;

// Ejecutamos el ranking de productos vendidos paginado directo en MySQL
$ranking_productos = $cobranzaModel->listarProductosVendidosHistorial($fecha_desde, $fecha_hasta, $por_pagina, $offset);

// 4. Sincronización corporativa de URL_BASE
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
if (!defined('URL_BASE')) {
    define('URL_BASE', ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ventas por Producto - Jungle Pizza</title>
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    <style>
        .reporte-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(27,67,50,0.05); padding: 25px; border-top: 4px solid var(--verde-claro, #52b788); margin-top: 20px; }
        .reporte-card h3 { color: var(--verde-oscuro, #1b4332); font-size: 1.25rem; margin-bottom: 15px; border-bottom: 2px solid var(--verde-menta, #d8f3dc); padding-bottom: 8px; }
        .toolbar-reporte { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .form-group-rep { display: flex; flex-direction: column; gap: 4px; }
        .form-group-rep label { font-size: 11px; font-weight: bold; color: var(--verde-oscuro); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control-rep { padding: 10px; border: 2px solid #cbd5e1; border-radius: 6px; font-size: 14px; font-weight: bold; background: #fff; }
        .form-control-rep:focus { outline: none; border-color: var(--verde-claro); }
        .table-responsive { width: 100%; overflow-x: auto; border-radius: 8px; border: 1px solid #edf2f7; }
        .jungle-table { width: 100%; border-collapse: collapse; text-align: left; min-width: 650px; }
        .jungle-table th { background-color: var(--verde-oscuro, #1b4332); color: #ffffff; padding: 12px 15px; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .jungle-table td { padding: 12px 15px; border-bottom: 1px solid #edf2f7; font-size: 0.95rem; vertical-align: middle; }
        .jungle-table tr:hover { background-color: rgba(216, 243, 220, 0.2); }
        .btn-rep-submit { background-color: var(--verde-selva, #2d6a4f); color: #fff; border: none; padding: 11px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; text-transform: uppercase; font-size: 13px; }
        .btn-rep-submit:hover { background-color: var(--verde-oscuro); }
        .pagination-jungle { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .page-jungle-link { padding: 8px 12px; background: #fff; border: 2px solid #e2e8f0; border-radius: 6px; color: #333; text-decoration: none; font-weight: bold; font-family: monospace; }
        .page-jungle-link.active { background: var(--verde-claro); color: #fff; border-color: var(--verde-claro); }
    </style>
</head>
<body>

<header class="mobile-header">
    <button class="hamburger-btn" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
    <div class="mobile-logo">🍕🍕🍕🍕 Jungle Dash</div>
</header>

<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="dashboard-layout">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <h2>Reporte Analítico de Ventas por Producto</h2>
        <p style="color: #666; margin-bottom: 20px;">Monitorea el volumen de salida de insumos y alimentos en cocina, evalúa los platillos estrella y analiza la rentabilidad neta.</p>

        <div class="reporte-card">
            <h3>📊 Filtrar por Periodo</h3>
            
            <form action="index.php" method="GET" class="toolbar-reporte">
                <input type="hidden" name="v" value="ventas_productos">
                
                <div class="form-group-rep">
                    <label>Fecha Desde</label>
                    <input type="date" name="fecha_desde" class="form-control-rep" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                </div>
                
                <div class="form-group-rep">
                    <label>Fecha Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control-rep" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                </div>
                
                <button type="submit" class="btn-rep-submit">📊 Generar Reporte</button>
                <?php if(!empty($fecha_desde) || !empty($fecha_hasta)): ?>
                    <a href="index.php?v=ventas_productos" class="page-jungle-link" style="padding:11px 15px; text-decoration:none; background:#64748b; color:#fff; border-color:#64748b; border-radius:6px; font-size:13px; text-transform:uppercase;">❌ Limpiar</a>
                <?php endif; ?>
            </form>
            <!-- PARTE 2: TABLA DE RANKING DE VENTAS Y PAGINACIÓN COMPACTA -->
            <div class="table-responsive" style="margin-top: 20px;">
                <table class="jungle-table">
                    <thead>
                        <tr>
                            <th style="width: 120px; text-align: center;">Código Prod</th>
                            <th>Descripción del Alimento / Producto</th>
                            <th style="text-align: right; width: 180px;">Volumen Total Vendido</th>
                            <th style="text-align: right; width: 200px;">Total Recaudado Bruto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ranking_productos)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #999; padding: 35px; font-size: 15px;">
                                    🍕 No se registran ventas de productos en el rango de fechas seleccionado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $gran_total_periodo = 0;
                            foreach ($ranking_productos as $row): 
                                $gran_total_periodo += floatval($row['total_recaudado']);
                            ?>
                                <tr>
                                    <td style="text-align: center;"><code>#<?php echo $row['producto_id']; ?></code></td>
                                    <td>
                                        <strong style="color: var(--verde-oscuro, #1b4332); font-size: 15px;">
                                            <?php echo htmlspecialchars($row['producto_nombre']); ?>
                                        </strong>
                                    </td>
                                    <td style="text-align: right; font-weight: bold; font-family: monospace; color: #475569; font-size: 15px;">
                                        <?php echo floatval($row['total_unidades']); ?> <?php echo htmlspecialchars($row['unidad_medida'] ?? 'Und'); ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 800; font-family: monospace; color: var(--verde-selva, #2d6a4f); font-size: 15px;">
                                        C$ <?php echo number_format($row['total_recaudado'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- RENGLÓN DE TOTALIZACIÓN GENERAL DEL BLOQUE -->
                            <tr style="background: #f1f5f9; font-weight: 900; border-top: 2px solid var(--verde-oscuro);">
                                <td colspan="3" style="text-align: right; text-transform: uppercase; letter-spacing: 0.5px; padding: 14px;">Total Neto Recaudado (Esta Página):</td>
                                <td style="text-align: right; font-family: monospace; color: #000; font-size: 16px; padding: 14px;">
                                    C$ <?php echo number_format($gran_total_periodo, 2); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 🎛️ BOTONERA DE PAGINACIÓN FLUIDA CON ARRASTRE DE CALENDARIOS (10 EN 10) -->
            <?php if ($total_paginas > 1): ?>
                <div class="pagination-jungle" style="display: flex; justify-content: center; gap: 5px; margin-top: 25px; width: 100%;">
                    
                    <!-- Enlace Atrás -->
                    <?php if ($pagina_actual > 1): ?>
                        <a href="index.php?v=ventas_productos&pagina=<?php echo $pagina_actual - 1; ?>&fecha_desde=<?php echo urlencode($fecha_desde); ?>&fecha_hasta=<?php echo urlencode($fecha_hasta); ?>" class="page-jungle-link" style="padding: 8px 12px; background: #fff; border: 2px solid #e2e8f0; border-radius: 6px; color: #333; text-decoration: none; font-weight: bold; font-family: monospace;">&laquo;</a>
                    <?php endif; ?>

                    <!-- Números Centrales -->
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <a href="index.php?v=ventas_productos&pagina=<?php echo $i; ?>&fecha_desde=<?php echo urlencode($fecha_desde); ?>&fecha_hasta=<?php echo urlencode($fecha_hasta); ?>" 
                           class="page-jungle-link"
                           style="padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 6px; text-decoration: none; font-weight: bold; font-family: monospace; <?php echo ($pagina_actual === $i) ? 'background: var(--verde-claro, #52b788); color: #fff; border-color: var(--verde-claro, #52b788);' : 'background: #fff; color: #333;'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Enlace Siguiente -->
                    <?php if ($pagina_actual < $total_paginas): ?>
                        <a href="index.php?v=ventas_productos&pagina=<?php echo $pagina_actual + 1; ?>&fecha_desde=<?php echo urlencode($fecha_desde); ?>&fecha_hasta=<?php echo urlencode($fecha_hasta); ?>" class="page-jungle-link" style="padding: 8px 12px; background: #fff; border: 2px solid #e2e8f0; border-radius: 6px; color: #333; text-decoration: none; font-weight: bold; font-family: monospace;">&raquo;</a>
                    <?php endif; ?>
                    
                </div>
            <?php endif; ?>

        </div> <!-- Fin de reporte-card -->
    </main>
</div> <!-- Fin de dashboard-layout -->

<script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>
</html>
