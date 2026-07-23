<?php
// views/areas.php

// 1. Instanciamos el modelo directamente para extraer los salones existentes
require_once __DIR__ . '/../models/SalonModelo.php';
$modeloSalon = new SalonModelo();
$todas_areas = $modeloSalon->listarAreasTodas();

// 2. Sincronización automática de URL_BASE (Localhost y Hostinger)
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
    <title>Gestión de Áreas - Jungle Pizza</title>
    
    <!-- Hojas de estilo base y de barra lateral -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    
    <!-- 🎨 MAQUETACIÓN ELÁSTICA INTERNA PARA MÁXIMA VELOCIDAD -->
    <style>
        .salon-grid { display: grid !important; grid-template-columns: 1fr; gap: 25px; margin-top: 20px; width: 100%; }
        .salon-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(27,67,50,0.05); padding: 25px; border-top: 4px solid var(--verde-claro, #52b788); }
        .salon-card h3 { color: var(--verde-oscuro, #1b4332); font-size: 1.25rem; margin-bottom: 20px; border-bottom: 2px solid var(--verde-menta, #d8f3dc); padding-bottom: 8px; }
        .salon-card label { display: block !important; margin-bottom: 6px !important; font-weight: 600 !important; font-size: 13px !important; color: var(--verde-oscuro, #1b4332) !important; }
        
        .form-control {
            width: 100% !important; padding: 12px 14px !important; border: 2px solid #e2e8f0 !important; border-radius: 8px !important;
            box-sizing: border-box !important; font-size: 0.95rem !important; background-color: #fafbfc !important; color: #333 !important; transition: all 0.2s ease;
        }
        .form-control:focus { outline: none !important; border-color: var(--verde-claro, #52b788) !important; background-color: #fff !important; box-shadow: 0 0 0 3px rgba(82,183,136,0.15) !important; }
        textarea.form-control { resize: vertical; min-height: 90px; }
        
        .table-responsive { width: 100%; overflow-x: auto; border-radius: 8px; border: 1px solid #edf2f7; margin-top: 10px; }
        .jungle-table { width: 100%; border-collapse: collapse; text-align: left; min-width: 500px; }
        .jungle-table th { background-color: var(--verde-oscuro, #1b4332); color: #ffffff; padding: 12px 15px; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .jungle-table td { padding: 12px 15px; border-bottom: 1px solid #edf2f7; font-size: 0.95rem; vertical-align: middle; }
        .jungle-table tr:hover { background-color: rgba(216, 243, 220, 0.2); }
        
        .jungle-table a.btn-action, .salon-card button.btn-action { display: inline-flex !important; align-items: center !important; justify-content: center !important; padding: 10px 20px !important; font-size: 0.85rem !important; font-weight: 700 !important; text-transform: uppercase !important; border-radius: 6px !important; text-decoration: none !important; border: none !important; cursor: pointer !important; transition: all 0.2s ease !important; }
        .jungle-table a.btn-delete { background-color: #c92a2a !important; color: #ffffff !important; }
        .jungle-table a.btn-delete:hover { background-color: #a61e1e !important; box-shadow: 0 4px 8px rgba(201,42,42,0.2) !important; }
        
        .alert { padding: 14px; border-radius: 8px; margin-bottom: 20px; font-size: 0.95rem; font-weight: 500; }
        .alert-error { background: #ffe3e3; color: #c92a2a; border: 1px solid #ffa8a8; }
        .alert-success { background: #ebfbee; color: #2b8a3e; border: 1px solid #96f2d7; }

        @media (min-width: 992px) { .salon-grid { grid-template-columns: 340px 1fr; align-items: start; } }
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
            <h2>Gestión de Áreas del Local</h2>
            <p style="color: #666; margin-bottom: 20px;">Divide tu pizzería en zonas lógicas (Terraza, Salón VIP, Barra) para ordenar las comandas de los meseros.</p>
            
            <!-- Notificaciones de Alerta URL -->
            <?php if ($msg_error): ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
            <?php if ($msg_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div><?php endif; ?>

            <div class="salon-grid">
                
                <!-- COLUMNA 1: REGISTRO DE NUEVA AREA -->
                <div class="salon-card">
                    <h3>🗺️ Crear Nueva Zona</h3>
                    
                    <form action="<?php echo URL_BASE; ?>controllers/SalonController.php" method="POST" style="margin-top: 15px;">
                        <input type="hidden" name="accion" value="crear_area">

                        <div style="margin-bottom: 15px;">
                            <label>Nombre del Área / Salón</label>
                            <input type="text" name="nombre" class="form-control" required placeholder="Ej. Terraza Selva, Segundo Piso">
                        </div>

                        <div style="margin-bottom: 20px;">
                            <label>Descripción o Notas Operativas</label>
                            <textarea name="descripcion" class="form-control" placeholder="Ej. Zona abierta al aire libre con mesas familiares..."></textarea>
                        </div>

                        <div>
                            <button type="submit" class="btn-action" style="background: var(--verde-selva, #2d6a4f); color: #fff; width: 100%;">
                                🚀 Dar de Alta Área
                            </button>
                        </div>
                    </form>
                </div>

                <!-- COLUMNA 2: LISTADO DE AREAS REGISTRADAS -->
                <div class="salon-card">
                    <h3>📋 Salones y Ambientes Activos</h3>
                    
                    <div class="table-responsive">
                        <table class="jungle-table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">ID</th>
                                    <th>Nombre del Salón</th>
                                    <th>Descripción / Características</th>
                                    <th style="width: 120px; text-align: center;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($todas_areas)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #999; padding: 25px;">
                                            No hay áreas configuradas en el local. Empieza registrando una a la izquierda.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($todas_areas as $a): ?>
                                        <tr>
                                            <td><code>#<?php echo $a['id']; ?></code></td>
                                            <td><strong style="color: var(--verde-oscuro, #1b4332); font-size: 15px;"><?php echo htmlspecialchars($a['nombre']); ?></strong></td>
                                            <td style="color: #555; font-size: 13px;"><?php echo htmlspecialchars($a['descripcion'] ?: 'Sin notas comerciales.'); ?></td>
                                            <td style="text-align: center;">
                                                <a href="<?php echo URL_BASE; ?>controllers/SalonController.php?action=eliminar_area&del_id=<?php echo $a['id']; ?>" 
                                                   class="btn-action btn-delete" 
                                                   onclick="return confirm('¿Estás seguro de dar de baja este salón? Las mesas vinculadas a él dejarán de mostrarse en el mapa.');">
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
