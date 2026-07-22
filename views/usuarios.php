<?php
// views/usuarios.php

// Requerimos el controlador de usuarios para poder invocar sus métodos de datos
require_once __DIR__ . '/../controllers/UsuarioController.php';

// Instanciamos el controlador de manera segura respetando tu lógica
$controller = new UsuarioController();
$usuarios = $controller->listar(); 
$roles = $controller->obtenerRoles(); 

// Detección automática de la URL base para el CSS y JS (Tu lógica intacta)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
if (!defined('URL_BASE')) {
    define('URL_BASE', ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/");
}

// Cargar datos si se va a editar utilizando tu modelo interno de forma segura
$modelo = new UsuarioModelo();
$usuarioEditar = null;
if (isset($_GET['edit_id'])) {
    $usuarioEditar = $modelo->obtenerUsuarioPorId(intval($_GET['edit_id']));
}

$msg_error = $_GET['error'] ?? null;
$msg_success = $_GET['success'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mantenimiento de Usuarios - Pizzería</title>
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    <style>
        :root {
            --verde-oscuro: #1b4332;
            --verde-selva: #2d6a4f;
            --verde-claro: #52b788;
            --verde-menta: #d8f3dc;
            --naranja-pizza: #e67e22;
        }
        .table-responsive { width: 100%; overflow-x: auto; margin-top: 20px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .crud-table { width: 100%; border-collapse: collapse; background: #fff; overflow: hidden; min-width: 600px; }
        .crud-table th, .crud-table td { padding: 14px 15px; text-align: left; border-bottom: 1px solid #eeeeee; font-size: 0.95rem; }
        .crud-table th { background-color: var(--verde-oscuro); color: #fff; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
        .crud-table tr:hover { background-color: rgba(216, 243, 220, 0.25); }
        .btn-action { padding: 10px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; color: white; display: inline-block; border: none; cursor: pointer; font-weight: bold; transition: background 0.2s; }
        .btn-edit { background-color: var(--naranja-pizza); }
        .btn-edit:hover { background-color: #d35400; }
        .form-card { background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(27,67,50,0.05); border-top: 4px solid var(--verde-claro); }
        .form-card h3 { color: var(--verde-oscuro); margin-bottom: 15px; font-size: 1.2rem; }
        .form-group-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .form-control { width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; font-size: 1rem; background-color: #fafbfc; transition: border-color 0.2s; }
        .form-control:focus { outline: none; border-color: var(--verde-claro); background-color: #fff; }
        select.form-control { appearance: none; cursor: pointer; }
        .alert { padding: 14px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; font-weight: 500; }
        .alert-error { background: #ffe3e3; color: #c92a2a; border: 1px solid #ffa8a8; }
        .alert-success { background: #ebfbee; color: #2b8a3e; border: 1px solid #96f2d7; }
        .badge-rol { background: var(--verde-menta); color: var(--verde-oscuro); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
    </style>
</head>
<body>
    <header class="mobile-header">
        <button class="hamburger-btn" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <div class="mobile-logo">🍕 Pizzería Dash</div>
    </header>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>

        <main class="main-content">
            <h2>Gestión y Mantenimiento de Personal</h2>
            <p>Registra y actualiza los accesos de tus empleados.</p>
            
            <?php if ($msg_error): ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
            <?php if ($msg_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div><?php endif; ?>

            <div class="form-card">
                <h3><?php echo $usuarioEditar ? '✏️ Modificar Empleado' : '➕ Registrar Nuevo Empleado'; ?></h3>
                
                <form action="<?php echo URL_BASE; ?>controllers/UsuarioController.php" method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="accion" value="<?php echo $usuarioEditar ? 'editar_usuario' : 'crear_usuario'; ?>">
                    <input type="hidden" name="id" value="<?php echo $usuarioEditar['id'] ?? ''; ?>">

                    <div class="form-group-grid">
                        <input type="text" name="nombre" class="form-control" value="<?php echo $usuarioEditar['nombre'] ?? ''; ?>" placeholder="Nombre" required>
                        <input type="text" name="apellido" class="form-control" value="<?php echo $usuarioEditar['apellido'] ?? ''; ?>" placeholder="Apellido" required>
                    </div>
                    
                    <div class="form-group-grid">
                        <input type="text" name="usuario" class="form-control" value="<?php echo $usuarioEditar['usuario'] ?? ''; ?>" placeholder="Nombre de usuario" required>
                        
                        <select name="rol_id" class="form-control" required>
                            <option value="">-- Seleccionar Puesto / Rol --</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['id']; ?>" <?php echo ($usuarioEditar && (int)$usuarioEditar['rol_id'] === (int)$r['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(strtoupper($r['nombre'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group-grid" style="grid-template-columns: 1fr;">
                        <input type="password" name="password" class="form-control" placeholder="<?php echo $usuarioEditar ? 'Dejar vacío para no cambiar clave' : 'Contraseña'; ?>" <?php echo $usuarioEditar ? '' : 'required'; ?>>
                    </div>

                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn-action" style="background: var(--verde-selva); font-weight: bold; padding: 12px 20px;">
                            <?php echo $usuarioEditar ? 'Actualizar Datos' : 'Guardar Empleado'; ?>
                        </button>
                        <?php if ($usuarioEditar): ?>
                            <a href="index.php?v=usuarios" style="margin-left: 15px; color: #666; font-size: 14px; text-decoration: none;">Cancelar Edición</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="crud-table">
                    <thead>
                        <tr>
                            <th>Nombre Completo</th>
                            <th>Usuario de Acceso</th>
                            <th>Puesto / Rol</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                                       <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr><td colspan="4" style="text-align: center; color: #999; padding: 25px;">No hay empleados registrados todavía.</td></tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($u['usuario']); ?></td>
                                    <td>
                                        <span class="badge-rol">
                                            <?php echo htmlspecialchars($u['nombre_rol'] ?? 'Empleado'); ?>
                                        </span>
                                    </td>
                                    <!-- 🛠️ COLUMNA DE ACCIONES AMPLIADA -->
                                    <td style="white-space: nowrap;">
                                        <!-- Botón de Editar original -->
                                        <a href="index.php?v=gestion_usuarios&edit_id=<?php echo $u['id']; ?>" class="btn-action btn-edit" style="margin-right: 5px;">Editar</a>
                                        
                                        <!-- Botón Nuevo de Baja con Confirmación Táctil JavaScript -->
                                        <a href="<?php echo URL_BASE; ?>controllers/UsuarioController.php?action=eliminar_usuario&del_id=<?php echo $u['id']; ?>" 
                                           class="btn-action" 
                                           style="background-color: #c92a2a; transition: background 0.2s;"
                                           onclick="return confirm('¿Estás seguro de dar de baja a este empleado? Perderá el acceso inmediato al sistema.');">
                                            Dar de Baja
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>

                </table>
            </div>
        </main>
    </div>

    <script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>
</html>
