<?php
// controllers/PedidoController.php (Parte 1 de 2 - Jungle POS Controller)
require_once __DIR__ . '/../models/PedidoModelo.php';

class PedidoController
{
    private $modelo;

    public function __construct()
    {
        $this->modelo = new PedidoModelo();
    }

    public function procesarPeticiones()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 1. Candado de seguridad básico: El usuario debe estar autenticado
        if (!isset($_SESSION['usuario_id'])) {
            return [
                'status' => 'error',
                'msg' => 'Sesión expirada. Por favor, vuelva a iniciar sesión.',
                'origen' => 'login'
            ];
        }

        $usuario_id = (int)$_SESSION['usuario_id'];
        $caja_turno_id = isset($_SESSION['caja_turno_id']) ? (int)$_SESSION['caja_turno_id'] : null;

        // --- MANEJO DE PETICIONES POST (ACCIONES DE COMANDA) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accion = $_POST['accion'] ?? '';

            // 🏁🏁 ACCIÓN A: Apertura de una nueva cuenta
            if ($accion === 'aperturar_pedido') {
                $tipo_pedido = trim($_POST['tipo_pedido'] ?? 'local');
                $mesa_id = isset($_POST['mesa_id']) ? intval($_POST['mesa_id']) : null;
                $monto_envio = ($tipo_pedido === 'delivery') ? floatval($_POST['monto_envio'] ?? 0) : 0.00;

                if ($tipo_pedido === 'local' && empty($caja_turno_id)) {
                    return [
                        'status' => 'error',
                        'msg' => 'Debe abrir un turno de caja antes de atender mesas in el local.',
                        'origen' => 'mesas'
                    ];
                }

                $pedido_id = $this->modelo->abrirNuevaComanda($usuario_id, $caja_turno_id, $mesa_id, $tipo_pedido, $monto_envio);
                if ($pedido_id) {
                    return [
                        'status' => 'success',
                        'msg' => 'Comanda abierta con éxito.',
                        'origen' => 'tomar_pedido',
                        'id' => $pedido_id
                    ];
                }
                return ['status' => 'error', 'msg' => 'No se pudo aperturar la cuenta.', 'origen' => 'mesas'];
            }

