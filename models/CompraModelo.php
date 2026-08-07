<?php
// models/CompraModelo.php
require_once __DIR__ . '/../config/conexion.php';

class CompraModelo extends Conexion {
    private $db;

    public function __construct() {
        $this->db = $this->conectar();
    }

    // 1. LISTAR COMPRAS: Obtiene el historial de facturas con tu columna real 'nombre_empresa'
    public function listarComprasPaginado($limite = 10, $offset = 0) {
        $sql = "SELECT c.*, p.nombre_empresa as proveedor_nombre, u.nombre as usuario_nombre 
                FROM compras c
                INNER JOIN proveedores p ON c.proveedor_id = p.id
                INNER JOIN usuarios u ON c.usuario_id = u.id
                ORDER BY c.id DESC LIMIT :limite OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // 2. CONTADOR: Cuenta las compras totales para la paginación de 10 en 10
    public function contarTotalCompras() {
        $sql = "SELECT COUNT(*) as total FROM compras";
        return (int)$this->db->query($sql)->fetchColumn();
    }

    // 3. FILTRO ESTRICTO PROVEEDOR: Extrae insumos con su proveedor_id vinculante para el filtro reactivo
    public function listarProductosInventariables() {
        $sql = "SELECT id, categoria_id, proveedor_id, nombre, unidad_medida, stock_actual, precio_costo as precio_compra, precio_base as precio_venta 
                FROM productos 
                WHERE maneja_stock = 1 AND deleted_at IS NULL
                ORDER BY nombre ASC";
        return $this->db->query($sql)->fetchAll();
    }

    // 4. TRANSACCIÓN MAESTRA: Sincroniza stock y actualiza precios compartiendo la conexión PDO
    public function registrarCompraTransaccional($proveedor_id, $usuario_id, $numero_factura, $fecha_compra, $total, $observaciones, $insumos) {
        try {
            $this->db->beginTransaction();

            // A. Insertar cabecera de la compra
            $sqlC = "INSERT INTO compras (proveedor_id, usuario_id, numero_factura, fecha_compra, total, observaciones) 
                     VALUES (:prov_id, :user_id, :num_fac, :fecha, :total, :obs)";
            $stmtC = $this->db->prepare($sqlC);
            $stmtC->execute([
                'prov_id' => $proveedor_id,
                'user_id' => $usuario_id,
                'num_fac' => $numero_factura,
                'fecha'   => $fecha_compra,
                'total'   => $total,
                'obs'     => $observaciones
            ]);
            $compra_id = $this->db->lastInsertId();

            // B. Instanciamos el Kardex e inyectamos la conexión compartida activa
            require_once __DIR__ . '/InventarioModelo.php';
            $inventarioModel = new InventarioModelo();
            $inventarioModel->establecerConexionCompartida($this->db); // 🔥 CRÍTICO: Elimina el Deadlock

            // C. Insertar detalles y actualizar catálogos con tus columnas reales
            $sqlD = "INSERT INTO compra_detalles (compra_id, producto_id, cantidad, precio_unitario, subtotal) 
                     VALUES (:compra_id, :prod_id, :cant, :precio, :sub)";
            $stmtD = $this->db->prepare($sqlD);

            $sqlUpPrecios = "UPDATE productos SET precio_costo = :p_costo, precio_base = :p_base WHERE id = :id";
            $stmtUpPrecios = $this->db->prepare($sqlUpPrecios);

            foreach ($insumos as $insumo) {
                $producto_id  = (int)$insumo['producto_id'];
                $cantidad     = floatval($insumo['cantidad']);
                $precio_costo = floatval($insumo['precio_unitario']);
                $precio_base  = floatval($insumo['precio_venta']); 
                $subtotal     = $cantidad * $precio_costo;

                if ($cantidad <= 0) continue;

                // 1. Guardar renglón en la tabla compra_detalles
                $stmtD->execute([
                    'compra_id' => $compra_id,
                    'prod_id'   => $producto_id,
                    'cant'      => $cantidad,
                    'precio'    => $precio_costo,
                    'sub'       => $subtotal
                ]);

                // 2. ACTUALIZACIÓN EN CALIENTE: Sincroniza costos y precios de menú reales
                $stmtUpPrecios->execute([
                    'p_costo' => $precio_costo,
                    'p_base'  => $precio_base,
                    'id'      => $producto_id
                ]);

                // 3. Incrementar stock en productos y estampar bitácora en la tabla kardex
                $inventarioModel->registrarMovimiento(
                    $producto_id,
                    'compra_proveedor',
                    $cantidad,
                    "Abastecimiento y Ajuste Precios - Fac. Compra #{$numero_factura}",
                    $compra_id,
                    $usuario_id
                );
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
        // 5. ANALÍTICA MENSUAL: Agrupa y totaliza las ventas entregadas por Año y Mes
    public function obtenerVentasMensualesHistorial($anio) {
        $sql = "SELECT 
                    MONTH(CONVERT_TZ(created_at, '+00:00', '-06:00')) as mes_numero,
                    SUM(total) as total_ventas_mes,
                    COUNT(id) as transacciones_ventas
                FROM pedidos 
                WHERE estado = 'entregado' AND YEAR(CONVERT_TZ(created_at, '+00:00', '-06:00')) = :anio
                GROUP BY MONTH(CONVERT_TZ(created_at, '+00:00', '-06:00'))
                ORDER BY mes_numero DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['anio' => (int)$anio]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 6. ANALÍTICA MENSUAL: Agrupa y totaliza las compras a proveedores por Año y Mes
    public function obtenerComprasMensualesHistorial($anio) {
        $sql = "SELECT 
                    MONTH(fecha_compra) as mes_numero,
                    SUM(total) as total_compras_mes,
                    COUNT(id) as transacciones_compras
                FROM compras 
                WHERE YEAR(fecha_compra) = :anio
                GROUP BY MONTH(fecha_compra)
                ORDER BY mes_numero DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['anio' => (int)$anio]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
?>
