<?php
// views/imprimir_ticket.php (Parte 1 de 2)
// 1. REQUERIMIENTO CRÍTICO: Forzamos la carga de la conexión nativa para evitar la pantalla en blanco
require_once __DIR__ . '/../config/conexion.php';

$pedido_id = intval($_GET['pedido_id'] ?? 0);
$db = (new Conexion())->conectar();

// 2. Extraemos los datos maestros de la comanda cobrada
$stmtPed = $db->prepare("SELECT p.*, m.numero_mesa, a.nombre as nombre_area, u.nombre as nombre_mesero 
                         FROM pedidos p 
                         LEFT JOIN mesas m ON p.mesa_id = m.id
                         LEFT JOIN areas a ON m.area_id = a.id
                         INNER JOIN usuarios u ON p.usuario_id = u.id
                         WHERE p.id = :id LIMIT 1");
$stmtPed->execute(['id' => $pedido_id]);
$pedidoInfo = $stmtPed->fetch(PDO::FETCH_ASSOC);

if (!$pedidoInfo) {
    die("🚨🚨 Error: Comanda inválida o no registrada en el sistema.");
}

// 3. Extraer el rastro contable del Pago Mixto recibido por caja
$stmtPagos = $db->prepare("SELECT pp.metodo_pago, pp.monto, pp.referencia, b.nombre as nombre_banco 
    FROM pedido_pagos pp 
    LEFT JOIN bancos b ON pp.banco_id = b.id 
    WHERE pp.pedido_id = :id");
$stmtPagos->execute(['id' => $pedido_id]);
$pagos_recibidos = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);


// 4. Extraemos el desglose de productos activos para procesar extras y mitades combinadas
$stmtDet = $db->prepare("SELECT pd.id, pd.cantidad, pd.precio_unitario, pd.subtotal, pd.es_mixta, p.nombre as nombre_producto
                         FROM pedido_detalles pd 
                         INNER JOIN productos p ON pd.producto_id = p.id
                         WHERE pd.pedido_id = :pedido_id AND pd.estado NOT IN ('quitado_antes', 'quitado_despues')");
$stmtDet->execute(['pedido_id' => $pedido_id]);
$itemsComanda = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Ticket_Factura_#<?php echo $pedido_id; ?></title>
    <style>
        /* Estilos de alta densidad ultra-compactos corregidos para rollo continuo */
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14.5px;
            font-weight: 500;
            letter-spacing: -0.4px;
            line-height: 1.25;
            color: #000000;
            background: #ffffff;
            margin: 0;
            padding: 6px;
            width: 275px;
            height: auto !important;
            /* 🌟 Evita restricciones fijas de altura */
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .divider {
            border-top: 2px dashed #000000;
            margin: 8px 0;
        }

        .ticket-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            letter-spacing: -0.4px;
        }

        .ticket-table td {
            padding: 3px 0;
            vertical-align: top;
        }

        .extra-line {
            font-size: 11.5px;
            padding-left: 8px !important;
        }

        .totals-section {
            font-weight: 700;
            margin-top: 6px;
            font-size: 14px;
        }

        /* 🖨️ CONFIGURACIÓN DE IMPRESIÓN ADAPTATIVA (SIN SALTO DE PÁGINA) */
        @media print {
            .no-print {
                display: none !important;
            }

            html,
            body {
                height: auto !important;
                /* 🔥 El ticket se estira según los productos */
                overflow: visible !important;
                /* 🔥 Elimina cualquier corte de scroll */
                padding: 0;
                margin: 0;
                width: 100%;
            }

            /* 🌟 Evita que una pizza combinada o un ingrediente extra se rompan a la mitad */
            tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>

</head>

<body>

    <!-- BOTONERA OPERATIVA DE LA TABLET (Oculta en papel) -->
    <div class="no-print" style="background: #f1f5f9; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center;">
        <button onclick="window.print()" style="background: #2b8a3e; color: #fff; border: none; padding: 10px 15px; font-weight: bold; border-radius: 4px; cursor: pointer; margin-right: 5px;">🖨🖨 Imprimir Factura</button>
        <button onclick="window.close();" style="background: #64748b; color: #fff; border: none; padding: 10px 15px; font-weight: bold; border-radius: 4px; cursor: pointer;">❌ Cerrar</button>
    </div>
    <!-- CUERPO DEL TICKET TÉRMICO -->
    <div class="text-center">
        <!-- 🦁🦁 LOGOTIPO COMERCIAL CORREGIDO DE FORMA NATIVA (Muestra la imagen al instante) -->
        <img src="public/uploads/logo_jungle_1784916357.jpeg" style="width: 140px; height: auto; margin-bottom: 6px; display: inline-block; object-fit: contain;" onerror="this.style.display='none';">
        <h2 style="margin: 0; font-size: 18px; font-weight: 800; letter-spacing: 0.5px;">JUNGLE PIZZA</h2>
        <p style="margin: 4px 0; font-size: 13px; font-weight: bold;">Comprobante de Pago Oficial</p>
        <p style="margin: 2px 0; font-size: 11px;">Fecha/Hora: <?php echo date('d/m/Y h:i A', strtotime($pedidoInfo['created_at'])); ?></p>
    </div>

    <div class="divider"></div>

    <!-- METADATOS DE LA FACTURA CORREGIDOS -->
    <div style="font-size: 14px; line-height: 1.4;">
        <strong>Factura N°:</strong> #<?php echo $pedido_id; ?><br>
        <strong>Ubicación:</strong> <?php
                                    if (!empty($pedidoInfo['numero_mesa'])) {
                                        // Combina el nombre del área (ej. Terraza) con el número de mesa nativo de tu base de datos
                                        echo htmlspecialchars($pedidoInfo['nombre_area'] . ' - ' . $pedidoInfo['numero_mesa']);
                                    } else {
                                        echo 'Express / Delivery';
                                    }
                                    ?><br>
        <strong>Atendido por:</strong> <?php echo htmlspecialchars($pedidoInfo['nombre_mesero']); ?><br>
        <strong>Modalidad:</strong> <?php echo strtoupper($pedidoInfo['tipo_pedido']); ?><br>
    </div>


    <div class="divider"></div>

    <!-- 🛒🛒 TABLA DEL MENÚ: CON DESGLOSE DE INGREDIENTES EXTRAS Y SABORES COMBINADOS -->
    <table class="ticket-table">
        <thead>
            <tr style="border-bottom: 1px dashed #000; font-weight: bold;">
                <td>Cant/Platillo</td>
                <td class="text-right">Total</td>
            </tr>
        </thead>
        <tbody>
            <?php
            $subtotal_calculado = 0;
            foreach ($itemsComanda as $it):
                $subtotal_calculado += floatval($it['subtotal']);
                $id_detalle = (int)$it['id'];

                // Extracción relacional de extras en español
                $stmtExt = $db->prepare("SELECT pde.*, p.nombre FROM pedido_detalle_extras pde INNER JOIN productos p ON pde.producto_id = p.id WHERE pde.pedido_detalle_id = :det_id");
                $stmtExt->execute(['det_id' => $id_detalle]);
                $extras = $stmtExt->fetchAll(PDO::FETCH_ASSOC);

                // Extracción relacional de mitades combinadas de pizza
                $saboresText = "";
                if ((int)$it['es_mixta'] === 1) {
                    $stmtSab = $db->prepare("SELECT p.nombre FROM pedido_detalle_sabores pds INNER JOIN productos p ON pds.producto_id = p.id WHERE pds.pedido_detalle_id = :det_id");
                    $stmtSab->execute(['det_id' => $id_detalle]);
                    $mitades = $stmtSab->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($mitades)) {
                        $saboresText = "(" . implode(" / ", $mitades) . ")";
                    }
                }
            ?>
                <tr>
                    <td style="padding: 4px 0;">
                        <strong><?php echo (int)$it['cantidad']; ?>x</strong> <?php echo ((int)$it['es_mixta'] === 1) ? "Pizza Mixta Combinada" : htmlspecialchars($it['nombre_producto']); ?>
                        <?php if (!empty($saboresText)): ?><br><span style="font-size:12px; color:#333; padding-left: 10px; display: block; margin-top: 2px;">🌓🌓 <?php echo htmlspecialchars($saboresText); ?></span><?php endif; ?>
                    </td>
                    <td class="text-right" style="font-weight: bold; padding: 4px 0;">C$ <?php echo number_format($it['subtotal'], 2); ?></td>
                </tr>

                <!-- Desglose inline de adicionales o extras agregados -->
                <?php foreach ($extras as $ex):
                    $costo_extra = floatval($ex['cantidad']) * floatval($ex['precio_cobrado']);
                    $subtotal_calculado += $costo_extra;
                ?>
                    <tr>
                        <td class="extra-line">✦ +<?php echo (int)$ex['cantidad']; ?> <?php echo htmlspecialchars($ex['nombre']); ?></td>
                        <td class="text-right extra-line" style="font-weight: bold;">C$ <?php echo number_format($costo_extra, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="divider"></div>

   </table> <!-- Cierre de la tabla de productos de tu Página 6 -->
<div class="divider"></div>

<!-- 📊📊 SECCIÓN DE TOTALES, DESCUENTOS Y PROPINAS -->
<div style="font-size: 14px; line-height: 1.4; margin-bottom: 5px; font-family: monospace;">
    
    <!-- 1. TOTAL CONSUMO (SUBTOTAL DE PRODUCTOS) -->
    <div style="display:flex; justify-content:space-between;">
        <span>TOTAL CONSUMO:</span>
        <span>C$ <?php echo number_format($subtotal_calculado, 2); ?></span>
    </div>

    <!-- 2. DESCUENTO (Sólo se muestra si es mayor a 0) -->
    <?php if (floatval($pedidoInfo['monto_descuento']) > 0): ?>
        <div style="display:flex; justify-content:space-between; color: #000; font-weight: bold;">
            <span>DESCUENTO APLICADO:</span>
            <span>- C$ <?php echo number_format($pedidoInfo['monto_descuento'], 2); ?></span>
        </div>
    <?php endif; ?>

    <!-- 3. CARGO POR DELIVERY / ENVÍO (Sólo si aplica para delivery) -->
    <?php if (floatval($pedidoInfo['monto_envio']) > 0): ?>
        <div style="display:flex; justify-content:space-between;">
            <span>MONTO ENVÍO / DELIVERY:</span>
            <span>C$ <?php echo number_format($pedidoInfo['monto_envio'], 2); ?></span>
        </div>
    <?php endif; ?>

    <!-- 4. PROPINA VOLUNTARIA (Sólo se muestra si es mayor a 0) -->
    <?php if (floatval($pedidoInfo['monto_propina']) > 0): ?>
        <div style="display:flex; justify-content:space-between;">
            <span>PROPINA VOLUNTARIA:</span>
            <span>C$ <?php echo number_format($pedidoInfo['monto_propina'], 2); ?></span>
        </div>
    <?php endif; ?>

    <div class="divider"></div>

    <!-- 5. NETO TOTAL A PAGAR BRUTO -->
    <div style="display:flex; justify-content:space-between; font-size: 16px; font-weight: bold;">
        <span>TOTAL A PAGAR:</span>
        <span>C$ <?php echo number_format($pedidoInfo['total'], 2); ?></span>
    </div>
</div>

<div class="divider"></div>

<!-- 📊📊 SECCIÓN UNIFICADA Y CORREGIDA: DESGLOSE DE PAGO RECIBIDO -->
<div style="font-size: 13px; line-height: 1.35; margin-bottom: 5px;">
    <strong style="display:block; margin-bottom:4px; text-transform:uppercase; font-size:12px;">Desglose de Pago Recibido:</strong>
    <?php
    $total_abonado_cliente = 0;
    foreach ($pagos_recibidos as $pago):
        $total_abonado_cliente += floatval($pago['monto']);

        // Si el pago tiene un banco asignado, lo concatenamos al método de pago
        $txtBanco = !empty($pago['nombre_banco']) ? ' (' . strtoupper($pago['nombre_banco']) . ')' : '';
    ?>
        <div style="display:flex; justify-content:space-between; font-family:monospace; margin-bottom: 2px;">
            <span>
                &bull; <?php echo strtoupper($pago['metodo_pago']) . $txtBanco; ?>
                <?php echo !empty($pago['referencia']) ? '<span style="font-size:11px; color:#444;"> [Ref: ' . $pago['referencia'] . ']</span>' : ''; ?>:
            </span>
            <span style="font-weight:bold;">C$ <?php echo number_format($pago['monto'], 2); ?></span>
        </div>
    <?php endforeach; ?>
</div> <!-- AQUÍ SE CIERRA CORRECTAMENTE EL CONTENEDOR DEL DESGLOSE -->


    <div class="divider"></div>

    <!-- PIE DE COMPROBANTE OFICIAL UNIFICADO -->
    <p class="text-center" style="font-size: 12px; margin: 0; line-height: 1.5; font-weight: 500;">
        *** COMPROBANTE DE PAGO RECAUDADO ***<br>
        ¡MUCHAS GRACIAS POR SU PREFERENCIA! 🍕🦁<br>
        <span style="font-size: 9px; color:#333;">Jungle Dash POS Systems</span>
    </p>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>

</html>