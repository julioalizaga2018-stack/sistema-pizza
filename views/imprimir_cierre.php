<?php
// views/imprimir_cierre.php (Parte 1 de 2)
// 1. REQUERIMIENTO CRÍTICO: Forzamos la conexión nativa para evitar la pantalla en blanco
require_once __DIR__ . '/../config/conexion.php'; 

$turno_id = intval($_GET['turno_id'] ?? 0);
$db = (new Conexion())->conectar();

// 2. Extraemos el rastro maestro del turno clausurado desde tu tabla caja_turnos
$stmtTurno = $db->prepare("SELECT ct.*, u.nombre as nombre_cajero 
                           FROM caja_turnos ct 
                           INNER JOIN usuarios u ON ct.usuario_id = u.id 
                           WHERE ct.id = :id LIMIT 1");
$stmtTurno->execute(['id' => $turno_id]);
$turno = $stmtTurno->fetch(PDO::FETCH_ASSOC);

if (!$turno) {
    die("🚨🚨 Error crítico: El turno de caja solicitado no existe o es inválido.");
}

// 3. Extraemos el desglose de ventas reales del sistema por pasarela combinada para este turno
$sqlVentas = "SELECT 
                SUM(CASE WHEN metodo_pago = 'efectivo' THEN monto ELSE 0 END) as v_efectivo,
                SUM(CASE WHEN metodo_pago = 'tarjeta' THEN monto ELSE 0 END) as v_tarjeta,
                SUM(CASE WHEN metodo_pago = 'transferencia' THEN monto ELSE 0 END) as v_transferencia
              FROM pedido_pagos 
              WHERE caja_turno_id = :turno_id";
$stmtV = $db->prepare($sqlVentas);
$stmtV->execute(['turno_id' => $turno_id]);
$ventas = $stmtV->fetch(PDO::FETCH_ASSOC);

// 4. Extraemos el acumulado e historial de vales / movimientos manuales de caja
$stmtTotMovs = $db->prepare("SELECT 
                                SUM(CASE WHEN tipo = 'entrada' THEN monto ELSE 0 END) as t_entradas,
                                SUM(CASE WHEN tipo = 'salida' THEN monto ELSE 0 END) as t_salidas
                             FROM caja_movimientos WHERE caja_turno_id = :turno_id");
$stmtTotMovs->execute(['turno_id' => $turno_id]);
$tot_movs = $stmtTotMovs->fetch(PDO::FETCH_ASSOC);

$entradas_manuales = floatval($tot_movs['t_entradas'] ?? 0);
$salidas_manuales  = floatval($tot_movs['t_salidas'] ?? 0);

// Lista de la bitácora física de vales de gastos para imprimir al final
$stmtBit = $db->prepare("SELECT * FROM caja_movimientos WHERE caja_turno_id = :turno_id ORDER BY id ASC");
$stmtBit->execute(['turno_id' => $turno_id]);
$bitacora_vales = $stmtBit->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cierre_Caja_Turno_#<?php echo $turno_id; ?></title>
    <style>
        /* Estilos térmicos de alta nítidez ultra-compactos Courier New */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Courier New', Courier, monospace; 
            font-size: 13px; 
            color: #000000; 
            background: #ffffff; 
            width: 275px; 
            padding: 8px; 
            margin: 0 auto; 
            line-height: 1.3; 
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000000; margin: 8px 0; }
        .ticket-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        .ticket-table td { padding: 3px 0; vertical-align: top; }
        .header-ticket h2 { font-size: 17px; text-transform: uppercase; margin-bottom: 3px; font-weight: 800; letter-spacing: 0.5px; }
        
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; width: 100%; }
            @page { margin: 0; }
        }
    </style>
