<?php
// Incluimos el controlador para consultar el estado del sistema
require_once __DIR__ . '/../controllers/UsuarioController.php';

$instanciaController = new UsuarioController();
$sistemaNuevo = $instanciaController->esSistemaNuevo();

// Detección automática de entorno (Local vs Hostinger)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
define('URL_BASE', ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/");

// Captura de mensajes de éxito o error enviados por el controlador
$msg_error = $_GET['error'] ?? null;
$msg_success = $_GET['success'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $sistemaNuevo ? 'Registro Inicial' : 'Login'; ?> - Jungle Pizza</title>
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/login.css">
    <style>
        /* Estilos rápidos para alertas sutiles dentro del Login */
        .alert { padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 15px; text-align: left; }
        .alert-error { background-color: rgba(230, 57, 70, 0.2); color: #e63946; border: 1px solid #e63946; }
        .alert-success { background-color: rgba(46, 204, 113, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
    </style>
</head>
<body class="login-body">

    <div class="login-container">
        <div class="login-box">
            <div class="login-logo">🍕</div>
            <h2>Jungle Pizza</h2>
            
            <?php if ($msg_error): ?>
                <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($msg_error); ?></div>
            <?php endif; ?>
            
            <?php if ($msg_success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div>
            <?php endif; ?>

            <?php if ($sistemaNuevo): ?>
                <!-- FORMULARIO A: SISTEMA NUEVO (Registro de primer Superadmin) -->
                <p class="subtitle">Configuración Inicial: Crea el Administrador</p>
                <form action="<?php echo URL_BASE; ?>controllers/UsuarioController.php" method="POST">
                    <input type="hidden" name="accion" value="registrar_primer_admin">
                    
                    <div class="input-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" id="nombre" name="nombre" placeholder="Tu nombre" required>
                    </div>
                    <div class="input-group">
                        <label for="apellido">Apellido</label>
                        <input type="text" id="apellido" name="apellido" placeholder="Tu apellido" required>
                    </div>
                    <div class="input-group">
                        <label for="usuario">Nombre de Usuario</label>
                        <input type="text" id="usuario" name="usuario" placeholder="Ej: admin_jungle" required>
                    </div>
                    <div class="input-group">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" name="password" placeholder="Crea una clave segura" required>
                    </div>
                    
                    <button type="submit" class="btn-login">Configurar Sistema</button>
                </form>

            <?php else: ?>
                <!-- FORMULARIO B: SISTEMA REGULAR (Inicio de sesión estándar) -->
                <p class="subtitle">Panel de Control Operativo</p>
                <form action="<?php echo URL_BASE; ?>controllers/UsuarioController.php" method="POST">
                    <input type="hidden" name="accion" value="login_regular">

                    <div class="input-group">
                        <label for="usuario">Usuario</label>
                        <input type="text" id="usuario" name="usuario" placeholder="Ingresa tu usuario" required>
                    </div>
                    
                    <div class="input-group">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>
                    
                    <button type="submit" class="btn-login">Iniciar Sesión</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>