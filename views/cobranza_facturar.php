<?php
// views/cobranza_facturar.php
require_once __DIR__ . '/../models/CobranzaModelo.php';
require_once __DIR__ . '/../models/CajaModelo.php';
require_once __DIR__ . '/../models/ProveedorModelo.php';

$cobranzaModel = new CobranzaModelo();
$cajaModel = new CajaModelo();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$usuario_id = $_SESSION['usuario_id'] ?? 0;

// 🌟 NUEVO: Generamos un token único e inmutable para esta ventana de cobro
if (!isset($_SESSION['pago_token'])) {
    $_SESSION['pago_token'] = bin2hex(random_bytes(16));
}

// 1. Validar que la caja del usuario esté abierta antes de renderizar la calculadora
$turno_activo = $cajaModel->obtenerTurnoActivo($usuario_id);
if (!$turno_activo) {
    header("Location: index.php?v=gestion_caja&error=" . urlencode("Debe abrir un turno de caja para poder facturar pedidos."));
    exit;
}

// 2. Capturar y validar el ID del pedido a cobrar
$pedido_id = isset($_GET['pedido_id']) ? intval($_GET['pedido_id']) : 0;

// Conexión rápida para extraer el pedido maestro respetando tu estructura
$db = (new Conexion())->conectar();
$stmtP = $db->prepare("SELECT p.*, m.numero_mesa as numero_mesa FROM pedidos p LEFT JOIN mesas m ON p.mesa_id = m.id WHERE p.id = :id LIMIT 1");
$stmtP->execute(['id' => $pedido_id]);
$pedido = $stmtP->fetch();

if (!$pedido) {
    header("Location: index.php?v=cobranza_lista&error=" . urlencode("El pedido solicitado no existe."));
    exit;
}

// 3. Extraer los productos consumidos en la comanda
$productos_comanda = $cobranzaModel->obtenerDesglosePedido($pedido_id);

