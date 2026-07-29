<?php
// controllers/CompraController.php
//use PDO; 
require_once __DIR__ . '/../models/CompraModelo.php';

class CompraController {
    private $modelo;

    public function __construct() {
        $this->modelo = new CompraModelo();
    }

    public function historialCompras($limite = 10, $offset = 0) {
        return $this->modelo->listarComprasPaginado((int)$limite, (int)$offset);
    }

    public function totalCompras() {
        return $this->modelo->contarTotalCompras();
    }

    public function listarInsumosDisponibles() {
        return $this->modelo->listarProductosInventariables();
    }

       // 📡 ENDPOINT ASÍNCRONO BLINDADO: Limpia salidas corruptas y fuerza cabeceras JSON estrictas
    public function obtenerDetalleCompraAjax() {
        // 🚀 CANDADO DE SEGURIDAD 1: Limpiamos cualquier espacio en blanco o eco residual en memoria
        if (ob_get_length()) { ob_clean(); }
        
        // Forza al navegador a interpretar la respuesta estrictamente como JSON
        header('Content-Type: application/json; charset=utf-8');

        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        if (!isset($_SESSION['usuario_id'])) {
            echo json_encode(['status' => 'error', 'msg' => 'Sesión expirada o inválida.']);
            exit;
        }

        $compra_id = filter_var($_GET['compra_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$compra_id) {
            echo json_encode(['status' => 'error', 'msg' => 'ID de comanda de compra no válido.']);
            exit;
        }

        try {
            $db = $this->modelo->conectar();
            
            // Evaluamos la consistencia de tu tabla relacional compra_detalles
            $sql = "SELECT cd.*, p.nombre as producto_nombre, p.unidad_medida 
                    FROM compra_detalles cd
                    INNER JOIN productos p ON cd.producto_id = p.id
                    WHERE cd.compra_id = :compra_id";
                    
            $stmt = $db->prepare($sql);
            $stmt->execute(['compra_id' => $compra_id]);
            $detalles = $stmt->fetchAll();

            echo json_encode(['status' => 'success', 'data' => $detalles]);
            exit; // Detiene en seco cualquier impresión posterior
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'msg' => 'Falla en Base de Datos: ' . $e->getMessage()]);
            exit;
        }
    }


    public function procesarRegistroCompra() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }

            $usuario_id = $_SESSION['usuario_id'] ?? null;
            if (!$usuario_id) {
                return ['status' => 'error', 'msg' => 'Sesión expirada. Intente de nuevo.', 'origen' => 'login'];
            }

            $accion = $_POST['accion'] ?? '';

            if ($accion === 'registrar_compra_proveedor') {
                $proveedor_id   = intval($_POST['proveedor_id'] ?? 0);
                $numero_factura = trim($_POST['numero_factura'] ?? '');
                $fecha_compra   = trim($_POST['fecha_compra'] ?? '');
                $observaciones  = trim($_POST['observaciones'] ?? '');
                
                $productos_ids   = $_POST['prod_id'] ?? [];
                $cantidades      = $_POST['cantidad'] ?? [];
                $precios_costos  = $_POST['precio_unitario'] ?? [];
                $precios_ventas  = $_POST['precio_venta_publico'] ?? []; 

                if ($proveedor_id <= 0 || empty($numero_factura) || empty($fecha_compra)) {
                    return ['status' => 'error', 'msg' => 'Por favor, rellene todos los campos obligatorios de la cabecera.', 'origen' => 'compras'];
                }

                $insumos_detalle = [];
                $total_factura_calculado = 0;

                foreach ($productos_ids as $index => $prod_id) {
                    $p_id     = intval($prod_id);
                    $cant     = floatval($cantidades[$index] ?? 0);
                    $p_costo  = floatval($precios_costos[$index] ?? 0);
                    $p_venta  = floatval($precios_ventas[$index] ?? 0);

                    if ($p_id <= 0 || $cant <= 0 || $p_costo < 0 || $p_venta < 0) {
                        continue;
                    }

                    $total_factura_calculado += ($cant * $p_costo);
                    
                    $insumos_detalle[] = [
                        'producto_id'     => $p_id,
                        'cantidad'        => $cant,
                        'precio_unitario' => $p_costo,
                        'precio_venta'    => $p_venta
                    ];
                }

                if (empty($insumos_detalle)) {
                    return ['status' => 'error', 'msg' => 'Debe añadir al menos un insumo con datos correctos a la lista.', 'origen' => 'compras'];
                }

                $resultado = $this->modelo->registrarCompraTransaccional(
                    $proveedor_id,
                    $usuario_id,
                    $numero_factura,
                    $fecha_compra,
                    $total_factura_calculado,
                    $observaciones,
                    $insumos_detalle
                );

                if ($resultado) {
                    return ['status' => 'success', 'msg' => '¡Factura de compra guardada con éxito! Almacenes y precios actualizados.', 'origen' => 'compras'];
                } else {
                    return ['status' => 'error', 'msg' => 'Error crítico al procesar la inserción en el servidor.', 'origen' => 'compras'];
                }
            }
        }
        return null;
    }
}

// --- DISPARADOR INTEGRADO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && ($_POST['accion'] === 'registrar_compra_proveedor')) {
    $controller = new CompraController();
    $resultado  = $controller->procesarRegistroCompra();

    if ($resultado) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host     = $_SERVER['HTTP_HOST'];
        $url_base = ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/";
        $tipo     = ($resultado['status'] === 'success') ? 'success' : 'error';

        if ($resultado['origen'] === 'compras') {
            header("Location: " . $url_base . "index.php?v=compras_lista&" . $tipo . "=" . urlencode($resultado['msg']));
        } else {
            header("Location: " . $url_base . "index.php?v=login&" . $tipo . "=" . urlencode($resultado['msg']));
        }
        exit;
    }
}
?>
