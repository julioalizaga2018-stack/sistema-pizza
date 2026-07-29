<?php
// views/reportes_mensuales.php (Parte 1 de 2)
require_once __DIR__ . '/../models/CompraModelo.php';
$reporteModel = new CompraModelo();

// 1. Capturamos el año a auditar desde la URL, por defecto toma el año actual (2026)
$anio_actual = date('Y');
$anio_filtrar = isset($_GET['anio']) ? intval($_GET['anio']) : intval($anio_actual);
if ($anio_filtrar < 2020) $anio_filtrar = intval($anio_actual);

// 2. Ejecutamos las consultas estadísticas unificadas en tu CompraModelo
$ventas_data  = $reporteModel->obtenerVentasMensualesHistorial($anio_filtrar);
$compras_data = $reporteModel->obtenerComprasMensualesHistorial($anio_filtrar);

// 3. Mapeamos las colecciones en un único array indexado por el número de mes para cruzar balances
$balance_mensual = [];
for ($m = 1; $m <= 12; $m++) {
    $balance_mensual[$m] = [
        'ventas_total'   => 0.00,
        'ventas_cant'    => 0,
        'compras_total'  => 0.00,
        'compras_cant'   => 0
    ];
}

// Inyectamos los montos reales de comandas cobradas entregadas
foreach ($ventas_data as $v) {
    $mes = (int)$v['mes_numero'];
    $balance_mensual[$mes]['ventas_total'] = floatval($v['total_ventas_mes']);
    $balance_mensual[$mes]['ventas_cant']  = (int)$v['transacciones_ventas'];
}

// Inyectamos los montos reales de facturas de compras a proveedores
foreach ($compras_data as $c) {
    $mes = (int)$c['mes_numero'];
    $balance_mensual[$mes]['compras_total'] = floatval($c['total_compras_mes']);
    $balance_mensual[$mes]['compras_cant']  = (int)$c['transacciones_compras'];
}

