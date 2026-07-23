<?php
// views/productos.php

// 1. Requerimos el controlador de productos para procesar catálogos e imágenes
require_once __DIR__ . '/../controllers/ProductoController.php';

$controller = new ProductoController();
$productos = $controller->listar();       // Carga el inventario maestro con INNER JOIN de categorías
$categorias = $controller->obtenerCategorias(); // Carga el combo select de clasificaciones

// 2. Detección de la URL base idéntica a tu PC local y entorno en Hostinger
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
if (!defined('URL_BASE')) {
    define('URL_BASE', ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/");
}

// 3. Capturar datos para el flujo de modificación (Modo Edición)
$modeloInterno = new ProductoModelo();
$productoEditar = null;
if (isset($_GET['edit_id'])) {
    $productoEditar = $modeloInterno->obtenerProductoPorId(intval($_GET['edit_id']));
}

$msg_error = $_GET['error'] ?? null;
$msg_success = $_GET['success'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mantenimiento de Catálogo - Jungle Pizza</title>
    
    <!-- Enlace a tus hojas de estilo globales -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    <!-- Enlace a tu nueva hoja de estilos aislada con versión dinámica -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/productos.css?v=1.2">
</head>
<body>
    <!-- Cabecera Mobile original intacta -->
    <header class="mobile-header">
        <button class="hamburger-btn" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <div class="mobile-logo">🍕🍕 Jungle Dash</div>
    </header>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="dashboard-layout">
        <!-- Inclusión nativa de tu barra lateral sin alteraciones -->
        <?php include 'sidebar.php'; ?>

        <main class="main-content">
            <h2>Mantenimiento de Menú y Productos</h2>
            <p style="color: #666; margin-bottom: 20px;">Configura las especialidades, bebidas, mermas y modificadores del sistema.</p>
            
            <!-- Bloque de Notificaciones de URL (Mensajes del Controlador) -->
            <?php if ($msg_error): ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
            <?php if ($msg_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div><?php endif; ?>

            <div class="productos-grid">
                
                <!-- COLUMNA 1: FORMULARIO CRUD (REGISTRO / EDICIÓN) -->
                <div class="product-card">
                    <h3><?php echo $productoEditar ? '✏️ Modificar Producto' : '➕ Registrar Alimento / Insumo'; ?></h3>
                    
                    <!-- Formulario multiparte preparado para la carga de archivos -->
                    <form action="<?php echo URL_BASE; ?>controllers/ProductoController.php" method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                        <input type="hidden" name="accion" value="<?php echo $productoEditar ? 'editar_producto' : 'crear_producto'; ?>">
                        <input type="hidden" name="id" value="<?php echo $productoEditar['id'] ?? ''; ?>">

                        <!-- Fila 1: Nombre del Producto -->
                        <div style="margin-bottom: 15px;">
                            <label style="display:block; margin-bottom:5px; font-weight:600; font-size:14px; color:var(--verde-oscuro);">Nombre Comercial</label>
                            <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($productoEditar['nombre'] ?? ''); ?>" required placeholder="Ej. Pizza Súper Suprema">
                        </div>

                        <!-- Fila 2: Categoría y Unidad de Medida -->
                        <div class="form-row-grid">
                            <div>
                                <label style="display:block; margin-bottom:5px; font-weight:600; font-size:14px; color:var(--verde-oscuro);">Clasificación / Categoría</label>
                                <select name="categoria_id" class="form-control" required>
                                    <option value="">-- Seleccionar --</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($productoEditar && (int)$productoEditar['categoria_id'] === (int)$cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; margin-bottom:5px; font-weight:600; font-size:14px; color:var(--verde-oscuro);">Unidad de Medida</label>
                                <input type="text" name="unidad_medida" class="form-control" value="<?php echo htmlspecialchars($productoEditar['unidad_medida'] ?? 'Unidad'); ?>" required placeholder="Ej. Unidad, Porción">
                            </div>
                        </div>
                        <!-- Fila 3: Control Financiero Avanzado (Precios de Costo y Venta) -->
                        <div class="form-row-grid">
                            <div>
                                <label style="display:block; margin-bottom:5px; font-weight:600; font-size:14px; color:var(--verde-oscuro);">Precio Costo (C$)</label>
                                <input type="number" step="0.01" min="0" name="precio_costo" class="form-control" value="<?php echo htmlspecialchars($productoEditar['precio_costo'] ?? '0.00'); ?>" required>
                            </div>
                            <div>
                                <label style="display:block; margin-bottom:5px; font-weight:600; font-size:14px; color:var(--verde-oscuro);">Precio Venta PVP (C$)</label>
                                <input type="number" step="0.01" min="0" name="precio_base" class="form-control" value="<?php echo htmlspecialchars($productoEditar['precio_base'] ?? '0.00'); ?>" required>
                            </div>
                        </div>

                        <!-- Fila 4: Flags Lógicos de Casillas de Verificación -->
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="maneja_stock" id="maneja_stock" <?php echo (!isset($productoEditar) || (isset($productoEditar) && (int)$productoEditar['maneja_stock'] === 1)) ? 'checked' : ''; ?> onchange="toggleStockFields(this.checked)">
                                📦 ¿Maneja Inventario?
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="es_extra" <?php echo (isset($productoEditar) && (int)$productoEditar['es_extra'] === 1) ? 'checked' : ''; ?>>
                                🧀 Es Adicional / Extra
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="es_sabor_pizza" <?php echo (isset($productoEditar) && (int)$productoEditar['es_sabor_pizza'] === 1) ? 'checked' : ''; ?>>
                                🍕 Es Sabor Mixto
                            </label>
                        </div>

                        <!-- Fila 5: Valores de Inventario Físico -->
                        <div class="form-row-grid" id="inventario-inputs-box">
                            <div>
                                <label style="display:block; margin-bottom:5px; font-weight:600; font-size:14px; color:var(--verde-oscuro);">Stock Actual</label>
                                <input type="number" step="0.01" name="stock_actual" id="stock_actual" class="form-control" value="<?php echo htmlspecialchars($productoEditar['stock_actual'] ?? '0.00'); ?>">
                            </div>
                            <div>
                                <label style="display:block; margin-bottom:5px; font-weight:600; font-size:14px; color:var(--verde-oscuro);">Alerta Mínimo</label>
                                <input type="number" step="0.01" name="stock_minimo" id="stock_minimo" class="form-control" value="<?php echo htmlspecialchars($productoEditar['stock_minimo'] ?? '0.00'); ?>">
                            </div>
                        </div>

                        <!-- Fila 6: Descripción del Producto -->
                        <div style="margin-bottom: 15px;">
                            <label style="display:block; margin-bottom:5px; font-weight:600; font-size:14px; color:var(--verde-oscuro);">Descripción / Ingredientes</label>
                            <input type="text" name="descripcion" class="form-control" value="<?php echo htmlspecialchars($productoEditar['descripcion'] ?? ''); ?>" placeholder="Ej. Salsa artesanal, jamón y piña.">
                        </div>

                        <!-- Fila 7: Carga y Previsualización de la Fotografía -->
                        <div style="margin-bottom: 20px;">
                            <label style="display:block; margin-bottom:5px; font-weight:600; font-size:14px; color:var(--verde-oscuro);">Fotografía del Menú</label>
                            <div class="img-preview-container">
                                <?php if (!empty($productoEditar['imagen']) && file_exists(__DIR__ . '/../public/uploads/productos/' . $productoEditar['imagen'])): ?>
                                    <img src="<?php echo URL_BASE; ?>public/uploads/productos/<?php echo $productoEditar['imagen']; ?>" class="img-menu-thumbnail" alt="Preview">
                                    <span style="font-size:12px; color:#555; font-family:monospace; word-break:break-all;"><?php echo htmlspecialchars($productoEditar['imagen']); ?></span>
                                <?php else: ?>
                                    <div style="width:65px; height:65px; background:#e2e8f0; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:20px;">🍕🍕</div>
                                    <span style="font-size:12px; color:#999;">Formatos JPG o PNG (Max 2MB).</span>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="imagen" class="form-control" accept="image/png, image/jpeg, image/jpg">
                        </div>

                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn-action" style="background: var(--verde-selva); padding: 14px 28px; width:100%;">
                                <?php echo $productoEditar ? '💾 Guardar Modificaciones' : '🚀 Añadir al Menú Comercial'; ?>
                            </button>
                            <?php if ($productoEditar): ?>
                                <a href="index.php?v=mantenimiento_productos" style="display:block; text-align:center; margin-top:15px; color:#666; font-size:14px; text-decoration:none; font-weight:bold;">❌ Cancelar Edición</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <!-- COLUMNA 2: TABLA DE VISUALIZACIÓN DEL MENÚ ACTUAL -->
                <div class="product-card">
                    <h3>📋 Menú Activo en Sucursal</h3>
                    <div class="table-responsive">
                        <table class="jungle-table">
                            <thead>
                                <tr>
                                    <th>Imagen</th>
                                    <th>Detalles Producto</th>
                                    <th>Costo / PVP</th>
                                    <th>Estado Stock</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($productos)): ?>
                                    <tr><td colspan="5" style="text-align: center; color: #999; padding: 25px;">No hay productos registrados en el menú todavía.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($productos as $p): ?>
                                        <tr>
                                            <!-- Miniatura Visual del Producto -->
                                            <td>
                                                <?php if (!empty($p['imagen']) && file_exists(__DIR__ . '/../public/uploads/productos/' . $p['imagen'])): ?>
                                                    <img src="<?php echo URL_BASE; ?>public/uploads/productos/<?php echo $p['imagen']; ?>" class="img-menu-thumbnail" style="width:50px; height:50px; margin:0;" alt="Foto">
                                                <?php else: ?>
                                                    <div style="width:50px; height:50px; background:#e2e8f0; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:18px;">🍕🍕</div>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Nombre Comercial y Categoría -->
                                            <td>
                                                <strong style="color:var(--verde-oscuro); display:block; font-size:15px;"><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                                <span style="font-size:11px; text-transform:uppercase; font-weight:bold; color:#777; background:#f1f5f9; padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($p['nombre_categoria']); ?></span>
                                            </td>
                                            
                                            <!-- Control Financiero de Precios -->
                                            <td>
                                                <span style="font-size:12px; color:#777; display:block;">Costo: C$ <?php echo number_format($p['precio_costo'], 2); ?></span>
                                                <strong style="color:var(--naranja-pizza); font-size:14px;">Venta: C$ <?php echo number_format($p['precio_base'], 2); ?></strong>
                                            </td>
                                            
                                            <!-- Stock Dinámico con Insignias Inteligentes -->
                                            <td>
                                                <?php if ((int)$p['maneja_stock'] === 0): ?>
                                                    <span class="stock-badge stock-infinito">⏰ Al Instante</span>
                                                <?php else: ?>
                                                    <?php if (floatval($p['stock_actual']) <= floatval($p['stock_minimo'])): ?>
                                                        <span class="stock-badge stock-bajo">⚠️ <?php echo floatval($p['stock_actual']); ?> <?php echo htmlspecialchars($p['unidad_medida']); ?></span>
                                                    <?php else: ?>
                                                        <span class="stock-badge stock-ok">✅ <?php echo floatval($p['stock_actual']); ?> <?php echo htmlspecialchars($p['unidad_medida']); ?></span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <?php if ((int)$p['es_extra'] === 1): ?><span style="display:inline-block; font-size:9px; background:#fff3cd; color:#856404; padding:1px 4px; border-radius:3px; margin-left:2px; font-weight:bold;">EXTRA</span><?php endif; ?>
                                                <?php if ((int)$p['es_sabor_pizza'] === 1): ?><span style="display:inline-block; font-size:9px; background:#e3f2fd; color:#0d47a1; padding:1px 4px; border-radius:3px; margin-left:2px; font-weight:bold;">MIXTO</span><?php endif; ?>
                                            </td>
                                            
                                            <!-- Botones de Control de Fila -->
                                            <td style="white-space: nowrap;">
                                                <a href="index.php?v=mantenimiento_productos&edit_id=<?php echo $p['id']; ?>" class="btn-action btn-edit" style="padding:8px 12px; margin-right:3px;">Editar</a>
                                                <a href="<?php echo URL_BASE; ?>controllers/ProductoController.php?action=eliminar_producto&del_id=<?php echo $p['id']; ?>" class="btn-action" style="background:#c92a2a; padding:8px 12px;" onclick="return confirm('¿Estás seguro de eliminar este producto?');">Eliminar</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Script de control interactivo para deshabilitar campos de stock si el insumo se prepara al momento -->
    <script>
        function toggleStockFields(checked) {
            const container = document.getElementById('inventario-inputs-box');
            const actualInput = document.getElementById('stock_actual');
            const minimoInput = document.getElementById('stock_minimo');
            
            if (!container || !actualInput || !minimoInput) return;
            
            if (checked) {
                container.style.opacity = '1';
                container.style.pointerEvents = 'auto';
                actualInput.setAttribute('required', 'required');
                minimoInput.setAttribute('required', 'required');
            } else {
                container.style.opacity = '0.4';
                container.style.pointerEvents = 'none';
                actualInput.removeAttribute('required');
                minimoInput.removeAttribute('required');
                actualInput.value = '0.00';
                minimoInput.value = '0.00';
            }
        }
        
        // Ejecución inmediata al cargar para validar el estado si estamos editando
        document.addEventListener("DOMContentLoaded", function() {
            const checkbox = document.getElementById('maneja_stock');
            if (checkbox) toggleStockFields(checkbox.checked);
        });
    </script>
    <script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>
</html>
