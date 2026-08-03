<?php
// views/mesas_salon.php (Parte 1 de 2)
// 1. Instanciamos los controladores necesarios para pintar el salón operativo
require_once __DIR__ . '/../controllers/MesaController.php';
require_once __DIR__ . '/../models/SalonModelo.php';
$mesaController = new MesaController();
$modeloSalon = new SalonModelo();

$mesas = $mesaController->listar(); // Obtiene todas las mesas activas con su área vinculada
$areas = $modeloSalon->listarAreasTodas(); // Obtiene las zonas activas para agrupar las mesas

// 🌟 NUEVO MAPEO: Conexión rápida para inyectar los nombres de los meseros en las mesas ocupadas
$db_mesas_salon = (new Conexion())->conectar();
$stmtMeserosSalón = $db_mesas_salon->prepare("SELECT p.mesa_id, u.nombre as nombre_mesero 
    FROM pedidos p 
    INNER JOIN usuarios u ON p.usuario_id = u.id 
    WHERE p.estado IN ('pendiente', 'cocina', 'horno', 'bar') AND p.tipo_pedido = 'local'");
$stmtMeserosSalón->execute();
$relacion_meseros = $stmtMeserosSalón->fetchAll(PDO::FETCH_KEY_PAIR); // Crea un arreglo rápido [mesa_id => 'Nombre Mesero']

// 2. Sincronización automática de URL_BASE (PC Local y Hostinger)
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
    <title>Mapa de Mesas Interactivo - Jungle Pizza</title>
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/base.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>public/css/estilos.css">
    <style>
        .salon-container {
            width: 100%;
            margin-top: 15px;
        }

        .area-section {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(27, 67, 50, 0.05);
            padding: 20px;
            margin-bottom: 25px;
            border-left: 5px solid var(--verde-claro, #52b788);
        }

        .area-section h3 {
            color: var(--verde-oscuro, #1b4332);
            font-size: 1.2rem;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #edf2f7;
            padding-bottom: 6px;
        }

        .mesas-grid-layout {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)) !important;
            gap: 15px;
        }

        .mesa-btn-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            border-radius: 10px;
            padding: 18px 12px;
            text-align: center;
            text-decoration: none;
            position: relative;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            min-height: 155px;
            box-sizing: border-box;
        }

        .mesa-btn-card:active {
            transform: scale(0.96);
        }

        .mesa-disponible {
            background-color: #d4edda !important;
            border: 2px solid #c3e6cb !important;
            color: #155724 !important;
        }

        .mesa-disponible:hover {
            background-color: #c3e6cb !important;
        }

        .mesa-ocupada {
            background-color: #f8d7da !important;
            border: 2px solid #f5c6cb !important;
            color: #721c24 !important;
        }

        .mesa-ocupada:hover {
            background-color: #f5c6cb !important;
        }

        .mesa-reservada {
            background-color: #fff3cd !important;
            border: 2px solid #ffeeba !important;
            color: #856404 !important;
        }

        .mesa-reservada:hover {
            background-color: #ffeeba !important;
        }

        .mesa-mantenimiento {
            background-color: #e2e8f0 !important;
            border: 2px solid #ced4da !important;
            color: #383d41 !important;
            cursor: not-allowed;
        }

        .mesa-icon {
            font-size: 1.8rem;
            margin-bottom: 3px;
        }

        .mesa-name {
            font-size: 1.05rem;
            font-weight: 800;
            display: block;
            margin-bottom: 2px;
        }

        .mesa-cap {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            opacity: 0.8;
        }

        .btn-liberar-rapido {
            margin-top: 8px;
            background-color: #333333 !important;
            color: #ffffff !important;
            font-size: 10px !important;
            font-weight: bold !important;
            text-transform: uppercase !important;
            padding: 5px 8px !important;
            border-radius: 4px !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center;
            gap: 4px;
            border: none !important;
            z-index: 10;
            transition: background 0.2s;
            width: 90%;
            justify-content: center;
        }

        .btn-liberar-rapido:hover {
            background-color: #111111 !important;
            color: #ffffff !important;
        }

        .alert {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .alert-error {
            background: #ffe3e3;
            color: #c92a2a;
            border: 1px solid #ffa8a8;
        }

        .alert-success {
            background: #ebfbee;
            color: #2b8a3e;
            border: 1px solid #96f2d7;
        }
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
            <h2>Mapa del Salón e Interfaz de Comandas</h2>
            <p style="color: #666; margin-bottom: 20px;">Selecciona una mesa verde para abrir un pedido o gestiona las cuentas en preparación.</p>

            <?php if ($msg_error): ?><div class="alert alert-error">⚠ <?php echo htmlspecialchars($msg_error); ?></div><?php endif; ?>
            <?php if ($msg_success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg_success); ?></div><?php endif; ?>

            <div style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; width: 100%;">
                <a href="javascript:void(0);" onclick="abrirPedidoRapido('delivery')" style="flex: 1; min-width: 200px; background-color: #e67e22; color: #ffffff; padding: 18px; border-radius: 10px; text-decoration: none; text-align: center; font-weight: 800; font-size: 1.1rem; box-shadow: 0 4px 10px rgba(230, 126, 34, 0.2); display: flex; align-items: center; justify-content: center; gap: 10px; transition: transform 0.1s ease;">
                    📞📞 <span>Pedido Delivery (A Domicilio)</span>
                </a>
                <a href="javascript:void(0);" onclick="abrirPedidoRapido('retiro')" style="flex: 1; min-width: 200px; background-color: #2d6a4f; color: #ffffff; padding: 18px; border-radius: 10px; text-decoration: none; text-align: center; font-weight: 800; font-size: 1.1rem; box-shadow: 0 4px 10px rgba(45, 106, 79, 0.2); display: flex; align-items: center; justify-content: center; gap: 10px; transition: transform 0.1s ease;">
                    🛍🛍 <span>Entrega en Local (Para Llevar)</span>
                </a>
            </div>
          <div class="salon-container">
<?php if (empty($areas)): ?>
    <div class="area-section" style="text-align: center; color: #999; padding: 30px;">
        No hay áreas configuradas en el local. Registra una zona primero en el menú de Mantenimiento.
    </div>
<?php else: ?>
    <!-- 🔄 ITERACIÓN DE SALONES / ÁREAS DEL RESTAURANTE -->
    <?php foreach ($areas as $area): ?>
        <div class="area-section">
            <h3>🗺🗺 <?php echo htmlspecialchars($area['nombre']); ?></h3>
            <div class="mesas-grid-layout">
                <?php
                // Filtramos las mesas que pertenecen estrictamente a este salón
                $mesas_este_area = array_filter($mesas, function ($m) use ($area) {
                    return (int)$m['area_id'] === (int)$area['id'];
                });
                ?>
                
                <?php foreach ($mesas_este_area as $mesa): 
                    $idMesaReg = (int)$mesa['id'];
                    $nombreMeseroAtendiendo = $relacion_meseros[$idMesaReg] ?? null;
                ?>
                    <!-- Contenedor relativo de la celda de la mesa (Ajustamos alto mínimo seguro) -->
                    <div style="position: relative; display: flex; flex-direction: column; background: #ffffff; border-radius: 10px; min-height: 165px; box-sizing: border-box;">
                        
                        <!-- Tarjeta Principal de la Mesa (Formatos elásticos con espacio para mesero flotante) -->
                        <a href="javascript:void(0);"
                           class="mesa-btn-card mesa-<?php echo $mesa['estado']; ?>" 
                           style="text-decoration: none; width: 100%; min-height: 120px !important; padding: 14px 12px 45px 12px !important;"
                           onclick="gestionarMesa(<?php echo $mesa['id']; ?>, '<?php echo $mesa['estado']; ?>', '<?php echo htmlspecialchars($mesa['numero_mesa']); ?>')">
                            
                            <div class="mesa-icon">
                                <?php
                                switch ($mesa['estado']) {
                                    case 'ocupada': echo '🍕🍕'; break;
                                    case 'reservada': echo '📅📅'; break;
                                    case 'mantenimiento': echo '🛠🛠'; break;
                                    default: echo '🪑🪑'; break;
                                }
                                ?>
                            </div>
                            
                            <span class="mesa-name"><?php echo htmlspecialchars($mesa['numero_mesa']); ?></span>
                            
                            <!-- Si la mesa está libre, muestra el indicador MAX de comensales tradicional -->
                            <?php if ($mesa['estado'] !== 'ocupada'): ?>
                                <span class="mesa-cap">Max: <?php echo (int)$mesa['capacidad']; ?> p.</span>
                            <?php endif; ?>
                        </a>

                        <!-- 🌟 ESCUDO VISUAL: El nombre del mesero se monta con fondo sólido arriba del botón negro -->
                        <?php if ($mesa['estado'] === 'ocupada' && !empty($nombreMeseroAtendiendo)): ?>
                            <div style="position: absolute; bottom: 42px; left: 5%; width: 90%; text-align: center; font-size: 11px; font-weight: 800; background: #ffffff; color: #721c24; padding: 3px 4px; border-radius: 4px; border: 1px solid #f5c6cb; box-sizing: border-box; z-index: 5; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; box-shadow: 0 2px 4px rgba(0,0,0,0.04);">
                                👤 <?php echo htmlspecialchars($nombreMeseroAtendiendo); ?>
                            </div>
                        <?php endif; ?>

                        <!-- BOTÓN DE LIBERACIÓN RAPIDA (Fijo abajo del todo en la base de la tarjeta) -->
                        <?php if ($mesa['estado'] === 'ocupada' || $mesa['estado'] === 'reservada'): ?>
                            <a href="<?php echo URL_BASE; ?>controllers/MesaController.php?action=liberar_mesa&id=<?php echo $mesa['id']; ?>"
                               class="btn-liberar-rapido"
                               style="position: absolute; bottom: 8px; left: 5%; width: 90%; margin: 0; box-sizing: border-box; z-index: 10;"
                               onclick="event.stopPropagation(); return confirm('¿Estás seguro de liberar la <?php echo htmlspecialchars($mesa['numero_mesa']); ?> forzadamente?');">
                                🔄🔄 LIBERAR
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>


            <!-- SECCIÓN EXTRA: MONITOREO DE PEDIDOS EXPRESOS (DELIVERY / RETIRO) -->
            <?php
            $db_limpia = (new Conexion())->conectar();
            $stmtPend = $db_limpia->prepare("SELECT p.*, u.nombre as nombre_mesero 
    FROM pedidos p 
    INNER JOIN usuarios u ON p.usuario_id = u.id
    WHERE p.tipo_pedido IN ('delivery', 'retiro') 
    AND p.estado = 'pendiente'
    ORDER BY p.id DESC");
            $stmtPend->execute();
            $deliverysAbiertos = $stmtPend->fetchAll();
            ?>
            <?php if (!empty($deliverysAbiertos)): ?>
                <div class="area-section" style="border-left: 5px solid #e67e22; margin-top: 20px;">
                    <h3 style="color: #b55d05;">🏍🏍 Pedidos Expresos en Preparación / Sin Cerrar</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 15px; margin-top: 15px;">
                        <?php foreach ($deliverysAbiertos as $del): ?>
                            <div style="background: #fffcf5; border: 1px solid #ffe8cc; padding: 15px; border-radius: 8px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
                                <div>
                                    <div style="display:flex; justify-content: space-between; margin-bottom: 6px;">
                                        <span style="font-weight:800; color:#e67e22;">📦📦 TICKET #<?php echo $del['id']; ?></span>
                                        <span style="background:#ffe8cc; color:#b55d05; font-size:10px; font-weight:bold; padding:2px 6px; border-radius:10px; text-transform:uppercase;"><?php echo $del['tipo_pedido']; ?></span>
                                    </div>
                                    <small style="display:block; color:#666; font-weight:600; margin-bottom:4px;">👤👤 Operario: <?php echo htmlspecialchars($del['nombre_mesero']); ?></small>
                                    <small style="display:block; color:#888;">Hora: <?php echo date('h:i A', strtotime($del['created_at'])); ?></small>
                                </div>
                                <div style="margin-top: 15px; display:flex; gap: 8px;">
                                    <a href="index.php?v=tomar_pedido&pedido_id=<?php echo $del['id']; ?>" style="flex:1; background:#2d6a4f; color:#fff; text-align:center; padding:8px; border-radius:6px; text-decoration:none; font-size:12px; font-weight:bold;">🍕🍕 Abrir POS</a>
                                    <a href="<?php echo URL_BASE; ?>controllers/MesaController.php?action=liberar_mesa&id=<?php echo $del['id']; ?>"
                                        onclick="return confirm('⚠ ¿Estás seguro de cancelar y borrar permanentemente este pedido de delivery abandonado?');"
                                        style="background:#ffe3e3; color:#c92a2a; padding:8px 12px; border-radius:6px; text-decoration:none; font-size:12px; font-weight:bold; display:flex; align-items:center; cursor:pointer;">❌</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- LÓGICA DE NAVEGACIÓN DIGITAL TÁCTIL -->
    <script>
        function gestionarMesa(id, estado, numeroMesa) {
            const protocol = window.location.protocol + "//";
            const host = window.location.host;
            const urlBase = host === 'localhost' ? protocol + host + "/pizzeria/" : protocol + host + "/";

            if (estado === 'mantenimiento') {
                alert(`⚠ La ${numeroMesa} se encuentra actualmente bajo mantenimiento operativo.`);
                return;
            }
            if (estado === 'disponible' || estado === 'ocupada') {
                window.location.href = `${urlBase}index.php?v=abrir_comanda&mesa_id=${id}&tipo_pedido=local`;
            } else if (estado === 'reservada') {
                if (confirm(`¿Llegaron los clientes de la ${numeroMesa}? ¿Deseas abrir la comanda?`)) {
                    window.location.href = `${urlBase}index.php?v=abrir_comanda&mesa_id=${id}&tipo_pedido=local`;
                }
            }
        }

        function abrirPedidoRapido(tipo) {
            const protocol = window.location.protocol + "//";
            const host = window.location.host;
            const urlBase = host === 'localhost' ? protocol + host + "/pizzeria/" : protocol + host + "/";

            if (tipo === 'delivery') {
                const costoEnvio = prompt("🏍🏍 PEDIDO DELIVERY\nIngrese el costo de envío para el motorizado (C$):", "40.00");
                if (costoEnvio !== null && costoEnvio.trim() !== "") {
                    window.location.href = `${urlBase}index.php?v=abrir_comanda&mesa_id=0&tipo_pedido=delivery&monto_envio=${parseFloat(costoEnvio)}`;
                }
            } else if (tipo === 'retiro') {
                if (confirm("🛍🛍 ENTREGA EN LOCAL / PARA LLEVAR\n¿Desea levantar un pedido de retiro inmediato?")) {
                    window.location.href = `${urlBase}index.php?v=abrir_comanda&mesa_id=0&tipo_pedido=retiro`;
                }
            }
        }
    </script>
    <script src="<?php echo URL_BASE; ?>public/js/main.js"></script>
</body>

</html>