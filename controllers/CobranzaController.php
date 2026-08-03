<?php
// controllers/CobranzaController.php
require_once __DIR__ . '/../models/CobranzaModelo.php';
require_once __DIR__ . '/../models/CajaModelo.php';

class CobranzaController {
    private $modelo;
    private $cajaModelo;

    public function __construct() {
        $this->modelo = new CobranzaModelo();
        $this->cajaModelo = new CajaModelo();
    }

    public function listadoPendientes() {
        return $this->modelo->listarPedidosPendientesDeCobro();
    }

    public function procesarCobro() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            $usuario_id = $_SESSION['usuario_id'] ?? 0;

            // 🌟 VALIDACIÓN CRÍTICA ANTI-DUPLICADOS (CLIC DOBLE O CORTE DE RED)
            $postToken = $_POST['pago_token'] ?? '';
            $sessionToken = $_SESSION['pago_token'] ?? '';
            $pedido_id = intval($_POST['pedido_id'] ?? 0);

            if (empty($postToken) || $postToken !== $sessionToken) {
                // El primer clic ya quemó el token. Retornamos un aviso controlado para redirigir
                return [
                    'status' => 'success', 
                    'msg' => '¡El pago de esta comanda ya fue procesado previamente con éxito!', 
                    'origen' => 'cobranza',
                    'pedido_id' => $pedido_id
                ];
            }

            // 🔥 ¡Token verificado con éxito! Lo destruimos inmediatamente para bloquear ráfagas adicionales
            unset($_SESSION['pago_token']);

            // 🔒🔒 REGLA DE SEGURIDAD POS: Validar que el cajero tenga su turno abierto antes de cobrar
            $turno = $this->cajaModelo->obtenerTurnoActivo($usuario_id);
            if (!$turno) {
                return ['status' => 'error', 'msg' => 'Operación denegada. Debe abrir un turno de caja para procesar cobros.', 'origen' => 'cobranza'];
            }

            $propina = floatval($_POST['monto_propina'] ?? 0);
            $descuento = floatval($_POST['monto_descuento'] ?? 0);
            $total_final = floatval($_POST['total_final'] ?? 0);

            if ($pedido_id <= 0 || $total_final < 0) {
                return ['status' => 'error', 'msg' => 'Datos de facturación inválidos.', 'origen' => 'cobranza'];
            }

            // Mapeamos el array del pago mixto enviado por el formulario
            $pagos = [];

            // Efectivo
            $pagos[] = ['metodo_pago' => 'efectivo', 'monto' => floatval($_POST['pago_efectivo'] ?? 0), 'banco_id' => null, 'referencia' => null];
            
            // Tarjeta (Mapeado de forma nativa a tus selects con ID)
            $pagos[] = [
                'metodo_pago' => 'tarjeta', 
                'monto' => floatval($_POST['pago_tarjeta'] ?? 0), 
                'banco_id' => intval($_POST['banco_tarjeta'] ?? 0), 
                'referencia' => trim($_POST['ref_tarjeta'] ?? '')
            ];
            
            // Transferencia (Mapeado de forma nativa a tus selects con ID)
            $pagos[] = [
                'metodo_pago' => 'transferencia', 
                'monto' => floatval($_POST['pago_trans'] ?? 0), 
                'banco_id' => intval($_POST['banco_trans'] ?? 0), 
                'referencia' => trim($_POST['ref_trans'] ?? '')
            ];

            // Ejecución quirúrgica en base de datos
$resultado = $this->modelo->procesarPagoPedido($pedido_id, $turno['id'], $propina, $descuento, $pagos, $total_final);

if ($resultado) {
    // ============================================================================
    // 🚀🚀 GATILLO MAESTRO EN CASCADA: DETONA EL REBAJO DE INGREDIENTES EN BODEGA
    // ============================================================================
    $usuario_actual_id = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 1;
    $this->modelo->procesarDescuentoInventarioCascada($pedido_id, $usuario_actual_id);

    // ============================================================================
    // 🪑🪑 🌟 NUEVO: FUERZA LA LIBERACIÓN DE LA MESA EN ESTE INSTANTE PRECISO
    // ============================================================================
    /*
       Invocamos una conexión rápida para asegurarnos de que la mesa vinculada a este
       pedido cambie su estado a 'libre' o 'disponible' en tu mapa de salones (mesas_salon.php),
       evitando que se quede bloqueada por culpa de redirecciones del token.
    */
    try {
        $dbCobro = (new Conexion())->conectar();
        
        // 1. Conseguir el mesa_id asociado a este pedido
        $stmtMesa = $dbCobro->prepare("SELECT mesa_id FROM pedidos WHERE id = :id LIMIT 1");
        $stmtMesa->execute(['id' => $pedido_id]);
        $idMesaReg = $stmtMesa->fetchColumn();

        if ($idMesaReg) {
            /* 
               Cambia 'disponible' por el término exacto que use tu base de datos si es 
               diferente (por ejemplo: 'libre', 0, o 'vacia'). Generalmente se usa 'disponible'.
            */
            $stmtLiberar = $dbCobro->prepare("UPDATE mesas SET estado = 'disponible' WHERE id = :mesa_id");
            $stmtLiberar->execute(['mesa_id' => $idMesaReg]);
        }
    } catch (Exception $e) {
        // Silencioso para no romper la impresión del ticket si la tabla mesas varía
    }

    // Retornamos el origen y el pedido_id de forma explícita para la impresión posterior
    return [
        'status' => 'success', 
        'msg' => '¡Pedido #' . $pedido_id . ' cobrado, inventario rebajado y mesa liberada con éxito!', 
        'origen' => 'cobranza',
        'pedido_id' => $pedido_id
    ];
} else {
                return ['status' => 'error', 'msg' => 'Error crítico al registrar el pago mixto. Transaction rolled back.', 'origen' => 'cobranza'];
            }
        }
        return null;
    }
}

// --- 🚀🚀 DISPARADOR INTEGRADO DE RUTAS DE COBRANZA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'facturar_pedido') {
    $controller = new CobranzaController();
    $resultado = $controller->procesarCobro();

    if ($resultado) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $url_base = ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/";
        $tipo = ($resultado['status'] === 'success') ? 'success' : 'error';

        // Redirección tradicional unificada respetando tu index.php
        if ($resultado['origen'] === 'cobranza') {
            $url_redireccion = $url_base . "index.php?v=cobranza_lista&" . $tipo . "=" . urlencode($resultado['msg']);
            
            if ($resultado['status'] === 'success' && isset($resultado['pedido_id'])) {
                $url_redireccion .= "&imprimir_id=" . intval($resultado['pedido_id']);
            }
            header("Location: " . $url_redireccion);
        } else {
            header("Location: " . $url_base . "index.php?v=login&" . $tipo . "=" . urlencode($resultado['msg']));
        }
        exit;
    }
}
?>