// 4. Cargar bancos activos de tu base de datos para tarjetas/transferencias
$stmtB = $db->query("SELECT id, nombre FROM bancos ORDER BY nombre ASC");
$bancos = $stmtB->fetchAll();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
if (!defined('URL_BASE')) {
    define('URL_BASE', ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/");
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Caja - Facturar Pedido #<?php echo $pedido_id; ?> - Jungle Pizza</title>
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    <style>
        .cobro-layout {
            display: grid !important;
            grid-template-columns: 1fr;
            gap: 25px;
            margin-top: 20px;
            width: 100%;
        }

        .cobro-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(27, 67, 50, 0.05);
            padding: 25px;
            border-top: 4px solid var(--verde-claro, #52b788);
        }

        .cobro-card h3 {
            color: var(--verde-oscuro, #1b4332);
            font-size: 1.2rem;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--verde-menta, #d8f3dc);
            padding-bottom: 8px;
        }

        .item-linea {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 14px;
        }

        .form-control-cobro {
            width: 100% !important;
            padding: 10px 12px !important;
            border: 2px solid #cbd5e1 !important;
            border-radius: 6px !important;
            font-size: 1.05rem !important;
            font-weight: bold !important;
            font-family: monospace !important;
        }

        .form-control-cobro:focus {
            outline: none !important;
            border-color: var(--verde-claro, #52b788) !important;
        }

        .pago-box {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
        }

        .pago-box.active {
            border-color: var(--verde-claro, #52b788);
            background: rgba(216, 243, 220, 0.1);
        }

        .banner-totales {
            background: #1b4332;
            color: #ffffff;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .banner-totales div {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .banner-cambio {
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 20px;
            font-family: monospace;
        }

        .cambio-falta {
            background: #ffe3e3;
            color: #c92a2a;
            border: 1px solid #ffa8a8;
        }

        .cambio-ok {
            background: #ebfbee;
            color: #2b8a3e;
            border: 1px solid #96f2d7;
        }

        .btn-facturar {
            background: var(--verde-selva, #2d6a4f);
            color: #fff;
            border: none;
            padding: 15px;
            width: 100%;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-facturar:hover {
            background: var(--verde-oscuro, #1b4332);
        }

        @media (min-width: 992px) {
            .cobro-layout {
                grid-template-columns: 380px 1fr;
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
            <h2>Procesar Cobranza Comercial</h2>
            <p style="color: #666;">Aplica políticas de descuento, ajusta propinas voluntarias y distribuye el pago en múltiples monedas o pasarelas.</p>

            <div class="cobro-layout">
                <!-- COLUMNA 1: DESGLOSE DE PRODUCTOS Y RESUMEN DE LA COMANDA -->
                <div class="cobro-card">
                    <h3>📋 Desglose de Consumo</h3>
                    <div class="info-turno-box" style="background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0; font-size:13px; margin-bottom:15px; color:#475569;">
                        <strong>Pedido ID: #<?php echo $pedido['id']; ?></strong><br>
                        Tipo: <code><?php echo strtoupper($pedido['tipo_pedido']); ?></code>
                        <?php echo $pedido['mesa_id'] ? '(Mesa ' . htmlspecialchars($pedido['numero_mesa']) . ')' : ''; ?><br>
                        Atendido por: ID Usuario #<?php echo $pedido['usuario_id']; ?>
                    </div>

                    <div style="margin-bottom: 20px; max-height: 280px; overflow-y: auto; padding-right: 5px;">
                        <?php if (empty($productos_comanda)): ?>
                            <p style="text-align:center; color:#999; font-size:13px;">No hay productos activos en este pedido.</p>
                        <?php else: ?>
                            <?php
                            $subtotal_calculado_pantalla = 0;
                            foreach ($productos_comanda as $item):
                                $subtotal_calculado_pantalla += floatval($item['subtotal']);
                                $id_detalle_cobro = (int)$item['id'];

                                // ✦ EXTRACCIÓN EN CALIENTE DE LOS EXTRAS ASOCIADOS
                                $stmtExtCobro = $db->prepare("SELECT pde.*, p.nombre FROM pedido_detalle_extras pde INNER JOIN productos p ON pde.producto_id = p.id WHERE pde.pedido_detalle_id = :det_id");
                                $stmtExtCobro->execute(['det_id' => $id_detalle_cobro]);
                                $extras_cobro = $stmtExtCobro->fetchAll(PDO::FETCH_ASSOC);

                                // 🌓 EXTRACCIÓN EN CALIENTE DE LAS MITADES DE PIZZAS MIXTAS
                                $saboresTextCobro = "";
                                if ((int)$item['es_mixta'] === 1) {
                                    $stmtSabCobro = $db->prepare("SELECT p.nombre FROM pedido_detalle_sabores pds INNER JOIN productos p ON pds.producto_id = p.id WHERE pds.pedido_detalle_id = :det_id");
                                    $stmtSabCobro->execute(['det_id' => $id_detalle_cobro]);
                                    $mitades_cobro = $stmtSabCobro->fetchAll(PDO::FETCH_COLUMN);
                                    if (!empty($mitades_cobro)) {
                                        $saboresTextCobro = "(" . implode(" / ", $mitades_cobro) . ")";
                                    }
                                }
                            ?>
                                <!-- Renglón Principal del Producto -->
                                <div class="item-linea" style="border-bottom: 1px dashed #cbd5e1; padding: 6px 0;">
                                    <span>
                                        <strong><?php echo (int)$item['cantidad']; ?>x</strong>
                                        <?php echo ((int)$item['es_mixta'] === 1) ? "Pizza Mixta Combinada" : htmlspecialchars($item['producto_nombre']); ?>
                                        <?php if (!empty($saboresTextCobro)): ?>
                                            <small style="display:block; color:#475569; padding-left:12px; margin-top:2px;">🌓 <?php echo htmlspecialchars($saboresTextCobro); ?></small>
                                        <?php endif; ?>
                                    </span>
                                    <span style="font-family:monospace; font-weight:600; color:var(--verde-oscuro);">
                                        C$ <?php echo number_format($item['subtotal'], 2); ?>
                                    </span>
                                </div>

                                <!-- Renglones Inline para cada Extra / Ingrediente Adicional -->
                                <?php foreach ($extras_cobro as $ex):
                                    $costo_extra_pantalla = floatval($ex['cantidad']) * floatval($ex['precio_cobrado']);
                                    $subtotal_calculado_pantalla += $costo_extra_pantalla;
                                ?>
                                    <div class="item-linea" style="border-bottom: 1px dotted #e2e8f0; padding: 4px 0; font-size:12.5px; color:#475569;">
                                        <span style="padding-left: 14px;">✦ +<?php echo (int)$ex['cantidad']; ?> <?php echo htmlspecialchars($ex['nombre']); ?></span>
                                        <span style="font-family:monospace;">C$ <?php echo number_format($costo_extra_pantalla, 2); ?></span>
                                    </div>
                                <?php endforeach; ?>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>


                    <!-- 🎛️ CONTROLES POLÍTICOS: PROPINA Y DESCUENTO DINÁMICO -->
                    <div style="margin-top: 15px;">
                        <div class="form-group-caja" style="margin-bottom: 12px;">
                            <label>💸 Propina Voluntaria (C$)</label>
                            <input type="number" step="0.01" min="0" name="monto_propina" id="monto_propina" class="form-control-cobro"
                                value="<?php echo floatval($pedido['monto_propina']); ?>"
                                onfocus="this.select();" oninput="calcularTotalNetoYVuelto();">
                            <small style="color:#64748b; font-size:11px; display:block; margin-top:2px;">Puedes modificarla o dejarla en 0.00 si el cliente no la autoriza.</small>
                        </div>

                        <div class="form-group-caja" style="margin-bottom: 10px;">
                            <label>🏷️ Descuento Comercial (C$)</label>
                            <input type="number" step="0.01" min="0" name="monto_descuento" id="monto_descuento" class="form-control-cobro"
                                value="0.00"
                                onfocus="this.select();" oninput="calcularTotalNetoYVuelto();">
                            <small style="color:#64748b; font-size:11px; display:block; margin-top:2px;">Monto fijo en Córdobas a restar del subtotal por promociones.</small>
                        </div>
                    </div>
                </div> <!-- Fin de Columna 1 -->
                <!-- COLUMNA 2: CALCULADORA DE PAGO MIXTO Y CIERRE DE FACTURA -->
                <div class="cobro-card">
                    <h3>💳💳 Pasarela de Pago Combinado</h3>
                    <form action="<?php echo URL_BASE; ?>controllers/CobranzaController.php" method="POST" id="form-cobrar-pizza" onsubmit="return validarEnvioCobro();">
                        <!-- Campos Ocultos de Control Operativo -->
                        <input type="hidden" name="accion" value="facturar_pedido">
                        <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                        <input type="hidden" name="total_final" id="total_final_input" value="<?php echo floatval($pedido['total']); ?>">
                        <input type="hidden" name="monto_propina" id="propina_oculta" value="<?php echo floatval($pedido['monto_propina']); ?>">
                        <input type="hidden" name="monto_descuento" id="descuento_oculto" value="0.00">

                        <!-- 🌟 NUEVO: Enviamos el token criptográfico al controlador -->
                        <input type="hidden" name="pago_token" value="<?php echo $_SESSION['pago_token']; ?>">

                        <!-- MÉTODOS DE PAGO MIXTO SIMULTÁNEOS -->

                        <!-- Canal 1: Efectivo -->
                        <div class="pago-box" id="box_efectivo">
                            <label style="display:block; font-weight:700; font-size:13px; color:var(--verde-oscuro); margin-bottom:6px;">💵 Abono en Efectivo (C$)</label>
                            <input type="number" step="0.01" min="0" name="pago_efectivo" id="pago_efectivo" class="form-control-cobro" value="0.00" onfocus="this.select();" oninput="calcularTotalNetoYVuelto();">
                        </div>

                        <!-- Canal 2: Tarjeta POS -->
                        <div class="pago-box" id="box_tarjeta">
                            <label style="display:block; font-weight:700; font-size:13px; color:var(--verde-oscuro); margin-bottom:6px;">💳 Abono con Tarjeta (C$)</label>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:8px;">
                                <input type="number" step="0.01" min="0" name="pago_tarjeta" id="pago_tarjeta" class="form-control-cobro" value="0.00" onfocus="this.select();" oninput="calcularTotalNetoYVuelto();">
                                <select name="banco_tarjeta" id="banco_tarjeta_select" class="form-control-cobro" style="font-family:inherit; font-size:13px;">

                                    <option value="0">-- Seleccione POS --</option>
                                    <?php foreach ($bancos as $b): ?>
                                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="text" name="ref_tarjeta" class="form-control-cobro" style="font-size:12px; font-weight:normal;" placeholder="N° de Váucher / Transacción">
                        </div>

                        <!-- Canal 3: Transferencia Bancaria -->
                        <div class="pago-box" id="box_trans">
                            <label style="display:block; font-weight:700; font-size:13px; color:var(--verde-oscuro); margin-bottom:6px;">📲 Abono por Transferencia (C$)</label>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:8px;">
                                <input type="number" step="0.01" min="0" name="pago_trans" id="pago_trans" class="form-control-cobro" value="0.00" onfocus="this.select();" oninput="calcularTotalNetoYVuelto();">
                                <select name="banco_trans" id="banco_trans_select" class="form-control-cobro" style="font-family:inherit; font-size:13px;">

                                    <option value="0">-- Banco Destino --</option>
                                    <?php foreach ($bancos as $b): ?>
                                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="text" name="ref_trans" class="form-control-cobro" style="font-size:12px; font-weight:normal;" placeholder="N° de Referencia Bancaria (Minuta)">
                        </div>

                        <!-- LIBRO DE BALANCES OPERATIVOS EN VIVO -->
                        <div class="banner-totales">
                            <div><span>Subtotal de Comanda:</span><span>C$ <span id="lbl_subtotal"><?php echo number_format(floatval($pedido['total']) - floatval($pedido['monto_propina']) - floatval($pedido['monto_envio']), 2); ?></span></span></div>
                            <div><span>Cargo por Delivery / Envío:</span><span>C$ <?php echo number_format(floatval($pedido['monto_envio']), 2); ?></span></div>
                            <div><span>Propina Voluntaria (+):</span><span>C$ <span id="lbl_propina"><?php echo number_format(floatval($pedido['monto_propina']), 2); ?></span></span></div>
                            <div style="color:#ffa8a8;"><span>Descuento Aplicado (-):</span><span>C$ <span id="lbl_descuento">0.00</span></span></div>
                            <div style="font-size:18px; font-weight:bold; border-top:1px solid #52b788; padding-top:8px; margin-top:8px;">
                                <span>TOTAL NETO A COBRAR:</span>
                                <span>C$ <span id="lbl_total_neto"><?php echo number_format(floatval($pedido['total']), 2); ?></span></span>
                            </div>
                        </div>
                        <!-- BANNER INTERACTIVO DE VUELTO O SALDO FALTANTE -->
                        <div id="banner_vuelto" class="banner-cambio cambio-falta">
                            Falta Dinero: C$ <?php echo number_format(floatval($pedido['total']), 2); ?>
                        </div>

                        <button type="submit" id="btn-facturar-submit" class="btn-facturar">
                            🖨️🖨️ Cobrar y Emitir Factura
                        </button>
                    </form>
                </div> <!-- Fin de Columna 2 -->
            </div> <!-- Fin de cobro-layout -->
        </main>
    </div>

    <!-- LÓGICA REACTIVA DE AUDITORÍA DE FACTURACIÓN -->
    <script>
        // Valores Base Inmutables inyectados desde el Servidor PHP
        const subtotalComanda = <?php echo floatval($pedido['total']) - floatval($pedido['monto_propina']) - floatval($pedido['monto_envio']); ?>;
        const montoEnvio = <?php echo floatval($pedido['monto_envio']); ?>;

        function calcularTotalNetoYVuelto() {
            // 1. Extraer lo que el cajero digita en los controles políticos
            const propinaInput = parseFloat(document.getElementById('monto_propina').value) || 0;
            const descuentoInput = parseFloat(document.getElementById('monto_descuento').value) || 0;

            // Sincronizar de inmediato los inputs ocultos que viajan por POST
            document.getElementById('propina_oculta').value = propinaInput;
            document.getElementById('descuento_oculto').value = descuentoInput;

            // Calcular el Gran Total Neto Real de la Pizzería
            const totalNetoA_Cobrar = (subtotalComanda + montoEnvio + propinaInput) - descuentoInput;
            document.getElementById('total_final_input').value = totalNetoA_Cobrar.toFixed(2);

            // Actualizar etiquetas visuales del bloque de balances
            document.getElementById('lbl_propina').innerText = propinaInput.toFixed(2);
            document.getElementById('lbl_descuento').innerText = descuentoInput.toFixed(2);
            document.getElementById('lbl_total_neto').innerText = new Intl.NumberFormat('es-NI').format(totalNetoA_Cobrar);

            // 2. Extraer los abonos de la pasarela mixta
            const abonoEfectivo = parseFloat(document.getElementById('pago_efectivo').value) || 0;
            const abonoTarjeta = parseFloat(document.getElementById('pago_tarjeta').value) || 0;
            const abonoTrans = parseFloat(document.getElementById('pago_trans').value) || 0;

            const totalAbonado = abonoEfectivo + abonoTarjeta + abonoTrans;
            const balanceVuelto = totalAbonado - totalNetoA_Cobrar;

            // Iluminar dinámicamente las cajas de pago activas en azul o verde
            document.getElementById('box_efectivo').className = (abonoEfectivo > 0) ? "pago-box active" : "pago-box";
            document.getElementById('box_tarjeta').className = (abonoTarjeta > 0) ? "pago-box active" : "pago-box";
            document.getElementById('box_trans').className = (abonoTrans > 0) ? "pago-box active" : "pago-box";

            // 3. Renderizar el Banner de Vuelto / Alertas
            const banner = document.getElementById('banner_vuelto');

            if (balanceVuelto === 0) {
                banner.className = "banner-cambio cambio-ok";
                banner.innerText = "⭐ Cuenta Exacta. No requiere vuelto.";
            } else if (balanceVuelto > 0) {
                banner.className = "banner-cambio cambio-ok";
                banner.innerText = "💸 Entregar Vuelto en Efectivo: C$ " + balanceVuelto.toFixed(2);
            } else {
                banner.className = "banner-cambio cambio-ta cambio-falta";
                banner.innerText = "⚠ Saldo Incompleto. Falta por cubrir: C$ " + Math.abs(balanceVuelto).toFixed(2);
            }
        }

      function validarEnvioCobro() {
    const totalNeto = parseFloat(document.getElementById('total_final_input').value) || 0;
    const abonoEfectivo = parseFloat(document.getElementById('pago_efectivo').value) || 0;
    const abonoTarjeta = parseFloat(document.getElementById('pago_tarjeta').value) || 0;
    const abonoTrans = parseFloat(document.getElementById('pago_trans').value) || 0;
    const totalAbonado = abonoEfectivo + abonoTarjeta + abonoTrans;

    // 1. Validar descuadres de caja existentes
    if (totalAbonado < totalNeto) {
        alert("🚨🚨 Operación Detenida: El monto total abonado en la pasarela mixta es menor al total neto de la factura.");
        return false;
    }

    // 2. 🌟 NUEVO: Validar obligatoriedad del banco en pagos con Tarjeta POS
    if (abonoTarjeta > 0) {
        const bancoTarjeta = document.getElementById('banco_tarjeta_select').value;
        if (!bancoTarjeta || bancoTarjeta === "0" || bancoTarjeta === "") {
            alert("🚨 Operación Detenida: Ha ingresado un abono con Tarjeta pero no seleccionó la terminal POS / Banco destino.");
            document.getElementById('banco_tarjeta_select').focus();
            return false;
        }
    }

    // 3. 🌟 NUEVO: Validar obligatoriedad del banco en pagos con Transferencia
    if (abonoTrans > 0) {
        const bancoTrans = document.getElementById('banco_trans_select').value;
        if (!bancoTrans || bancoTrans === "0" || bancoTrans === "") {
            alert("🚨 Operación Detenida: Ha ingresado un abono por Transferencia pero no seleccionó el Banco destino de la minuta.");
            document.getElementById('banco_trans_select').focus();
            return false;
        }
    }

    // 4. Confirmación del operario
    const seguro = confirm("¿Confirmar cobro y cerrar comanda comercial?");
    
    // 5. 🌟 NUEVO: Protección contra Doble Clic y micro-cortes de red
    if (seguro) {
        const btnSubmit = document.getElementById('btn-facturar-submit');
        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.style.opacity = "0.6";
            btnSubmit.innerHTML = "⏳ Guardando transacciones de caja... Por favor espere";
        }
        return true;
    }
    
    return false;
}


        // Ejecutar cálculo automático al cargar la vista
        document.addEventListener("DOMContentLoaded", calcularTotalNetoYVuelto);
    </script>

    <script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>

</html>