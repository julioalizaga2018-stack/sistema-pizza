<?php
require_once __DIR__ . '/../controllers/KdsController.php';
$kdsCtrl = new KdsController();
$kdsCtrl->procesarAccionesKds();

$estacion_activa = $_GET['estacion'] ?? null;
if (!$estacion_activa && isset($_SESSION['rol_id'])) {
    $rol = (int)$_SESSION['rol_id'];
    if ($rol === 5) {
        $estacion_activa = 'cocina';
    } elseif ($rol === 6) {
        $estacion_activa = 'horno';
    } elseif ($rol === 7) {
        $estacion_activa = 'bar';
    }
}

if (!$estacion_activa || !in_array($estacion_activa, ['horno', 'cocina', 'bar'], true)) {
    $estacion_activa = 'cocina';
}

$kdsModelo = new KdsModelo();
$comandas = $kdsModelo->obtenerComandasPorEstacion($estacion_activa);

$titulos = [
    'horno' => ['titulo' => '🔥🔥 HORNO DE PIZZAS', 'color' => '#d9480f', 'bg' => '#fff4e6'],
    'cocina' => ['titulo' => '🍳🍳 COCINA CENTRAL', 'color' => '#2b8a3e', 'bg' => '#ebfbee'],
    'bar' => ['titulo' => '🍹🍹 BAR & BEBIDAS', 'color' => '#0d47a1', 'bg' => '#e3f2fd']
];
$info_estacion = $titulos[$estacion_activa];

$mensaje_success = trim($_GET['success'] ?? '');
$mensaje_error = trim($_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Monitor KDS - Jungle Pizza</title>
<style>
:root {
    --verde-jungle: #1b4332;
    --gris-oscuro: #334155;
}
body {
    font-family: -apple-system, system-ui, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #0f172a;
    color: #f8fafc;
    margin: 0;
    padding: 15px;
    box-sizing: border-box;
}
.kds-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 20px;
    background: #1e293b;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
}
.station-title {
    font-size: 22px;
    font-weight: 800;
    padding: 6px 16px;
    border-radius: 6px;
    letter-spacing: 0.5px;
}
.btn-toggle-station {
    background: #334155;
    color: #f8fafc;
    border: none;
    padding: 8px 14px;
    font-weight: bold;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    font-size: 13px;
    margin-left: 5px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.kds-grid-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    align-items: start;
}
.ticket-card-kds {
    background: #1e293b;
    border-radius: 12px;
    border-top: 5px solid #cbd5e1;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.ticket-card-header {
    padding: 12px;
    background: #334155;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #475569;
}
.ticket-card-body {
    padding: 15px;
    flex: 1;
}
.product-line {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 8px;
    color: #ffffff;
    display: flex;
    justify-content: space-between;
}
.btn-kds-action {
    padding: 12px;
    border: none;
    font-weight: 800;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    text-transform: uppercase;
    text-align: center;
}
.status-pill {
    font-size: 11px;
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
}
.message-box {
    margin-bottom: 16px;
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 14px;
}
.message-box.success {
    background: #164e2f;
    color: #a7f3d0;
}
.message-box.error {
    background: #4c1d2a;
    color: #fecaca;
}
</style>
</head>
<body>
<div class="kds-header">
    <div class="station-title" style="background: <?php echo $info_estacion['bg']; ?>; color: <?php echo $info_estacion['color']; ?>;">
        <?php echo $info_estacion['titulo']; ?>
    </div>
    <?php if (isset($_SESSION['rol_id']) && (int)$_SESSION['rol_id'] <= 3): ?>
    <div>
        <a href="index.php?v=mesas" class="btn-toggle-station" style="background: var(--verde-jungle);">🏠🏠 Salón</a>
        <a href="index.php?v=kds_monitor&estacion=horno" class="btn-toggle-station">🔥🔥 Horno</a>
        <a href="index.php?v=kds_monitor&estacion=cocina" class="btn-toggle-station">🍳🍳 Cocina</a>
        <a href="index.php?v=kds_monitor&estacion=bar" class="btn-toggle-station">🍹🍹 Bar</a>
    </div>
    <?php endif; ?>
</div>

<?php if ($mensaje_success): ?>
<div class="message-box success"><?php echo htmlspecialchars($mensaje_success); ?></div>
<?php endif; ?>

