<?php
// controllers/PedidoController.php (Parte A de 2)
require_once __DIR__ . '/../models/PedidoModelo.php';

class PedidoController {
    private $modelo;

    public function __construct() {
        $this->modelo = new PedidoModelo();
    }

    public function procesarPeticiones() {
        if (session_status() === PHP_SESSION_NONE) { 
            session_start(); 
        }

        // 1. Candado de seguridad básico: El usuario debe estar autenticado
        if (!isset($_SESSION['usuario_id'])) {
            return [
                'status' => 'error', 
                'msg'    => 'Sesión expirada. Por favor, vuelva a iniciar sesión.', 
                'origen' => 'login'
            ];
        }

        $usuario_id    = (int)$_SESSION['usuario_id'];
        $caja_turno_id = isset($_SESSION['caja_turno_id']) ? (int)$_SESSION['caja_turno_id'] : null;

        // --- MANEJO DE PETICIONES POST (ACCIONES DE COMANDA) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accion = $_POST['accion'] ?? '';

            // 🏁 ACCIÓN A: Apertura de una nueva cuenta
            if ($accion === 'aperturar_pedido') {
                $tipo_pedido = trim($_POST['tipo_pedido'] ?? 'local');
                $mesa_id     = isset($_POST['mesa_id']) ? intval($_POST['mesa_id']) : null;
                $monto_envio = ($tipo_pedido === 'delivery') ? floatval($_POST['monto_envio'] ?? 0) : 0.00;

                if ($tipo_pedido === 'local' && empty($caja_turno_id)) {
                    return [
                        'status' => 'error', 
                        'msg'    => 'Debe abrir un turno de caja antes de atender mesas en el local.', 
                        'origen' => 'mesas'
                    ];
                }

                $pedido_id = $this->modelo->abrirNuevaComanda($usuario_id, $caja_turno_id, $mesa_id, $tipo_pedido, $monto_envio);
                if ($pedido_id) {
                    return [
                        'status' => 'success', 
                        'msg'    => 'Comanda abierta con éxito.', 
                        'origen' => 'tomar_pedido', 
                        'id'     => $pedido_id
                    ];
                }
                return ['status' => 'error', 'msg' => 'No se pudo aperturar la cuenta.', 'origen' => 'mesas'];
            }

            // 📦 ACCIÓN B: Cargar un plato o pizza raíz (Nace siempre en 'solicitado')
            if ($accion === 'agregar_item') {
                $pedido_id       = intval($_POST['pedido_id'] ?? 0);
                $producto_id     = intval($_POST['producto_id'] ?? 0);
                $cantidad        = intval($_POST['cantidad'] ?? 1);
                $precio_unitario = floatval($_POST['precio_unitario'] ?? 0);
                $es_mixta        = isset($_POST['es_mixta']) ? intval($_POST['es_mixta']) : 0;

                if ($pedido_id > 0 && $producto_id > 0 && $cantidad > 0) {
                    $detalle_id = $this->modelo->agregarItemAPedido($pedido_id, $producto_id, $cantidad, $precio_unitario, $es_mixta);
                    if ($detalle_id) {
                        $this->modelo->actualizarTotalesPedido($pedido_id);
                        return [
                            'status' => 'success', 
                            'msg'    => 'Ítems sumados a la cuenta.', 
                            'origen' => 'tomar_pedido', 
                            'id'     => $pedido_id
                        ];
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
                        return [
                            'status' => 'success', 
                            'msg'    => 'Extra cargado con éxito.', 
                            'origen' => 'tomar_pedido', 
                            'id'     => $pedido_id
                        ];
                    }
                }
                return ['status' => 'error', 'msg' => 'No se pudo añadir el adicional.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
            }

            // 🛠️ ACCIÓN D: Quitar un ítem aplicando la regla de auditoría (Antes o Después de servido)
            if ($accion === 'quitar_item') {
                $pedido_id   = intval($_POST['pedido_id'] ?? 0);
                $detalle_id  = intval($_POST['pedido_detalle_id'] ?? 0);
                $fue_servido = isset($_POST['fue_servido']) ? 1 : 0;
                $motivo      = trim($_POST['motivo_quitar'] ?? '');

                if ($detalle_id > 0 && !empty($motivo)) {
                    $nuevo_estado = $fue_servido ? 'quitado_despues' : 'quitado_antes';
                    if ($this->modelo->modificarEstadoItem($detalle_id, $nuevo_estado, $motivo)) {
                        $this->modelo->actualizarTotalesPedido($pedido_id); 
                        return [
                            'status' => 'success', 
                            'msg'    => 'Renglón modificado en la auditoría de mermas.', 
                            'origen' => 'tomar_pedido', 
                            'id'     => $pedido_id
                        ];
                    }
                }
                return ['status' => 'error', 'msg' => 'Debe ingresar obligatoriamente un motivo para quitar el plato.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
            }

            // 🌟🌟 ACCIÓN E: CONTROL KDS UNIFICADO - PRESIONAR BOTÓN VERDE "ENVIAR ORDEN"
            if ($accion === 'comandar_orden_kds') {
                $pedido_id = intval($_POST['pedido_id'] ?? 0);
                if ($pedido_id > 0) {
                    $exitoKds = $this->modelo->enviarPedidoAProduccion($pedido_id);
                    if ($exitoKds) {
                        return [
                            'status' => 'success', 
                            'msg'    => '¡Comanda enviada correctamente a las estaciones de producción!', 
                            'origen' => 'tomar_pedido', 
                            'id'     => $pedido_id
                        ];
                    } else {
                        return [
                            'status' => 'error', 
                            'msg'    => 'No hay productos nuevos en borrador para enviar a las cocinas.', 
                            'origen' => 'tomar_pedido', 
                            'id'     => $pedido_id
                        ];
                    }
                }
                return ['status' => 'error', 'msg' => 'No se pudo procesar el envío de la orden.', 'origen' => 'mesas'];
            }

                     // 🍕 ACCIÓN F REPARADA: Registrar Pizza Mixta con Tasación del Sabor Más Caro
            if ($accion === 'agregar_mixta') {
                $pedido_id  = intval($_POST['pedido_id'] ?? 0);
                $sabor_1_id = intval($_POST['sabor_1_id'] ?? 0);
                $sabor_2_id = intval($_POST['sabor_2_id'] ?? 0);
                $cantidad   = intval($_POST['cantidad'] ?? 1);

                if ($pedido_id > 0 && $sabor_1_id > 0 && $sabor_2_id > 0) {
                    $db = (new Conexion())->conectar();
                    
                    // 🌟 SOLUCIÓN: Buscamos ambos sabores por separado de forma lineal para evitar conflictos de FETCH_UNIQUE
                    $stmtS1 = $db->prepare("SELECT precio_base FROM productos WHERE id = :s1 LIMIT 1");
                    $stmtS1->execute(['s1' => $sabor_1_id]);
                    $resS1 = $stmtS1->fetch(PDO::FETCH_ASSOC);

                    $stmtS2 = $db->prepare("SELECT precio_base FROM productos WHERE id = :s2 LIMIT 1");
                    $stmtS2->execute(['s2' => $sabor_2_id]);
                    $resS2 = $stmtS2->fetch(PDO::FETCH_ASSOC);

                    if ($resS1 && $resS2) {
                        $precio_sabor_1 = floatval($resS1['precio_base']);
                        $precio_sabor_2 = floatval($resS2['precio_base']);
                        
                        // Capturamos el precio más alto para cobrarlo de forma legal al cliente
                        $precio_final_unidad = max($precio_sabor_1, $precio_sabor_2);
                        
                        // Determinamos el ID del producto raíz comercial basándonos en la pizza más cara
                        $producto_raiz_id = ($precio_sabor_1 >= $precio_sabor_2) ? $sabor_1_id : $sabor_2_id;

                        // Inyectamos el renglón principal a pedido_detalles en estado 'solicitado' (Nuevo borrador)
                        $detalle_id = $this->modelo->agregarItemAPedido($pedido_id, $producto_raiz_id, $cantidad, $precio_final_unidad, 1);
                        
                        if ($detalle_id) {
                            // Amárramos las dos mitades relacionales reales dentro de tu tabla pedido_detalle_sabores
                            $this->modelo->agregarMitadesAPizzaMixta($detalle_id, $sabor_1_id, $sabor_2_id);
                            
                            // Forzamos el recalculo matemático final de la cabecera
                            $this->modelo->actualizarTotalesPedido($pedido_id);
                            
                            return [
                                'status' => 'success', 
                                'msg'    => '¡Pizza Mixta Combinada incorporada con éxito al ticket!', 
                                'origen' => 'tomar_pedido', 
                                'id'     => $pedido_id
                            ];
                        }
                    }
                }
                return ['status' => 'error', 'msg' => 'No se pudo procesar la combinación de sabores. Verifique el catálogo.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
            }


            // 👥 ACCIÓN G: Actualizar el número de comensales en tiempo real (AJAX)
            if ($accion === 'actualizar_comensales_ajax') {
                $pedido_id    = intval($_POST['pedido_id'] ?? 0);
                $num_personas = intval($_POST['num_personas'] ?? 1);

                if ($pedido_id > 0 && $num_personas > 0) {
                    $db = (new Conexion())->conectar();
                    $stmt = $db->prepare("UPDATE pedidos SET num_personas = :num WHERE id = :id");
                    $stmt->execute(['num' => $num_personas, 'id' => $pedido_id]);
                    
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'msg' => 'Comensales actualizados']);
                    exit;
                }
            }
        }
        return null;
    }
}

// --- DISPARADOR MAESTRO DE REDIRECCIONES EN RUTAS DE PEDIDOS (Optimizado POST/GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' || ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']))) {
    $controller = new PedidoController();
    $resultado = $controller->procesarPeticiones();

    if ($resultado) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host     = $_SERVER['HTTP_HOST'];
        $url_base = ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/";
        
        $tipo   = ($resultado['status'] === 'success') ? 'success' : 'error';
        $id_ref = (isset($resultado['id']) && $resultado['origen'] === 'tomar_pedido') ? "&pedido_id=" . $resultado['id'] : "";

        header("Location: " . $url_base . "index.php?v=" . $resultado['origen'] . $id_ref . "&" . $tipo . "=" . urlencode($resultado['msg']));
        exit;
    }
}
?>
