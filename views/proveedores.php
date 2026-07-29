<?php
// views/proveedores.php
// 1. Requerimos el controlador e instancias necesarias para procesar proveedores
require_once __DIR__ . '/../models/ProveedorModelo.php';
$modeloInterno = new Proveedor();

// 2. Captura de filtros desde la URL (GET) para el buscador
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// 3. Paginación adaptada (Ajustable según rendimiento)
$por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
if ($pagina_actual < 1) $pagina_actual = 1;

// Listar tus proveedores activos de forma limpia
$lista_proveedores = $modeloInterno->listarActivos(); 

// Si hay un término de búsqueda, filtramos el array para que funcione el buscador nativo
if (!empty($buscar)) {
    $lista_proveedores = array_filter($lista_proveedores, function($p) use ($buscar) {
        return stripos($p['nombre_empresa'], $buscar) !== false || stripos($p['nombre_contacto'], $buscar) !== false;
    });
}

$total_registros = count($lista_proveedores);
$total_paginas = ceil($total_registros / $por_pagina);
if ($total_paginas < 1) $total_paginas = 1;

// 4. Sincronización automática de URL_BASE
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
if (!defined('URL_BASE')) {
    define('URL_BASE', ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/");
}

// 5. Capturar datos si estamos en Modo Edición tradicional por URL
$proveedorEditar = null;
if (isset($_GET['edit_id'])) {
    $id_editar = intval($_GET['edit_id']);
    foreach ($lista_proveedores as $prov) {
        if ((int)$prov['id'] === $id_editar) {
            $proveedorEditar = $prov;
            break;
        }
    }
}

$msg_error = $_GET['error'] ?? null;
$msg_success = $_GET['success'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mantenimiento de Proveedores - Jungle Pizza</title>
    <!-- Hojas de estilo globales de tu proyecto -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    
    <!-- Estilos internos integrados adaptados exactamente de categorías -->
    <style>
        .proveedores-grid { display: grid !important; grid-template-columns: 1fr; gap: 25px; margin-top: 20px; width: 100%; }
        .cat-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(27,67,50,0.05); padding: 25px; border-top: 4px solid var(--verde-claro, #52b788); }
        .cat-card h3 { color: var(--verde-oscuro, #1b4332); font-size: 1.25rem; margin-bottom: 20px; border-bottom: 2px solid var(--verde-menta, #d8f3dc); padding-bottom: 8px; }
        .cat-card label { display: block !important; margin-bottom: 6px !important; font-weight: 600 !important; font-size: 13px !important; color: var(--verde-oscuro, #1b4332) !important; }
        .form-control { width: 100% !important; padding: 12px 14px !important; border: 2px solid #e2e8f0 !important; border-radius: 8px !important; box-sizing: border-box !important; font-size: 0.95rem !important; background-color: #fafbfc !important; color: #333 !important; transition: all 0.2s ease; }
        .form-control:focus { outline: none !important; border-color: var(--verde-claro, #52b788) !important; background-color: #fff !important; box-shadow: 0 0 0 3px rgba(82,183,136,0.15) !important; }
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
        .alert { padding: 14px; border-radius: 8px; margin-bottom: 20px; font-size: 0.95rem; font-weight: 500; }
        .alert-error { background: #ffe3e3; color: #c92a2a; border: 1px solid #ffa8a8; }
        .alert-success { background: #ebfbee; color: #2b8a3e; border: 1px solid #96f2d7; }
        @media (min-width: 992px) { .proveedores-grid { grid-template-columns: 340px 1fr; align-items: start; } }
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
        <h2>Mantenimiento de Proveedores</h2>
        <p style="color: #666; margin-bottom: 20px;">Administra las empresas proveedoras de insumos y materias primas de la pizzería.</p>

        <?php if ($msg_error): ?><div class="alert alert-error">⚠ <?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
        <?php if ($msg_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div><?php endif; ?>

        <div class="proveedores-grid">
            <!-- COLUMNA 1: FORMULARIO CRUD -->
            <div class="cat-card">
                <h3><?php echo $proveedorEditar ? '✏ Modificar Proveedor' : '🚚 Nuevo Proveedor'; ?></h3>
                
                <form action="<?php echo URL_BASE; ?>controllers/ProveedorController.php" method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="accion" value="<?php echo $proveedorEditar ? 'editar' : 'registrar'; ?>">
                    <input type="hidden" name="id" value="<?php echo $proveedorEditar['id'] ?? ''; ?>">

                    <div style="margin-bottom: 15px;">
                        <label>Nombre de la Empresa *</label>
                        <input type="text" name="nombre_empresa" class="form-control" value="<?php echo htmlspecialchars($proveedorEditar['nombre_empresa'] ?? ''); ?>" required placeholder="Ej. Distribuidora Quesos S.A.">
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label>Nombre de Contacto</label>
                        <input type="text" name="nombre_contacto" class="form-control" value="<?php echo htmlspecialchars($proveedorEditar['nombre_contacto'] ?? ''); ?>" placeholder="Ej. Carlos Mendoza">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($proveedorEditar['telefono'] ?? ''); ?>" placeholder="Ej. +505 8888-8888">
                    </div>

                    <div>
                        <button type="submit" class="btn-action" style="background: var(--verde-selva, #2d6a4f); color: #fff; padding: 12px; width: 100%;">
                            <?php echo $proveedorEditar ? '💾💾 Guardar Cambios' : '🚀🚀 Registrar Proveedor'; ?>
                        </button>
                        <?php if ($proveedorEditar): ?>
                            <a href="index.php?v=proveedores" style="display:block; text-align:center; margin-top:15px; color:#666; font-size:14px; text-decoration:none; font-weight:bold;">❌ Cancelar Edición</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <!-- COLUMNA 2: TABLA DE VISUALIZACIÓN CON BUSCADOR -->
            <div class="cat-card">
                <!-- BARRA DE FILTRADO SUPERIOR RÁPIDO -->
                <div class="table-toolbar">
                    <form action="index.php" method="GET" class="filter-group">
                        <input type="hidden" name="v" value="proveedores">
                        <input type="text" name="buscar" class="form-control" style="max-width: 320px;" value="<?php echo htmlspecialchars($buscar); ?>" placeholder="Buscar proveedor por empresa o contacto...">
                        <button type="submit" class="btn-action" style="background: var(--verde-oscuro, #1b4332); color: #fff; padding: 10px 18px;">🔍🔍 Buscar</button>
                        <?php if (!empty($buscar)): ?>
                            <a href="index.php?v=proveedores" class="btn-action" style="background: #666; color: #fff; text-decoration: none; padding: 10px 18px;">❌ Limpiar</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- TABLA RESPONSIVA DE REGISTROS -->
                <div class="table-responsive">
                    <table class="jungle-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Nombre de Empresa</th>
                                <th>Contacto</th>
                                <th>Teléfono</th>
                                <th style="width: 180px; text-align: center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lista_proveedores)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #999; padding: 25px;">
                                        No se encontraron proveedores registrados activos.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lista_proveedores as $p): ?>
                                    <tr>
                                        <td><code>#<?php echo $p['id']; ?></code></td>
                                        <td><strong style="color: var(--verde-oscuro, #1b4332); font-size: 15px;"><?php echo htmlspecialchars($p['nombre_empresa']); ?></strong></td>
                                        <td style="color: #555; font-size: 13px;"><?php echo htmlspecialchars($p['nombre_contacto'] ?: 'N/A'); ?></td>
                                        <td style="color: #555; font-size: 13px;"><?php echo htmlspecialchars($p['telefono'] ?: 'N/A'); ?></td>
                                        <td style="white-space: nowrap; text-align: center;">
                                            <!-- Botón Editar -->
                                            <a href="index.php?v=proveedores&edit_id=<?php echo $p['id']; ?>" class="btn-action btn-edit">Editar</a>
                                            
                                            <!-- Botón Eliminar (Borrado Lógico) -->
                                            <form action="<?php echo URL_BASE; ?>controllers/ProveedorController.php" method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de dar de baja este proveedor?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" class="btn-action btn-delete">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div> <!-- Fin de cat-card tabla -->
        </div> <!-- Fin de proveedores-grid -->
    </main>
</div> <!-- Fin de dashboard-layout -->

<script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>
</html>