<?php if ($mensaje_error): ?>
<div class="message-box error"><?php echo htmlspecialchars($mensaje_error); ?></div>
<?php endif; ?>

<div class="kds-grid-cards" id="contenedor-maestro-kds">
<?php if (empty($comandas)): ?>
    <div style="grid-column: 1 / -1; text-align: center; padding: 60px; color: #64748b; font-style: italic; font-size: 18px;">
        🔕🔕 No hay comandas pendientes en esta estación. ¡Buen trabajo!
    </div>
<?php else: ?>
<?php
$tickets_agrupados = [];
foreach ($comandas as $item) {
    $tickets_agrupados[$item['pedido_id']][] = $item;
}

foreach ($tickets_agrupados as $pedido_id => $items_pedido):
    $primer_item = $items_pedido[0];
    $tipo_orden = strtoupper($primer_item['tipo_pedido']);
    $border_color = ($tipo_orden === 'LOCAL') ? '#2b8a3e' : '#e67e22';
?>
<div class="ticket-card-kds" style="border-top-color: <?php echo $border_color; ?>;" id="card-pedido-<?php echo $pedido_id; ?>">
<div class="ticket-card-header">
    <div>
        <span style="font-weight: 800; font-size: 15px; color: #cbd5e1;">ORDER #<?php echo $pedido_id; ?></span>
        <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">⏰ <?php echo date('h:i A', strtotime($primer_item['hora_pedido'])); ?></div>
    </div>
    <span style="background: <?php echo $border_color; ?>20; color: <?php echo $border_color; ?>; padding: 4px 8px; border-radius: 4px; font-weight: 800; font-size: 11px; display: inline-flex; align-items: center; gap: 4px; letter-spacing: 0.2px;">
    <?php
    $modalidad_limpia = strtoupper(trim($tipo_orden ?? 'LOCAL'));
    if ($modalidad_limpia === 'LOCAL') {
        $area_comercial = !empty($primer_item['nombre_area']) ? htmlspecialchars($primer_item['nombre_area']) . ' ➔ ' : '';
        $mesa_comercial = !empty($primer_item['nombre_mesa']) ? htmlspecialchars($primer_item['nombre_mesa']) : 'Mesa';
        echo '🪑🪑 ' . $area_comercial . 'MESA ' . $mesa_comercial;
    } elseif ($modalidad_limpia === 'RETIRO') {
        echo '🏃🏃 RETIRO / PARA LLEVAR';
    } else {
        echo '📦📦 DELIVERY';
    }
    ?>
    </span>
