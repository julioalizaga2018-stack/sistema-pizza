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
            
            // 🔒 REGLA DE SEGURIDAD POS: Validar que el cajero tenga su turno abierto antes de cobrar
            $turno = $this->cajaModelo->obtenerTurnoActivo($usuario_id);
            if (!$turno) {
                return ['status' => 'error', 'msg' => 'Operación denegada. Debe abrir un turno de caja para procesar cobros.', 'origen' => 'cobranza'];
            }

            $pedido_id   = intval($_POST['pedido_id'] ?? 0);
            $propina     = floatval($_POST['monto_propina'] ?? 0);
            $descuento   = floatval($_POST['monto_descuento'] ?? 0);
            $total_final = floatval($_POST['total_final'] ?? 0);

            if ($pedido_id <= 0 || $total_final < 0) {
                return ['status' => 'error', 'msg' => 'Datos de facturación inválidos.', 'origen' => 'cobranza'];
            }

            // Mapeamos el array del pago mixto enviado por el formulario
            $pagos = [];
            
            // Efectivo
            $pagos[] = ['metodo_pago' => 'efectivo', 'monto' => floatval($_POST['pago_efectivo'] ?? 0), 'banco_id' => null, 'referencia' => null];
            
            // Tarjeta
            $pagos[] = [
                'metodo_pago' => 'tarjeta', 
                'monto' => floatval($_POST['pago_tarjeta'] ?? 0), 
                'banco_id' => intval($_POST['banco_tarjeta'] ?? 0), 
                'referencia' => trim($_POST['ref_tarjeta'] ?? '')
            ];
            
            // Transferencia
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
            // Como el controlador ya cuenta con una variable de sesión iniciada arriba,
            // capturamos de forma limpia el ID del usuario cajero en turno
            $usuario_actual_id = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 1;
            
            // Invocamos el método relacional que creamos en tu CobranzaModelo
            // Este método escanea recetas, mitades (0.50) y la tabla de extras (ID 22)
            $this->modelo->procesarDescuentoInventarioCascada($pedido_id, $usuario_actual_id);

            // Retornamos el origen y el pedido_id de forma explícita para la impresión posterior
            return [
                'status'    => 'success', 
                'msg'       => '¡Pedido #' . $pedido_id . ' cobrado y facturado con éxito!', 
                'origen'    => 'cobranza',
                'pedido_id' => $pedido_id
            ];
        } else {
            return ['status' => 'error', 'msg' => 'Error crítico al registrar el pago mixto. Transaction rolled back.', 'origen' => 'cobranza'];
        }
        }
        return null;
    }
}

// --- 🚀 DISPARADOR INTEGRADO DE RUTAS DE COBRANZA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'facturar_pedido') {
    $controller = new CobranzaController();
    $resultado  = $controller->procesarCobro();

    if ($resultado) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host     = $_SERVER['HTTP_HOST'];
        $url_base = ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/";
        $tipo     = ($resultado['status'] === 'success') ? 'success' : 'error';

        // Redirección tradicional unificada respetando tu index.php
        if ($resultado['origen'] === 'cobranza') {
            // Si el cobro es exitoso, inyectamos el parámetro imprimir_id en la URL
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
