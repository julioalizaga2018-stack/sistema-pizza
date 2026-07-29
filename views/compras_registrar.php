<?php
// views/compras_registrar.php (Parte 1 de 2)
require_once __DIR__ . '/../controllers/CompraController.php';
$compraCtrl = new CompraController();

// 1. Extraemos los proveedores activos nativos de la base de datos
$db_local = (new Conexion())->conectar();
$stmtP = $db_local->query("SELECT id, nombre_empresa FROM proveedores WHERE estado = 'activo' ORDER BY nombre_empresa ASC");
$proveedores = $stmtP->fetchAll(PDO::FETCH_ASSOC);

// 2. Extraemos estrictamente los insumos que manejan stock (con sus costos base actuales)
$insumos_disponibles = $compraCtrl->listarInsumosDisponibles();

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
    <title>Registrar Compra de Insumos - Jungle Pizza</title>
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    <style>
        .compras-container { max-width: 1100px; margin: 20px auto; width: 100%; }
        .compras-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(27,67,50,0.05); padding: 25px; border-top: 4px solid #3b82f6; margin-top: 20px; }
        .compras-card h3 { color: var(--verde-oscuro, #1b4332); font-size: 1.25rem; margin-bottom: 15px; border-bottom: 2px solid var(--verde-menta, #d8f3dc); padding-bottom: 8px; }
        .grid-cabecera-compra { display: grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 25px; }
        @media (min-width: 768px) { .grid-cabecera-compra { grid-template-columns: 1fr 1fr 1fr; } }
        .form-group-compra { display: flex; flex-direction: column; gap: 5px; }
        .form-group-compra label { font-size: 12px; font-weight: bold; color: var(--verde-oscuro, #1b4332); text-transform: uppercase; }
        .form-control-compra { width: 100% !important; padding: 11px !important; border: 2px solid #cbd5e1 !important; border-radius: 6px !important; font-size: 14px !important; background: #fff !important; box-sizing: border-box !important; }
        .form-control-compra:focus { outline: none !important; border-color: #3b82f6 !important; }
        .table-responsive { width: 100%; overflow-x: auto; border-radius: 8px; border: 1px solid #edf2f7; margin-top: 15px; }
        .tabla-insumos { width: 100%; border-collapse: collapse; text-align: left; min-width: 900px; }
        .tabla-insumos th { background-color: var(--verde-oscuro, #1b4332); color: #ffffff; padding: 12px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        .tabla-insumos td { padding: 10px; border-bottom: 1px solid #edf2f7; vertical-align: middle; }
        .input-table { padding: 8px !important; font-size: 13px !important; font-weight: bold !important; font-family: monospace !important; text-align: right; border-width: 1px !important; border-radius: 4px !important; }
        .btn-logistico { border: none; padding: 10px 18px; font-weight: bold; border-radius: 6px; cursor: pointer; text-transform: uppercase; font-size: 12px; transition: all 0.2s; }
        .btn-add-row { background: #3b82f6; color: #fff; }
        .btn-add-row:hover { background: #2563eb; }
        .btn-remove-row { background: #ef4444; color: #fff; padding: 6px 10px !important; border-radius: 4px; }
        .btn-remove-row:hover { background: #dc2626; }
        .banner-total-compra { background: #1e293b; color: #fff; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 18px; font-weight: bold; text-align: right; margin-top: 15px; }
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
        <div class="compras-container">
            <h2>Ingreso de Facturas de Abastecimiento</h2>
            <p style="color: #666;">Registra las compras de insumos que manejan inventario y actualiza los precios de costo y del menú en tiempo real.</p>

            <form action="<?php echo URL_BASE; ?>controllers/CompraController.php" method="POST" onsubmit="return validarEnvioCompra();">
                <input type="hidden" name="accion" value="registrar_compra_proveedor">

                <!-- 🏷️ SECCIÓN A: CABECERA DE LA COMPRA -->
                <div class="compras-card">
                    <h3>🧾 Datos de la Factura de Compra</h3>
                    <div class="grid-cabecera-compra">
                        <div class="form-group-compra">
                            <label>Proveedor Logístico *</label>
                            <!-- 🌟 POR ESTA VERSIÓN CON EL ID INYECTADO: -->
<select name="proveedor_id" id="proveedor_id" class="form-control-compra" required style="font-family: inherit;">
                                <option value="">-- Seleccione Proveedor --</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?php echo $prov['id']; ?>"><?php echo htmlspecialchars($prov['nombre_empresa'] ?? $prov['nombre_proveedor']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-compra">
                            <label>N° Factura Física *</label>
                            <input type="text" name="numero_factura" class="form-control-compra" required placeholder="Ej. FAC-12945">
                        </div>
                        <div class="form-group-compra">
                            <label>Fecha de Compra *</label>
                            <input type="date" name="fecha_compra" class="form-control-compra" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>

                <!-- 🛒 SECCIÓN B: REJILLA DINÁMICA DE REVALORIZACIÓN -->
                <div class="compras-card" style="border-top-color: var(--verde-claro, #52b788);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid var(--verde-menta, #d8f3dc); padding-bottom: 8px;">
                        <h3 style="margin-bottom: 0; border-bottom: none; padding-bottom: 0;">📦 Detalle de Insumos con Inventario</h3>
                        <button type="button" class="btn-logistico btn-add-row" onclick="agregarFilaInsumo();">➕ Añadir Insumo</button>
                    </div>

                    <div class="table-responsive">
                        <table class="tabla-insumos" id="tabla_detalles_compra">
                            <thead>
                                <tr>
                                    <th>Seleccionar Insumo / Producto *</th>
                                    <th style="width: 100px; text-align: right;">Cantidad</th>
                                    <th style="width: 140px; text-align: right;">Precio Compra (C$) *</th>
                                    <th style="width: 140px; text-align: right;">Precio Menú (C$) *</th>
                                    <th style="width: 140px; text-align: right;">Subtotal Lineal</th>
                                    <th style="width: 70px; text-align: center;">Quitar</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpo_tabla_compra">
                                <!-- Las filas interactivas se inyectarán dinámicamente mediante JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    <!-- PANEL TOTALIZADOR GLOBAL -->
                    <div class="banner-total-compra">
                        TOTAL FACTURA COMPRA: C$ <span id="lbl_total_global_compra">0.00</span>
                    </div>

                    <div class="form-group-compra" style="margin-top: 20px;">
                        <label>Observaciones Contables / Novedades de Recibo</label>
                        <textarea name="observaciones" class="form-control-compra" rows="3" placeholder="Ej. Se recibió mercadería en buen estado. Harina con costo incrementado por el distribuidor." style="font-family: inherit; font-weight: normal;"></textarea>
                    </div>

                    <button type="submit" class="btn-logistico" style="background: var(--verde-selva, #2d6a4f); color: #fff; width: 100%; padding: 14px; font-size: 15px; margin-top: 20px;">
                        📦 Finalizar Registro e Incrementar Inventario
                    </button>
                </div>
            </form>
        </div> <!-- Fin de compras-container -->
    </main>
</div> <!-- Fin de dashboard-layout -->

<!-- 🚀 INGENIERÍA REACTIVA DE COMPRAS, FILTRADO POR PROVEEDOR Y REVALORIZACIÓN EN CALIENTE -->
<script>
// 1. Inyectamos la matriz de insumos con stock desde PHP (Mapeada con tus columnas reales)
const catálogoInsumos = <?php echo json_encode($insumos_disponibles); ?>;

// Contador de control para IDs de filas únicas en el DOM
let contadorFilas = 0;

// 🔄 FILTRADO INTERACTIVO: Genera una fila cuyos productos pertenecen estrictamente al proveedor de la cabecera
function agregarFilaInsumo() {
    // Capturamos el selector del proveedor principal
    const selectProv = document.getElementById('proveedor_id') || document.getElementsByName('proveedor_id')[0];
    const proveedorSeleccionadoId = parseInt(selectProv.value) || 0;
    
    // Regla de negocio: Forzar la selección del proveedor antes de abrir renglones
    if (proveedorSeleccionadoId === 0) {
        alert("⚠ Operación Detenida: Primero debe seleccionar un Proveedor Logístico en la cabecera para filtrar sus insumos correspondientes.");
        return;
    }

    contadorFilas++;
    const cuerpo = document.getElementById('cuerpo_tabla_compra');
    
    // Filtramos en memoria local de JS los productos que pertenecen a este proveedor_id nativo
    const insumosFiltrados = catálogoInsumos.filter(insumo => parseInt(insumo.proveedor_id) === proveedorSeleccionadoId);

    if (insumosFiltrados.length === 0) {
        alert("📝 Nota: Este proveedor no tiene insumos inventariables vinculados en tu catálogo de productos.");
    }

    // Construcción de las opciones del select dinámico
    let opcionesSelect = '<option value="">-- Seleccione Insumo --</option>';
    insumosFiltrados.forEach(insumo => {
        opcionesSelect += `<option value="${insumo.id}">${insumo.nombre} (${insumo.unidad_medida})</option>`;
    });

    const nuevaFila = document.createElement('tr');
    nuevaFila.id = `fila_compra_${contadorFilas}`;
    nuevaFila.innerHTML = `
        <td>
            <select name="prod_id[]" class="form-control-compra" required style="font-family:inherit; font-size:13px;" onchange="cargarPreciosBaseAlSeleccionar(this, ${contadorFilas});">
                ${opcionesSelect}
            </select>
        </td>
        <td>
            <input type="number" step="0.01" min="0.01" name="cantidad[]" id="cant_${contadorFilas}" class="form-control-compra input-table" required value="1.00" onfocus="this.select();" oninput="recalcularMatematicaFila(${contadorFilas});">
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="precio_unitario[]" id="p_compra_${contadorFilas}" class="form-control-compra input-table" required value="0.00" onfocus="this.select();" oninput="recalcularMatematicaFila(${contadorFilas});">
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="precio_venta_publico[]" id="p_venta_${contadorFilas}" class="form-control-compra input-table" required value="0.00" onfocus="this.select();">
        </td>
        <td style="text-align: right; font-family: monospace; font-weight: bold; font-size: 14px; color: var(--verde-oscuro);" id="subtotal_lbl_${contadorFilas}">
            C$ 0.00
        </td>
        <td style="text-align: center;">
            <button type="button" class="btn-logistico btn-remove-row" onclick="eliminarFilaInsumo(${contadorFilas});">❌</button>
        </td>
    `;
    
    cuerpo.appendChild(nuevaFila);
}

// ⚡ AUTOCOMPLETADO EN CALIENTE: Al elegir el insumo, inyecta al instante sus costos viejos y dispara los subtotales
function cargarPreciosBaseAlSeleccionar(selectorElement, numeroFila) {
    const productoId = parseInt(selectorElement.value) || 0;
    
    // Buscamos los metadatos del producto seleccionado
    const datosProducto = catálogoInsumos.find(insumo => parseInt(insumo.id) === productoId);
    
    if (datosProducto) {
        // Rellenamos los inputs con los valores reales de tu base de datos (precio_costo y precio_base)
        document.getElementById(`p_compra_${numeroFila}`).value = parseFloat(datosProducto.precio_compra || 0).toFixed(2);
        document.getElementById(`p_venta_${numeroFila}`).value  = parseFloat(datosProducto.precio_venta || 0).toFixed(2);
    } else {
        document.getElementById(`p_compra_${numeroFila}`).value = "0.00";
        document.getElementById(`p_venta_${numeroFila}`).value  = "0.00";
    }
    
    // Forzamos la actualización matemática de la fila de forma inmediata
    recalcularMatematicaFila(numeroFila);
}

// Calcula el subtotal lineal de la fila seleccionada (Cantidad * Costo)
function recalcularMatematicaFila(numeroFila) {
    const cantidad = parseFloat(document.getElementById(`cant_${numeroFila}`).value) || 0;
    const precioCompra = parseFloat(document.getElementById(`p_compra_${numeroFila}`).value) || 0;
    
    const subtotalLinea = cantidad * precioCompra;
    document.getElementById(`subtotal_lbl_${numeroFila}`).innerText = "C$ " + subtotalLinea.toFixed(2);
    
    recalcularGranTotalGlobalFactura();
}

// Escanea la rejilla de datos activa y actualiza el banner del gran total en Córdobas
function recalcularGranTotalGlobalFactura() {
    let totalAcumuladoFactura = 0;
    const selectsProductos = document.getElementsByName('prod_id[]');
    
    for (let i = 0; i < selectsProductos.length; i++) {
        const inputIdAttr = selectsProductos[i].getAttribute('onchange');
        // Extraemos el ID numérico de la fila usando una expresión regular ágil
        const match = inputIdAttr.match(/\d+/);
        if (match) {
            const numeroFila = match[0];
            const cantidad = parseFloat(document.getElementById(`cant_${numeroFila}`).value) || 0;
            const precioCompra = parseFloat(document.getElementById(`p_compra_${numeroFila}`).value) || 0;
            totalAcumuladoFactura += (cantidad * precioCompra);
        }
    }
    
    // Pintamos el gran total formateado con la nomenclatura local
    document.getElementById('lbl_total_global_compra').innerText = new Intl.NumberFormat('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(totalAcumuladoFactura);
}

// Remueve renglones de la rejilla contable
function eliminarFilaInsumo(numeroFila) {
    const fila = document.getElementById(`fila_compra_${numeroFila}`);
    if (fila) {
        fila.remove();
        recalcularGranTotalGlobalFactura();
    }
}

// Validaciones estrictas de consistencia de datos antes de enviar por POST al controlador
function validarEnvioCompra() {
    const selectsProductos = document.getElementsByName('prod_id[]');
    if (selectsProductos.length === 0) {
        alert("🚨 Operación Detenida: Debe añadir al menos un insumo al detalle de la factura antes de procesar el guardado.");
        return false;
    }
    
    let seleccionIncompleta = false;
    for (let i = 0; i < selectsProductos.length; i++) {
        if (selectsProductos[i].value === "") {
            seleccionIncompleta = true;
            break;
        }
    }
    
    if (seleccionIncompleta) {
        alert("🚨 Operación Detenida: Hay filas en la rejilla donde no ha seleccionado ningún producto. Por favor, corríjalas o elimínelas.");
        return false;
    }
    
    return confirm("¿Confirmar el ingreso de esta factura de proveedor? Las existencias en stock, precios de catálogo y el Kardex general se actualizarán inmediatamente.");
}

// 🔒 REGLA DE CONSISTENCIA LOGÍSTICA: Si cambias de proveedor en la cabecera, se limpia la tabla para forzar el nuevo filtro
document.addEventListener("DOMContentLoaded", () => {
    const selectProveedorCabecera = document.getElementById('proveedor_id') || document.getElementsByName('proveedor_id')[0];
    
    if (selectProveedorCabecera) {
        // Aseguramos que la tabla empiece completamente limpia
        document.getElementById('cuerpo_tabla_compra').innerHTML = '';
        
        selectProveedorCabecera.addEventListener('change', () => {
            // Vaciamos la rejilla para impedir el cruce accidental de facturas entre distintos proveedores
            document.getElementById('cuerpo_tabla_compra').innerHTML = '';
            recalcularGranTotalGlobalFactura();
            
            // Si seleccionó un proveedor válido, abrimos automáticamente la primera fila filtrada
            if (selectProveedorCabecera.value !== "") {
                agregarFilaInsumo();
            }
        });
    }
});
</script>
