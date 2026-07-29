<?php
// models/InventarioModelo.php
require_once __DIR__ . '/../config/conexion.php';

class InventarioModelo extends Conexion {
    private $db;

    public function __construct() {
        $this->db = $this->conectar();
    }

    // 🚀 MÉTODO PUENTE: Permite heredar la misma conexión PDO padre para evitar Deadlocks
    public function establecerConexionCompartida($conexion_pdo) {
        if ($conexion_pdo instanceof PDO) {
            $this->db = $conexion_pdo;
        }
    }

    // 🍕 MÉTODO MAESTRO: Registra el movimiento y actualiza las existencias sin duplicar transacciones
    public function registrarMovimiento($producto_id, $tipo, $cantidad, $motivo, $referencia_id = null, $usuario_id = null) {
        // Detectamos si el proceso ya viene envuelto en una transacción de Compra o Venta
        $transaccion_externa_activa = $this->db->inTransaction();
        
        try {
            if (!$transaccion_externa_activa) {
                $this->db->beginTransaction();
            }

            // 1. Obtener el stock actual del producto directo de la BD con candado de fila
            $sqlProd = "SELECT stock_actual, maneja_stock FROM productos WHERE id = :id FOR UPDATE";
            $stmtProd = $this->db->prepare($sqlProd);
            $stmtProd->execute(['id' => $producto_id]);
            $producto = $stmtProd->fetch();

            if (!$producto || (int)$producto['maneja_stock'] !== 1) {
                if (!$transaccion_externa_activa) {
                    $this->db->rollBack();
                }
                return true;
            }

            $stock_anterior = floatval($producto['stock_actual']);
            $cantidad_float = floatval($cantidad);
            $stock_posterior = $stock_anterior;

            // 2. Calcular la nueva existencia basada en el flujo comercial
            if ($tipo === 'entrada_ajuste' || $tipo === 'compra_proveedor' || $tipo === 'cancelacion_factura') {
                $stock_posterior = $stock_anterior + $cantidad_float;
            } else if ($tipo === 'salida_ajuste' || $tipo === 'venta_factura') {
                $stock_posterior = $stock_anterior - $cantidad_float;
            }

            // 3. Modificar el inventario en la tabla maestra de productos
            $sqlUp = "UPDATE productos SET stock_actual = :nuevo_stock WHERE id = :id";
            $stmtUp = $this->db->prepare($sqlUp);
            $stmtUp->execute(['nuevo_stock' => $stock_posterior, 'id' => $producto_id]);

            // 4. Estampar la fila histórica en la tabla de Kardex
            $sqlKardex = "INSERT INTO kardex (producto_id, tipo_movimiento, cantidad, stock_anterior, stock_posterior, motivo, referencia_id, usuario_id) 
                          VALUES (:producto_id, :tipo, :cantidad, :anterior, :posterior, :motivo, :ref_id, :user_id)";
            $stmtKardex = $this->db->prepare($sqlKardex);
            $stmtKardex->execute([
                'producto_id' => $producto_id,
                'tipo'        => $tipo,
                'cantidad'    => $cantidad_float,
                'anterior'    => $stock_anterior,
                'posterior'   => $stock_posterior,
                'motivo'      => $motivo,
                'ref_id'      => $referencia_id,
                'user_id'     => $usuario_id
            ]);

            if (!$transaccion_externa_activa) {
                $this->db->commit();
            }
            return true;
        } catch (Exception $e) {
            if (!$transaccion_externa_activa) {
                $this->db->rollBack();
            } else {
                throw new Exception("Error interno en Kardex: " . $e->getMessage());
            }
            return false;
        }
    }

    // Listar todos los movimientos históricos para la tabla visual del Kardex
    public function obtenerHistorialKardex($limite = 50, $offset = 0) {
        $sql = "SELECT k.*, p.nombre as nombre_producto, p.unidad_medida 
                FROM kardex k 
                INNER JOIN productos p ON k.producto_id = p.id 
                ORDER BY k.id DESC LIMIT :limite OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Contar el total de registros en el Kardex para la botonera de paginación
    public function contarRegistrosKardex() {
        $sql = "SELECT COUNT(*) as total FROM kardex";
        return (int)$this->db->query($sql)->fetchColumn();
    }
}
?>
