<?php
// views/imprimir_ticket.php (Parte 1 de 3)
// 1. REQUERIMIENTO CRÍTICO: Forzamos la carga de la conexión nativa para evitar la pantalla en blanco
require_once __DIR__ . '/../config/conexion.php';

$pedido_id = intval($_GET['pedido_id'] ?? 0);
$db = (new Conexion())->conectar();

// 2. Extraemos los datos maestros de la comanda cobrada (CONEXIÓN Y EXECUTE UNIFICADOS)
$stmtPed = $db->prepare("SELECT p.*, m.numero_mesa, a.nombre as nombre_area, u.nombre as nombre_mesero 
                         FROM pedidos p 
                         LEFT JOIN mesas m ON p.mesa_id = m.id
                         LEFT JOIN areas a ON m.area_id = a.id
                         INNER JOIN usuarios u ON p.usuario_id = u.id
                         WHERE p.id = :id LIMIT 1");
$stmtPed->execute(['id' => $pedido_id]);
$pedidoInfo = $stmtPed->fetch(PDO::FETCH_ASSOC);

if (!$pedidoInfo) {
    die("🚨🚨🚨🚨 Error: Comanda inválida o no registrada en el sistema.");
}

// 3. Extraer el rastro contable del Pago Mixto recibido por caja (Cruza con tabla bancos)
$stmtPagos = $db->prepare("SELECT pp.metodo_pago, pp.monto, pp.referencia, b.nombre as nombre_banco 
                           FROM pedido_pagos pp 
                           LEFT JOIN bancos b ON pp.banco_id = b.id
                           WHERE pp.pedido_id = :id");
$stmtPagos->execute(['id' => $pedido_id]);
$pagos_recibidos = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);

// 4. Extraemos el desglose de productos activos pidiendo producto_id y categoria_id de forma explícita
$stmtDet = $db->prepare("SELECT pd.id, pd.producto_id, pd.cantidad, pd.precio_unitario, pd.subtotal, pd.es_mixta, p.nombre as nombre_producto, p.categoria_id
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
    height: auto !important; /* Evita restricciones fijas de altura */
}
.text-center { text-align: center; }
.text-right { text-align: right; }
.bold { font-weight: bold; }
.divider { border-top: 2px dashed #000000; margin: 8px 0; }

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
/* CONFIGURACIÓN DE IMPRESIÓN ADAPTATIVA (SIN SALTO DE PÁGINA) */
@media print {
    .no-print { display: none !important; }
    html, body {
        height: auto !important; /* El ticket se estira según los productos */
        overflow: visible !important; /* Elimina cualquier corte de scroll */
        padding: 0; margin: 0; width: 100%;
    }
    tr {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
}
</style>
</head>
<body>
<!-- PARTE 2 DE 3: CUERPO GENERAL Y LÓGICA INTERMEDIA -->
<!-- BOTONERA OPERATIVA DE LA TABLET (Oculta en papel) -->
<div class="no-print" style="background: #f1f5f9; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center;">
    <button onclick="window.print()" style="background: #2b8a3e; color: #fff; border: none; padding: 10px 15px; font-weight: bold; border-radius: 4px; cursor: pointer; margin-right: 5px;">🖨 Imprimir Factura</button>
    <button onclick="window.close();" style="background: #64748b; color: #fff; border: none; padding: 10px 15px; font-weight: bold; border-radius: 4px; cursor: pointer;">❌ Cerrar</button>
</div>

<!-- CUERPO DEL TICKET TÉRMICO -->
<div class="text-center">
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
        echo htmlspecialchars($pedidoInfo['nombre_area'] . ' - ' . $pedidoInfo['numero_mesa']);
    } else {
        echo 'Express / Delivery';
    }
    ?><br>
    <strong>Atendido por:</strong> <?php echo htmlspecialchars($pedidoInfo['nombre_mesero']); ?><br>
    <strong>Modalidad:</strong> <?php echo strtoupper($pedidoInfo['tipo_pedido']); ?><br>
</div>

<div class="divider"></div>

<!-- TABLA DEL MENÚ COMPACTA -->
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
$comida_procesada = [];
$bebidas_agrupadas = [];
$ID_CATEGORIA_BEBIDAS = 5; // ID de Bebidas en tu base de datos

// 🔄 PASO 1: SEPARACIÓN OPERATIVA Y AGRUPACIÓN CLASIFICADA POR ID DE PRODUCTO Y CATEGORÍA
foreach ($itemsComanda as $it) {
    $subtotal_calculado += floatval($it['subtotal']);
    $id_detalle = (int)$it['id'];
    $idProducto = (int)$it['producto_id']; 
    $idCategoria = (int)$it['categoria_id'];

    if ($idCategoria === $ID_CATEGORIA_BEBIDAS) {
        // Si la bebida comparte el mismo ID de producto, la consolidamos en caliente
        if (isset($bebidas_agrupadas[$idProducto])) {
            $bebidas_agrupadas[$idProducto]['cantidad'] += intval($it['cantidad']);
            $bebidas_agrupadas[$idProducto]['subtotal'] += floatval($it['subtotal']);
        } else {
            $bebidas_agrupadas[$idProducto] = $it;
        }
    } else {
        // Alimentos se guardan en su bandeja tradicional extrayendo extras y mitades
        $stmtExt = $db->prepare("SELECT pde.*, p.nombre FROM pedido_detalle_extras pde INNER JOIN productos p ON pde.producto_id = p.id WHERE pde.pedido_detalle_id = :det_id");
        $stmtExt->execute(['det_id' => $id_detalle]);
        $it['extras_lista'] = $stmtExt->fetchAll(PDO::FETCH_ASSOC);

        $it['sabores_texto'] = "";
        if ((int)$it['es_mixta'] === 1) {
            $stmtSab = $db->prepare("SELECT p.nombre FROM pedido_detalle_sabores pds INNER JOIN productos p ON pds.producto_id = p.id WHERE pds.pedido_detalle_id = :det_id");
            $stmtSab->execute(['det_id' => $id_detalle]);
            $mitades = $stmtSab->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($mitades)) {
                $it['sabores_texto'] = "(" . implode(" / ", $mitades) . ")";
            }
        }
        $comida_procesada[] = $it;
    }
}
// PARTE 3 DE 3: RENDERIZADO FINAL HTML Y TOTALES
// 🍕 2.A. RENDERIZAR COMIDA (Muestra las pizzas renglón por renglón con sus desgloses)
foreach ($comida_procesada as $it):
?>
    <tr>
        <td style="padding: 4px 0; vertical-align: top;">
            <strong><?php echo (int)$it['cantidad']; ?>x</strong>
            <?php echo ((int)$it['es_mixta'] === 1) ? "Pizza Mixta Combinada" : htmlspecialchars($it['nombre_producto']); ?>
            <?php if (!empty($it['sabores_texto'])): ?>
                <br><span style="font-size:12px; color:#333; padding-left: 10px; display: block; margin-top: 2px;">🌓🌓🌓🌓 <?php echo htmlspecialchars($it['sabores_texto']); ?></span>
            <?php endif; ?>
        </td>
        <td class="text-right" style="font-weight: bold; padding: 4px 0; font-family: monospace;">
            C$ <?php echo number_format($it['subtotal'], 2); ?>
        </td>
    </tr>

    <?php if (!empty($it['extras_lista'])): ?>
        <?php foreach ($it['extras_lista'] as $ex): 
            $costo_extra = floatval($ex['cantidad']) * floatval($ex['precio_cobrado']);
            $subtotal_calculado += $costo_extra;
        ?>
            <tr>
                <td class="extra-line" style="font-size:11.5px; padding-left:8px; color:#555;">
                    ✦ +<?php echo (int)$ex['cantidad']; ?> <?php echo htmlspecialchars($ex['nombre']); ?>
                </td>
                <td class="text-right extra-line" style="font-weight: bold; font-size:11.5px; font-family: monospace;">
                    C$ <?php echo number_format($costo_extra, 2); ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
<?php 
endforeach; 

// 🥤 2.B. RENDERIZAR BEBIDAS TOTALMENTE CONSOLIDADAS EN UNA SOLA LÍNEA AL FINAL
if (!empty($bebidas_agrupadas)): 
?>
    <tr>
        <td colspan="2" style="text-align: center; font-size: 11px; font-weight: bold; padding: 6px 0; border-top: 1px dashed #000000; border-bottom: 1px dashed #000000; margin-top: 4px;">
            --- 🥤 BEBIDAS Y REFRESCOS ---
        </td>
    </tr>
    <?php foreach ($bebidas_agrupadas as $bebida): ?>
        <tr>
            <td style="padding: 3px 0; vertical-align: top;">
                <strong><?php echo (int)$bebida['cantidad']; ?>x</strong>
                <?php echo htmlspecialchars($bebida['nombre_producto']); ?>
            </td>
            <td class="text-right" style="padding: 3px 0; vertical-align: top; font-family:monospace; font-weight:bold;">
                C$ <?php echo number_format($bebida['subtotal'], 2); ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>

<div class="divider"></div>

<!-- BLOQUE FINANCIERO: SUB-TOTALES, DESCUENTOS Y PROPINA EXTRAÍDOS -->
<div style="font-size: 14px; line-height: 1.4; margin-bottom: 5px; font-family: monospace;">
    <div style="display:flex; justify-content:space-between;">
        <span>TOTAL CONSUMO:</span>
        <span>C$ <?php echo number_format($subtotal_calculado, 2); ?></span>
    </div>
    <?php if (floatval($pedidoInfo['monto_descuento']) > 0): ?>
        <div style="display:flex; justify-content:space-between; color: #000; font-weight: bold;">
            <span>DESCUENTO APLICADO:</span>
            <span>- C$ <?php echo number_format($pedidoInfo['monto_descuento'], 2); ?></span>
        </div>
    <?php endif; ?>
    <?php if (floatval($pedidoInfo['monto_envio']) > 0): ?>
        <div style="display:flex; justify-content:space-between;">
            <span>MONTO ENVÍO / DELIVERY:</span>
            <span>C$ <?php echo number_format($pedidoInfo['monto_envio'], 2); ?></span>
        </div>
    <?php endif; ?>
    <?php if (floatval($pedidoInfo['monto_propina']) > 0): ?>
        <div style="display:flex; justify-content:space-between;">
            <span>PROPINA VOLUNTARIA:</span>
            <span>C$ <?php echo number_format($pedidoInfo['monto_propina'], 2); ?></span>
        </div>
    <?php endif; ?>
    <div class="divider"></div>
    <div style="display:flex; justify-content:space-between; font-size: 16px; font-weight: bold;">
        <span>TOTAL A PAGAR:</span>
        <span>C$ <?php echo number_format($pedidoInfo['total'], 2); ?></span>
    </div>
</div>

<div class="divider"></div>

<!-- MÓDULO CONTABLE: DESGLOSE AUDITABLE DEL PAGO MIXTO Y BANCO EN TICKET -->
<div style="font-size: 13px; line-height: 1.35; margin-bottom: 5px;">
    <strong style="display:block; margin-bottom:4px; text-transform:uppercase; font-size:12px;">Desglose de Pago Recibido:</strong>
    <?php
    $total_abonado_cliente = 0;
    foreach ($pagos_recibidos as $pago):
        $total_abonado_cliente += floatval($pago['monto']);
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
</div>

<!-- CUADRO DE VUELTO CON ALTO CONTRASTE (OPTIMIZADO PARA PAPEL TÉRMICO) -->
<?php
$vuelto_cambio = $total_abonado_cliente - floatval($pedidoInfo['total']);
if ($vuelto_cambio > 0.01):
?>
    <div style="display:flex; justify-content:space-between; align-items:center; font-family:monospace; margin-top: 8px; border: 3px double #000000; padding: 6px 4px; font-size: 15px; background: #ffffff;">
        <span class="bold" style="color: #000000;">💸💸 CAMBIO / VUELTO:</span>
        <span class="bold" style="font-size: 17px; color: #000000;">C$ <?php echo number_format($vuelto_cambio, 2); ?></span>
    </div>
<?php endif; ?>

<div class="divider"></div>

<p class="text-center" style="font-size: 12px; margin: 0; line-height: 1.5; font-weight: 500;">
    *** COMPROBANTE DE PAGO RECAUDADO ***<br>
    ¡MUCHAS GRACIAS POR SU PREFERENCIA! 🍕🍕 <br>
    <span style="font-size: 9px; color:#333;">Jungle Dash POS Systems</span>
</p>

<script>
window.onload = function() {
    window.print();
}
</script>
</body>
</html>
