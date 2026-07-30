<?php
// views/sidebar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$rolUsuario = isset($_SESSION['rol_id']) ? (int)$_SESSION['rol_id'] : 0;
$vista_actual = $_GET['v'] ?? 'dashboard';

if (!defined('URL_BASE')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    define('URL_BASE', ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/");
}
?>
<nav class="sidebar" id="sidebar-menu">
    <div class="sidebar-top">
        <div class="sidebar-brand">
            <h3>🍕 Jungle Pizza</h3>
            <?php if (isset($_SESSION['nombre'])): ?>
                <div style="background-color: rgba(255, 255, 255, 0.08); padding: 12px; border-radius: 8px; margin-top: 12px; font-size: 13px; text-align: left; border-left: 4px solid #52b788;">
                    <p style="margin: 0; color: #ffffff; font-weight: bold;">👤 <?php echo htmlspecialchars($_SESSION['nombre']); ?></p>
                    <p style="margin: 4px 0 0 0; color: #d8f3dc; font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">
                        🔑 <?php 
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
        </div>

       <ul class="nav-list">
    <li>
        <a href="<?php echo URL_BASE; ?>index.php?v=dashboard" class="nav-link <?php echo ($vista_actual == 'dashboard') ? 'active' : ''; ?>">📊 Dashboard</a>
    </li>
    
    <!-- 🌟 SECCIÓN CENTRALIZADA KDS: Apunta a tu nuevo kds_monitor dinámico -->
    <li>
        <a href="<?php echo URL_BASE; ?>index.php?v=kds_monitor&estacion=cocina" class="nav-link <?php echo ($vista_actual == 'kds_monitor' && ($_GET['estacion'] ?? '') === 'cocina') ? 'active' : ''; ?>">🍳 Cocina</a>
    </li>
    <li>
        <a href="<?php echo URL_BASE; ?>index.php?v=kds_monitor&estacion=horno" class="nav-link <?php echo ($vista_actual == 'kds_monitor' && ($_GET['estacion'] ?? '') === 'horno') ? 'active' : ''; ?>">🔥 Horno</a>
    </li>
    <li>
        <a href="<?php echo URL_BASE; ?>index.php?v=kds_monitor&estacion=bar" class="nav-link <?php echo ($vista_actual == 'kds_monitor' && ($_GET['estacion'] ?? '') === 'bar') ? 'active' : ''; ?>">🍹 Bar</a>
    </li>
    
    <li>
        <a href="<?php echo URL_BASE; ?>index.php?v=mesas" class="nav-link <?php echo ($vista_actual == 'mesas') ? 'active' : ''; ?>">🪑 Mapa de Mesas</a>
    </li>

    <!-- 🛠️ ENLACES DE MANTENIMIENTO: Sincronizados con tu variable numéricas de phpMyAdmin -->
    <?php if ((int)$rolUsuario === 1 || (int)$rolUsuario === 2 || (int)$rolUsuario === 3): ?>
        <li style="margin-top: 15px; padding-left: 15px; font-size: 11px; text-transform: uppercase; color: #888; font-weight: bold; letter-spacing: 1px;">🛠️ Catálogos</li>
        <li>
            <a href="<?php echo URL_BASE; ?>index.php?v=mantenimiento_productos" class="nav-link <?php echo ($vista_actual == 'mantenimiento_productos' || $vista_actual == 'mantenimiento_productos_nuevo') ? 'active' : ''; ?>">📦 Menú y Productos</a>
        </li>
         <!-- 🌟 NUEVO ENLACE AL CRUD DE MAESTRO DE RECETAS -->
        <li>
            <a href="<?php echo URL_BASE; ?>index.php?v=recetas_lista" class="nav-link <?php echo ($vista_actual == 'recetas_lista') ? 'active' : ''; ?>">🍳 Fórmulas Recetas</a>
        </li>
        <li>
            <a href="<?php echo URL_BASE; ?>index.php?v=mantenimiento_mesas" class="nav-link <?php echo ($vista_actual == 'mantenimiento_mesas') ? 'active' : ''; ?>">🪑 Gestión Mesas</a>
        </li>
        <li>
            <a href="<?php echo URL_BASE; ?>index.php?v=mantenimiento_areas" class="nav-link <?php echo ($vista_actual == 'mantenimiento_areas') ? 'active' : ''; ?>">🗺️ Gestión Áreas</a>
        </li>
        <li>
            <a href="<?php echo URL_BASE; ?>index.php?v=mantenimiento_categorias" class="nav-link <?php echo ($vista_actual == 'mantenimiento_categorias') ? 'active' : ''; ?>">🗂️ Categorías</a>
        </li>
         <!-- 🌟 NUEVO ENLACE INTEGRADO NATIVAMENTE -->
        <li>
            <a href="<?php echo URL_BASE; ?>index.php?v=proveedores" class="nav-link <?php echo ($vista_actual == 'proveedores') ? 'active' : ''; ?>">🚚 Proveedores</a>
        </li>
        <!-- 🌟 NUEVO ENLACE DE AUDITORÍA INTEGRADO NATIVAMENTE -->
        <li>
            <a href="<?php echo URL_BASE; ?>index.php?v=inventario_ajustes" class="nav-link <?php echo ($vista_actual == 'inventario_ajustes') ? 'active' : ''; ?>">📊 Historial Kardex</a>
        </li>
        <!-- 🌟 NUEVO ENLACE DE CAJA INTEGRADO NATIVAMENTE -->
        <li>
            <a href="<?php echo URL_BASE; ?>index.php?v=gestion_caja" class="nav-link <?php echo ($vista_actual == 'gestion_caja') ? 'active' : ''; ?>">💵 Apertura y Arqueo</a>
        </li>
         <!-- 🌟 NUEVO ENLACE DE FACTURACIÓN INTEGRADO NATIVAMENTE -->
        <li>
            <a href="<?php echo URL_BASE; ?>index.php?v=cobranza_lista" class="nav-link <?php echo ($vista_actual == 'cobranza_lista' || $vista_actual == 'cobranza_facturar') ? 'active' : ''; ?>">🧾 Módulo de Cobranza</a>
        </li>
        <!-- 🌟 NUEVO ENLACE DE AUDITORÍA HISTÓRICA INTEGRADO NATIVAMENTE -->
        <li>
            <a href="<?php echo URL_BASE; ?>index.php?v=cobranza_historial" class="nav-link <?php echo ($vista_actual == 'cobranza_historial') ? 'active' : ''; ?>">📊 Historial Facturas</a>
        </li>
        <!-- 🌟 NUEVO ENLACE DE REPORTE POR PRODUCTOS INTEGRADO NATIVAMENTE -->
        <li>
            <a href="<?php echo URL_BASE; ?>index.php?v=ventas_productos" class="nav-link <?php echo ($vista_actual == 'ventas_productos') ? 'active' : ''; ?>">🍕 Ventas por Producto</a>
        </li>
         <!-- 🌟 NUEVO ENLACE DE COMPRAS DE INSUMOS INTEGRADO NATIVAMENTE -->
        <li>
            <a href="<?php echo URL_BASE; ?>index.php?v=compras_lista" class="nav-link <?php echo ($vista_actual == 'compras_lista' || $vista_actual == 'compras_registrar') ? 'active' : ''; ?>">📦 Registro de Compras</a>
        </li>
        <!-- 🌟 NUEVO ENLACE DE RENDIMIENTO MENSUAL INTEGRADO NATIVAMENTE -->
        <li>
            <a href="<?php echo URL_BASE; ?>index.php?v=reportes_mensuales" class="nav-link <?php echo ($vista_actual == 'reportes_mensuales') ? 'active' : ''; ?>">📊 Cierre Balance Mensual</a>
        </li>
    <?php endif; ?>
</ul>

            <!-- ⚙️ TU SECCIÓN DE CONFIGURACIÓN ORIGINAL INTACTA (Solo Superadmin y Admin) -->
            <?php if ($rolUsuario === 1 || $rolUsuario === 2): ?>
                <li class="dropdown-container" style="margin-top: 15px;">
                    <!-- Restaurada tu llamada original exacta que sí te funcionaba -->
                    <button class="menu-btn" onclick="toggleMenu()" aria-expanded="false" type="button">
                        <span>⚙️ Configuración</span>
                        <i class="arrow" id="menu-arrow"></i>
                    </button>
                    
                    <ul class="submenu" id="config-submenu">
                        <li><a href="#perfil" class="submenu-link">👤 Mi Perfil</a></li>
                        <?php if ($rolUsuario === 1 || $rolUsuario === 2): ?>
                            <li><a href="<?php echo URL_BASE; ?>index.php?v=config_empresa" class="submenu-link <?php echo ($vista_actual == 'config_empresa') ? 'active' : ''; ?>">🏢 Empresa</a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo URL_BASE; ?>index.php?v=gestion_usuarios" class="submenu-link <?php echo ($vista_actual == 'gestion_usuarios') ? 'active' : ''; ?>">👥 Usuarios</a></li>
                    </ul>
                </li>
            <?php endif; ?>

        </ul>
    </div>

    <div class="sidebar-bottom">
        <a href="<?php echo URL_BASE; ?>index.php?v=logout" class="nav-link logout-btn">❌ Cerrar Sesión</a>
    </div>
</nav>
