<?php
// views/sidebar.php

// 1. Iniciamos la sesión de forma segura si aún no se ha inicializado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Extraemos el Rol ID del usuario en sesión (Si no existe, se asigna 0 por seguridad)
// 1 = Superadmin, 2 = Admin, 3 = Supervisor, etc.
$rolUsuario = isset($_SESSION['rol_id']) ? (int)$_SESSION['rol_id'] : 0;

// Detectamos la vista actual directamente desde el parámetro de la URL
$vista_actual = $_GET['v'] ?? 'dashboard';

// Detección automática de la URL base (Compatible con tu PC y Hostinger)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
if (!defined('URL_BASE')) {
    if ($host === 'localhost') {
        define('URL_BASE', $protocol . $host . "/pizzeria/");
    } else {
        define('URL_BASE', $protocol . $host . "/");
    }
}
?>
<nav class="sidebar" id="sidebar-menu">
        <!-- Parte Superior: Logotipo y Enlaces Directos del Menú -->
    <div class="sidebar-top">
        <div class="sidebar-brand">
            <!-- Nombre de marca actualizado -->
            <h3>🍕 Jungle Pizza</h3>
            
            <!-- 👤 TARJETA DE PERFIL: Identifica automáticamente al usuario y su rol en sesión -->
            <?php if (isset($_SESSION['nombre'])): ?>
                <div style="background-color: rgba(255, 255, 255, 0.08); padding: 12px; border-radius: 8px; margin-top: 12px; font-size: 13px; text-align: left; border-left: 4px solid #52b788; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <p style="margin: 0; color: #ffffff; font-weight: bold;">
                        👤 <?php echo htmlspecialchars($_SESSION['nombre']); ?>
                    </p>
                    <p style="margin: 4px 0 0 0; color: #d8f3dc; font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">
                        🔑 <?php 
                            // Traducimos el ID de rol a un texto comprensible en las pantallas del local
                            switch ($rolUsuario) {
                                case 1: echo "Superadmin"; break;
                                case 2: echo "Administrador"; break;
                                case 3: echo "Supervisor"; break;
                                case 4: echo "Mesero"; break;
                                case 5: echo "Cocina"; break;
                                case 6: echo "Horno"; break;
                                case 7: echo "Bar"; break;
                                case 8: echo "Cajero"; break;
                                default: echo "Personal"; break;
                            }
                        ?>
                    </p>
                </div>
            <?php endif; ?>
            <!-- Fin de la Tarjeta de Identificación -->
        </div>

        <ul class="nav-list">
            <li>
                <a href="<?php echo URL_BASE; ?>index.php?v=dashboard" class="nav-link <?php echo ($vista_actual == 'dashboard') ? 'active' : ''; ?>">📊 Dashboard</a>
            </li>
            
            <li>
                <a href="<?php echo URL_BASE; ?>index.php?v=cocina" class="nav-link <?php echo ($vista_actual == 'cocina') ? 'active' : ''; ?>">🍳 Cocina</a>
            </li>
            <li>
                <a href="<?php echo URL_BASE; ?>index.php?v=horno" class="nav-link <?php echo ($vista_actual == 'horno') ? 'active' : ''; ?>">🔥 Horno</a>
            </li>
            <li>
                <a href="<?php echo URL_BASE; ?>index.php?v=bar" class="nav-link <?php echo ($vista_actual == 'bar') ? 'active' : ''; ?>">🍹 Bar</a>
            </li>
            <li>
                <a href="<?php echo URL_BASE; ?>index.php?v=mesas" class="nav-link <?php echo ($vista_actual == 'mesas') ? 'active' : ''; ?>">🪑 Mapa de Mesas</a>
            </li>

            <!-- ⚙️ SECCIÓN PROTEGIDA: CONFIGURACIÓN (Visible solo para Superadmin [1] y Admin) -->
            <?php if ($rolUsuario === 1 || $rolUsuario === 2): ?>
                <li class="dropdown-container">
                    <button class="menu-btn" onclick="toggleMenu()" aria-expanded="false" type="button">
                        <span>⚙️ Configuración</span>
                        <i class="arrow" id="menu-arrow"></i>
                    </button>
                    
                    <ul class="submenu" id="config-submenu">
                        <li>
                            <a href="#perfil" class="submenu-link">👤 Mi Perfil</a>
                        </li>
                        
                        <!-- Filtro para el Submenú Empresa: Solo visible para Superadmin -->
                        <?php if ($rolUsuario === 1): ?>
                            <li>
                                <a href="<?php echo URL_BASE; ?>index.php?v=config_empresa" class="submenu-link <?php echo ($vista_actual == 'config_empresa') ? 'active' : ''; ?>">🏢 Empresa</a>
                            </li>
                        <?php endif; ?>

                        <li>
                            <!-- Corregido a v=gestion_usuarios para que se alinee con tu controlador y el enrutador central -->
                            <a href="<?php echo URL_BASE; ?>index.php?v=gestion_usuarios" class="submenu-link <?php echo ($vista_actual == 'gestion_usuarios') ? 'active' : ''; ?>">
                                👥 Usuarios
                            </a>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>
            <!-- Fin de la sección protegida -->

        </ul>
    </div>


    <!-- Parte Inferior: Botón de Salir fijado al fondo -->
    <div class="sidebar-bottom">
        <a href="<?php echo URL_BASE; ?>index.php?v=logout" class="nav-link logout-btn">❌ Cerrar Sesión</a>
    </div>
</nav>
