<?php
// views/compras_lista.php (Parte 1 de 2)
// 1. Requerimos las dependencias operativas del backend
require_once __DIR__ . '/../controllers/CompraController.php';
$compraCtrl = new CompraController();

// 2. Configuración matemática de paginación industrial compacta (De 10 en 10)
$por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
if ($pagina_actual < 1) $pagina_actual = 1;

// Obtenemos los totales directos del contador del controlador
$total_registros = $compraCtrl->totalCompras();
$total_paginas   = ceil($total_registros / $por_pagina);
if ($total_paginas < 1) $total_paginas = 1;
if ($pagina_actual > $total_paginas) $pagina_actual = $total_paginas;

$offset = ($pagina_actual - 1) * $por_pagina;

// Ejecutamos la consulta paginada de las facturas registradas
$lista_compras = $compraCtrl->historialCompras($por_pagina, $offset);

// 3. Sincronización automática de URL_BASE
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
    <title>Historial de Compras - Jungle Pizza</title>
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    <style>
        .compras-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(27, 67, 50, 0.05);
            padding: 25px;
            border-top: 4px solid #3b82f6;
            margin-top: 20px;
        }

        .compras-card h3 {
            color: var(--verde-oscuro, #1b4332);
            font-size: 1.25rem;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--verde-menta, #d8f3dc);
            padding-bottom: 8px;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #edf2f7;
        }

        .jungle-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            min-width: 750px;
        }

        .jungle-table th {
            background-color: var(--verde-oscuro, #1b4332);
            color: #ffffff;
            padding: 12px 15px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .jungle-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #edf2f7;
            font-size: 0.95rem;
            vertical-align: middle;
        }

        .jungle-table tr:hover {
            background-color: rgba(216, 243, 220, 0.2);
        }

        .btn-compras-action {
            background-color: #3b82f6;
            color: #ffffff !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-compras-action:hover {
            background-color: #2563eb;
            transform: translateY(-1px);
        }

        .alert {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .alert-error {
            background: #ffe3e3;
            color: #c92a2a;
            border: 1px solid #ffa8a8;
        }

        .alert-success {
            background: #ebfbee;
            color: #2b8a3e;
            border: 1px solid #96f2d7;
        }

        .pagination-jungle {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }

        .page-jungle-link {
            padding: 8px 12px;
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            color: #333;
            text-decoration: none;
            font-weight: bold;
            font-family: monospace;
        }

        .page-jungle-link.active {
            background: #3b82f6;
            color: #fff;
            border-color: #3b82f6;
        }
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
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 5px;">
                <div>
                    <h2>Historial de Facturas de Compras</h2>
                    <p style="color: #666;">Consulta la bitácora contable de abastecimientos ingresados de materias primas e insumos.</p>
                </div>
                <a href="index.php?v=compras_registrar" class="btn-compras-action">📦 Registrar Nueva Compra</a>
            </div>

            <?php if ($msg_error): ?><div class="alert alert-error">⚠ <?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
            <?php if ($msg_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div><?php endif; ?>

            <div class="compras-card">
                <h3>📋 Registro de Compras Realizadas</h3>

                <div class="table-responsive">
                    <table class="jungle-table">
                        <thead>
                            <tr>
                                <th style="width: 80px; text-align: center;">ID</th>
                                <th>N° Factura Proveedor</th>
                                <th>Proveedor Logístico</th>
                                <th style="width: 140px;">Fecha Factura</th>
                                <th>Ingresado Por</th>
                                <th style="text-align: right; width: 160px;">Total Invertido</th>
                                <th style="text-align: center; width: 120px;">Acción</th>
                            </tr>
                        </thead>
                       <tbody>
    <?php if (empty($lista_compras)): ?>
        <tr>
            <td colspan="7" style="text-align: center; color: #999; padding: 35px; font-size: 15px;">📦 No se registran facturas de compras en el sistema aún.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($lista_compras as $c): ?>
            <tr>
                <td style="text-align: center;"><code>#<?php echo $c['id']; ?></code></td>
                <td><strong><?php echo htmlspecialchars($c['numero_factura']); ?></strong></td>
                <td><span style="font-weight: 600; color: var(--verde-oscuro);"><?php echo htmlspecialchars($c['proveedor_nombre']); ?></span></td>
                <td style="font-family: monospace; font-size: 13.5px;"><?php echo date('d/m/Y', strtotime($c['fecha_compra'])); ?></td>
                <td style="color: #555; font-size: 13px;">👤 <?php echo htmlspecialchars($c['usuario_nombre']); ?></td>
                <td style="text-align: right; font-weight: 800; font-family: monospace; color: #1e293b; font-size: 15px;">C$ <?php echo number_format($c['total'], 2); ?></td>
                
                <td style="text-align: center;">
                    <!-- 🌟 CONTENEDOR FLEXBOX: Alinea horizontalmente y evita colisiones visuales -->
                    <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                        
                        <!-- Botón 1: Visor Modal interactivo por AJAX -->
                        <button class="btn-compras-action" style="padding: 6px 10px; font-size: 11px; background: #64748b; cursor: pointer; border: none; white-space: nowrap;" onclick="abrirModalAuditoriaCompra(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars($c['numero_factura']); ?>');">
                            👁️ Ver Detalle
                        </button>
                        
                        <!-- Botón 2: Lanzador físico de ticket térmico de abasto -->
                        <a href="index.php?v=imprimir_compra&compra_id=<?php echo $c['id']; ?>" target="_blank" class="btn-compras-action" style="padding: 6px 10px; font-size: 11px; background: var(--verde-selva, #2d6a4f); text-decoration: none; border-radius: 6px; font-weight: bold; white-space: nowrap;">
                            🖨️ Imprimir
                        </a>
                        
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</tbody>

                    </table>
                </div>
                <!-- 🎛️ BOTONERA DE PAGINACIÓN NATIVA (BLOQUES DE 10 EN 10) -->
                <?php if ($total_paginas > 1): ?>
                    <div class="pagination-jungle">
                        <?php if ($pagina_actual > 1): ?>
                            <a href="index.php?v=compras_lista&pagina=<?php echo $pagina_actual - 1; ?>" class="page-jungle-link">&laquo;</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <a href="index.php?v=compras_lista&pagina=<?php echo $i; ?>"
                                class="page-jungle-link <?php echo ($pagina_actual === $i) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($pagina_actual < $total_paginas): ?>
                            <a href="index.php?v=compras_lista&pagina=<?php echo $pagina_actual + 1; ?>" class="page-jungle-link">&raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div> <!-- Fin de compras-card -->
        </main>
    </div> <!-- Fin de dashboard-layout -->

    <!-- 📦 VENTANA MODAL INTERACTIVA DE AUDITORÍA (Oculta por defecto) -->
    <div id="modal_detalle_compra" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
        <div style="background: #ffffff; padding: 25px; border-radius: 12px; max-width: 650px; width: 90%; box-shadow: 0 5px 25px rgba(0,0,0,0.15); border-top: 4px solid #3b82f6;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 15px;">
                <h3 style="margin: 0; color: var(--verde-oscuro, #1b4332); font-size: 1.2rem;" id="modal_titulo_factura">Desglose de Factura</h3>
                <button onclick="cerrarModalAuditoriaCompra();" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; line-height: 1;">&times;</button>
            </div>

            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                <table class="jungle-table" style="min-width: 100%; font-size: 13.5px;">
                    <thead>
                        <tr style="background: #f1f5f9; color: #fff;">
                            <th style="background: #64748b; padding: 8px;">Insumo / Materia Prima</th>
                            <th style="background: #64748b; padding: 8px; text-align: right;">Cantidad</th>
                            <th style="background: #64748b; padding: 8px; text-align: right;">Costo U. (C$)</th>
                            <th style="background: #64748b; padding: 8px; text-align: right;">Subtotal (C$)</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpo_modal_detalles">
                        <!-- Los renglones se inyectarán aquí dinámicamente -->
                    </tbody>
                </table>
            </div>

            <div style="text-align: right; margin-top: 15px; border-top: 1px dashed #cbd5e1; padding-top: 12px;">
                <button onclick="cerrarModalAuditoriaCompra();" class="btn-compras-action" style="background: #64748b; padding: 8px 16px;">Cerrar Visor</button>
            </div>
        </div>
    </div>

    <script>
        function abrirModalAuditoriaCompra(compraId, numFactura) {
            const modal = document.getElementById('modal_detalle_compra');
            const titulo = document.getElementById('modal_titulo_factura');
            const cuerpo = document.getElementById('cuerpo_modal_detalles');

            titulo.innerText = `🔍 Auditoría de Insumos - Factura N° ${numFactura}`;
            cuerpo.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px; color:#666;">Buscando desglose en el almacén local...</td></tr>';
            modal.style.display = 'flex';

            fetch(`index.php?v=api_detalle_compra&compra_id=${compraId}`)
                .then(response => response.json())
                .then(res => {
                    if (res.status === 'success') {
                        cuerpo.innerHTML = '';
                        if (res.data.length === 0) {
                            cuerpo.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:15px;">La factura no posee detalles registrados.</td></tr>';
                            return;
                        }

                        res.data.forEach(item => {
                            const fila = document.createElement('tr');
                            fila.innerHTML = `
                        <td style="padding: 10px;"><strong>${item.producto_nombre}</strong></td>
                        <td style="padding: 10px; text-align: right; font-family: monospace; font-weight: bold;">${parseFloat(item.cantidad)} ${item.unidad_medida}</td>
                        <td style="padding: 10px; text-align: right; font-family: monospace; color:#475569;">C$ ${parseFloat(item.precio_unitario).toFixed(2)}</td>
                        <td style="padding: 10px; text-align: right; font-family: monospace; font-weight: 800; color:var(--verde-selva, #2d6a4f);">C$ ${parseFloat(item.subtotal).toFixed(2)}</td>
                    `;
                            cuerpo.appendChild(fila);
                        });
                    } else {
                        cuerpo.innerHTML = `<tr><td colspan="4" style="text-align:center; color:red; padding:15px;">${res.msg}</td></tr>`;
                    }
                })
                .catch(err => {
                    cuerpo.innerHTML = '<tr><td colspan="4" style="text-align:center; color:red; padding:15px;">Error al comunicar con el servidor local de XAMPP.</td></tr>';
                });
        }

        function cerrarModalAuditoriaCompra() {
            document.getElementById('modal_detalle_compra').style.display = 'none';
        }
    </script>

    <script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>

</html>