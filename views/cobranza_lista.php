<?php
// views/cobranza_lista.php
// 1. Requerimos las dependencias operativas del backend
require_once __DIR__ . '/../controllers/CobranzaController.php';
$cobranzaCtrl = new CobranzaController();

// 2. Cargamos la lista viva de pedidos pendientes de pago directo de la BD
$pedidos_pendientes = $cobranzaCtrl->listadoPendientes();

// 3. Sincronización automática de URL_BASE corporativa
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
    <title>Cuentas Pendientes de Cobro - Jungle Pizza</title>
    <!-- CSS Nativo de tu Ecosistema -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    <style>
        .cobranza-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(27,67,50,0.05); padding: 25px; border-top: 4px solid var(--verde-claro, #52b788); margin-top: 20px; }
        .cobranza-card h3 { color: var(--verde-oscuro, #1b4332); font-size: 1.25rem; margin-bottom: 15px; border-bottom: 2px solid var(--verde-menta, #d8f3dc); padding-bottom: 8px; }
        .table-responsive { width: 100%; overflow-x: auto; border-radius: 8px; border: 1px solid #edf2f7; }
        .jungle-table { width: 100%; border-collapse: collapse; text-align: left; min-width: 700px; }
        .jungle-table th { background-color: var(--verde-oscuro, #1b4332); color: #ffffff; padding: 12px 15px; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .jungle-table td { padding: 12px 15px; border-bottom: 1px solid #edf2f7; font-size: 0.95rem; vertical-align: middle; }
        .jungle-table tr:hover { background-color: rgba(216, 243, 220, 0.2); }
        .badge-pos { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .badge-local { background-color: #e3f2fd; color: #0d47a1; border: 1px solid #bbdefb; }
        .badge-delivery { background-color: #fff4e6; color: #d9480f; border: 1px solid #ffe8cc; }
        .badge-retiro { background-color: #f3f0ff; color: #5f3dc4; border: 1px solid #e5dbff; }
        .badge-estado { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .btn-cobrar-action { background-color: var(--verde-selva, #2d6a4f); color: #ffffff !important; display: inline-flex; align-items: center; justify-content: center; padding: 8px 14px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; border-radius: 6px; text-decoration: none; border: none; cursor: pointer; transition: all 0.2s ease; }
        .btn-cobrar-action:hover { background-color: var(--verde-oscuro, #1b4332); transform: translateY(-1px); }
        .alert { padding: 14px; border-radius: 8px; margin-bottom: 20px; font-size: 0.95rem; font-weight: 500; }
        .alert-error { background: #ffe3e3; color: #c92a2a; border: 1px solid #ffa8a8; }
        .alert-success { background: #ebfbee; color: #2b8a3e; border: 1px solid #96f2d7; }
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
        <h2>Módulo de Cobranza y Facturación</h2>
        <p style="color: #666;">Selecciona una orden de la sucursal para aplicar descuentos, modificar propinas y procesar el cobro.</p>

        <?php if ($msg_error): ?><div class="alert alert-error">⚠ <?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
        <?php if ($msg_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div><?php endif; ?>

        <div class="cobranza-card">
            <h3>🧾 Cola de Pedidos Pendientes de Pago</h3>
            
            <div class="table-responsive">
                <table class="jungle-table">
                    <thead>
                        <tr>
                            <th style="width: 90px;">Pedido ID</th>
                            <th style="width: 150px;">Fecha / Hora</th>
                            <th>Ubicación / Tipo</th>
                            <th>Estado Actual</th>
                            <th>Total Acumulado</th>
                            <th style="width: 140px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos_pendientes)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #999; padding: 35px; font-size: 15px;">
                                    🎉 ¡Excelente! No hay cuentas pendientes de cobro en este momento.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pedidos_pendientes as $p): ?>
                                <tr>
                                    <td><code>#<?php echo $p['id']; ?></code></td>
                                    <td style="font-size: 13px; color: #555; font-family: monospace;">
                                    <td>
<?php
// Renderizado limpio de Área y Mesa sincronizado con Mesero y Cliente
if ($p['tipo_pedido'] === 'local') {
    $area = !empty($p['nombre_area']) ? htmlspecialchars($p['nombre_area']) : 'Salón';
    $mesa = !empty($p['numero_mesa']) ? htmlspecialchars($p['numero_mesa']) : 'N/A';
    
    // 1. Imprimimos el indicador de ubicación original de la pizzería
    echo '<span class="badge-pos badge-local">🪑🪑 ' . $area . ' - ' . $mesa . '</span>';
    
    // 2. 🌟 NUEVO: Si el pedido fue dividido o tiene un nombre de cliente asignado, lo estampamos a la par
    if (!empty($p['cliente_nombre'])) {
        echo '<span class="badge-pos" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; margin-left: 5px;">👤 ' . htmlspecialchars($p['cliente_nombre']) . '</span>';
    }
    
    // 3. 🌟 NUEVO: Extraemos e inyectamos el nombre del mesero que atiende la mesa
    // (Usa la columna exacta de tu consulta, ej: $p['nombre_mesero'] o $p['mesero'])
    $alias_mesero = !empty($p['nombre_mesero']) ? $p['nombre_mesero'] : (!empty($p['mesero']) ? $p['mesero'] : 'Mesero');
    echo '<br><span style="font-size: 11.5px; color: #64748b; font-weight: 700; margin-top: 4px; display: inline-block; padding-left: 4px;">🏃 Atendido por: <strong>' . htmlspecialchars($alias_mesero) . '</strong></span>';

} elseif ($p['tipo_pedido'] === 'delivery') {
    echo '<span class="badge-pos badge-delivery">🛵🛵 Delivery</span>';
    if (!empty($p['cliente_nombre'])) {
        echo '<br><span style="font-size: 12px; color: #475569; font-weight: bold; display:block; margin-top:3px;">👤 Cliente: ' . htmlspecialchars($p['cliente_nombre']) . '</span>';
    }
} elseif ($p['tipo_pedido'] === 'retiro') {
    echo '<span class="badge-pos badge-retiro">📦📦 Llevar / Retiro</span>';
    if (!empty($p['cliente_nombre'])) {
        echo '<br><span style="font-size: 12px; color: #475569; font-weight: bold; display:block; margin-top:3px;">👤 Cliente: ' . htmlspecialchars($p['cliente_nombre']) . '</span>';
    }
}
?>
</td>


                                    <td>
                                        <span class="badge-pos badge-estado">
                                            ⚙️ <?php echo htmlspecialchars($p['estado']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color: var(--verde-oscuro); font-family: monospace; font-size: 15px;">
                                            C$ <?php echo number_format($p['total'], 2); ?>
                                        </strong>
                                    </td>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <!-- Enrutamiento hacia la calculadora de pago mixto pasando el ID del pedido por GET -->
                                        <a href="index.php?v=cobranza_facturar&pedido_id=<?php echo $p['id']; ?>" class="btn-cobrar-action">
                                            💳 Procesar Cobro
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
<?php if (isset($_GET['imprimir_id'])): ?>
<script>
    // Abre en una pestaña limpia e independiente el ticket de 80mm optimizado
    window.open('index.php?v=imprimir_ticket&pedido_id=<?php echo intval($_GET['imprimir_id']); ?>', '_blank', 'width=400,height=600');
</script>
<?php endif; ?>

</body>
</html>
