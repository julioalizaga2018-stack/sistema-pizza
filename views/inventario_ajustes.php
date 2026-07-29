<?php
// views/inventario_ajustes.php
// 1. Requerimos las instancias operativas de inventario y productos
require_once __DIR__ . '/../controllers/InventarioController.php';
require_once __DIR__ . '/../models/ProductoModelo.php';

$inventarioCtrl = new InventarioController();
$productoModel  = new ProductoModelo();

// 2. Cargamos todos los productos para el combo select (Solo los que manejan stock real)
$lista_completa = $productoModel->listarProductos();
$productos_inventariables = array_filter($lista_completa, function($p) {
    return (int)$p['maneja_stock'] === 1;
});

// 3. Configuración matemática de paginación para el reporte del Kardex
$por_pagina = 10; // Renglones de auditoría por pantalla
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $por_pagina;

// Carga el historial del Kardex directo de la base de datos de forma paginada
$historial_kardex = $inventarioCtrl->obtenerHistorial($por_pagina, $offset);

// 4. Sincronización de URL_BASE corporativa
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
    <title>Historial Kardex y Ajustes - Jungle Pizza</title>
    <!-- Estilos corporativos -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    
    <style>
        .kardex-grid { display: grid !important; grid-template-columns: 1fr; gap: 25px; margin-top: 20px; width: 100%; }
        .jungle-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(27,67,50,0.05); padding: 25px; border-top: 4px solid var(--verde-claro, #52b788); }
        .jungle-card h3 { color: var(--verde-oscuro, #1b4332); font-size: 1.25rem; margin-bottom: 20px; border-bottom: 2px solid var(--verde-menta, #d8f3dc); padding-bottom: 8px; }
        .jungle-card label { display: block !important; margin-bottom: 6px !important; font-weight: 600 !important; font-size: 13px !important; color: var(--verde-oscuro, #1b4332) !important; }
        .form-control { width: 100% !important; padding: 12px 14px !important; border: 2px solid #e2e8f0 !important; border-radius: 8px !important; box-sizing: border-box !important; font-size: 0.95rem !important; background-color: #fafbfc !important; color: #333 !important; transition: all 0.2s ease; }
        .form-control:focus { outline: none !important; border-color: var(--verde-claro, #52b788) !important; background-color: #fff !important; box-shadow: 0 0 0 3px rgba(82,183,136,0.15) !important; }
        .table-responsive { width: 100%; overflow-x: auto; border-radius: 8px; border: 1px solid #edf2f7; margin-top: 15px; }
        .jungle-table { width: 100%; border-collapse: collapse; text-align: left; min-width: 700px; }
        .jungle-table th { background-color: var(--verde-oscuro, #1b4332); color: #ffffff; padding: 12px 15px; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .jungle-table td { padding: 12px 15px; border-bottom: 1px solid #edf2f7; font-size: 0.95rem; vertical-align: middle; }
        .jungle-table tr:hover { background-color: rgba(216, 243, 220, 0.2); }
        
        /* Badges semánticos para el tipo de movimiento en auditoría */
        .badge-mov { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .mov-entrada { background-color: #ebfbee; color: #2b8a3e; border: 1px solid #c3fae8; }
        .mov-salida { background-color: #fff5f5; color: #c92a2a; border: 1px solid #ffe3e3; }
        .mov-compra { background-color: #e3f2fd; color: #0d47a1; border: 1px solid #bbdefb; }
        .mov-venta { background-color: #fff4e6; color: #d9480f; border: 1px solid #ffe8cc; }
        .mov-cancel { background-color: #f3f0ff; color: #5f3dc4; border: 1px solid #e5dbff; }
        
        .alert { padding: 14px; border-radius: 8px; margin-bottom: 20px; font-size: 0.95rem; font-weight: 500; }
        .alert-error { background: #ffe3e3; color: #c92a2a; border: 1px solid #ffa8a8; }
        .alert-success { background: #ebfbee; color: #2b8a3e; border: 1px solid #96f2d7; }
        .btn-submit { background: var(--verde-selva, #2d6a4f); color: #fff; border: none; padding: 12px; width: 100%; border-radius: 6px; font-weight: bold; text-transform: uppercase; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: var(--verde-oscuro, #1b4332); }
        @media (min-width: 992px) { .kardex-grid { grid-template-columns: 350px 1fr; align-items: start; } }
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
        <h2>Control de Inventario y Libro Kardex</h2>
        <p style="color: #666; margin-bottom: 20px;">Realiza auditorías manuales por mermas o roturas de insumos y visualiza el historial de movimientos.</p>

        <?php if ($msg_error): ?><div class="alert alert-error">⚠ <?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
        <?php if ($msg_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div><?php endif; ?>

        <div class="kardex-grid">
            <!-- COLUMNA 1: FORMULARIO DE AJUSTES MANUALES -->
            <div class="jungle-card">
                <h3>⚖️ Aplicar Ajuste Manual</h3>
                
                <form action="<?php echo URL_BASE; ?>controllers/InventarioController.php" method="POST" style="margin-top: 15px;">
                    
                    <!-- Campo: Selección de Insumo / Producto -->
                    <div style="margin-bottom: 15px;">
                        <label>Seleccionar Producto / Insumo *</label>
                        <select name="producto_id" class="form-control" required style="width: 100%;">
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($productos_inventariables as $prod): ?>
                                <option value="<?php echo $prod['id']; ?>">
                                    <?php echo htmlspecialchars($prod['nombre']); ?> (Act: <?php echo floatval($prod['stock_actual']); ?> <?php echo htmlspecialchars($prod['unidad_medida']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Campo: Tipo de Flujo -->
                    <div style="margin-bottom: 15px;">
                        <label>Tipo de Operación *</label>
                        <select name="tipo_movimiento" class="form-control" required style="width: 100%;">
                            <option value="entrada_ajuste">➕ Entrada (Suma al inventario / Reabastecimiento)</option>
                            <option value="salida_ajuste">➖ Salida (Resta al inventario / Merma / Rotura)</option>
                        </select>
                    </div>

                    <!-- Campo: Cantidad Física -->
                    <div style="margin-bottom: 15px;">
                        <label>Cantidad a Ajustar *</label>
                        <input type="number" step="0.01" min="0.01" name="cantidad" class="form-control" required placeholder="Ej. 5.50 o 10">
                    </div>

                    <!-- Campo: Motivo o Justificación -->
                    <div style="margin-bottom: 20px;">
                        <label>Motivo o Justificación *</label>
                        <input type="text" name="motivo" class="form-control" required placeholder="Ej. Queso mozzarella vencido o Conteo físico semanal">
                    </div>

                    <!-- Botón de Envío -->
                    <div>
                        <button type="submit" class="btn-submit">
                            ⚡ Aplicar Ajuste en Caliente
                        </button>
                    </div>
                </form>
            </div> <!-- Fin de jungle-card Formulario -->
            <!-- COLUMNA 2: REPORTE DEL LIBRO HISTÓRICO KARDEX -->
            <div class="jungle-card">
                <h3>📖 Historial del Libro Contable (Kardex)</h3>
                
                <div class="table-responsive">
                    <table class="jungle-table">
                        <thead>
                            <tr>
                                <th style="width: 150px;">Fecha / Hora</th>
                                <th>Insumo / Alimento</th>
                                <th style="width: 130px;">Operación</th>
                                <th style="width: 100px; text-align: right;">Cantidad</th>
                                <th style="width: 160px; text-align: center;">Balances (Prev -> Post)</th>
                                <th>Motivo / Referencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($historial_kardex)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #999; padding: 25px;">
                                        No se registran movimientos en el Kardex de inventario.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($historial_kardex as $k): ?>
                                    <tr>
                                        <td style="font-size: 12px; color: #555; font-family: monospace;">
                                            <?php echo date('d/m/Y g:i a', strtotime($k['fecha_registro'])); ?>
                                        </td>
                                        <td>
                                            <strong style="color: var(--verde-oscuro);"><?php echo htmlspecialchars($k['nombre_producto']); ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            // Asignación semántica de etiquetas dinámicas según tu base de datos
                                            switch ($k['tipo_movimiento']) {
                                                case 'entrada_ajuste':
                                                    echo '<span class="badge-mov mov-entrada">➕ Ajuste Ent</span>';
                                                    break;
                                                case 'salida_ajuste':
                                                    echo '<span class="badge-mov mov-salida">➖ Ajuste Sal</span>';
                                                    break;
                                                case 'compra_proveedor':
                                                    echo '<span class="badge-mov mov-compra">🚚 Compra</span>';
                                                    break;
                                                case 'venta_factura':
                                                    echo '<span class="badge-mov mov-venta">🚩 Venta</span>';
                                                    break;
                                                case 'cancelacion_factura':
                                                    echo '<span class="badge-mov mov-cancel">❌ Anulación</span>';
                                                    break;
                                                default:
                                                    echo '<span class="badge-mov" style="background:#666; color:#fff;">Otro</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                        <td style="text-align: right; font-weight: bold; font-family: monospace;">
                                            <?php echo floatval($k['cantidad']); ?> <?php echo htmlspecialchars($k['unidad_medida']); ?>
                                        </td>
                                        <td style="text-align: center; font-size: 13px; color: #555; font-family: monospace;">
                                            <?php echo floatval($k['stock_anterior']); ?> &rarr; <strong style="color:#000;"><?php echo floatval($k['stock_posterior']); ?></strong>
                                        </td>
                                        <td style="font-size: 13px; color: #475569;">
                                            <?php echo htmlspecialchars($k['motivo']); ?>
                                            <?php if (!empty($k['referencia_id'])): ?>
                                                <small style="display:block; color:#94a3b8; font-family:monospace;">Ref ID: #<?php echo $k['referencia_id']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                                   </table>
            </div> <!-- Fin de table-responsive (Cierre correcto) -->

            <!-- 🚀 INYECCIÓN QUIRÚRGICA REVISADA: BOTONERA DE PAGINACIÓN FLUIDA PARA KARDEX -->
            <?php
            // Ejecutamos la consulta directa de conteo usando la conexión nativa del modelo
            $total_movs_kardex = $productoModel->conectar()->query("SELECT COUNT(*) FROM kardex")->fetchColumn();
            $total_paginas_kardex = ceil($total_movs_kardex / $por_pagina);
            
            if ($total_paginas_kardex > 1): 
            ?>
                <div style="display: flex; justify-content: center; gap: 6px; margin-top: 25px; width: 100%;">
                    
                    <!-- Flecha de Página Anterior -->
                    <?php if ($pagina_actual > 1): ?>
                        <a href="index.php?v=inventario_ajustes&pagina=<?php echo $pagina_actual - 1; ?>" 
                           class="page-jungle-link" 
                           style="padding: 8px 12px; background: #ffffff; border: 2px solid #e2e8f0; border-radius: 6px; color: #333333; text-decoration: none; font-weight: bold; font-family: monospace;">&laquo;</a>
                    <?php endif; ?>

                    <!-- Iteración Numérica de Páginas -->
                    <?php for ($i = 1; $i <= $total_paginas_kardex; $i++): ?>
                        <a href="index.php?v=inventario_ajustes&pagina=<?php echo $i; ?>"
                           class="page-jungle-link"
                           style="padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 6px; text-decoration: none; font-weight: bold; font-family: monospace; <?php echo ($pagina_actual === $i) ? 'background: var(--verde-claro, #52b788); color: #ffffff; border-color: var(--verde-claro, #52b788);' : 'background: #ffffff; color: #333333;'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Flecha de Página Siguiente -->
                    <?php if ($pagina_actual < $total_paginas_kardex): ?>
                        <a href="index.php?v=inventario_ajustes&pagina=<?php echo $pagina_actual + 1; ?>" 
                           class="page-jungle-link" 
                           style="padding: 8px 12px; background: #ffffff; border: 2px solid #e2e8f0; border-radius: 6px; color: #333333; text-decoration: none; font-weight: bold; font-family: monospace;">&raquo;</a>
                    <?php endif; ?>
                    
                </div>
            <?php endif; ?>

        </div> <!-- Fin de la segunda jungle-card Reporte (Ubicación exacta de cierre) -->
    </div> <!-- Fin de kardex-grid (Cierre estructural de las dos columnas) -->
</main>
</div> <!-- Fin de dashboard-layout -->

<script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>
</html>
