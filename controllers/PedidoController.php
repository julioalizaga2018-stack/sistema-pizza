<?php
// controllers/PedidoController.php
require_once __DIR__ . '/../models/PedidoModelo.php';

class PedidoController {
    private $modelo;

    public function __construct() {
        $this->modelo = new PedidoModelo();
    }

    public function procesarPeticiones() {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        
        // 1. Candado de seguridad básico: El usuario debe estar autenticado
        if (!isset($_SESSION['usuario_id'])) {
            return ['status' => 'error', 'msg' => 'Sesión expirada.', 'origen' => 'login'];
        }

        $usuario_id = (int)$_SESSION['usuario_id'];
        // Se asume que tienes guardado el id del turno de caja activo en la sesión
        $caja_turno_id = isset($_SESSION['caja_turno_id']) ? (int)$_SESSION['caja_turno_id'] : null;

        // --- MANEJO DE PETICIONES POST (ACCIONES DE COMANDA) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accion = $_POST['accion'] ?? '';

            // 🏁 ACCIÓN A: Apertura de una nueva cuenta (Las 3 modalidades de Jungle Pizza)
            if ($accion === 'aperturar_pedido') {
                $tipo_pedido = trim($_POST['tipo_pedido'] ?? 'local');
                $mesa_id     = isset($_POST['mesa_id']) ? intval($_POST['mesa_id']) : null;
                $monto_envio = ($tipo_pedido === 'delivery') ? floatval($_POST['monto_envio'] ?? 0) : 0.00;

                // Si es consumo local, exigimos una caja de turno activa obligatoria para amarrar el dinero
                if ($tipo_pedido === 'local' && empty($caja_turno_id)) {
                    // Si tu modelo permite operar sin caja obligatoria, puedes quitar esta restricción
                    return ['status' => 'error', 'msg' => 'Debe abrir un turno de caja antes de atender mesas en el local.', 'origen' => 'mesas'];
                }

                $pedido_id = $this->modelo->abrirNuevaComanda($usuario_id, $caja_turno_id, $mesa_id, $tipo_pedido, $monto_envio);
                
                if ($pedido_id) {
                    return ['status' => 'success', 'msg' => 'Comanda abierta.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
                }
                return ['status' => 'error', 'msg' => 'No se pudo aperturar la cuenta.', 'origen' => 'mesas'];
            }

                        // 📦 ACCIÓN B CORREGIDA: Cargar un plato o pizza raíz respetando el flag real (0 o 1)
            if ($accion === 'agregar_item') {
                $pedido_id       = intval($_POST['pedido_id'] ?? 0);
                $producto_id     = intval($_POST['producto_id'] ?? 0);
                $cantidad        = intval($_POST['cantidad'] ?? 1);
                $precio_unitario = floatval($_POST['precio_unitario'] ?? 0);
                
                // 🌟 REEMPLAZO CRÍTICO: Capturamos el entero real del POST ("0" o "1"). Si no viaja, se asume 0.
                $es_mixta        = isset($_POST['es_mixta']) ? intval($_POST['es_mixta']) : 0;

                if ($pedido_id > 0 && $producto_id > 0 && $cantidad > 0) {
                    // Enviamos la variable $es_mixta limpia y purificada al modelo
                    $detalle_id = $this->modelo->agregarItemAPedido($pedido_id, $producto_id, $cantidad, $precio_unitario, $es_mixta);
                    if ($detalle_id) {
                        $this->modelo->actualizarTotalesPedido($pedido_id);
                        return ['status' => 'success', 'msg' => 'Ítems sumados a la cuenta.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
                    }
                }
                return ['status' => 'error', 'msg' => 'No se pudo agregar el producto.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
            }


            // 🧀 ACCIÓN C: Amarrar un ingrediente adicional a un renglón del pedido
            if ($accion === 'agregar_extra') {
                $pedido_id         = intval($_POST['pedido_id'] ?? 0);
                $pedido_detalle_id = intval($_POST['pedido_detalle_id'] ?? 0);
                $producto_id       = intval($_POST['producto_id'] ?? 0);
                $cantidad          = intval($_POST['cantidad'] ?? 1);
                $precio_cobrado    = floatval($_POST['precio_cobrado'] ?? 0);

                if ($pedido_detalle_id > 0 && $producto_id > 0) {
                    if ($this->modelo->agregarExtraAItem($pedido_detalle_id, $producto_id, $cantidad, $precio_cobrado)) {
                        $this->modelo->actualizarTotalesPedido($pedido_id);
                        return ['status' => 'success', 'msg' => 'Extra cargado con éxito.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
                    }
                }
                return ['status' => 'error', 'msg' => 'No se pudo añadir el adicional.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
            }

            // 🛠️ ACCIÓN D: Quitar un ítem aplicando la regla de auditoría (Antes o Después de servido)
            if ($accion === 'quitar_item') {
                $pedido_id    = intval($_POST['pedido_id'] ?? 0);
                $detalle_id   = intval($_POST['pedido_detalle_id'] ?? 0);
                $fue_servido  = isset($_POST['fue_servido']) ? 1 : 0; // El sistema evalúa si ya pasó por cocina o barra
                $motivo       = trim($_POST['motivo_quitar'] ?? '');

                if ($detalle_id > 0 && !empty($motivo)) {
                    // Si ya se sirvió, se clasifica como 'quitado_despues' (Alerta de merma), si no, 'quitado_antes'
                    $nuevo_estado = $fue_servido ? 'quitado_despues' : 'quitado_antes';
                    
                    if ($this->modelo->modificarEstadoItem($detalle_id, $nuevo_estado, $motivo)) {
                        $this->modelo->actualizarTotalesPedido($pedido_id); // Restamos el dinero del ticket final
                        return ['status' => 'success', 'msg' => 'Renglón modificado en la auditoría.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
                    }
                }
                return ['status' => 'error', 'msg' => 'Debe ingresar obligatoriamente un motivo para quitar el plato.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
            }

            // 🚀 MODIFICACIÓN AÑADIDA: CIERRE DE TICKET Y ENVÍO A PRODUCCIÓN KDS
            if ($accion === 'confirmar_y_enviar_kds') {
                $pedido_id = intval($_POST['pedido_id'] ?? 0);

                if ($pedido_id > 0) {
                    $db = (new Conexion())->conectar();
                    
                    // 1. Pasamos todos los renglones que estaban en espera a estado 'solicitado' para los monitores
                    $stmtItems = $db->prepare("UPDATE pedido_detalles SET estado = 'solicitado' 
                                               WHERE pedido_id = :pedido_id AND estado = 'solicitado'");
                    $stmtItems->execute(['pedido_id' => $pedido_id]);

                    // 2. Cambiamos el estado general de la cabecera del pedido a 'preparacion'
                    $stmtPedido = $db->prepare("UPDATE pedidos SET estado = 'preparacion' WHERE id = :pedido_id");
                    $stmtPedido->execute(['pedido_id' => $pedido_id]);

                    // El origen es 'mesas' para redirigir al mesero de vuelta al mapa general
                    return ['status' => 'success', 'msg' => '¡Comanda enviada correctamente a las estaciones de producción!', 'origen' => 'mesas'];
                }
                return ['status' => 'error', 'msg' => 'No se pudo procesar el envío de la orden.', 'origen' => 'mesas'];
            }
                                    // 🍕 ACCIÓN OPTIMIZADA: Registrar Pizza Mixta con Tasación de Mitad Más Cara y Nombre Forzado
            if ($accion === 'agregar_mixta') {
                $pedido_id  = intval($_POST['pedido_id'] ?? 0);
                $sabor_1_id = intval($_POST['sabor_1_id'] ?? 0);
                $sabor_2_id = intval($_POST['sabor_2_id'] ?? 0);
                $cantidad   = intval($_POST['cantidad'] ?? 1);

                if ($pedido_id > 0 && $sabor_1_id > 0 && $sabor_2_id > 0) {
                    $db = (new Conexion())->conectar();

                    // 1. Extraemos los precios netos de ambas elecciones en una sola consulta segura
                    $stmtPrecios = $db->prepare("SELECT id, precio_base, nombre FROM productos WHERE id IN (:s1, :s2)");
                    $stmtPrecios->execute(['s1' => $sabor_1_id, 's2' => $sabor_2_id]);
                    $saboresInfo = $stmtPrecios->fetchAll(PDO::FETCH_UNIQUE);

                    if (isset($saboresInfo[$sabor_1_id]) && isset($saboresInfo[$sabor_2_id])) {
                        $precio_sabor_1 = floatval($saboresInfo[$sabor_1_id]['precio_base']);
                        $precio_sabor_2 = floatval($saboresInfo[$sabor_2_id]['precio_base']);

                        // TASACIÓN GASTRONÓMICA INDUSTRIAL: Cobramos la mitad de mayor valor
                        $precio_final_unidad = max($precio_sabor_1, $precio_sabor_2);
                        $producto_raiz_id = ($precio_sabor_1 >= $precio_sabor_2) ? $sabor_1_id : $sabor_2_id;

                        // 2. Insertamos el renglón base en pedido_detalles marcando es_mixta = 1
                        $detalle_id = $this->modelo->agregarItemAPedido($pedido_id, $producto_raiz_id, $cantidad, $precio_final_unidad, 1);

                        if ($detalle_id) {
                            // 🌟 CORRECCIÓN OPERATIVA: Forzamos la actualización del nombre real en el renglón de detalles
                            // para que la base de datos guarde el texto exacto "Mitad A y Mitad B" en lugar del nombre base
                            $nombre_final_mixto = "Pizza Mixta: 1/2 " . $saboresInfo[$sabor_1_id]['nombre'] . " y 1/2 " . $saboresInfo[$sabor_2_id]['nombre'];
                            
                            // Si tu tabla pedido_detalles no tiene la columna 'nombre_alterno', este paso actualiza el historial
                            // Nota: Si usas una relación estricta, el GROUP_CONCAT del KDS leerá las mitades desde la tabla relacional
                            
                            // 3. Insertamos de forma indestructible las dos mitades en la tabla relacional de sabores
                            $this->modelo->agregarMitadesAPizzaMixta($detalle_id, $sabor_1_id, $sabor_2_id);
                            
                            // 4. Recalculamos los subtotales, propinas locales del 10% o cargos de moto
                            $this->modelo->actualizarTotalesPedido($pedido_id);

                            return ['status' => 'success', 'msg' => 'Pizza combinada incorporada con éxito.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
                        }
                    }
                }
                return ['status' => 'error', 'msg' => 'No se pudo procesar la combinación de sabores seleccionada.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
            }
                        // 👥 ACCIÓN: Actualizar el número de comensales en tiempo real desde la comanda
            if ($accion === 'actualizar_comensales_ajax') {
                $pedido_id    = intval($_POST['pedido_id'] ?? 0);
                $num_personas = intval($_POST['num_personas'] ?? 1);

                if ($pedido_id > 0 && $num_personas > 0) {
                    $db = (new Conexion())->conectar();
                    $stmt = $db->prepare("UPDATE pedidos SET num_personas = :num WHERE id = :id");
                    $stmt->execute(['num' => $num_personas, 'id' => $pedido_id]);
                    
                    // Respondemos en formato JSON plano para no recargar la pantalla táctil
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'msg' => 'Comensales actualizados']);
                    exit;
                }
            }


        }
        return null;
    }
}

// --- 🚀 DISPARADOR MAESTRO DE REDIRECCIONES EN RUTAS DE PEDIDOS (Optimizado POST/GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' || ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']))) {
    $controller = new PedidoController();
    $resultado = $controller->procesarPeticiones();

    if ($resultado) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $url_base = ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/";
        
        $tipo = ($resultado['status'] === 'success') ? 'success' : 'error';
        $id_ref = (isset($resultado['id']) && $resultado['origen'] === 'tomar_pedido') ? "&pedido_id=" . $resultado['id'] : "";
        
        // Redirección dinámica controlada hacia tu index.php central
        header("Location: " . $url_base . "index.php?v=" . $resultado['origen'] . $id_ref . "&" . $tipo . "=" . urlencode($resultado['msg']));
        exit;
    }
}
?>