</head>
<body>

    <!-- BOTONERA OPERATIVA WEB (Oculta en el papel térmico) -->
    <div class="no-print" style="background: #f1f5f9; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center;">
        <button onclick="window.print()" style="background: #2b8a3e; color: #fff; border: none; padding: 8px 14px; font-weight: bold; border-radius: 4px; cursor: pointer;">🖨️ Imprimir Z de Cierre</button>
        <button onclick="window.close();" style="background: #64748b; color: #fff; border: none; padding: 8px 14px; font-weight: bold; border-radius: 4px; cursor: pointer; margin-left: 5px;">❌ Cerrar</button>
    </div>
    <!-- CABECERA DEL ARQUEO DE TURNO -->
    <div class="text-center header-ticket">
        <img src="public/uploads/logo_jungle_1784916357.jpeg" style="width: 120px; height: auto; margin-bottom: 5px; display: inline-block; object-fit: contain;" onerror="this.style.display='none';">
        <h2>JUNGLE PIZZA</h2>
        <p class="bold" style="font-size: 14px; margin-top: 2px;">AUDITORÍA DE ARQUEO Z</p>
        <p style="font-size: 11px;">Clausura Oficial de Terminal de Caja</p>
    </div>

    <div class="divider"></div>

    <!-- METADATOS DEL TURNO -->
    <div style="font-size: 12.5px; line-height: 1.4;">
        <span class="bold">Turno ID:</span> #<?php echo $turno_id; ?><br>
        <span class="bold">Cajero:</span> <?php echo htmlspecialchars($turno['nombre_cajero']); ?><br>
        <span class="bold">Apertura:</span> <?php echo date('d/m/Y h:i A', strtotime($turno['fecha_apertura'])); ?><br>
        <span class="bold">Cierre:</span> <?php echo date('d/m/Y h:i A', strtotime($turno['fecha_cierre'])); ?><br>
        <span class="bold">Estado:</span> TERMINADO / <?php echo strtoupper($turno['estado']); ?><br>
    </div>

    <div class="divider"></div>

    <!-- 📊 SECCIÓN 1: BALANCE TEÓRICO EN VIVO (LO QUE REPORTA EL SISTEMA) -->
    <div class="bold" style="font-size: 11px; margin-bottom: 4px; text-transform: uppercase;">1. Resumen Esperado Sistema:</div>
    <table class="ticket-table">
        <tr>
            <td>Fondo Inicial Base:</td>
            <td class="text-right">C$ <?php echo number_format($turno['monto_inicial'], 2); ?></td>
        </tr>
        <tr>
            <td>(+) Ventas en Efectivo:</td>
            <td class="text-right">C$ <?php echo number_format(floatval($ventas['v_efectivo']), 2); ?></td>
        </tr>
        <tr>
            <td>(+) Inyecciones Manuales:</td>
            <td class="text-right">C$ <?php echo number_format($entradas_manuales, 2); ?></td>
        </tr>
        <tr>
            <td>(&minus;) Gastos / Vales Caja:</td>
            <td class="text-right">&minus;C$ <?php echo number_format($salidas_manuales, 2); ?></td>
        </tr>
        <tr style="border-top: 1px dotted #000; font-weight: bold;">
            <td>Total Efectivo Esperado:</td>
            <td class="text-right">C$ <?php echo number_format(floatval($turno['monto_inicial']) + floatval($ventas['v_efectivo']) + $entradas_manuales - $salidas_manuales, 2); ?></td>
        </tr>
        <tr>
            <td>💳 Ventas por Tarjeta POS:</td>
            <td class="text-right">C$ <?php echo number_format(floatval($ventas['v_tarjeta']), 2); ?></td>
        </tr>
        <tr>
            <td>📲 Ventas por Transferencia:</td>
            <td class="text-right">C$ <?php echo number_format(floatval($ventas['v_transferencia']), 2); ?></td>
        </tr>
        <tr style="border-top: 1px dashed #000; font-weight: bold; font-size: 13.5px;">
            <td style="padding-top: 4px;">TOTAL ESPERADO GLOBAL:</td>
            <td class="text-right" style="padding-top: 4px;">C$ <?php echo number_format((floatval($turno['monto_inicial']) + floatval($ventas['v_efectivo']) + $entradas_manuales - $salidas_manuales) + floatval($ventas['v_tarjeta']) + floatval($ventas['v_transferencia']), 2); ?></td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- 🔒 SECCIÓN 2: ENTREGA FÍSICA REAL (LO QUE EL CAJERO DEPOSITÓ) -->
    <div class="bold" style="font-size: 11px; margin-bottom: 4px; text-transform: uppercase;">2. Declaración Física Cajero:</div>
    <table class="ticket-table" style="font-weight: bold;">
        <?php
        // Recuperamos lo que se guardó en la tabla caja_turnos al cerrar
        $total_contado_por_cajero = floatval($turno['monto_final_real']);
        ?>
        <tr>
            <td style="font-weight: normal;">Efectivo Entregado:</td>
            <td class="text-right">C$ <?php echo number_format(floatval($turno['monto_final_real']) - floatval($turno['total_tarjeta']) - floatval($turno['total_transferencia']), 2); ?></td>
        </tr>
        <tr>
            <td style="font-weight: normal;">Váuchers de Tarjeta:</td>
            <td class="text-right">C$ <?php echo number_format($turno['total_tarjeta'], 2); ?></td>
        </tr>
        <tr>
            <td style="font-weight: normal;">Cuentas Bancarias:</td>
            <td class="text-right">C$ <?php echo number_format($turno['total_transferencia'], 2); ?></td>
        </tr>
        <tr style="border-top: 1px dashed #000; font-size: 13.5px; font-weight: 900;">
            <td style="padding-top: 4px;">TOTAL CONTRALADO:</td>
            <td class="text-right" style="padding-top: 4px;">C$ <?php echo number_format($total_contado_por_cajero, 2); ?></td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- ⚖️ SECCIÓN 3: RESULTADO FINAL DEL ARQUEO DE AUDITORÍA -->
    <div class="bold" style="font-size: 11px; margin-bottom: 4px; text-transform: uppercase;">3. Dictamen de Cuadre:</div>
    <table class="ticket-table" style="font-size: 14px; font-weight: bold;">
        <tr>
            <td>
                <?php 
                $dif = floatval($turno['diferencia']);
                if ($dif === 0.00) {
                    echo "⭐ STATUS: CAJA CUADRADA";
                } elseif ($dif < 0) {
                    echo "⚠ STATUS: FALTANTE CAJA";
                } else {
                    echo "🎁 STATUS: SOBRANTE CAJA";
                }
                ?>
            </td>
            <td class="text-right" style="<?php echo ($dif < 0) ? 'background:#000; color:#fff; padding:0 3px;' : ''; ?>">
                C$ <?php echo number_format($dif, 2); ?>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- 📋 SECCIÓN 4: DESGLOSE COMPLETO DE VALES Y MOVIMIENTOS INTERNOS -->
    <div class="bold" style="font-size: 11px; margin-bottom: 4px; text-transform: uppercase;">4. Desgloses de Bitácora Manual:</div>
    <table class="ticket-table" style="font-size: 11.5px;">
        <tbody>
            <?php if (empty($bitacora_vales)): ?>
                <tr>
                    <td colspan="2" style="color:#555; font-style:italic;">No se reportaron movimientos manuales.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($bitacora_vales as $v): ?>
                    <tr style="border-bottom: 1px dotted #ccc;">
                        <td style="padding: 2px 0;">
                            <strong>[<?php echo strtoupper($v['tipo']); ?>]</strong> <?php echo htmlspecialchars($v['motivo']); ?>
                        </td>
                        <td class="text-right bold" style="white-space:nowrap; vertical-align: bottom;">
                            C$ <?php echo number_format($v['monto'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="divider"></div>

    <!-- OBSERVACIONES -->
    <?php if (!empty($turno['observaciones'])): ?>
        <div style="font-size:12px; margin-bottom: 15px;">
            <span class="bold">Observaciones/Novedades:</span><br>
            <span style="color:#222; font-style: italic;"><?php echo htmlspecialchars($turno['observaciones']); ?></span>
        </div>
        <div class="divider"></div>
    <?php endif; ?>

    <!-- BLOQUE DE FIRMAS LEGAL DE ENTREGA -->
    <div style="margin-top: 40px; text-align: center; font-size: 12px;">
        <p>___________________________</p>
        <p class="bold" style="margin-top: 3px;">Firma de Cajero Entregador</p>
        <p style="font-size: 10px; color:#555;"><?php echo htmlspecialchars($turno['nombre_cajero']); ?></p>
        
        <p style="margin-top: 35px;">___________________________</p>
        <p class="bold" style="margin-top: 3px;">Firma Auditor / Supervisor</p>
        <p style="font-size: 9px; color:#666; margin-top: 15px;">Arqueo Cerrado por Sistema Jungle Dash</p>
    </div>

    <!-- Disparador automático de impresión térmica -->
    <script>
        window.onload = function() { window.print(); }
    </script>
</body>
</html>