            // 📦📦 ACCIÓN B: Cargar un plato o pizza raíz (Nace siempre en 'solicitado')
            if ($accion === 'agregar_item') {
                $pedido_id = intval($_POST['pedido_id'] ?? 0);
                $producto_id = intval($_POST['producto_id'] ?? 0);
                $cantidad = intval($_POST['cantidad'] ?? 1);
                $precio_unitario = floatval($_POST['precio_unitario'] ?? 0);
                $es_mixta = isset($_POST['es_mixta']) ? intval($_POST['es_mixta']) : 0;

                if ($pedido_id > 0 && $producto_id > 0 && $cantidad > 0) {
                    $detalle_id = $this->modelo->agregarItemAPedido($pedido_id, $producto_id, $cantidad, $precio_unitario, $es_mixta);
                    if ($detalle_id) {
                        $this->modelo->actualizarTotalesPedido($pedido_id);
                        return [
                            'status' => 'success',
                            'msg' => 'Ítems sumados a la cuenta.',
                            'origen' => 'tomar_pedido',
                            'id' => $pedido_id
                        ];
                    }
                }
                return ['status' => 'error', 'msg' => 'No se pudo agregar el producto.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
            }

            // 🧀🧀 ACCIÓN C: Amarrar un ingrediente adicional a un renglón del pedido
            if ($accion === 'agregar_extra') {
                $pedido_id = intval($_POST['pedido_id'] ?? 0);
                $pedido_detalle_id = intval($_POST['pedido_detalle_id'] ?? 0);
                $producto_id = intval($_POST['producto_id'] ?? 0);
                $cantidad = intval($_POST['cantidad'] ?? 1);
                $precio_cobrado = floatval($_POST['precio_cobrado'] ?? 0);

                if ($pedido_detalle_id > 0 && $producto_id > 0) {
                    if ($this->modelo->agregarExtraAItem($pedido_detalle_id, $producto_id, $cantidad, $precio_cobrado)) {
                        $this->modelo->actualizarTotalesPedido($pedido_id);
                        return [
                            'status' => 'success',
                            'msg' => 'Extra cargado con éxito.',
                            'origen' => 'tomar_pedido',
                            'id' => $pedido_id
                        ];
                    }
                }
                return ['status' => 'error', 'msg' => 'No se pudo añadir el adicional.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
            }

            // 🛠🛠 ACCIÓN D: Quitar un ítem aplicando la regla de auditoría (Antes o Después de servido)
            if ($accion === 'quitar_item') {
                $pedido_id = intval($_POST['pedido_id'] ?? 0);
                $detalle_id = intval($_POST['pedido_detalle_id'] ?? 0);
                $fue_servido = isset($_POST['fue_servido']) ? 1 : 0;
                $motivo = trim($_POST['motivo_quitar'] ?? '');

                if ($detalle_id > 0 && !empty($motivo)) {
                    $nuevo_estado = $fue_servido ? 'quitado_despues' : 'quitado_antes';
                    if ($this->modelo->modificarEstadoItem($detalle_id, $nuevo_estado, $motivo)) {
                        $this->modelo->actualizarTotalesPedido($pedido_id);
                        return [
                            'status' => 'success',
                            'msg' => 'Renglón modificado en la auditoría de mermas.',
                            'origen' => 'tomar_pedido',
                            'id' => $pedido_id
                        ];
                    }
                }
                return ['status' => 'error', 'msg' => 'Debe ingresar obligatoriamente un motivo para quitar el plato.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
            }
            // 🌟 ACCIÓN INYECTADA NATIVAMENTE: Cambio / Transferencia de Mesa (MVC Puro)
            if ($accion === 'cambiar_mesa_pedido') {
                $pedido_id = intval($_POST['pedido_id'] ?? 0);
                $mesa_origen_id = intval($_POST['mesa_origen_id'] ?? 0);
                $mesa_destino_id = intval($_POST['mesa_destino_id'] ?? 0);

                if ($pedido_id > 0 && $mesa_destino_id > 0) {
                    // Invocamos el método transaccional que encapsulamos en PedidoModelo
                    $exitoTransferencia = $this->modelo->transferirPedidoDeMesa($pedido_id, $mesa_origen_id, $mesa_destino_id);

                    if ($exitoTransferencia) {
                        return [
                            'status' => 'success',
                            'msg' => '¡Pedido transferido de mesa con éxito!',
                            'origen' => 'tomar_pedido',
                            'id' => $pedido_id
                        ];
                    } else {
                        return [
                            'status' => 'error',
                            'msg' => 'Error crítico al procesar la transferencia de la mesa.',
                            'origen' => 'tomar_pedido',
                            'id' => $pedido_id
                        ];
                    }
                }
                return ['status' => 'error', 'msg' => 'Parámetros de cambio de mesa inválidos.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
            }

            // 🌟🌟🌟🌟 ACCIÓN E: CONTROL KDS UNIFICADO - PRESIONAR BOTÓN VERDE "ENVIAR ORDEN"
            if ($accion === 'comandar_orden_kds') {
                $pedido_id = intval($_POST['pedido_id'] ?? 0);
                if ($pedido_id > 0) {
                    $exitoKds = $this->modelo->enviarPedidoAProduccion($pedido_id);
                    if ($exitoKds) {
                        return [
                            'status' => 'success',
                            'msg' => '¡Comanda enviada correctamente a las estaciones de producción!',
                            'origen' => 'tomar_pedido',
                            'id' => $pedido_id
                        ];
                    } else {
                        return [
                            'status' => 'error',
                            'msg' => 'No hay productos nuevos en borrador para enviar a las cocinas.',
                            'origen' => 'tomar_pedido',
                            'id' => $pedido_id
                        ];
                    }
                }
                return ['status' => 'error', 'msg' => 'No se pudo procesar el envío de la orden.', 'origen' => 'mesas'];
            }

            // 🍕🍕 ACCIÓN F REPARADA: Registrar Pizza Mixta con Tasación del Sabor Más Caro
            if ($accion === 'agregar_mixta') {
                $pedido_id = intval($_POST['pedido_id'] ?? 0);
                $sabor_1_id = intval($_POST['sabor_1_id'] ?? 0);
                $sabor_2_id = intval($_POST['sabor_2_id'] ?? 0);
                $cantidad = intval($_POST['cantidad'] ?? 1);

                if ($pedido_id > 0 && $sabor_1_id > 0 && $sabor_2_id > 0) {
                    $db = (new Conexion())->conectar();
                    // Buscamos ambos sabores por separado de forma lineal para evitar conflictos de FETCH_UNIQUE
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
                                'msg' => '¡Pizza Mixta Combinada incorporada con éxito al ticket!',
                                'origen' => 'tomar_pedido',
                                'id' => $pedido_id
                            ];
                        }
                    }
                }
                return ['status' => 'error', 'msg' => 'No se pudo procesar la combinación de sabores. Verifique el catálogo.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
            }

                       // 👥👥 ACCIÓN G: Actualizar el número de comensales en tiempo real (AJAX)
            if ($accion === 'actualizar_comensales_ajax') {
                $pedido_id = intval($_POST['pedido_id'] ?? 0);
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

            // 👤 ACCIÓN INTEGRADA: Guardar o actualizar el nombre del cliente vía AJAX en segundo plano
            if ($accion === 'actualizar_nombre_cliente_ajax') {
                $pedido_id = intval($_POST['pedido_id'] ?? 0);
                $cliente_nombre = trim($_POST['cliente_nombre'] ?? '');

                if ($pedido_id > 0) {
                    $dbNombre = (new Conexion())->conectar();
                    $stmt = $dbNombre->prepare("UPDATE pedidos SET cliente_nombre = :nombre WHERE id = :id");
                    $stmt->execute([
                        'nombre' => !empty($cliente_nombre) ? $cliente_nombre : null, 
                        'id' => $pedido_id
                    ]);
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'msg' => 'Nombre de cliente sincronizado']);
                    exit;
                }
            }

            // 👥 ACCIÓN INYECTADA NATIVAMENTE: Dividir / Separar Cuentas (Split Bill)
            if ($accion === 'dividir_cuenta_pedido') {
                $pedido_id = intval($_POST['pedido_id'] ?? 0);
                $items_seleccionados = $_POST['items_dividir'] ?? []; // Recibe un arreglo de IDs de detalles

                if ($pedido_id > 0 && !empty($items_seleccionados)) {
                    // Invocamos el nuevo método transaccional de división que guardamos en tu modelo
                    $nuevo_pedido_id = $this->modelo->dividirItemsAPedidoNuevo($pedido_id, $items_seleccionados);
                    
                    if ($nuevo_pedido_id) {
                        return [
                            'status' => 'success', 
                            'msg' => "¡Cuenta dividida con éxito! Se ha generado el Ticket #{$nuevo_pedido_id} para los productos separados.", 
                            'origen' => 'tomar_pedido', 
                            'id' => $pedido_id
                        ];
                    } else {
                        return [
                            'status' => 'error', 
                            'msg' => 'Error crítico al procesar la división de cuentas en el servidor.', 
                            'origen' => 'tomar_pedido', 
                            'id' => $pedido_id
                        ];
                    }
                }
                return ['status' => 'error', 'msg' => 'Debe seleccionar al menos un producto para poder dividir la cuenta.', 'origen' => 'tomar_pedido', 'id' => $pedido_id];
            }

        } // 👈 AQUÍ CIERRA CORRECTAMENTE LA LLAVE DEL IF DE PETICIONES POST
        return null;
    } // 👈 AQUÍ CIERRA CORRECTAMENTE LA LLAVE DEL MÉTODO procesarPeticiones()
        /**
     * 📊 Despacha los datos agrupados de ventas diarias hacia el panel de administración
     */
       /**
     * 📊 REPORTE DIARIO CON PAGINACIÓN: Calcula el total de días registrados
     * y extrae únicamente un bloque segmentado de 10 días para alta velocidad.
     */
    public function obtenerReporteDiarioPaginado($pagina_actual = 1, $registros_por_pagina = 10) {
        $db = (new Conexion())->conectar();
        
        // 1. Contamos de forma exacta cuántos días únicos con ventas existen en total
        $sqlTotal = "SELECT COUNT(DISTINCT DATE(CONVERT_TZ(created_at, '+00:00', '-06:00'))) as total FROM pedidos WHERE estado = 'entregado'";
        $stmtTotal = $db->prepare($sqlTotal);
        $stmtTotal->execute();
        $total_registros = intval($stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        
        // 2. Calculamos los límites matemáticos para MySQL
        $total_paginas = ceil($total_registros / $registros_por_pagina);
        $pagina_actual = max(1, min($pagina_actual, $total_paginas));
        $offset = ($pagina_actual - 1) * $registros_por_pagina;
        
        // 3. Extraemos únicamente el segmento correspondiente a la página activa
        $sqlData = "SELECT 
                        DATE(CONVERT_TZ(created_at, '+00:00', '-06:00')) as fecha,
                        COUNT(id) as total_pedidos,
                        SUM(total) as ingresos_totales,
                        SUM(monto_propina) as total_propinas,
                        SUM(monto_envio) as total_delivery,
                        SUM(monto_descuento) as total_descuentos
                    FROM pedidos 
                    WHERE estado = 'entregado'
                    GROUP BY DATE(CONVERT_TZ(created_at, '+00:00', '-06:00'))
                    ORDER BY DATE(CONVERT_TZ(created_at, '+00:00', '-06:00')) DESC
                    LIMIT :limite OFFSET :offset";
                    
        $stmtData = $db->prepare($sqlData);
        // Vinculamos como enteros estrictos para evitar quiebres sintácticos en PDO
        $stmtData->bindValue(':limite', (int)$registros_por_pagina, PDO::PARAM_INT);
        $stmtData->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmtData->execute();
        $registros = $stmtData->fetchAll(PDO::FETCH_ASSOC);
        
        // Retornamos un paquete de datos unificado
        return [
            'data' => $registros,
            'pagina_actual' => $pagina_actual,
            'total_paginas' => $total_paginas,
            'total_registros' => $total_registros
        ];
    }


} // 👈 AQUÍ CIERRA CORRECTAMENTE LA LLAVE DE LA CLASE PedidoController



// --- 🚀🚀 DISPARADOR MAESTRO DE REDIRECCIONES EN RUTAS DE PEDIDOS (Optimizado POST/GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' || ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']))) {
    $controller = new PedidoController();
    $resultado = $controller->procesarPeticiones();

    if ($resultado) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $url_base = ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/";
        $tipo = ($resultado['status'] === 'success') ? 'success' : 'error';
        $id_ref = (isset($resultado['id']) && $resultado['origen'] === 'tomar_pedido') ? "&pedido_id=" . $resultado['id'] : "";

        header("Location: " . $url_base . "index.php?v=" . $resultado['origen'] . $id_ref . "&" . $tipo . "=" . urlencode($resultado['msg']));
        exit;
    }
    
}


?>
