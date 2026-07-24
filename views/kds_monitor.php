<?php
// views/kds_monitor.php

require_once __DIR__ . '/../controllers/KdsController.php';

$kdsCtrl = new KdsController();
// Procesamos cualquier pulsación asíncrona de los botones antes de pintar la pantalla
$kdsCtrl->procesarAccionesKds();

// 1. Detección automática de la Estación activa
// Primero lee la URL (?estacion=horno), si no viene, lee el rol de la sesión actual
$estacion_activa = $_GET['estacion'] ?? null;

if (!$estacion_activa && isset($_SESSION['rol_id'])) {
    $rol = (int)$_SESSION['rol_id'];
    if ($rol === 5) $estacion_activa = 'cocina';
    elseif ($rol === 6) $estacion_activa = 'horno';
    elseif ($rol === 7) $estacion_activa = 'bar';
}

// Respaldo de cortesía: Si es Administrador o Supervisor va a cocina por defecto
if (!$estacion_activa || !in_array($estacion_activa, ['horno', 'cocina', 'bar'])) {
    $estacion_activa = 'cocina';
}

// 2. Extraemos los registros iniciales desde la base de datos
$kdsModelo = new KdsModelo();
$comandas = $kdsModelo->obtenerComandasPorEstacion($estacion_activa);

// 3. Mapeo estético de títulos y emoticonos institucionales
$titulos = [
    'horno'   => ['titulo' => '🔥 HORNO DE PIZZAS', 'color' => '#d9480f', 'bg' => '#fff4e6'],
    'cocina'  => ['titulo' => '🍳 COCINA CENTRAL', 'color' => '#2b8a3e', 'bg' => '#ebfbee'],
    'bar'     => ['titulo' => '🍹 BAR & BEBIDAS', 'color' => '#0d47a1', 'bg' => '#e3f2fd']
];
$info_estacion = $titulos[$estacion_activa];
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
            font-family: -apple-system, sans-serif;
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
        }

        /* Cuadrícula responsiva: Las comandas se acomodan solas según el tamaño del monitor */
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

        /* Botonera inferior táctil */
        .ticket-card-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 12px;
            background: #1e293b;
            border-top: 1px solid #475569;
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

        .btn-kds-prepare {
            background: #d9480f;
            color: #fff;
        }

        .btn-kds-ready {
            background: #2b8a3e;
            color: #fff;
        }

        .btn-kds-reject {
            background: #c92a2a;
            color: #fff;
            grid-column: span 2;
            margin-top: 5px;
            font-size: 11px;
            padding: 8px;
        }

        .status-pill {
            font-size: 11px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }
    </style>
</head>

