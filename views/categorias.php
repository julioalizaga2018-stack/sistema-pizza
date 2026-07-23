<?php
// views/categorias.php

// 1. Requerimos el controlador de categorías para procesar clasificaciones
require_once __DIR__ . '/../controllers/CategoriaController.php';
$controller = new CategoriaController();
$modeloInterno = new CategoriaModelo();

// 2. Captura y purificación de filtros desde la URL (GET)
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// 3. MATEMÁTICA DE PAGINACIÓN INDUSTRIAL
$por_pagina = 10; // Cantidad fija de categorías a renderizar por bloque en la tablet
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
if ($pagina_actual < 1) $pagina_actual = 1;

// Obtenemos los totales según la búsqueda para calcular el número de páginas
$total_registros = $modeloInterno->contarCategoriasFiltradas($buscar);
$total_paginas = ceil($total_registros / $por_pagina);
if ($total_paginas < 1) $total_paginas = 1;
if ($pagina_actual > $total_paginas) $pagina_actual = $total_paginas;

// Cálculo del Offset para MySQL
$offset = ($pagina_actual - 1) * $por_pagina;

// Ejecutamos la consulta con límites estrictos de rendimiento
$lista_categorias = $modeloInterno->listarCategoriasPaginadas($buscar, $por_pagina, $offset);

