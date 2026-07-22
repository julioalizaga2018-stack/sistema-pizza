<?php
// views/config_empresa.php

// 1. Control estricto de acceso de seguridad (Solo el Dueño/Superadmin con ID 1 puede entrar)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario_id']) || (int)$_SESSION['rol_id'] !== 1) {
    header('Location: index.php?v=dashboard');
    exit;
}

// 2. Instanciar el controlador de empresa para rellenar los inputs en pantalla
require_once __DIR__ . '/../controllers/EmpresaController.php';
$controller = new EmpresaController();
$empresa = $controller->cargarDatos();

// Detección de la URL base idéntica a tu PC y Hostinger
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
if (!defined('URL_BASE')) {
    if ($host === 'localhost') {
        define('URL_BASE', $protocol . $host . "/pizzeria/");
    } else {
        define('URL_BASE', $protocol . $host . "/");
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
    <title>Configuración de la Empresa - Jungle Pizza</title>
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    <style>
        :root {
            --verde-oscuro: #1b4332;
            --verde-selva: #2d6a4f;
            --verde-claro: #52b788;
            --verde-menta: #d8f3dc;
            --naranja-pizza: #e67e22;
        }
        .form-card { background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(27,67,50,0.05); border-top: 4px solid var(--verde-claro); max-width: 800px; margin-top: 20px; }
        .form-card h3 { color: var(--verde-oscuro); margin-bottom: 15px; font-size: 1.2rem; }
        .form-group-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .form-control { width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; font-size: 1rem; background-color: #fafbfc; transition: border-color 0.2s; font-family: inherit; }
        .form-control:focus { outline: none; border-color: var(--verde-claro); background-color: #fff; }
        textarea.form-control { resize: vertical; min-height: 100px; }
        .alert { padding: 14px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; font-weight: 500; }
        .alert-error { background: #ffe3e3; color: #c92a2a; border: 1px solid #ffa8a8; }
        .alert-success { background: #ebfbee; color: #2b8a3e; border: 1px solid #96f2d7; }
        .btn-action { padding: 12px 24px; border-radius: 6px; text-decoration: none; font-size: 14px; color: white; display: inline-block; border: none; cursor: pointer; font-weight: bold; transition: background 0.2s; background: var(--verde-selva); }
        .btn-action:hover { background: var(--verde-oscuro); }
        .logo-preview-box { display: flex; align-items: center; gap: 20px; background: #fafbfc; padding: 15px; border-radius: 8px; border: 2px dashed #e2e8f0; margin-bottom: 15px; }
        .logo-img { max-width: 100px; max-height: 100px; object-fit: contain; border-radius: 6px; background: white; border: 1px solid #e2e8f0; }
        .logo-info { font-size: 13px; color: #666; }
    </style>
</head>
<body>
    <!-- Cabecera Mobile original intacta -->
    <header class="mobile-header">
        <button class="hamburger-btn" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <div class="mobile-logo">🍕 Jungle Dash</div>
    </header>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="dashboard-layout">
        <!-- Inclusión nativa de tu barra lateral -->
        <?php include 'sidebar.php'; ?>

        <main class="main-content">
            <h2>Configuración Comercial e Identidad</h2>
            <p style="color: #666;">Modifica los datos globales que aparecerán en la cabecera de tus recibos y facturas.</p>
            
            <!-- Notificaciones del Sistema -->
            <?php if ($msg_error): ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
            <?php if ($msg_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div><?php endif; ?>

            <div class="form-card">
                <h3>🏢 Información de la Marca</h3>
                
                <!-- IMPORTANTE: Usamos enctype="multipart/form-data" para permitir subida de imágenes -->
                <form action="<?php echo URL_BASE; ?>controllers/EmpresaController.php" method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                    <input type="hidden" name="accion" value="guardar_empresa">

                    <!-- Fila 1: Nombre y Teléfono de la pizzería -->
                    <div class="form-group-grid">
                        <div>
                            <label style="display:block; margin-bottom:5px; font-weight:600; font-size:14px; color:var(--verde-oscuro);">Nombre del Establecimiento</label>
                            <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($empresa['nombre'] ?? ''); ?>" required placeholder="Nombre de la Pizzería">
                        </div>
                        <div>
                            <label style="display:block; margin-bottom:5px; font-weight:600; font-size:14px; color:var(--verde-oscuro);">Teléfono Comercial</label>
                            <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($empresa['telefono'] ?? ''); ?>" required placeholder="Ej. +505 7777-1234">
                        </div>
                    </div>
                    
                    <!-- Fila 2: Dirección física completa -->
                    <div style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom:5px; font-weight:600; font-size:14px; color:var(--verde-oscuro);">Dirección Sucursal Principal</label>
                        <textarea name="direccion" class="form-control" required placeholder="Ubicación exacta de la pizzería..."><?php echo htmlspecialchars($empresa['direccion'] ?? ''); ?></textarea>
                    </div>

                    <!-- Fila 3: Carga del Logotipo Corporativo -->
                    <div style="margin-bottom: 20px;">
                        <label style="display:block; margin-bottom:5px; font-weight:600; font-size:14px; color:var(--verde-oscuro);">Logotipo de la Empresa</label>
                        
                        <!-- Caja visualizadora del Logo actual -->
                        <div class="logo-preview-box">
                            <?php if (!empty($empresa['logo']) && file_exists(__DIR__ . '/../public/uploads/' . $empresa['logo'])): ?>
                                <img src="<?php echo URL_BASE; ?>public/uploads/<?php echo $empresa['logo']; ?>" alt="Logo Establecimiento" class="logo-img">
                                <div class="logo-info">
                                    <p style="margin:0; font-weight:bold; color:#333;">Logo Activo:</p>
                                    <p style="margin:2px 0 0 0; font-size:12px; font-family:monospace;"><?php echo htmlspecialchars($empresa['logo']); ?></p>
                                </div>
                            <?php else: ?>
                                <div style="width:70px; height:70px; background:#e2e8f0; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:24px;">🏢</div>
                                <div class="logo-info">
                                    <p style="margin:0; font-weight:bold; color:#777;">Sin logotipo registrado</p>
                                    <p style="margin:2px 0 0 0; font-size:11px;">Sube un archivo cuadrado JPG o PNG (Max 2MB).</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Input nativo ocultable/estilizado por navegador para subir la imagen -->
                        <input type="file" name="logo" class="form-control" accept="image/png, image/jpeg, image/jpg">
                    </div>

                    <!-- Botón de Guardado -->
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn-action">
                            💾 Guardar Cambios Comerciales
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Tu script JS intacto -->
    <script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>
</html>
