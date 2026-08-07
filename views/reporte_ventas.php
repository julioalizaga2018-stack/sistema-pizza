<?php
// views/reporte_ventas.php (Versión con Paginación Avanzada para Jungle Pizza)
require_once __DIR__ . '/../controllers/PedidoController.php';
$pedidoCtrl = new PedidoController();

// Capturamos el número de página que viaja por la URL (Ej: index.php?v=reporte_ventas&p=2)
$pagina_solicitada = intval($_GET['p'] ?? 1);
$registros_por_pagina = 10;

// Invocamos el nuevo método paginado del controlador
$resultadoPaginado = $pedidoCtrl->obtenerReporteDiarioPaginado($pagina_solicitada, $registros_por_pagina);

$reporteDiario = $resultadoPaginado['data'];
$pagina_actual  = $resultadoPaginado['pagina_actual'];
$total_paginas  = $resultadoPaginado['total_paginas'];

// 🌟 KPIs ACUMULADOS: Para que las tarjetas informativas muestren el balance GLOBAL real de la base de datos
$dbKPI = (new Conexion())->conectar();
$stmtKPI = $dbKPI->query("SELECT COUNT(id) as total_p, SUM(total) as total_v, SUM(monto_propina) as total_pr 
                         FROM pedidos WHERE estado = 'entregado'");
$kpisGlobales = $stmtKPI->fetch(PDO::FETCH_ASSOC);

$acumulado_pedidos  = intval($kpisGlobales['total_p'] ?? 0);
$acumulado_ventas   = floatval($kpisGlobales['total_v'] ?? 0.00);
$acumulado_propinas = floatval($kpisGlobales['total_pr'] ?? 0.00);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Resumen de Ventas - Jungle Pizza</title>
<link rel="stylesheet" href="public/css/base.css">
<link rel="stylesheet" href="public/css/estilos.css">
<style>
.report-grid { display: flex; flex-direction: column; gap: 20px; margin-top: 15px; }
.kpi-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
.kpi-card { background: #ffffff; border-radius: 10px; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; text-align: center; }
.kpi-val { font-size: 22px; font-weight: 800; color: #1b4332; margin-top: 5px; }
.kpi-title { font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase; }
.table-responsive { width: 100%; overflow-x: auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.report-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14.5px; }
.report-table th { background: #f8fafc; padding: 12px; font-weight: 700; color: #334155; border-bottom: 2px solid #e2e8f0; }
.report-table td { padding: 12px; border-bottom: 1px solid #edf2f7; color: #475569; font-weight: 600; }
.report-table tr:hover { background: #f8fafc; }
.badge-count { background: #e0f2fe; color: #0369a1; padding: 4px 8px; border-radius: 6px; font-weight: 800; font-family: monospace; }

/* 🎛️ ESTILOS PARA LA BOTONERA DE PAGINACIÓN */
.pagination-container { display: flex; justify-content: center; align-items: center; gap: 5px; margin-top: 20px; margin-bottom: 10px; }
.page-link-btn { text-decoration: none; background: #ffffff; color: #334155; border: 1px solid #cbd5e1; padding: 8px 14px; font-size: 13.5px; font-weight: 700; border-radius: 6px; transition: all 0.1s ease; }
.page-link-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
.page-link-btn.active-page { background: #1b4332; color: #ffffff; border-color: #1b4332; cursor: default; }
.page-link-btn.disabled-btn { background: #f8fafc; color: #94a3b8; border-color: #e2e8f0; cursor: not-allowed; pointer-events: none; }
</style>
</head>
<body>
<div class="dashboard-layout">
<?php include 'sidebar.php'; ?>
<main class="main-content" style="padding: 20px; box-sizing: border-box; background: #f8fafc; min-height: 100vh;">
    
    <div style="margin-bottom: 20px;">
        <h2 style="margin: 0; color: #1b4332; font-weight: 800; font-size: 24px;">📊 Reporte de Cierre Financiero Diario</h2>
        <p style="margin: 4px 0; color: #64748b; font-size: 13.5px;">Balances consolidados e ingresos segmentados por páginas de alta velocidad.</p>
    </div>

    <!-- TARJETAS DE ACUMULADOS GLOBALES (KPIs) -->
    <div class="kpi-container">
        <div class="kpi-card" style="border-left: 5px solid #2b8a3e;">
            <div class="kpi-title">Pedidos Totales Históricos</div>
            <div class="kpi-val"><?php echo $acumulado_pedidos; ?> Órdenes</div>
        </div>
        <div class="kpi-card" style="border-left: 5px solid #0284c7;">
            <div class="kpi-title">Recaudación Global Neta</div>
            <div class="kpi-val">C$ <?php echo number_format($acumulado_ventas, 2); ?></div>
        </div>
        <div class="kpi-card" style="border-left: 5px solid #e67e22;">
            <div class="kpi-title font-bold">Total Propinas Recaudadas</div>
            <div class="kpi-val" style="color: #e67e22;">C$ <?php echo number_format($acumulado_propinas, 2); ?></div>
        </div>
    </div>

    <div class="report-grid">
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>📅 Fecha de Operación</th>
                        <th>📦 Cant. Pedidos</th>
                        <th>🧀 Descuentos</th>
                        <th>🛵 Delivery</th>
                        <th>🍹 Propina Recaudada</th>
                        <th style="text-align: right; color: #1b4332;">💰 Total Caja (C$)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reporteDiario)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8; font-style: italic;">No hay registros de ventas facturadas en este bloque.</td>
                        </tr>
                    <?php else: foreach ($reporteDiario as $row): ?>
                        <tr>
                            <td><strong><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></strong></td>
                            <td><span class="badge-count"><?php echo $row['total_pedidos']; ?> Órdenes</span></td>
                            <td style="color: #c92a2a;">- C$ <?php echo number_format($row['total_descuentos'], 2); ?></td>
                            <td>C$ <?php echo number_format($row['total_delivery'], 2); ?></td>
                            <td style="color: #2563eb; font-weight: 700;">C$ <?php echo number_format($row['total_propinas'], 2); ?></td>
                            <td style="text-align: right; font-weight: 800; color: #1b4332; font-family: monospace; font-size: 15px;">C$ <?php echo number_format($row['ingresos_totales'], 2); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 🎛️ BOTONERA DE PAGINACIÓN ADAPTATIVA JUNGLE POS -->
        <?php if ($total_paginas > 1): ?>
        <div class="pagination-container">
            <!-- Botón de Página Anterior -->
            <a href="index.php?v=reporte_ventas&p=<?php echo ($pagina_actual - 1); ?>" 
               class="page-link-btn <?php echo ($pagina_actual <= 1) ? 'disabled-btn' : ''; ?>">
               « Ant
            </a>

            <!-- Números Secuenciales de Páginas -->
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <a href="index.php?v=reporte_ventas&p=<?php echo $i; ?>" 
                   class="page-link-btn <?php echo ($pagina_actual === $i) ? 'active-page' : ''; ?>">
                   <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <!-- Botón de Página Siguiente -->
            <a href="index.php?v=reporte_ventas&p=<?php echo ($pagina_actual + 1); ?>" 
               class="page-link-btn <?php echo ($pagina_actual >= $total_paginas) ? 'disabled-btn' : ''; ?>">
               Sig »
            </a>
        </div>
        <div style="text-align: center; font-size: 12px; color: #64748b; font-weight: bold; margin-top: -5px;">
            Mostrando página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?> bloques de historial
        </div>
        <?php endif; ?>
    </div>
</main>
</div>
</body>
</html>
