<?php
// views/precuenta_ticket.php (Parte 1 de 2 - Versión Original Limpia)

$pedido_id = intval($_GET['pedido_id'] ?? 0);
$db = (new Conexion())->conectar();

// Extraemos los datos de la comanda
$stmtPed = $db->prepare("SELECT p.*, m.numero_mesa, a.nombre as nombre_area, u.nombre as nombre_mesero 
                         FROM pedidos p 
                         LEFT JOIN mesas m ON p.mesa_id = m.id
                         LEFT JOIN areas a ON m.area_id = a.id
                         INNER JOIN usuarios u ON p.usuario_id = u.id 
                         WHERE p.id = :id LIMIT 1");
$stmtPed->execute(['id' => $pedido_id]);
$pedidoInfo = $stmtPed->fetch(PDO::FETCH_ASSOC);

if (!$pedidoInfo) {
    die("🚨 Error: Comanda inválida.");
}

// Extraemos el desglose de productos activos
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
    <title>Precuenta_Ticket_#<?php echo $pedido_id; ?></title>
   <style>
/* 
==========================================================================
🎨🎨 TIPOGRAFÍA SANS-SERIF ULTRA-COMPACTA: MÁXIMO AHORRO DE PAPEL TÉRMICO
========================================================================== */
body { 
    font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
    font-size: 14.5px; 
    font-weight: 500; /* Letras definidas y esbeltas */
    letter-spacing: -0.4px; /* 🌟🌟 Junta las letras eliminando aire horizontal */
    line-height: 1.25; /* 🌟🌟 Reduce el espacio vertical entre líneas de texto */
    color: #000000; 
    background: #ffffff; 
    margin: 0; 
    padding: 6px; 
    width: 275px; 
    height: auto !important; /* 🌟 Evita restricciones físicas de altura */
}

.text-center { text-align: center; }
.text-right { text-align: right; }

/* Divisor compacto de alta densidad */
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
    padding: 3px 0; /* 🌟🌟 Encoge las filas para que el ticket sea más corto */
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

/* 
==========================================================================
🖨🖨 CONFIGURACIÓN DE IMPRESIÓN CORREGIDA (ROLLO CONTINUO SIN FIN)
========================================================================== */
@media print {
    .no-print { 
        display: none !important; 
    }
    
    html, body { 
        height: auto !important;     /* 🔥 Permite que el ticket crezca dinámicamente */
        overflow: visible !important; /* 🔥 Quita cualquier barra de scroll o corte en impresión */
        padding: 0; 
        margin: 0; 
        width: 100%; 
    }
    
    /* 🌟 Evita que un platillo o un extra se parta por la mitad si coincide con un salto de página */
    tr {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
}
</style>

</head>
<body>

    <!-- BOTONERA OPERATIVA DE LA TABLET -->
    <div class="no-print" style="background: #f1f5f9; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center;">
        <button onclick="window.print()" style="background: #2b8a3e; color: #fff; border: none; padding: 10px 15px; font-weight: bold; border-radius: 4px; cursor: pointer; margin-right: 5px;">🖨️ Imprimir Ticket</button>
        <button onclick="window.close();" style="background: #64748b; color: #fff; border: none; padding: 10px 15px; font-weight: bold; border-radius: 4px; cursor: pointer;">❌ Cerrar</button>
    </div>

    <!-- CUERPO DEL TICKET TÉRMICO -->
    <div class="text-center">
        <!-- 🦁 LOGOTIPO COMERCIAL EN HTML PURO (Sustituimos el validador físico de PHP para evitar caídas) -->
        <img src="public/uploads/logo_jungle_1784916357.jpeg" style="width: 140px; height: auto; margin-bottom: 6px; display: inline-block; object-fit: contain;" onerror="this.style.display='none';">
        
        <h2 style="margin: 0; font-size: 18px; font-weight: 800; letter-spacing: 0.5px;">JUNGLE PIZZA</h2>
        <p style="margin: 4px 0; font-size: 13px; font-weight: bold;">Precuenta / Estado de Cuenta</p>
        <p style="margin: 2px 0; font-size: 11px;">Fecha: <?php echo date('d/m/Y h:i A', strtotime($pedidoInfo['created_at'])); ?></p>
    </div>

    <div class="divider"></div>

    <div style="font-size: 14px; line-height: 1.4;">
        <strong>Ticket #:</strong> <?php echo $pedido_id; ?><br>
        <strong>Mesa:</strong> <?php echo htmlspecialchars($pedidoInfo['numero_mesa'] ?? 'Express'); ?><br>
        <strong>Mesero:</strong> <?php echo htmlspecialchars($pedidoInfo['nombre_mesero']); ?><br>
        <strong>Modalidad:</strong> <?php echo strtoupper($pedidoInfo['tipo_pedido']); ?><br>
    </div>

    <div class="divider"></div>
    <!-- 🛒 TABLA ORIGINAL DE ELEMENTOS DE CUENTA -->
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

                // Extracción relacional de extras en español (Tu código original)
                $stmtExt = $db->prepare("SELECT pde.*, p.nombre FROM pedido_detalle_extras pde INNER JOIN productos p ON pde.producto_id = p.id WHERE pde.pedido_detalle_id = :det_id");
                $stmtExt->execute(['det_id' => $id_detalle]);
                $extras = $stmtExt->fetchAll(PDO::FETCH_ASSOC);

                // Extracción relacional de mitades combinadas (Tu código original)
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
                        <?php if(!empty($saboresText)): ?><br><span style="font-size:12px; color:#333; padding-left: 10px; display: block; margin-top: 2px;">🌓 <?php echo htmlspecialchars($saboresText); ?></span><?php endif; ?>
                    </td>
                    <td class="text-right" style="font-weight: bold; padding: 4px 0;">C$ <?php echo number_format($it['subtotal'], 2); ?></td>
                </tr>

                <!-- Desglose de extras inline original -->
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

    <!-- CONTROL DE TOTALES FIEL A TU ESQUEMA ORIGINAL -->
    <table class="ticket-table totals-section">
        <tr>
            <td style="font-weight: normal; color: #333;">Consumo Neto:</td>
            <td class="text-right">C$ <?php echo number_format($subtotal_calculado, 2); ?></td>
        </tr>
        <?php if ($pedidoInfo['tipo_pedido'] === 'local'): ?>
        <tr>
            <td style="font-weight: normal; color: #333;">Propina Sugerida (10%):</td>
            <td class="text-right">C$ <?php echo number_format($subtotal_calculado * 0.10, 2); ?></td>
        </tr>
        <?php elseif ($pedidoInfo['tipo_pedido'] === 'delivery'): ?>
        <tr>
            <td style="font-weight: normal; color: #333;">Monto Envío:</td>
            <td class="text-right">C$ <?php echo number_format(floatval($pedidoInfo['monto_envio']), 2); ?></td>
        </tr>
        <?php endif; ?>
        <tr style="font-size: 17px; border-top: 2px dashed #000; font-weight: 900;">
            <td style="padding-top: 8px;">TOTAL FINAL:</td>
            <td class="text-right" style="padding-top: 8px;">C$ <?php echo number_format(floatval($pedidoInfo['total']), 2); ?></td>
        </tr>
    </table>

    <div class="divider"></div>
    <p class="text-center" style="font-size: 12px; margin: 0; line-height: 1.5; font-weight: 500;">
        *** DOCUMENTO DE REVISIÓN INTERNA ***<br>
        NO ES UN COMPROBANTE FISCAL<br>
        ¡Muchas gracias por su preferencia! 🦁
    </p>

    <script>
        // Disparador de impresión nativo original de tu vista
        window.onload = function() { window.print(); }
    </script>
</body>
</html>
