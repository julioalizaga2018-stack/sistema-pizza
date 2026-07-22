<?php
// 1. DETECCIÓN AUTOMÁTICA DE URL (Funciona en Local y Hostinger)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

if ($host === 'localhost') {
    define('URL_BASE', $protocol . $host . "/pizzeria/");
} else {
    define('URL_BASE', $protocol . $host . "/");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pizzería Dash</title>
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
</head>
<body>

    <!-- 📱 NAVBAR MÓVIL: Solo visible en Tablets y Celulares -->
    <header class="mobile-header">
        <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Abrir menú">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div class="mobile-logo">🍕 Jungle Pizza</div>
    </header>

    <!-- Capa oscura de fondo al abrir el menú en celular -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="dashboard-layout">
        
        <!-- ==================== BARRA LATERAL MODULAR ==================== -->
        <!-- Cambiado el código manual por el include dinámico hacia tu componente central -->
        <?php include __DIR__ . '/sidebar.php'; ?>
        <!-- ==================== FIN BARRA LATERAL ==================== -->

        <!-- ==================== CONTENIDO PRINCIPAL ==================== -->
        <main class="main-content">
            <header class="content-header">
                <h1>Panel de Control de la Pizzería</h1>
                <p>Bienvenido al sistema de gestión. Aquí puedes controlar el estado del negocio.</p>
            </header>
            
            <section class="dashboard-cards">
                <!-- Contenido dinámico futuro -->
            </section>
        </main>
        <!-- ==================== FIN CONTENIDO PRINCIPAL ==================== -->

    </div>

    <script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>
</html>