<body>

    <div class="kds-header">
        <div class="station-title" style="background: <?php echo $info_estacion['bg']; ?>; color: <?php echo $info_estacion['color']; ?>;">
            <?php echo $info_estacion['titulo']; ?>
        </div>

        <!-- CONTROL DE SUPERVISIÓN: Si es Administrador (1) o Supervisor (3), habilitamos los botones de salto rápido -->
        <?php if (isset($_SESSION['rol_id']) && (int)$_SESSION['rol_id'] <= 3): ?>
            <div>
                <a href="index.php?v=mesas" class="btn-toggle-station" style="background:var(--verde-jungle);">🏠 Salón</a>
                <a href="index.php?v=kds_monitor&estacion=horno" class="btn-toggle-station">🔥 Horno</a>
                <a href="index.php?v=kds_monitor&estacion=cocina" class="btn-toggle-station">🍳 Cocina</a>
                <a href="index.php?v=kds_monitor&estacion=bar" class="btn-toggle-station">🍹 Bar</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- CUADRÍCULA MAESTRA DE COMANDAS -->
    <div class="kds-grid-cards" id="contenedor-maestro-kds">
        <?php if (empty($comandas)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 60px; color: #64748b; font-style: italic; font-size: 18px;">
                🔕 No hay comandas pendientes en esta estación. ¡Buen trabajo!
            </div>
        <?php else: ?>
            <?php
            // Agrupamos en memoria los productos que pertenecen al mismo pedido para simular un ticket de comanda físico
            $tickets_agrupados = [];
            foreach ($comandas as $item) {
                $tickets_agrupados[$item['pedido_id']][] = $item;
            }

            foreach ($tickets_agrupados as $pedido_id => $items_pedido):
                $primer_item = $items_pedido[0];
                $tipo_orden = strtoupper($primer_item['tipo_pedido']);
                $border_color = ($tipo_orden === 'LOCAL') ? '#2b8a3e' : '#e67e22'; // Verde local, Naranja delivery
            ?>
                <!-- Tarjeta Individual de Cuenta -->
                <div class="ticket-card-kds" style="border-top-color: <?php echo $border_color; ?>;" id="card-pedido-<?php echo $pedido_id; ?>">
                    <div class="ticket-card-header">
                        <div>
                            <span style="font-weight: 800; font-size: 15px; color: #cbd5e1;">ORDER #<?php echo $pedido_id; ?></span>
                            <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">⏰ <?php echo date('h:i A', strtotime($primer_item['hora_pedido'])); ?></div>
                        </div>
                        <span style="background: <?php echo $border_color; ?>20; color: <?php echo $border_color; ?>; padding: 4px 8px; border-radius: 4px; font-weight: 800; font-size: 11px;">
                            <?php echo ($tipo_orden === 'LOCAL') ? '🪑 MESA ' . htmlspecialchars($primer_item['numero_mesa']) : '📦 DELIVERY'; ?>
                        </span>
                    </div>

                    <div class="ticket-card-body">
                        <div style="font-size: 12px; color: #94a3b8; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #334155;">
                            👤 Mesero: <strong style="color: #cbd5e1;"><?php echo htmlspecialchars($primer_item['nombre_mesero'] ?? 'Julio'); ?></strong>
                        </div>

                       <!-- 🚀 REEMPLAZE EL BUCLE FOREACH DE LOS PRODUCTOS INTERNOS ADENTRO DE LA TARJETA POR ESTE BLOQUE SENIOR: -->
<?php foreach ($items_pedido as $it): ?>
    <div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px dashed #475569;" id="row-item-<?php echo $it['detalle_id']; ?>">
        
        <!-- Línea Principal del Platillo -->
        <div class="product-line" style="display: flex; justify-content: space-between; align-items: center; font-size: 16px; font-weight: 700; margin-bottom: 6px;">
            <span><?php echo (int)$it['cantidad']; ?>x <?php echo htmlspecialchars($it['nombre_producto']); ?></span>
            
            <!-- 🌟 MÁQUINA DE ESTADOS VISUAL: Cambia de color e indicador según el flujo real -->
            <?php if ($it['item_estado'] === 'pendiente'): ?>
                <span class="status-pill" style="background: #c92a2a20; color: #ff8787; font-size: 11px; font-weight: bold; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">🛑 En Cola</span>
            <?php elseif ($it['item_estado'] === 'preparando'): ?>
                <span class="status-pill" style="background: #e67e2220; color: #ffd8a8; font-size: 11px; font-weight: bold; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">🔥 Fuego</span>
            <?php else: ?>
                <span class="status-pill" style="background: #2b8a3e20; color: #8ce99a; font-size: 11px; font-weight: bold; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;"><?php echo htmlspecialchars($it['item_estado']); ?></span>
            <?php endif; ?>
        </div>

        <!-- 🌓 PARTICIONES DE SABORES (Para Pizzas Combinadas / Mixtas) -->
        <?php if ((int)$it['es_mixta'] === 1 && !empty($it['sabores'])): ?>
            <div style="background: #1e293b; border: 1px solid #475569; border-left: 4px solid #3182ce; padding: 6px 10px; border-radius: 6px; margin-top: 4px; margin-bottom: 6px;">
                <span style="font-size: 11px; text-transform: uppercase; color: #63b3ed; font-weight: 800; display: block; margin-bottom: 2px;">🌗 Mitades Combinadas:</span>
                <?php foreach ($it['sabores'] as $sab): ?>
                    <div style="font-size: 13px; color: #cbd5e1; font-weight: bold; padding: 1px 0;">
                        🍕 Mitad: <?php echo htmlspecialchars($sab['nombre_sabor']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ➕ INGREDIENTES EXTRAS (Sincronizado de forma relacional al español) -->
        <?php if (!empty($it['extras'])): ?>
            <div style="background: #1e293b; border: 1px solid #475569; border-left: 4px solid #ecc94b; padding: 6px 10px; border-radius: 6px; margin-top: 4px; margin-bottom: 6px;">
                <?php foreach ($it['extras'] as $ex): ?>
                    <div style="font-size: 13px; color: #f6ad55; font-weight: bold; padding: 1px 0;">
                        ➕ <?php echo (int)$ex['cant_extra']; ?>x <?php echo htmlspecialchars($ex['nombre_extra']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 🎛️ BOTONERA DINÁMICA TÁCTIL SENIOR -->
        <div style="display: flex; gap: 6px; margin-top: 8px;">
            <?php if ($it['item_estado'] === 'pendiente'): ?>
                <!-- Estado 1: El plato está en cola, habilitamos el botón para meterlo a cocinar -->
                <button class="btn-kds-action btn-kds-prepare" style="flex:1; background: #d9480f; color:#fff; border:none; padding: 8px; font-weight:800; border-radius: 6px; font-size: 11px; cursor: pointer; text-transform:uppercase;" 
                        onclick="alterarEstadoItem(<?php echo $it['detalle_id']; ?>, 'preparando')">
                    👨‍🍳 Aceptar Orden
                </button>
            <?php elseif ($it['item_estado'] === 'preparando'): ?>
                <!-- Estado 2: El plato ya está en el fuego, habilitamos el botón para liberarlo de cocina -->
                <button class="btn-kds-action btn-kds-ready" style="flex:1; background: #2b8a3e; color:#fff; border:none; padding: 8px; font-weight:800; border-radius: 6px; font-size: 11px; cursor: pointer; text-transform:uppercase;" 
                        onclick="alterarEstadoItem(<?php echo $it['detalle_id']; ?>, 'listo')">
                    ✅ Despachar Platillo
                </button>
            <?php endif; ?>

            <!-- Botón de Contingencia: Remueve con merma inyectando 'quitado_despues' y pidiendo justificación -->
            <button class="btn-kds-action" style="background: #212529; color: #cbd5e1; border:none; padding: 8px; font-weight:800; border-radius: 6px; font-size: 11px; cursor: pointer;" 
                    onclick="rechazarItemPorInsumo(<?php echo $it['detalle_id']; ?>, <?php echo $pedido_id; ?>, '<?php echo htmlspecialchars($it['nombre_producto']); ?>')">
                ❌ Quitar
            </button>
        </div>
    </div>
<?php endforeach; ?>


                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ==========================================================================
       🚀 MOTOR DE AUTOMATIZACIÓN ASÍNCRONA: FETCH KDS CORE JS
       ========================================================================== -->
    <script>
        // Capturamos el string de la estación activa desde PHP de forma segura
        const estacionActualKds = '<?php echo $estacion_activa; ?>';

        /**
         * 🔄 TRANSICIÓN DE ESTADOS: Envía la orden asíncrona hacia el controlador
         */
        function alterarEstadoItem(detalleId, nuevoEstado) {
            const formData = new FormData();
            formData.append('accion', 'cambiar_estado_item');
            formData.append('detalle_id', detalleId);
            formData.append('nuevo_estado', nuevoEstado);

            fetch('index.php?v=kds_monitor&estacion=' + estacionActualKds, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Refrescamos la vista local de inmediato en lugar de parpadear la pantalla completa
                        recargarMonitorKdsSilencioso();
                    } else {
                        alert('⚠️ Error en KDS: ' + data.msg);
                    }
                })
                .catch(err => console.error('Error en red KDS transicion:', err));
        }

        /**
         * ❌ ELIMINACIÓN QUIRÚRGICA POR QUIEBRE DE INSUMOS
         */
        function rechazarItemPorInsumo(detalleId, pedidoId, nombreProducto) {
            if (confirm(`¿Confirmas que no hay insumos para preparar "${nombreProducto}"? Se removerá del ticket y se recalculará la cuenta del cliente.`)) {
                const formData = new FormData();
                formData.append('accion', 'rechazar_item_stock');
                formData.append('detalle_id', detalleId);
                formData.append('pedido_id', pedidoId);

                fetch('index.php?v=kds_monitor&estacion=' + estacionActualKds, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            recargarMonitorKdsSilencioso();
                        } else {
                            alert('⚠️ Error al descartar ítem: ' + data.msg);
                        }
                    })
                    .catch(err => console.error('Error en red KDS rechazo:', err));
            }
        }

        /**
         * 🔄 REFRESCO SILENCIOSO: Lee y re-inyecta el HTML de la cuadrícula para ver las pizzas nuevas
         */
        function recargarMonitorKdsSilencioso() {
            // Hacemos una carga asíncrona parcial usando la URL nativa limpia de la estación
            fetch(window.location.href)
                .then(response => response.text())
                .then(htmlTexto => {
                    // Creamos un DOM temporal en memoria para desestructurar el contenedor
                    const parser = new DOMParser();
                    const docTemporal = parser.parseFromString(htmlTexto, 'text/html');
                    const nuevaCuadricula = docTemporal.getElementById('contenedor-maestro-kds');

                    const contenedorActual = document.getElementById('contenedor-maestro-kds');
                    if (nuevaCuadricula && contenedorActual) {
                        // Reemplazamos únicamente el interior de la cuadrícula de comandas sin tocar la cabecera
                        contenedorActual.innerHTML = nuevaCuadricula.innerHTML;
                    }
                })
                .catch(err => console.error('Error en auto-refresco KDS:', err));
        }

        // ⏰ REGLA INDUSTRIAL: Dispara el auto-refresco automático cada 5 segundos exactos
        setInterval(recargarMonitorKdsSilencioso, 5000);
    </script>
</body>

</html>