</div>
<div class="ticket-card-body">
    <div style="font-size: 12px; color: #94a3b8; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #334155;">
        👤👤 Mesero: <strong style="color: #cbd5e1;"><?php echo htmlspecialchars($primer_item['nombre_mesero'] ?? 'Julio'); ?></strong>
    </div>

    <?php foreach ($items_pedido as $it): ?>
    <div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px dashed #475569;" id="row-item-<?php echo $it['detalle_id']; ?>">
        <div class="product-line" style="display: flex; justify-content: space-between; align-items: center; font-size: 16px; font-weight: 700; margin-bottom: 6px;">
            <span><?php echo (int)$it['cantidad']; ?>x <?php echo htmlspecialchars($it['nombre_producto']); ?></span>
            <?php if ($it['item_estado'] === 'pendiente'): ?>
                <span class="status-pill" style="background: #c92a2a20; color: #ff8787;">🛑 En Cola</span>
            <?php elseif ($it['item_estado'] === 'preparando'): ?>
                <span class="status-pill" style="background: #e67e2220; color: #ffd8a8;">🔥🔥 Fuego</span>
            <?php else: ?>
                <span class="status-pill" style="background: #2b8a3e20; color: #8ce99a;"><?php echo htmlspecialchars($it['item_estado']); ?></span>
            <?php endif; ?>
        </div>

        <?php if ((int)$it['es_mixta'] === 1 && !empty($it['sabores'])): ?>
        <div style="background: #1e293b; border: 1px solid #475569; border-left: 4px solid #3182ce; padding: 6px 10px; border-radius: 6px; margin-top: 4px; margin-bottom: 6px;">
            <span style="font-size: 11px; text-transform: uppercase; color: #63b3ed; font-weight: 800; display: block; margin-bottom: 2px;">🌗🌗 Mitades Combinadas:</span>
            <?php foreach ($it['sabores'] as $sab): ?>
            <div style="font-size: 13px; color: #cbd5e1; font-weight: bold; padding: 1px 0;">🍕🍕 Mitad: <?php echo htmlspecialchars($sab['nombre_sabor']); ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($it['extras'])): ?>
        <div style="background: #1e293b; border: 1px solid #475569; border-left: 4px solid #ecc94b; padding: 6px 10px; border-radius: 6px; margin-top: 4px; margin-bottom: 6px;">
            <?php foreach ($it['extras'] as $ex): ?>
            <div style="font-size: 13px; color: #f6ad55; font-weight: bold; padding: 1px 0;">➕ <?php echo (int)$ex['cant_extra']; ?>x <?php echo htmlspecialchars($ex['nombre_extra']); ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div style="display: flex; gap: 6px; margin-top: 8px;">
        <?php if ($it['item_estado'] === 'pendiente'): ?>
            <button class="btn-kds-action" style="flex:1; background: #d9480f; color:#fff;" onclick="alterarEstadoItem(<?php echo $it['detalle_id']; ?>, 'preparando')">🔥 Aceptar Orden</button>
        <?php elseif ($it['item_estado'] === 'preparando'): ?>
            <button class="btn-kds-action" style="flex:1; background: #2b8a3e; color:#fff;" onclick="alterarEstadoItem(<?php echo $it['detalle_id']; ?>, 'listo')">✅ Despachar Platillo</button>
        <?php endif; ?>
            <button class="btn-kds-action" style="background: #212529; color: #cbd5e1;" onclick="ejecutarBajaPorFaltaDeInsumo(<?php echo $it['detalle_id']; ?>, <?php echo $pedido_id; ?>, '<?php echo htmlspecialchars($it['nombre_producto'], ENT_QUOTES); ?>')">❌ Quitar</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<script>
const estacionActualKds = '<?php echo $estacion_activa; ?>';

function alterarEstadoItem(detalleId, nuevoEstado) {
    const formData = new FormData();
    formData.append('accion', 'cambiar_estado_item');
    formData.append('detalle_id', detalleId);
    formData.append('nuevo_estado', nuevoEstado);

    fetch('index.php?v=kds_monitor&estacion=' + estacionActualKds, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(async response => {
        const text = await response.text();
        try {
            const data = JSON.parse(text);
            return data;
        } catch (e) {
            console.error('Respuesta no JSON de KDS:', text);
            throw new Error('Respuesta inválida del servidor: ' + (text.substring(0, 200)));
        }
    })
    .then(data => {
        if (data && data.status === 'success') {
            window.location.reload();
        } else {
            console.warn('KDS retornó error:', data);
            alert('⚠ Error en KDS: ' + (data.msg || 'Respuesta inesperada'));
        }
    })
    .catch(err => {
        console.error('Error en petición a KDS:', err);
        alert('No se pudo conectar con el servidor KDS. Revisa la consola para más detalles.');
    });
}

function ejecutarBajaPorFaltaDeInsumo(detalleId, pedidoId, nombreProducto) {
    if (!confirm(`⚠ QUITAR DEL MONITOR DE PRODUCCIÓN:\n"${nombreProducto}"\n\n¿Confirmas que no hay insumos en el restaurante? Se registrará la merma de stock y se recalculará la cuenta del cliente.`)) {
        return;
    }

    const motivo = prompt(`📌 Motivo de la baja para: ${nombreProducto}`);
    if (motivo === null) {
        return;
    }

    const motivoLimpio = motivo.trim();
    if (motivoLimpio === '') {
        alert('Debes ingresar un motivo para quitar el ítem.');
        return;
    }

    const formVirtual = document.createElement('form');
    formVirtual.method = 'POST';
    formVirtual.action = 'index.php?v=kds_monitor&estacion=' + estacionActualKds;

    const fields = {
        accion: 'rechazar_item_stock',
        detalle_id: detalleId,
        pedido_id: pedidoId,
        motivo_quitar: motivoLimpio
    };

    Object.entries(fields).forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        formVirtual.appendChild(input);
    });

    document.body.appendChild(formVirtual);
    formVirtual.submit();
}
</script>
</body>
</html>
