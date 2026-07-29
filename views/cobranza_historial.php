<?php
// views/cobranza_historial.php (Parte 1 de 2)
// 1. Requerimos las instancias del modelo para procesar la auditoría
require_once __DIR__ . '/../models/CobranzaModelo.php';
$cobranzaModel = new CobranzaModelo();

// 2. Captura de filtros desde la URL (GET) para rango de fechas y número de orden
$fecha_desde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';
$num_pedido  = isset($_GET['num_pedido']) ? intval($_GET['num_pedido']) : 0;

// 3. Configuración matemática de paginación industrial (Idéntica a categorías y proveedores)
$por_pagina = 10; // Renglones de facturas a mostrar por pantalla
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
if ($pagina_actual < 1) $pagina_actual = 1;

// Obtenemos los totales basados en los filtros aplicados para la botonera numérica
$total_registros = $cobranzaModel->contarFacturasHistorial($fecha_desde, $fecha_hasta, $num_pedido);
$total_paginas   = ceil($total_registros / $por_pagina);
if ($total_paginas < 1) $total_paginas = 1;
if ($pagina_actual > $total_paginas) $pagina_actual = $total_paginas;

$offset = ($pagina_actual - 1) * $por_pagina;

// Ejecutamos la consulta paginada directo en MySQL para óptimo rendimiento
$lista_facturas = $cobranzaModel->listarFacturasHistorial($fecha_desde, $fecha_hasta, $num_pedido, $por_pagina, $offset);

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
    <title>Historial de Facturación - Jungle Pizza</title>
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    <style>
        .historial-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(27,67,50,0.05); padding: 25px; border-top: 4px solid var(--verde-claro, #52b788); margin-top: 20px; }
        .historial-card h3 { color: var(--verde-oscuro, #1b4332); font-size: 1.25rem; margin-bottom: 15px; border-bottom: 2px solid var(--verde-menta, #d8f3dc); padding-bottom: 8px; }
        .toolbar-busqueda { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .form-group-filter { display: flex; flex-direction: column; gap: 4px; }
        .form-group-filter label { font-size: 11px; font-weight: bold; color: var(--verde-oscuro); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control-filter { padding: 10px; border: 2px solid #cbd5e1; border-radius: 6px; font-size: 14px; font-weight: bold; background: #fff; }
        .form-control-filter:focus { outline: none; border-color: var(--verde-claro); }
        .table-responsive { width: 100%; overflow-x: auto; border-radius: 8px; border: 1px solid #edf2f7; }
        .jungle-table { width: 100%; border-collapse: collapse; text-align: left; min-width: 800px; }
        .jungle-table th { background-color: var(--verde-oscuro, #1b4332); color: #ffffff; padding: 12px 15px; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .jungle-table td { padding: 12px 15px; border-bottom: 1px solid #edf2f7; font-size: 0.95rem; vertical-align: middle; }
        .jungle-table tr:hover { background-color: rgba(216, 243, 220, 0.2); }
        .btn-print-re { background-color: var(--naranja-pizza, #e67e22) !important; color: #ffffff !important; padding: 8px 12px !important; font-size: 0.85rem !important; font-weight: bold !important; text-transform: uppercase !important; border-radius: 6px !important; text-decoration: none !important; border: none !important; cursor: pointer !important; transition: all 0.2s ease !important; display: inline-flex; align-items: center; gap: 5px; }
        .btn-print-re:hover { background-color: #d35400; transform: translateY(-1px); }
        .btn-filter-submit { background-color: var(--verde-selva, #2d6a4f); color: #fff; border: none; padding: 11px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; text-transform: uppercase; font-size: 13px; }
        .btn-filter-submit:hover { background-color: var(--verde-oscuro); }
        .badge-tipo { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
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
        <h2>Auditoría y Arqueo Histórico de Ventas</h2>
        <p style="color: #666;">Consulta el registro de comandas cerradas, analiza las recaudaciones pasadas y reimprime comprobantes de caja.</p>

        <div class="historial-card">
            <h3>🔍 Filtros de Búsqueda Avanzada</h3>
            
            <!-- FORMULARIO DE FILTRADO CON RANGOS Y NUMERO DE PEDIDO -->
            <form action="index.php" method="GET" class="toolbar-busqueda">
                <input type="hidden" name="v" value="cobranza_historial">
                
                <div class="form-group-filter">
                    <label>N° Factura / Pedido</label>
                    <input type="number" name="num_pedido" class="form-control-filter" style="width: 140px;" value="<?php echo $num_pedido > 0 ? $num_pedido : ''; ?>" placeholder="Ej. 73" onfocus="this.select();">
                </div>
                
                <div class="form-group-filter">
                    <label>Fecha Desde</label>
                    <input type="date" name="fecha_desde" class="form-control-filter" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                </div>
                
                <div class="form-group-filter">
                    <label>Fecha Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control-filter" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                </div>
                
                <button type="submit" class="btn-filter-submit">🔍 Aplicar Filtro</button>
                <?php if(!empty($fecha_desde) || !empty($fecha_hasta) || $num_pedido > 0): ?>
                    <a href="index.php?v=cobranza_historial" class="btn-print-re" style="background:#64748b; padding:11px 15px; text-decoration:none;">❌ Limpiar</a>
                <?php endif; ?>
            </form>
            <!-- PARTE 2: TABLA DE AUDITORÍA HISTÓRICA Y REIMPRESIÓN -->
            <div class="table-responsive" style="margin-top: 20px;">
                <table class="jungle-table">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Factura N°</th>
                            <th style="width: 160px;">Fecha / Hora</th>
                            <th>Ubicación / Modalidad</th>
                            <th style="text-align: right;">Total Neto</th>
                            <th style="text-align: right;">Propina Recabada</th>
                            <th style="text-align: right;">Descuento Otorgado</th>
                            <th style="text-align: center; width: 140px;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista_facturas)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #999; padding: 30px;">
                                    No se encontraron facturas registradas que coincidan con los filtros aplicados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lista_facturas as $fact): ?>
                                <tr>
                                    <td><code>#<?php echo $fact['id']; ?></code></td>
                                    <td style="font-family: monospace; font-size: 13px; color: #555;">
                                        <?php echo date('d/m/Y g:i a', strtotime($fact['created_at'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($fact['tipo_pedido'] === 'local'): ?>
                                            <span class="badge-tipo" style="background:#e3f2fd; color:#0d47a1;">🪑 Mesa <?php echo htmlspecialchars($fact['numero_mesa'] ?? 'N/A'); ?></span>
                                        <?php elseif ($fact['tipo_pedido'] === 'delivery'): ?>
                                            <span class="badge-tipo" style="background:#fff4e6; color:#d9480f;">🛵 Delivery</span>
                                        <?php else: ?>
                                            <span class="badge-tipo" style="background:#f3f0ff; color:#5f3dc4;">📦 Retiro / Llevar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; font-weight: bold; font-family: monospace; color: var(--verde-oscuro, #1b4332);">
                                        C$ <?php echo number_format($fact['total'], 2); ?>
                                    </td>
                                    <td style="text-align: right; font-family: monospace; color: #475569;">
                                        C$ <?php echo number_format($fact['monto_propina'], 2); ?>
                                    </td>
                                    <td style="text-align: right; font-family: monospace; color: #c92a2a;">
                                        -C$ <?php echo number_format($fact['monto_descuento'], 2); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <!-- 🖨️ DISPARADOR EN CALIENTE: Abre el ticket oficial en ventana limpia -->
                                        <button class="btn-print-re" onclick="reimprimirComprobante(<?php echo $fact['id']; ?>)">
                                            🖨️ Reimprimir
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 🎛️ BOTONERA DE PAGINACIÓN INDUSTRIAL NATIVA -->
            <?php if ($total_paginas > 1): ?>
                <div class="pagination-jungle">
                    <?php if ($pagina_actual > 1): ?>
                        <a href="index.php?v=cobranza_historial&pagina=<?php echo $pagina_actual - 1; ?>&fecha_desde=<?php echo urlencode($fecha_desde); ?>&fecha_hasta=<?php echo urlencode($fecha_hasta); ?>&num_pedido=<?php echo $num_pedido; ?>" class="page-jungle-link">&laquo;</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <a href="index.php?v=cobranza_historial&pagina=<?php echo $i; ?>&fecha_desde=<?php echo urlencode($fecha_desde); ?>&fecha_hasta=<?php echo urlencode($fecha_hasta); ?>&num_pedido=<?php echo $num_pedido; ?>" class="page-jungle-link <?php echo ($pagina_actual === $i) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($pagina_actual < $total_paginas): ?>
                        <a href="index.php?v=cobranza_historial&pagina=<?php echo $pagina_actual + 1; ?>&fecha_desde=<?php echo urlencode($fecha_desde); ?>&fecha_hasta=<?php echo urlencode($fecha_hasta); ?>&num_pedido=<?php echo $num_pedido; ?>" class="page-jungle-link">&raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div> <!-- Fin de historial-card -->
    </main>
</div> <!-- Fin de dashboard-layout -->

<!-- MOTOR DE REIMPRESIÓN CONTROLADO POR VENTANA EMERGENTE -->
<script>
function reimprimirComprobante(pedidoId) {
    if (pedidoId > 0) {
        // Invoca el interceptor superior que programamos en tu index.php de forma limpia
        window.open('index.php?v=imprimir_ticket&pedido_id=' + pedidoId, '_blank', 'width=400,height=650,scrollbars=yes,resizable=yes');
    }
}
</script>

<script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>
</html>