// 4. Sincronización automática de URL_BASE (PC Local y Hostinger)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
if (!defined('URL_BASE')) {
    define('URL_BASE', ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/");
}

// 5. Capturar datos si estamos en Modo Edición
$categoriaEditar = null;
if (isset($_GET['edit_id'])) {
    $categoriaEditar = $modeloInterno->obtenerCategoriaPorId(intval($_GET['edit_id']));
}

$msg_error = $_GET['error'] ?? null;
$msg_success = $_GET['success'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mantenimiento de Categorías - Jungle Pizza</title>
    
    <!-- Hojas de estilo globales -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    
    <!-- 🎨 ESTILOS INTERNOS INTEGRADOS PARA MÁXIMA RAPIDEZ -->
    <style>
        .categorias-grid { display: grid !important; grid-template-columns: 1fr; gap: 25px; margin-top: 20px; width: 100%; }
        .cat-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(27,67,50,0.05); padding: 25px; border-top: 4px solid var(--verde-claro, #52b788); }
        .cat-card h3 { color: var(--verde-oscuro, #1b4332); font-size: 1.25rem; margin-bottom: 20px; border-bottom: 2px solid var(--verde-menta, #d8f3dc); padding-bottom: 8px; }
        .cat-card label { display: block !important; margin-bottom: 6px !important; font-weight: 600 !important; font-size: 13px !important; color: var(--verde-oscuro, #1b4332) !important; }
        
        .form-control {
            width: 100% !important; padding: 12px 14px !important; border: 2px solid #e2e8f0 !important; border-radius: 8px !important;
            box-sizing: border-box !important; font-size: 0.95rem !important; background-color: #fafbfc !important; color: #333 !important; transition: all 0.2s ease;
        }
        .form-control:focus { outline: none !important; border-color: var(--verde-claro, #52b788) !important; background-color: #fff !important; box-shadow: 0 0 0 3px rgba(82,183,136,0.15) !important; }
        textarea.form-control { resize: vertical; min-height: 80px; }
        
        .table-toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px; margin-bottom: 15px; background: #f8fafc; padding: 12px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .filter-group { display: flex; flex-wrap: wrap; gap: 10px; flex: 1; }
        
        .table-responsive { width: 100%; overflow-x: auto; border-radius: 8px; border: 1px solid #edf2f7; }
        .jungle-table { width: 100%; border-collapse: collapse; text-align: left; min-width: 500px; }
        .jungle-table th { background-color: var(--verde-oscuro, #1b4332); color: #ffffff; padding: 12px 15px; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .jungle-table td { padding: 12px 15px; border-bottom: 1px solid #edf2f7; font-size: 0.95rem; vertical-align: middle; }
        .jungle-table tr:hover { background-color: rgba(216, 243, 220, 0.2); }
        
        .jungle-table a.btn-action, .cat-card button.btn-action { display: inline-flex !important; align-items: center !important; justify-content: center !important; padding: 8px 16px !important; font-size: 0.85rem !important; font-weight: 700 !important; text-transform: uppercase !important; border-radius: 6px !important; text-decoration: none !important; border: none !important; cursor: pointer !important; transition: all 0.2s ease !important; }
        .jungle-table a.btn-edit { background-color: var(--naranja-pizza, #e67e22) !important; color: #ffffff !important; }
        .jungle-table a.btn-edit:hover { background-color: #d35400 !important; }
        .jungle-table a.btn-delete { background-color: #c92a2a !important; color: #ffffff !important; }
        .jungle-table a.btn-delete:hover { background-color: #a61e1e !important; }
        .jungle-table .btn-system { background-color: #ced4da !important; color: #6c757d !important; cursor: not-allowed !important; }
        
        .pagination-container { display: flex; justify-content: center; align-items: center; gap: 5px; margin-top: 20px; }
        .page-number-link { padding: 6px 12px; border-radius: 6px; border: 1px solid #e2e8f0; background: #ffffff; color: #333; text-decoration: none; font-weight: 600; font-size: 0.85rem; }
        .page-number-link.page-active { background-color: var(--verde-oscuro, #1b4332); color: #ffffff; border-color: var(--verde-oscuro, #1b4332); }
        
        .alert { padding: 14px; border-radius: 8px; margin-bottom: 20px; font-size: 0.95rem; font-weight: 500; }
        .alert-error { background: #ffe3e3; color: #c92a2a; border: 1px solid #ffa8a8; }
        .alert-success { background: #ebfbee; color: #2b8a3e; border: 1px solid #96f2d7; }

        @media (min-width: 992px) { .categorias-grid { grid-template-columns: 340px 1fr; align-items: start; } }
    </style>
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
            <h2>Mantenimiento de Categorías</h2>
            <p style="color: #666; margin-bottom: 20px;">Administra las clasificaciones comerciales del menú (Pizzas, Bebidas, Entradas, etc.).</p>
            
            <!-- Bloque de Notificaciones de URL -->
            <?php if ($msg_error): ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
            <?php if ($msg_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div><?php endif; ?>

            <div class="categorias-grid">
                
                <!-- COLUMNA 1: FORMULARIO CRUD (REGISTRO / EDICIÓN) -->
                <div class="cat-card">
                    <h3><?php echo $categoriaEditar ? '✏️ Modificar Categoría' : '官方 📄 Nueva Categoría'; ?></h3>
                    
                    <form action="<?php echo URL_BASE; ?>controllers/CategoriaController.php" method="POST" style="margin-top: 15px;">
                        <input type="hidden" name="accion" value="<?php echo $categoriaEditar ? 'editar_categoria' : 'crear_categoria'; ?>">
                        <input type="hidden" name="id" value="<?php echo $categoriaEditar['id'] ?? ''; ?>">

                        <!-- Campo: Nombre de la Categoría -->
                        <div style="margin-bottom: 15px;">
                            <label>Nombre de Clasificación</label>
                            <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($categoriaEditar['nombre'] ?? ''); ?>" required placeholder="Ej. Postres, Combos">
                        </div>

                        <!-- Campo: Descripción Corta -->
                        <div style="margin-bottom: 20px;">
                            <label>Descripción / Notas Operativas</label>
                            <textarea name="descripcion" class="form-control" placeholder="Ej. Insumos dulces o adicionales para caja..."><?php echo htmlspecialchars($categoriaEditar['descripcion'] ?? ''); ?></textarea>
                        </div>

                        <!-- Botones de Acción Táctiles -->
                        <div>
                            <button type="submit" class="btn-action" style="background: var(--verde-selva, #2d6a4f); color: #fff; padding: 12px; width: 100%;">
                                <?php echo $categoriaEditar ? '💾 Guardar Modificaciones' : '🚀 Registrar Categoría'; ?>
                            </button>
                            <?php if ($categoriaEditar): ?>
                                <a href="index.php?v=mantenimiento_categorias" style="display:block; text-align:center; margin-top:15px; color:#666; font-size:14px; text-decoration:none; font-weight:bold;">❌ Cancelar Edición</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <!-- COLUMNA 2: TABLA DE VISUALIZACIÓN PAGINADA CON BUSCADOR -->
                <div class="cat-card">
                    <!-- 🛠️ BARRA DE FILTRADO SUPERIOR RÁPIDO -->
                    <div class="table-toolbar">
                        <form action="index.php" method="GET" class="filter-group">
                            <input type="hidden" name="v" value="mantenimiento_categorias">
                            <input type="text" name="buscar" class="form-control" style="max-width: 320px;" value="<?php echo htmlspecialchars($buscar); ?>" placeholder="Buscar categoría por nombre...">
                            <button type="submit" class="btn-action" style="background: var(--verde-oscuro, #1b4332); color: #fff; padding: 10px 18px;">🔍 Buscar</button>
                            <?php if (!empty($buscar)): ?>
                                <a href="index.php?v=mantenimiento_categorias" class="btn-action" style="background: #666; color: #fff; text-decoration: none; padding: 10px 18px;">❌ Limpiar</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- TABLA RESPONSIVA DE REGISTROS -->
                    <div class="table-responsive">
                        <table class="jungle-table">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">ID</th>
                                    <th>Nombre de Categoría</th>
                                    <th>Descripción Operativa</th>
                                    <th style="width: 180px; text-align: center;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lista_categorias)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #999; padding: 25px;">
                                            No se encontraron clasificaciones registradas.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($lista_categorias as $c): ?>
                                        <tr>
                                            <td><code>#<?php echo $c['id']; ?></code></td>
                                            <td><strong style="color: var(--verde-oscuro, #1b4332); font-size: 15px;"><?php echo htmlspecialchars($c['nombre']); ?></strong></td>
                                            <td style="color: #555; font-size: 13px;"><?php echo htmlspecialchars($c['descripcion'] ?: 'Sin notas comerciales.'); ?></td>
                                            <td style="white-space: nowrap; text-align: center;">
                                                <!-- Botón Editar -->
                                                <a href="index.php?v=mantenimiento_categorias&edit_id=<?php echo $c['id']; ?>" class="btn-action btn-edit">Editar</a>
                                                
                                                <!-- Regla de Negocio: Si es una de las 6 categorías iniciales del script, bloquea la baja -->
                                                <?php if ((int)$c['id'] <= 6): ?>
                                                    <a href="javascript:void(0);" class="btn-action btn-system" title="Categoría del sistema protegida">Bloqueado</a>
                                                <?php else: ?>
                                                    <a href="<?php echo URL_BASE; ?>controllers/CategoriaController.php?action=eliminar_categoria&del_id=<?php echo $c['id']; ?>" 
                                                       class="btn-action btn-delete" 
                                                       onclick="return confirm('¿Estás seguro de dar de baja esta categoría? Los productos asociados podrían quedar huérfanos.');">
                                                       Eliminar
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- 🎛️ BOTONERA DE PAGINACIÓN FLUIDA -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="pagination-container">
                            <?php if ($pagina_actual > 1): ?>
                                <a href="index.php?v=mantenimiento_categorias&pagina=<?php echo $pagina_actual - 1; ?>&buscar=<?php echo urlencode($buscar); ?>" class="page-number-link">&laquo;</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                <a href="index.php?v=mantenimiento_categorias&pagina=<?php echo $i; ?>&buscar=<?php echo urlencode($buscar); ?>" 
                                   class="page-number-link <?php echo ($pagina_actual === $i) ? 'page-active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($pagina_actual < $total_paginas): ?>
                                <a href="index.php?v=mantenimiento_categorias&pagina=<?php echo $pagina_actual + 1; ?>&buscar=<?php echo urlencode($buscar); ?>" class="page-number-link">&raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>
</html>
