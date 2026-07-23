<?php
// models/ProductoModelo.php
require_once __DIR__ . '/../config/conexion.php';

class ProductoModelo extends Conexion {
    private $db;

    public function __construct() {
        $this->db = $this->conectar();
    }

    // LISTAR: Obtiene todos los productos activos con el nombre de su categoría (INNER JOIN)
    public function listarProductos() {
        $sql = "SELECT p.*, c.nombre as nombre_categoria 
                FROM productos p 
                INNER JOIN categorias c ON p.categoria_id = c.id 
                WHERE p.deleted_at IS NULL 
                ORDER BY p.id DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    // LISTAR CATEGORÍAS: Para alimentar el combo select del formulario
    public function listarCategoriasActivas() {
        $sql = "SELECT id, nombre FROM categorias WHERE deleted_at IS NULL ORDER BY nombre ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    // BUSCAR POR ID: Para cargar los datos en el formulario al momento de editar
    public function obtenerProductoPorId($id) {
        $sql = "SELECT * FROM productos WHERE id = :id AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    // CREAR: Inserta un nuevo producto al catálogo maestro con su imagen
    public function registrarProducto($categoria_id, $nombre, $descripcion, $precio_costo, $precio_base, $unidad_medida, $maneja_stock, $stock_actual, $stock_minimo, $es_extra, $es_sabor_pizza, $imagen) {
        $sql = "INSERT INTO productos (categoria_id, nombre, descripcion, precio_costo, precio_base, unidad_medida, maneja_stock, stock_actual, stock_minimo, es_extra, es_sabor_pizza, imagen) 
                VALUES (:categoria_id, :nombre, :descripcion, :precio_costo, :precio_base, :unidad_medida, :maneja_stock, :stock_actual, :stock_minimo, :es_extra, :es_sabor_pizza, :imagen)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'categoria_id'   => $categoria_id,
            'nombre'         => $nombre,
            'descripcion'    => $descripcion,
            'precio_costo'   => $precio_costo,
            'precio_base'    => $precio_base,
            'unidad_medida'  => $unidad_medida,
            'maneja_stock'   => $maneja_stock,
            'stock_actual'   => $stock_actual,
            'stock_minimo'   => $stock_minimo,
            'es_extra'       => $es_extra,
            'es_sabor_pizza' => $es_sabor_pizza,
            'imagen'         => $imagen
        ]);
    }

    // ACTUALIZAR: Modifica los datos. Si la imagen viene vacía, conserva la anterior.
    public function actualizarProducto($id, $categoria_id, $nombre, $descripcion, $precio_costo, $precio_base, $unidad_medida, $maneja_stock, $stock_actual, $stock_minimo, $es_extra, $es_sabor_pizza, $imagen = null) {
        if ($imagen !== null) {
            $sql = "UPDATE productos SET categoria_id = :categoria_id, nombre = :nombre, descripcion = :descripcion, precio_costo = :precio_costo, precio_base = :precio_base, unidad_medida = :unidad_medida, maneja_stock = :maneja_stock, stock_actual = :stock_actual, stock_minimo = :stock_minimo, es_extra = :es_extra, es_sabor_pizza = :es_sabor_pizza, imagen = :imagen WHERE id = :id";
            $params = ['imagen' => $imagen];
        } else {
            $sql = "UPDATE productos SET categoria_id = :categoria_id, nombre = :nombre, descripcion = :descripcion, precio_costo = :precio_costo, precio_base = :precio_base, unidad_medida = :unidad_medida, maneja_stock = :maneja_stock, stock_actual = :stock_actual, stock_minimo = :stock_minimo, es_extra = :es_extra, es_sabor_pizza = :es_sabor_pizza WHERE id = :id";
            $params = [];
        }

        $params = array_merge($params, [
            'categoria_id'   => $categoria_id,
            'nombre'         => $nombre,
            'descripcion'    => $descripcion,
            'precio_costo'   => $precio_costo,
            'precio_base'    => $precio_base,
            'unidad_medida'  => $unidad_medida,
            'maneja_stock'   => $maneja_stock,
            'stock_actual'   => $stock_actual,
            'stock_minimo'   => $stock_minimo,
            'es_extra'       => $es_extra,
            'es_sabor_pizza' => $es_sabor_pizza,
            'id'             => $id
        ]);

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    // BORRADO LÓGICO: Registra la fecha de baja para no romper el historial de comandas
    public function eliminarProductoLogico($id) {
        $sql = "UPDATE productos SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
        // NUEVO: Cuenta el total de registros que cumplen con los filtros (Para calcular el número de páginas)
    public function contarProductosFiltrados($buscar = '', $categoria_id = 0) {
        $sql = "SELECT COUNT(*) as total FROM productos WHERE deleted_at IS NULL";
        $params = [];

        if (!empty($buscar)) {
            $sql .= " AND nombre LIKE :buscar";
            $params['buscar'] = '%' . $buscar . '%';
        }
        if ($categoria_id > 0) {
            $sql .= " AND categoria_id = :categoria_id";
            $params['categoria_id'] = $categoria_id;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $resultado = $stmt->fetch();
        return (int)$resultado['total'];
    }

    // NUEVO: Lista los productos con Filtros combinados y límites estrictos de Paginación (LIMIT / OFFSET)
    public function listarProductosPaginados($buscar = '', $categoria_id = 0, $limite = 10, $offset = 0) {
        $sql = "SELECT p.*, c.nombre as nombre_categoria 
                FROM productos p 
                INNER JOIN categorias c ON p.categoria_id = c.id 
                WHERE p.deleted_at IS NULL";
        $params = [];

        if (!empty($buscar)) {
            $sql .= " AND p.nombre LIKE :buscar";
            $params['buscar'] = '%' . $buscar . '%';
        }
        if ($categoria_id > 0) {
            $sql .= " AND p.categoria_id = :categoria_id";
            $params['categoria_id'] = $categoria_id;
        }

        // Ordenamos por los más recientes y aplicamos los límites de la página actual
        $sql .= " ORDER BY p.id DESC LIMIT :limite OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        
        // PDO requiere enlazar los números de paginación explícitamente como enteros
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

}
?>
