<?php
// views/catalogo.php

// Validación de Seguridad: Si acceden directo sin pasar por index.php o sin sesión, rebota al login
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol_id'])) {
    header('Location: index.php?v=login');
    exit;
}

// Mapeo rápido de los roles basados en tus seeders SQL para fácil lectura de código
$rolUsuario = (int)$_SESSION['rol_id'];
$esSuperAdmin = ($rolUsuario === 1);
$esAdmin      = ($rolUsuario === 2);
$esSupervisor = ($rolUsuario === 3);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Jungle Pizza - Menú</title>
    
    <!-- Cargamos estilos base del sistema -->
    <link rel="stylesheet" href="public/css/base.css">
    <!-- Cargamos estilos aislados del panel de navegación -->
    <link rel="stylesheet" href="public/css/catalogo.css">
</head>
<body>

<div class="dashboard-container">

    <!-- 📱 BARRA SUPERIOR: Visible únicamente en Celulares y Tablets -->
    <header class="dashboard-header">
        <div class="brand-text">Jungle Pizza 🍕</div>
        <div class="user-badge"><?= htmlspecialchars($_SESSION['nombre']); ?></div>
    </header>

    <!-- 🗺️ MENÚ LATERAL: Responsivo y Dinámico según Roles -->
    <aside class="sidebar">
        <div>
            <!-- Logotipo del Menú -->
            <div class="sidebar-brand">
                <svg class="sidebar-pizza-icon" viewBox="0 0 24 24">
                    <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12C20,13.4 19.6,14.71 18.91,15.82L15.36,12.27C15.77,11.58 15.6,10.68 14.93,10C14.24,9.32 13.21,9.25 12.46,9.81L9.12,6.47C10.03,4.9 11.2,4.17 12,4M7.71,7.88L11.06,11.23C10.5,12 10.55,13 11.24,13.7C11.93,14.39 12.92,14.47 13.7,13.91L16.41,16.62C15.17,17.47 13.65,18 12,18A6,6 0 0,1 6,12C6,10.45 6.47,9 7.71,7.88Z" />
                </svg>
                <h3 style="font-weight: 800;">Jungle Panel</h3>
            </div>

            <!-- Lista de Enlaces de Navegación -->
            <ul class="sidebar-menu">
                
                <!-- PESTAÑA: MENÚ (Accesible para todos los roles de atención y administración) -->
                <?php if ($esSuperAdmin || $esAdmin || $esSupervisor || $rolUsuario === 4): // 4 = mesero ?>
                    <li class="menu-item">
                        <a href="index.php?v=catalogo" class="menu-link active">
                            <span>🍕</span> Ver Menú
                        </a>
                    </li>
                <?php endif; ?>

                <!-- PESTAÑA: PRODUCTOS (Gestión de stock, exclusivo administradores y supervisores) -->
                <?php if ($esSuperAdmin || $esAdmin || $esSupervisor): ?>
                    <li class="menu-item">
                        <a href="index.php?v=productos" class="menu-link">
                            <span>📦</span> Productos
                        </a>
                    </li>
                <?php endif; ?>

                <!-- PESTAÑA PRINCIPAL: CONFIGURACIONES (Bloque condicional de jerarquía alta) -->
                <?php if ($esSuperAdmin || $esAdmin): ?>
                    <li class="menu-item" style="width: 100%;">
                        <div class="submenu-title">⚙️ Configuraciones</div>
                        <ul class="submenu-list">
                            
                            <!-- SUBMENÚ: EMPRESA (Solo dueños o superadministradores modifican datos globales) -->
                            <?php if ($esSuperAdmin): ?>
                                <li>
                                    <a href="index.php?v=config_empresa" class="menu-link" style="padding: 8px 10px; font-size: 0.9rem;">
                                        🏢 Datos Empresa
                                    </a>
                                </li>
                            <?php endif; ?>

                            <!-- SUBMENÚ: USUARIOS (Superadmin y administradores crean personal) -->
                            <li>
                                <a href="index.php?v=gestion_usuarios" class="menu-link" style="padding: 8px 10px; font-size: 0.9rem;">
                                    👥 Personal / Usuarios
                                Roth</a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

            </ul>
        </div>

        <!-- Botón de Cerrar Sesión (Se ubica abajo en pantallas grandes) -->
        <div style="padding: 10px; margin-top: auto;">
            <p style="font-size: 0.8rem; color: var(--verde-claro); margin-bottom: 8px;" class="dashboard-header-desktop">
                👤 Empleado: <strong><?= htmlspecialchars($_SESSION['nombre']); ?></strong>
            </p>
            <a href="index.php?v=logout" class="btn-logout" style="display: block; text-align: center;">Cerrar Sesión</a>
        </div>
    </aside>

    <!-- 🍕 CONTENEDOR CENTRAL: Aquí se pintarán las pizzas o contenido de cada módulo -->
    <main class="main-content">
        <h2>Bienvenido a Jungle Pizza, <?= htmlspecialchars($_SESSION['nombre']); ?>!</h2>
        <p style="color: #666; margin-top: 5px;">Selecciona una opción del menú lateral para comenzar a operar.</p>
        
        <!-- Próximamente aquí iteraremos las pizzas de la base de datos con PHP -->
    </main>

</div>

</body>
</html>
