<?php
// views/gestion_caja.php (Parte 1 de 3)
// 1. Requerimos el modelo de caja para auditar el estado del turno
require_once __DIR__ . '/../models/CajaModelo.php';
$cajaModel = new CajaModelo();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$usuario_id = $_SESSION['usuario_id'] ?? 0;

// 2. Verificación quirúrgica: ¿Existe un turno abierto para este cajero?
$turno_activo = $cajaModel->obtenerTurnoActivo($usuario_id);

$ventas_sistema = null;
$efectivo_esperado_sistema = 0.00;
$entradas_manuales = 0.00;
$salidas_manuales = 0.00;
$lista_movimientos_turno = [];

if ($turno_activo) {
    $turno_id = $turno_activo['id'];

    // Sumatoria de ventas del sistema por pedido_pagos
    $ventas_sistema = $cajaModel->calcularVentasDelTurno($turno_id);

    // 🚀 NUEVO: Cargamos los movimientos internos (ingresos y gastos) del turno actual
    $totales_movs = $cajaModel->obtenerTotalesMovimientos($turno_id);
    $entradas_manuales = floatval($totales_movs['total_entradas'] ?? 0);
    $salidas_manuales  = floatval($totales_movs['total_salidas'] ?? 0);

    // Lista de bitácora para la tabla visual
    $lista_movimientos_turno = $cajaModel->obtenerMovimientosDelTurno($turno_id);

    // 🧮 NUEVA MATEMÁTICA DE CONTROL: Fondo Inicial + Ventas Efectivo + Entradas de Caja - Gastos de Sucursal
    $efectivo_esperado_sistema = floatval($turno_activo['monto_inicial']) + floatval($ventas_sistema['calculado_efectivo']) + $entradas_manuales - $salidas_manuales;
}

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
    <title>Gestión de Caja - Jungle Pizza</title>
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    <style>
        .caja-layout-grid {
            display: grid !important;
            grid-template-columns: 1fr;
            gap: 25px;
            margin-top: 20px;
            width: 100%;
        }

        .caja-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(27, 67, 50, 0.05);
            padding: 25px;
            border-top: 4px solid var(--verde-claro, #52b788);
        }

        .caja-card h3 {
            color: var(--verde-oscuro, #1b4332);
            font-size: 1.25rem;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--verde-menta, #d8f3dc);
            padding-bottom: 8px;
        }

        .form-group-caja {
            margin-bottom: 15px;
        }

        .form-group-caja label {
            display: block !important;
            margin-bottom: 6px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            color: var(--verde-oscuro, #1b4332) !important;
        }

        .form-control-caja {
            width: 100% !important;
            padding: 12px;
            border: 2px solid #e2e8f0 !important;
            border-radius: 8px !important;
            box-sizing: border-box !important;
            font-size: 1rem !important;
            font-weight: bold !important;
            font-family: monospace !important;
            background-color: #fafbfc !important;
            transition: all 0.2s ease;
        }

        .form-control-caja:focus {
            outline: none !important;
            border-color: var(--verde-claro, #52b788) !important;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #edf2f7;
            margin-top: 10px;
        }

        .jungle-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            min-width: 450px;
            font-size: 13px;
        }

        .jungle-table th {
            background-color: var(--verde-oscuro, #1b4332);
            color: #ffffff;
            padding: 10px;
            text-transform: uppercase;
            font-size: 11px;
        }

        .jungle-table td {
            padding: 10px;
            border-bottom: 1px solid #edf2f7;
        }

        .badge-mov {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .mov-in {
            background-color: #ebfbee;
            color: #2b8a3e;
        }

        .mov-out {
            background-color: #fff5f5;
            color: #c92a2a;
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

        .btn-caja-act {
    border: none;
    padding: 14px;
    width: 100%;
    border-radius: 8px;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 0.95rem;
    cursor: pointer;
    transition: background-color 0.2s, transform 0.1s; /* Transición suave */
    
    /* Configuración de colores principales */
    background-color: #f43939; /* Fondo rojo */
    color: #ffffff;            /* Texto blanco */
}

/* Efecto visual al pasar el mouse por encima */
.btn-caja-act:hover {
    background-color: #d32f2f; /* Rojo más oscuro */
}

/* Efecto visual al hacer clic */
.btn-caja-act:active {
    transform: scale(0.98);    /* Pequeño hundimiento */
}


        @media (min-width: 992px) {
            .caja-layout-grid {
                grid-template-columns: 360px 1fr;
                align-items: start;
            }
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
            <h2>Control y Cuadre de Terminal de Caja</h2>
            <p style="color: #666; margin-bottom: 20px;">Monitorea el efectivo en vivo, registra vales de gastos internos y realiza el arqueo final por canal de pago.</p>

            <?php if ($msg_error): ?><div class="alert alert-error">⚠ <?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
            <?php if ($msg_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div><?php endif; ?>

            <!-- APERTURA CONDICIONAL -->
            <!-- Reemplázala por esta versión corregida: -->
            <?php if (!$turno_activo): ?>


                <div class="caja-card" style="max-width: 600px; margin: 20px auto;">
                    <h3>💵 Iniciar Turno Comercial</h3>
                    <form action="<?php echo URL_BASE; ?>controllers/CajaController.php" method="POST">
                        <input type="hidden" name="accion" value="abrir_caja">
                        <div class="form-group-caja">
                            <label>Fondo Base Inicial de Cambio (C$ Vuelto) *</label>
                            <input type="number" step="0.01" min="0" name="monto_inicial" class="form-control-caja" required value="0.00" onfocus="this.select();">
                        </div>
                        <button type="submit" class="btn-caja-act" style="background: var(--verde-selva, #2d6a4f);">🚀 Abrir Turno de Caja</button>
                    </form>
                </div>

            <?php else: ?>
                <div class="caja-layout-grid">
                    <!-- COLUMNA 1: REGISTRO DE MOVIMIENTOS INTERNOS Y VALES DE GASTOS -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">

                        <!-- Formulario de Movimiento Manual -->
                        <div class="caja-card" style="border-top-color: #3b82f6;">
                            <h3>💸 Movimiento Rápido de Caja</h3>
                            <form action="<?php echo URL_BASE; ?>controllers/CajaController.php" method="POST">
                                <input type="hidden" name="accion" value="registrar_movimiento">
                                <input type="hidden" name="turno_id" value="<?php echo $turno_activo['id']; ?>">

                                <div class="form-group-caja">
                                    <label>Tipo de Operación *</label>
                                    <select name="tipo_movimiento_manual" class="form-control-caja" required style="font-family: inherit; font-size: 13px;">
                                        <option value="salida">➖ Salida (Gasto / Vale / Pago Insumo)</option>
                                        <option value="entrada">➕ Entrada (Inyección de Sencillo / Cambio)</option>
                                    </select>
                                </div>

                                <div class="form-group-caja">
                                    <label>Monto en Córdobas (C$) *</label>
                                    <input type="number" step="0.01" min="0.01" name="monto_movimiento" class="form-control-caja" required placeholder="Ej. 150.00" onfocus="this.select();">
                                </div>

                                <div class="form-group-caja">
                                    <label>Justificación o Motivo *</label>
                                    <input type="text" name="motivo_movimiento" class="form-control-caja" required style="font-weight: normal; font-family: inherit; font-size: 13px;" placeholder="Ej. Compra de bolsas de hielo para bar">
                                </div>

                                <button type="submit" class="btn-caja-act" style="background: #3b82f6; padding: 10px;">
                                    💾 Grabar en Bitácora
                                </button>
                            </form>
                        </div>

                        <!-- Tabla Histórica de Vales del Turno -->
                        <div class="caja-card" style="border-top-color: #64748b;">
                            <h3>📋 Bitácora de Flujos del Turno</h3>
                            <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                                <table class="jungle-table">
                                    <thead>
                                        <tr>
                                            <th>Operación</th>
                                            <th>Monto</th>
                                            <th>Motivo / Concepto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($lista_movimientos_turno)): ?>
                                            <tr>
                                                <td colspan="3" style="text-align: center; color: #999; padding: 15px;">
                                                    No hay movimientos manuales en este turno.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($lista_movimientos_turno as $m): ?>
                                                <tr>
                                                    <td>
                                                        <?php if ($m['tipo'] === 'entrada'): ?>
                                                            <span class="badge-mov mov-in">➕ Entrada</span>
                                                        <?php else: ?>
                                                            <span class="badge-mov mov-out">➖ Salida</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="font-family: monospace; font-weight: bold;">
                                                        C$ <?php echo number_format($m['monto'], 2); ?>
                                                    </td>
                                                    <td style="color: #475569; font-size: 12px;">
                                                        <?php echo htmlspecialchars($m['motivo']); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div> <!-- Fin de Columna 1 -->
                    <!-- COLUMNA 2: REGISTRO DE ARQUEO FÍSICO Y CLAUSURA DE TURNO -->
                    <div class="caja-card" style="border-top-color: var(--verde-claro, #52b788);">
                        <h3>🔒 Formulario de Arqueo y Cierre Final</h3>

                        <div class="info-turno-box" style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0; font-size:13px; margin-bottom:15px; color:#475569;">
                            <strong>Turno de Caja Activo: #<?php echo $turno_activo['id']; ?></strong><br>
                            📅 Apertura: <code><?php echo date('d/m/Y g:i a', strtotime($turno_activo['fecha_apertura'])); ?></code><br>
                            💵 Fondo Inicial: <strong>C$ <?php echo number_format($turno_activo['monto_inicial'], 2); ?></strong>
                        </div>

                        <form action="<?php echo URL_BASE; ?>controllers/CajaController.php" method="POST" onsubmit="return confirm('¿Estás seguro de realizar el arqueo final y cerrar la caja? Esta acción inhabilitará la facturación hasta el próximo turno.');">
                            <input type="hidden" name="accion" value="cerrar_caja">
                            <!-- 🔒 CANDADO DE CAJA: Forzamos el nombre único 'turno_id' que el controlador procesa con filter_var -->
                            <input type="hidden" id="turno_id" name="turno_id" value="<?php echo (int)$turno_activo['id']; ?>">


                            <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                                <div class="form-group-caja">
                                    <label>Efectivo Físico Contado en Gaveta *</label>
                                    <input type="number" step="0.01" min="0" name="efectivo_real" id="efectivo_real" class="form-control-caja" required value="0.00" onfocus="this.select();" oninput="calcularDiferenciaCaliente();">
                                    <small style="color: #64748b; display:block; margin-top:4px;">Suma total de billetes y monedas (incluye el fondo inicial).</small>
                                </div>

                                <div class="form-group-caja">
                                    <label>Total Real en Váuchers (Tarjeta) *</label>
                                    <input type="number" step="0.01" min="0" name="tarjeta_real" id="tarjeta_real" class="form-control-caja" required value="0.00" onfocus="this.select();" oninput="calcularDiferenciaCaliente();">
                                </div>

                                <div class="form-group-caja">
                                    <label>Total Real en Cuenta (Transferencias) *</label>
                                    <input type="number" step="0.01" min="0" name="transferencia_real" id="transferencia_real" class="form-control-caja" required value="0.00" onfocus="this.select();" oninput="calcularDiferenciaCaliente();">
                                </div>
                            </div>

                            <!-- 📊 PANEL DE CÁLCULOS EN VIVO CON DESGLOSE DE AUDITORÍA COMPLETO -->
                            <div id="panel-calculo-vivo" style="background: #f8fafc; padding: 15px; border-radius: 10px; margin-top: 15px; margin-bottom: 15px; border: 1px solid #cbd5e1; border-left: 6px solid #cbd5e1;">

                                <div style="display:flex; justify-content:space-between; font-size:12.5px; margin-bottom:6px; color:#475569; border-bottom: 1px dashed #e2e8f0; padding-bottom: 4px;">
                                    <span>💵 Efectivo Esperado (Base + Ventas + Entradas - Gastos):</span>
                                    <span style="font-family:monospace; font-weight:600;">C$ <?php echo number_format($efectivo_esperado_sistema, 2); ?></span>
                                </div>

                                <div style="display:flex; justify-content:space-between; font-size:12.5px; margin-bottom:6px; color:#475569; border-bottom: 1px dashed #e2e8f0; padding-bottom: 4px;">
                                    <span>💳 Tarjeta / POS Esperado:</span>
                                    <span style="font-family:monospace; font-weight:600;">C$ <?php echo number_format(floatval($ventas_sistema['calculado_tarjeta'] ?? 0), 2); ?></span>
                                </div>

                                <div style="display:flex; justify-content:space-between; font-size:12.5px; margin-bottom:10px; color:#475569; border-bottom: 1px dashed #e2e8f0; padding-bottom: 4px;">
                                    <span>📲 Transferencias Esperadas:</span>
                                    <span style="font-family:monospace; font-weight:600;">C$ <?php echo number_format(floatval($ventas_sistema['calculado_transferencia'] ?? 0), 2); ?></span>
                                </div>

                                <div style="display:flex; justify-content:space-between; font-size:13.5px; margin-bottom:4px; background:#f1f5f9; padding:6px 10px; border-radius:6px;">
                                    <span>Total General Esperado por Sistema:</span>
                                    <strong style="font-family:monospace; color:#0f172a;">C$ <span><?php echo number_format($efectivo_esperado_sistema + floatval($ventas_sistema['calculado_tarjeta'] ?? 0) + floatval($ventas_sistema['calculado_transferencia'] ?? 0), 2); ?></span></strong>
                                </div>

                                <div style="display:flex; justify-content:space-between; font-size:15px; font-weight:bold; margin-top:10px; border-top:2px solid #cbd5e1; padding-top:8px;">
                                    <span id="lbl_status_dif">Diferencia de Cuadre:</span>
                                    <span style="font-family:monospace;" id="val_diferencia">C$ 0.00</span>
                                </div>
                            </div>

                            <div class="form-group-caja">
                                <label>Observaciones de Entrega de Turno</label>
                                <textarea name="observaciones" class="form-control-caja" rows="2" style="font-family: inherit; font-weight: normal; font-size: 13px;" placeholder="Ej. Todo cuadra completo o Faltó C$ 5.00 por billete roto."></textarea>
                            </div>

                            <button type="submit" class="btn-caja-act btn-cierre btn-danger" style="padding: 12px;">
                                🔒 Arquear y Clausurar Caja
                            </button>

                        </form>
                    </div> <!-- Fin de Columna 2 -->
                </div> <!-- Fin de caja-layout-grid -->
            <?php endif; ?>
        </main>
    </div>

    <!-- JAVASCRIPT: CALCULADORA DE ARQUEO REACTIVA -->
    <script>
        function calcularDiferenciaCaliente() {
            const efectivoEsperado = <?php echo floatval($efectivo_esperado_sistema); ?>;
            const tarjetaEsperado = <?php echo floatval($ventas_sistema['calculado_tarjeta'] ?? 0); ?>;
            const transEsperado = <?php echo floatval($ventas_sistema['calculado_transferencia'] ?? 0); ?>;

            const totalEsperadoSistema = efectivoEsperado + tarjetaEsperado + transEsperado;

            const efectivoReal = parseFloat(document.getElementById('efectivo_real').value) || 0;
            const tarjetaReal = parseFloat(document.getElementById('tarjeta_real').value) || 0;
            const transReal = parseFloat(document.getElementById('transferencia_real').value) || 0;

            const totalContadoCajero = efectivoReal + tarjetaReal + transReal;
            const diferencia = totalContadoCajero - totalEsperadoSistema;

            const panel = document.getElementById('panel-calculo-vivo');
            const lblStatus = document.getElementById('lbl_status_dif');
            const valDif = document.getElementById('val_diferencia');

            if (!panel || !lblStatus || !valDif) return;

            const formatoMoneda = new Intl.NumberFormat('es-NI', {
                style: 'currency',
                currency: 'NIO'
            }).format(diferencia).replace('NIO', 'C$');

            if (diferencia === 0) {
                panel.style.borderLeftColor = "#52b788";
                panel.style.background = "#ebfbee";
                lblStatus.innerText = "⭐ Caja Perfectamente Cuadrada:";
                lblStatus.style.color = "#2b8a3e";
                valDif.innerText = formatoMoneda;
                valDif.style.color = "#2b8a3e";
            } else if (diferencia < 0) {
                panel.style.borderLeftColor = "#c92a2a";
                panel.style.background = "#fff5f5";
                lblStatus.innerText = "⚠ Faltante de Dinero en Caja:";
                lblStatus.style.color = "#c92a2a";
                valDif.innerText = formatoMoneda;
                valDif.style.color = "#c92a2a";
            } else {
                panel.style.borderLeftColor = "#0d47a1";
                panel.style.background = "#e3f2fd";
                lblStatus.innerText = "🎁 Sobrante de Dinero en Caja:";
                lblStatus.style.color = "#0d47a1";
                valDif.innerText = "+" + formatoMoneda;
                valDif.style.color = "#0d47a1";
            }
        }

        document.addEventListener("DOMContentLoaded", () => {
            if (document.getElementById('efectivo_real')) {
                calcularDiferenciaCaliente();
            }
        });
        // Agrega esto adentro del bloque de script al final de views/gestion_caja.php
        <?php if (isset($_GET['imprimir_cierre_id'])): ?>
            // Abre la ventana compacta independiente directo hacia la ticketera de 80mm
            window.open('index.php?v=imprimir_cierre&turno_id=<?php echo intval($_GET['imprimir_cierre_id']); ?>', '_blank', 'width=400,height=650,scrollbars=yes');
        <?php endif; ?>
    </script>

    <script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>

</html>