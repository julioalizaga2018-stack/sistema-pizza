<?php
// views/mesas.php

// 1. Instanciamos el modelo para extraer tanto las áreas activas como el plano de mesas completo
require_once __DIR__ . '/../models/SalonModelo.php';
$modeloSalon = new SalonModelo();

$lista_mesas = $modeloSalon->listarMesasConAreas();
$lista_areas = $modeloSalon->listarAreasTodas(); // Alimenta el combo select de zonas

// 2. Sincronización de URL_BASE (Localhost y Hostinger)
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
    <title>Gestión de Mesas - Jungle Pizza</title>
    
    <!-- Hojas de estilo heredadas -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    
    <!-- 🎨 MAQUETACIÓN ELÁSTICA INTERNA PARA MÁXIMA VELOCIDAD -->
    <style>
        .mesas-grid { display: grid !important; grid-template-columns: 1fr; gap: 25px; margin-top: 20px; width: 100%; }
        .mesas-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(27,67,50,0.05); padding: 25px; border-top: 4px solid var(--verde-claro, #52b788); }
        .mesas-card h3 { color: var(--verde-oscuro, #1b4332); font-size: 1.25rem; margin-bottom: 20px; border-bottom: 2px solid var(--verde-menta, #d8f3dc); padding-bottom: 8px; }
        .mesas-card label { display: block !important; margin-bottom: 6px !important; font-weight: 600 !important; font-size: 13px !important; color: var(--verde-oscuro, #1b4332) !important; }
        
        .form-control {
            width: 100% !important; padding: 12px 14px !important; border: 2px solid #e2e8f0 !important; border-radius: 8px !important;
            box-sizing: border-box !important; font-size: 0.95rem !important; background-color: #fafbfc !important; color: #333 !important; transition: all 0.2s ease;
        }
        .form-control:focus { outline: none !important; border-color: var(--verde-claro, #52b788) !important; background-color: #fff !important; box-shadow: 0 0 0 3px rgba(82,183,136,0.15) !important; }
        
        select.form-control {
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://w3.org' viewBox='0 0 24 24' fill='%231b4332'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; background-size: 18px; padding-right: 35px !important; appearance: none;
        }

        .table-responsive { width: 100%; overflow-x: auto; border-radius: 8px; border: 1px solid #edf2f7; margin-top: 10px; }
        .jungle-table { width: 100%; border-collapse: collapse; text-align: left; min-width: 600px; }
        .jungle-table th { background-color: var(--verde-oscuro, #1b4332); color: #ffffff; padding: 12px 15px; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .jungle-table td { padding: 12px 15px; border-bottom: 1px solid #edf2f7; font-size: 0.95rem; vertical-align: middle; }
        .jungle-table tr:hover { background-color: rgba(216, 243, 220, 0.2); }
        
        .jungle-table a.btn-action, .mesas-card button.btn-action { display: inline-flex !important; align-items: center !important; justify-content: center !important; padding: 10px 20px !important; font-size: 0.85rem !important; font-weight: 700 !important; text-transform: uppercase !important; border-radius: 6px !important; text-decoration: none !important; border: none !important; cursor: pointer !important; transition: all 0.2s ease !important; }
        .jungle-table a.btn-delete { background-color: #c92a2a !important; color: #ffffff !important; }
        .jungle-table a.btn-delete:hover { background-color: #a61e1e !important; box-shadow: 0 4px 8px rgba(201,42,42,0.2) !important; }
        
        /* Insignias de Ocupación para Salón */
        .badge-estado { padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; }
        .estado-disponible { background-color: #d4edda; color: #155724; }
        .estado-ocupada { background-color: #f8d7da; color: #721c24; }
        .estado-reservada { background-color: #fff3cd; color: #856404; }
        .estado-mantenimiento { background-color: #e2e8f0; color: #383d41; }

        .alert { padding: 14px; border-radius: 8px; margin-bottom: 20px; font-size: 0.95rem; font-weight: 500; }
        .alert-error { background: #ffe3e3; color: #c92a2a; border: 1px solid #ffa8a8; }
        .alert-success { background: #ebfbee; color: #2b8a3e; border: 1px solid #96f2d7; }

        @media (min-width: 992px) { .mesas-grid { grid-template-columns: 340px 1fr; align-items: start; } }
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
        <!-- Barra lateral -->
        <?php include 'sidebar.php'; ?>

        <main class="main-content">
            <h2>Gestión de Mesas e Inventario de Salón</h2>
            <p style="color: #666; margin-bottom: 20px;">Da de alta las mesas físicas del restaurante amarrándolas a su salón y configurando sus capacidades de comensales.</p>
            
            <!-- Notificaciones URL del Controlador -->
            <?php if ($msg_error): ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
            <?php if ($msg_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div><?php endif; ?>

            <div class="mesas-grid">
                
                <!-- COLUMNA 1: REGISTRO DE NUEVA MESA -->
                <div class="mesas-card">
                    <h3>🪑 Incorporar Mesa</h3>
                    
                    <form action="<?php echo URL_BASE; ?>controllers/SalonController.php" method="POST" style="margin-top: 15px;">
                        <input type="hidden" name="accion" value="crear_mesa">

                        <!-- Selección del Área física (Alimentado dinámicamente) -->
                        <div style="margin-bottom: 15px;">
                            <label>Ubicación / Área Relacionada</label>
                            <select name="area_id" class="form-control" required>
                                <option value="">-- Seleccionar Zona --</option>
                                <?php foreach ($lista_areas as $area): ?>
                                    <option value="<?php echo $area['id']; ?>">
                                        <?php echo htmlspecialchars($area['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Número de identificación de Mesa -->
                        <div style="margin-bottom: 15px;">
                            <label>Identificador de Mesa</label>
                            <input type="text" name="numero_mesa" class="form-control" required placeholder="Ej. Mesa 5, Barra 2">
                        </div>

                        <!-- Capacidad de personas sentadas -->
                        <div style="margin-bottom: 20px;">
                            <label>Capacidad Máxima (Comensales)</label>
                            <input type="number" name="capacidad" min="1" max="20" class="form-control" value="4" required>
                        </div>

                        <div>
                            <button type="submit" class="btn-action" style="background: var(--verde-selva, #2d6a4f); color: #fff; width: 100%;">
                                🚀 Agregar Mesa al Plano
                            </button>
                        </div>
                    </form>
                </div>

                <!-- COLUMNA 2: TABLA DE MONITOREO DE PLAZAS -->
                <div class="mesas-card">
                    <h3>📋 Distribución General de Mesas</h3>
                    
                    <div class="table-responsive">
                        <table class="jungle-table">
                            <thead>
                                <tr>
                                    <th>Zona / Área</th>
                                    <th>Identificador</th>
                                    <th>Capacidad</th>
                                    <th>Estado de Ocupación</th>
                                    <th style="width: 120px; text-align: center;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lista_mesas)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: #999; padding: 25px;">
                                            No hay mesas registradas en el mapa del salón. Selecciona un área a la izquierda para empezar.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($lista_mesas as $m): ?>
                                        <tr>
                                            <td><span style="font-size: 11px; text-transform: uppercase; font-weight: bold; color: #555; background: #f1f5f9; padding: 3px 8px; border-radius: 4px;"><?php echo htmlspecialchars($m['nombre_area']); ?></span></td>
                                            <td><strong style="color: var(--verde-oscuro, #1b4332); font-size: 15px;"><?php echo htmlspecialchars($m['numero_mesa']); ?></strong></td>
                                            <td>👤 <strong><?php echo (int)$m['capacidad']; ?></strong> Personas</td>
                                            <td>
                                                <!-- Insignias de color automáticas según ENUM de base de datos -->
                                                <span class="badge-estado estado-<?php echo $m['estado']; ?>">
                                                    <?php echo htmlspecialchars($m['estado']); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <a href="<?php echo URL_BASE; ?>controllers/SalonController.php?action=eliminar_mesa&del_id=<?php echo $m['id']; ?>" 
                                                   class="btn-action btn-delete" 
                                                   onclick="return confirm('¿Estás seguro de remover esta mesa del mapa de salones?');">
                                                   Eliminar
                                                </a>
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

    <script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>
</html>