// Array auxiliar estático para traducir los meses en la pizzería de forma limpia
$nombres_meses = [
    1 => "Enero", 2 => "Febrero", 3 => "Marzo", 4 => "Abril", 5 => "Mayo", 6 => "Junio",
    7 => "Julio", 8 => "Agosto", 9 => "Septiembre", 10 => "Octubre", 11 => "Noviembre", 12 => "Diciembre"
];

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
    <title>Balance Comercial Mensual - Jungle Pizza</title>
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    <style>
        .balance-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(27,67,50,0.05); padding: 25px; border-top: 4px solid var(--verde-claro, #52b788); margin-top: 20px; }
        .balance-card h3 { color: var(--verde-oscuro, #1b4332); font-size: 1.25rem; margin-bottom: 15px; border-bottom: 2px solid var(--verde-menta, #d8f3dc); padding-bottom: 8px; }
        .toolbar-balance { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .form-group-bal { display: flex; flex-direction: column; gap: 4px; }
        .form-group-bal label { font-size: 11px; font-weight: bold; color: var(--verde-oscuro); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control-bal { padding: 10px; border: 2px solid #cbd5e1; border-radius: 6px; font-size: 14px; font-weight: bold; background: #fff; width: 140px; }
        .table-responsive { width: 100%; overflow-x: auto; border-radius: 8px; border: 1px solid #edf2f7; }
        .jungle-table { width: 100%; border-collapse: collapse; text-align: left; min-width: 800px; }
        .jungle-table th { background-color: var(--verde-oscuro, #1b4332); color: #ffffff; padding: 12px 15px; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .jungle-table td { padding: 12px 15px; border-bottom: 1px solid #edf2f7; font-size: 0.95rem; vertical-align: middle; }
        .jungle-table tr:hover { background-color: rgba(216, 243, 220, 0.2); }
        .btn-bal-submit { background-color: var(--verde-selva, #2d6a4f); color: #fff; border: none; padding: 11px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; text-transform: uppercase; font-size: 13px; }
        .badge-utilidad { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .utilidad-positiva { background-color: #ebfbee; color: #2b8a3e; }
        .utilidad-negativa { background-color: #fff5f5; color: #c92a2a; }
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
        <h2>Reporte Anual de Rendimiento Neto Mensual</h2>
        <p style="color: #666; margin-bottom: 20px;">Audita las fluctuaciones comerciales del restaurante, compara ingresos contra compras de materias primas y evalúa la utilidad real del ejercicio fiscal.</p>

        <div class="balance-card">
            <h3>📅 Seleccionar Ejercicio Comercial</h3>
            
            <form action="index.php" method="GET" class="toolbar-balance">
                <input type="hidden" name="v" value="reportes_mensuales">
                
                <div class="form-group-bal">
                    <label>Año de Auditoría</label>
                    <select name="anio" class="form-control-bal" style="font-family: inherit;">
                        <?php for($y = intval($anio_actual); $y >= 2022; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo ($anio_filtrar === $y) ? 'selected' : ''; ?>>Año <?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-bal-submit">📊 Cruzar Datos Balance</button>
            </form>
            <!-- PARTE 2: TABLA DE BALANCE ANUAL CRUZADO Y SEMÁFORO DE UTILIDADES -->
            <div class="table-responsive" style="margin-top: 20px;">
                <table class="jungle-table">
                    <thead>
                        <tr>
                            <th>Mes Auditado</th>
                            <th style="text-align: right; width: 140px;">Cant. Órdenes</th>
                            <th style="text-align: right; width: 180px;">Ingresos Ventas</th>
                            <th style="text-align: right; width: 140px;">Cant. Compras</th>
                            <th style="text-align: right; width: 180px;">Egresos Compras</th>
                            <th style="text-align: right; width: 200px;">Balance / Margen Neto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Inicializamos acumuladores globales para el cierre del año fiscal
                        $acumulado_ventas_anio  = 0;
                        $acumulado_compras_anio = 0;
                        $cant_ventas_anio       = 0;
                        $cant_compras_anio      = 0;

                        // Iteramos de Diciembre a Enero para ver los meses más recientes primero
                        for ($m = 12; $m >= 1; $m--): 
                            $v_total = $balance_mensual[$m]['ventas_total'];
                            $v_cant  = $balance_mensual[$m]['ventas_cant'];
                            $c_total = $balance_mensual[$m]['compras_total'];
                            $c_cant  = $balance_mensual[$m]['compras_cant'];

                            // Calculamos el margen del mes (Utilidad = Ingresos - Gastos)
                            $utilidad_mes = $v_total - $c_total;

                            // Sumamos al balance anual
                            $acumulado_ventas_anio  += $v_total;
                            $acumulado_compras_anio += $c_total;
                            $cant_ventas_anio       += $v_cant;
                            $cant_compras_anio      += $c_cant;
                        ?>
                            <tr>
                                <td>
                                    <strong style="color: var(--verde-oscuro, #1b4332); font-size: 15px;">
                                        <?php echo $nombres_meses[$m]; ?>
                                    </strong>
                                </td>
                                <td style="text-align: right; font-family: monospace; color: #64748b;">
                                    <?php echo $v_cant; ?> ord
                                </td>
                                <td style="text-align: right; font-weight: bold; font-family: monospace; color: #0d47a1;">
                                    C$ <?php echo number_format($v_total, 2); ?>
                                </td>
                                <td style="text-align: right; font-family: monospace; color: #64748b;">
                                    <?php echo $c_cant; ?> fac
                                </td>
                                <td style="text-align: right; font-weight: bold; font-family: monospace; color: #c92a2a;">
                                    C$ <?php echo number_format($c_total, 2); ?>
                                </td>
                                <td style="text-align: right;">
                                    <!-- 🚨 SEMÁFORO DE UTILIDAD EN CALIENTE -->
                                    <?php if ($utilidad_mes >= 0): ?>
                                        <span class="badge-utilidad utilidad-positiva" style="font-family: monospace;">
                                            📈 +C$ <?php echo number_format($utilidad_mes, 2); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-utilidad utilidad-negativa" style="font-family: monospace;">
                                            📉 -C$ <?php echo number_format(abs($utilidad_mes), 2); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endfor; ?>

                        <!-- 📊 RENGLÓN DE CLAUSURA DE BALANCE ANUAL TOTAL -->
                        <tr style="background: #1e293b; color: #ffffff; font-weight: bold; border-top: 3px solid #000;">
                            <td style="padding: 15px; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px;">
                                TOTAL EJERCICIO <?php echo $anio_filtrar; ?>:
                            </td>
                            <td style="text-align: right; font-family: monospace; color: #cbd5e1;">
                                <?php echo $cant_ventas_anio; ?> ord
                            </td>
                            <td style="text-align: right; font-family: monospace; color: #60a5fa; font-size: 15px;">
                                C$ <?php echo number_format($acumulado_ventas_anio, 2); ?>
                            </td>
                            <td style="text-align: right; font-family: monospace; color: #cbd5e1;">
                                <?php echo $cant_compras_anio; ?> fac
                            </td>
                            <td style="text-align: right; font-family: monospace; color: #f87171; font-size: 15px;">
                                C$ <?php echo number_format($acumulado_compras_anio, 2); ?>
                            </td>
                            <td style="text-align: right; padding: 12px 15px;">
                                <?php 
                                $utilidad_anual_neta = $acumulado_ventas_anio - $acumulado_compras_anio;
                                if ($utilidad_anual_neta >= 0): 
                                ?>
                                    <span style="background: #22c55e; color: #052e16; padding: 5px 10px; border-radius: 6px; font-family: monospace; font-size: 14px;">
                                        👑 BENEFICIO: C$ <?php echo number_format($utilidad_anual_neta, 2); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="background: #ef4444; color: #450a0a; padding: 5px 10px; border-radius: 6px; font-family: monospace; font-size: 14px;">
                                        🚨 DÉFICIT: -C$ <?php echo number_format(abs($utilidad_anual_neta), 2); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div> <!-- Fin de balance-card -->
    </main>
</div> <!-- Fin de dashboard-layout -->

<script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>
</html>
