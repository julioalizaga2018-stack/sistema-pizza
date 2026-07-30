<?php
// views/recetas_lista.php (Parte 1 de 2)
require_once __DIR__ . '/../controllers/RecetaController.php';
$recetaCtrl = new RecetaController();

// 1. Extraemos los platos que no manejan stock (Menú para asignarles ingredientes)
$platos_menu = $recetaCtrl->obtenerPlatosMenu();

// 2. Extraemos las materias primas con stock (Harina, Jamón, Queso, Pepperoni) para el selector JS
$insumos_bodega = $recetaCtrl->obtenerMateriasPrimasBodega();

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
    <title>Configuración de Recetas - Jungle Pizza</title>
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    <style>
        .recetas-grid { display: grid; grid-template-columns: 1fr; gap: 20px; margin-top: 20px; width: 100%; }
        @media (min-width: 992px) { .recetas-grid { grid-template-columns: 350px 1fr; align-items: start; } }
        .recetas-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(27,67,50,0.05); padding: 22px; border-top: 4px solid var(--verde-claro, #52b788); }
        .recetas-card h3 { color: var(--verde-oscuro, #1b4332); font-size: 1.2rem; margin-bottom: 15px; border-bottom: 2px solid var(--verde-menta, #d8f3dc); padding-bottom: 8px; }
        .listado-platos { max-height: 500px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; padding-right: 5px; }
        .btn-plato-item { width: 100%; text-align: left; padding: 12px 15px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; font-weight: bold; font-size: 14px; cursor: pointer; transition: all 0.2s ease; display: flex; justify-content: space-between; align-items: center; }
        .btn-plato-item:hover { background: rgba(216, 243, 220, 0.3); border-color: var(--verde-claro); }
        .btn-plato-item.active { background: var(--verde-claro, #52b788); color: #ffffff; border-color: var(--verde-claro); }
        .btn-plato-item span { font-size: 11px; background: rgba(0,0,0,0.05); padding: 2px 6px; border-radius: 4px; font-family: monospace; }
        .btn-plato-item.active span { background: rgba(255,255,255,0.2); color: #fff; }
        .form-control-receta { width: 100% !important; padding: 10px !important; border: 2px solid #cbd5e1 !important; border-radius: 6px !important; font-size: 13.5px !important; background: #fff !important; box-sizing: border-box !important; }
        .form-control-receta:focus { outline: none !important; border-color: #3b82f6 !important; }
        .tabla-receta-insumos { width: 100%; border-collapse: collapse; text-align: left; min-width: 500px; }
        .tabla-receta-insumos th { background-color: var(--verde-oscuro, #1b4332); color: #ffffff; padding: 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        .tabla-receta-insumos td { padding: 8px 10px; border-bottom: 1px solid #edf2f7; vertical-align: middle; }
        .input-receta-cant { text-align: right; font-family: monospace; font-weight: bold; font-size: 14px; }
        .btn-rec-action { border: none; padding: 10px 16px; font-weight: bold; border-radius: 6px; cursor: pointer; text-transform: uppercase; font-size: 11px; }
        .btn-danger-row { background: #ef4444; color: #fff; padding: 6px 10px; border-radius: 4px; }
        .btn-danger-row:hover { background: #dc2626; }
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
        <h2>Fórmulas y Fichas Técnicas de Cocina</h2>
        <p style="color: #666; margin-bottom: 20px;">Vincula a cada platillo del menú (Pizzas, Alitas, Churrascos) los insumos que consumirá de bodega al momento de facturar.</p>

        <?php if ($msg_error): ?><div class="alert alert-error">⚠ <?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
        <?php if ($msg_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div><?php endif; ?>

        <div class="recetas-grid">
            
            <!-- COLUMNA IZQUIERDA: SELECCIÓN DE PLATILLO -->
            <div class="recetas-card">
                <h3>🍽️ Platillos del Menú</h3>
                <div class="listado-platos">
                    <?php if(empty($platos_menu)): ?>
                        <p style="color:#999; font-size:13px; font-style:italic; text-align:center; padding:15px;">No hay productos con stock desactivado en el catálogo.</p>
                    <?php else: ?>
                        <?php foreach($platos_menu as $plato): ?>
                            <button type="button" class="btn-plato-item" id="btn_plato_<?php echo $plato['id']; ?>" onclick="cargarRecetaDePlatillo(<?php echo $plato['id']; ?>, '<?php echo htmlspecialchars($plato['nombre']); ?>');">
                                <?php echo htmlspecialchars($plato['nombre']); ?>
                                <span>Menú</span>
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <!-- COLUMNA DERECHA: EDICIÓN DINÁMICA DE LA RECETA -->
            <div class="recetas-card" style="border-top-color: #3b82f6;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 15px;">
                    <h3 style="margin-bottom: 0; border-bottom: none; padding-bottom: 0;" id="lbl_titulo_receta">← Seleccione un platillo del menú</h3>
                    <button type="button" class="btn-rec-action" id="btn_add_ingrediente" style="background: #3b82f6; color: #fff; display: none;" onclick="agregarFilaIngrediente();">➕ Añadir Ingrediente</button>
                </div>

                <!-- Formulario de Guardado Masivo -->
                <form action="<?php echo URL_BASE; ?>controllers/RecetaController.php" method="POST" id="form_maestro_recetas" style="display: none;">
                    <input type="hidden" name="accion" value="guardar_formula_receta">
                    <input type="hidden" name="producto_final_id" id="hidden_producto_final_id" value="0">

                    <div class="table-responsive">
                        <table class="tabla-receta-insumos">
                            <thead>
                                <tr>
                                    <th>Materia Prima de Bodega *</th>
                                    <th style="width: 150px; text-align: right;">Cantidad por Porción</th>
                                    <th style="width: 100px; text-align: center;">Unidad</th>
                                    <th style="width: 70px; text-align: center;">Quitar</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpo_tabla_receta">
                                <!-- Las filas de ingredientes se inyectarán dinámicamente aquí -->
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="btn-rec-action" style="background: var(--verde-selva, #2d6a4f); color: #fff; width: 100%; padding: 12px; font-size: 14px; margin-top: 20px;">
                        💾 Guardar Fórmula y Ficha Técnica
                    </button>
                </form>

                <!-- Vista neutral inicial -->
                <div id="panel_instruccion_receta" style="text-align: center; color: #94a3b8; padding: 45px 15px; font-size: 15px;">
                    🍕 Selecciona una pizza, alitas o plato fuerte en la columna izquierda para auditar, modificar o registrar su composición de ingredientes.
                </div>
            </div> <!-- Fin de Columna Derecha -->
        </div> <!-- Fin de recetas-grid -->
    </main>
</div> <!-- Fin de dashboard-layout -->

<!-- 🚀 INGENIERÍA REACTIVA DEL CRUD DE RECETAS (AJAX & DOM) -->
<script>
// 1. Inyectamos las materias primas con stock desde PHP para el selector local
const catalogoInsumos = <?php echo json_encode($insumos_bodega); ?>;
let contadorFilasReceta = 0;

// ⚡ CARGA ASÍNCRONA: Pinta la receta en pantalla al seleccionar un platillo sin recargar
function cargarRecetaDePlatillo(platoId, nombrePlato) {
    // Manejo visual de botones activos
    document.querySelectorAll('.btn-plato-item').forEach(btn => btn.classList.remove('active'));
    const btnActivo = document.getElementById(`btn_plato_${platoId}`);
    if (btnActivo) btnActivo.classList.add('active');

    // Inicializamos contenedores e inputs ocultos
    document.getElementById('hidden_producto_final_id').value = platoId;
    document.getElementById('lbl_titulo_receta').innerText = `🍳 Fórmula de: ${nombrePlato}`;
    document.getElementById('panel_instruccion_receta').style.display = 'none';
    document.getElementById('form_maestro_recetas').style.display = 'block';
    document.getElementById('btn_add_ingrediente').style.display = 'inline-block';

    const cuerpo = document.getElementById('cuerpo_tabla_receta');
    cuerpo.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px; color:#666;">Cargando ingredientes asignados...</td></tr>';

    // Consumimos el endpoint asíncrono que registramos en tu index.php
    
       // Consumimos el endpoint asíncrono con captura de texto crudo preventivo
     // 🌟 REEMPLÁZALA EXACTAMENTE POR ESTA VERSIÓN ABSOLUTA BLINDADA:
     fetch(`./index.php?v=api_receta_detalle&plato_id=${platoId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Código de respuesta HTTP inválido: ${response.status}`);
            }
            return response.text(); // Leemos como texto plano para interceptar Warnings latentes
        })
        .then(textoCrudo => {
            try {
                // Intentamos decodificar manualmente la trama
                const res = JSON.parse(textoCrudo.trim());
                
                cuerpo.innerHTML = '';
                contadorFilasReceta = 0;

                if (res.status === 'success' && res.data.length > 0) {
                    res.data.forEach(item => {
                        agregarFilaIngrediente(item.insumo_materia_prima_id, item.cantidad_porcion, item.unidad_medida);
                    });
                } else {
                    agregarFilaIngrediente();
                }
            } catch (errJson) {
                // 🔍 SI EL SERVIDOR DEVOLVIÓ UN ERROR, LO MOSTRAMOS EN EL CUADRO ROJO DE INMEDIATO
                console.error("Respuesta corrupta de XAMPP:", textoCrudo);
                cuerpo.innerHTML = `<tr><td colspan="4" style="text-align:left; color:#c92a2a; padding:15px; font-family:monospace; font-size:12px; background:#fff5f5; border:1px solid #ffa8a8;">
                    <strong>Error del Servidor Local. Detalles de depuración:</strong><br><br>
                    ${textoCrudo.substring(0, 400)}
                </td></tr>`;
            }
        })
        .catch(err => {
            console.error("Falla en petición:", err);
            cuerpo.innerHTML = `<tr><td colspan="4" style="text-align:center; color:red; padding:15px;">Falla de red: ${err.message}</td></tr>`;
        });

}

// ➕ DOM INJECTION: Agrega un renglón editable a la tabla de ingredientes
function agregarFilaIngrediente(insumoId = '', cantidad = '1.000', unidad = 'Kg') {
    contadorFilasReceta++;
    const cuerpo = document.getElementById('cuerpo_tabla_receta');

    // Generamos las opciones del select basándonos en los insumos con stock
    let opcionesSelect = '<option value="">-- Seleccione Ingrediente --</option>';
    catalogoInsumos.forEach(insumo => {
        const selected = (parseInt(insumo.id) === parseInt(insumoId)) ? 'selected' : '';
        opcionesSelect += `<option value="${insumo.id}" data-unidad="${insumo.unidad_medida}" ${selected}>${insumo.nombre}</option>`;
    });

    const nuevaFila = document.createElement('tr');
    nuevaFila.id = `fila_receta_${contadorFilasReceta}`;
    nuevaFila.innerHTML = `
        <td>
            <select name="insumo_id[]" class="form-control-receta" required style="font-family:inherit; font-size:13px;" onchange="actualizarEtiquetaUnidadFila(this, ${contadorFilasReceta});">
                ${opcionesSelect}
            </select>
        </td>
        <td>
            <input type="number" step="0.001" min="0.001" name="cantidad_porcion[]" class="form-control-receta input-receta-cant" required value="${parseFloat(cantidad).toFixed(3)}" onfocus="this.select();">
        </td>
        <td style="text-align: center; font-weight: bold; color: #475569; font-size: 13px;" id="lbl_unidad_receta_${contadorFilasReceta}">
            ${unidad}
        </td>
        <td style="text-align: center;">
            <button type="button" class="btn-danger-row" style="border:none; cursor:pointer;" onclick="eliminarFilaReceta(${contadorFilasReceta});">❌</button>
        </td>
    `;

    cuerpo.appendChild(nuevaFila);
}

// 🔄 SYNC: Sincroniza el texto de la unidad (gr/unidades) al cambiar de insumo
function actualizarEtiquetaUnidadFila(selectElement, numeroFila) {
    const opcionSeleccionada = selectElement.options[selectElement.selectedIndex];
    const unidadMedida = opcionSeleccionada.getAttribute('data-unidad') || 'Und';
    document.getElementById(`lbl_unidad_receta_${numeroFila}`).innerText = unidadMedida;
}

// ❌ REMOVE: Quita la fila de ingredientes seleccionada del DOM
function eliminarFilaReceta(numeroFila) {
    const fila = document.getElementById(`fila_receta_${numeroFila}`);
    if (fila) {
        fila.remove();
        
        // Si borra todo, forzamos abrir un renglón limpio de asistencia obligatoria
        const filasRestantes = document.getElementsByName('insumo_id[]');
        if (filasRestantes.length === 0) {
            agregarFilaIngrediente();
        }
    }
}
</script>

<script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>
</html>
