<?php
// controllers/ProductoController.php
require_once __DIR__ . '/../models/ProductoModelo.php';

class ProductoController {
    private $modelo;

    public function __construct() {
        $this->modelo = new ProductoModelo();
    }

    // Provee el listado a la vista de mantenimiento
    public function listar() {
        return $this->modelo->listarProductos();
    }
    // 🚀 NUEVA FUNCIÓN EXCLUSIVA PARA EL PUNTO DE VENTA (POS)
    public function listarParaMesero() {
        // Llama directamente al método filtrado que creamos en tu ProductoModelo
        return $this->modelo->listarProductosVenta(); 
    }

    // Actualiza este método dentro de tu ProductoController.php
    public function obtenerCategorias() {
        require_once __DIR__ . '/CategoriaController.php';
        $categoriaCtrl = new CategoriaController();
        return $categoriaCtrl->obtenerCategorias();
    }
        // 🌟 NUEVO MÉTODO EXCLUSIVO PARA EL POS: Carga el menú de categorías filtrado desde el modelo
    public function obtenerCategoriasPedido() {
        // Invoca de forma directa a la función filtrada de tu ProductoModelo que oculta "M. Prima"
        return $this->modelo->listarCategoriasPedido();
    }


    // 🚀 NUEVO MÉTODO OPERATIVO: Expone la lista de proveedores activos a la vista del formulario
    public function obtenerProveedores() {
        return $this->modelo->listarProveedoresActivos();
    }

    public function procesarPeticiones() {
        // Interceptamos bajas lógicas mediante método GET seguro
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'eliminar_producto') {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            
            // Filtro de seguridad jerárquico básico
            $rolSesion = isset($_SESSION['rol_id']) ? (int)$_SESSION['rol_id'] : 0;
            if ($rolSesion !== 1 && $rolSesion !== 2) {
                return ['status' => 'error', 'msg' => 'Acción no autorizada.', 'origen' => 'productos'];
            }

            $id = intval($_GET['del_id'] ?? 0);
            if ($id > 0) {
                if ($this->modelo->eliminarProductoLogico($id)) {
                    return ['status' => 'success', 'msg' => 'Producto eliminado del catálogo.', 'origen' => 'productos'];
                }
            }
            return ['status' => 'error', 'msg' => 'No se pudo eliminar el producto.', 'origen' => 'productos'];
        }

        // Interceptamos formularios de inserción y modificación por método POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accion = $_POST['accion'] ?? '';
            
            if ($accion === 'crear_producto' || $accion === 'editar_producto') {
                $id           = intval($_POST['id'] ?? 0);
                $categoria_id = intval($_POST['categoria_id'] ?? 0);
                
                // 🌟 INYECCIÓN QUIRÚRGICA: Captura segura de la llave foránea del distribuidor
                $proveedor_id = intval($_POST['proveedor_id'] ?? 0);
                
                // CORRECCIÓN CRÍTICA: Capturamos el ENUM del área de producción de la vista
                $area_produccion = trim($_POST['area_produccion'] ?? 'cocina');
                $nombre          = trim($_POST['nombre'] ?? '');
                $descripcion     = trim($_POST['descripcion'] ?? '');
                $precio_costo    = floatval($_POST['precio_costo'] ?? 0);
                $precio_base     = floatval($_POST['precio_base'] ?? 0); // Precio de Venta
                $unidad_medida   = trim($_POST['unidad_medida'] ?? 'Unidad');
                
                // Captura de flags lógicos e inventario (valores binarios 1 o 0)
                $maneja_stock = isset($_POST['maneja_stock']) ? 1 : 0;
                $stock_minimo = $maneja_stock ? floatval($_POST['stock_minimo'] ?? 0) : 0.00;
                $es_extra     = isset($_POST['es_extra']) ? 1 : 0;
                $es_sabor_pizza = isset($_POST['es_sabor_pizza']) ? 1 : 0;
                $nombre_imagen  = null;

                // 🔒 PROTECCIÓN INDUSTRIAL: Lógica condicional para proteger las existencias en base de datos
                if ($accion === 'editar_producto' && $id > 0) {
                    // Si estamos editando, consultamos el producto actual para recuperar sus existencias reales
                    $productoActual = $this->modelo->obtenerProductoPorId($id);
                    $stock_actual = $productoActual ? floatval($productoActual['stock_actual']) : 0.00;
                } else {
                    // Si es un producto nuevo, sí capturamos el inventario inicial del formulario
                    $stock_actual = $maneja_stock ? floatval($_POST['stock_actual'] ?? 0) : 0.00;
                }

                // PROCESAMIENTO SEGURO Y AUTOMÁTICO DE LA FOTOGRAFÍA (Tu código original intacto)
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath   = $_FILES['imagen']['tmp_name'];
                    $fileName      = $_FILES['imagen']['name'];
                    $fileSize      = $_FILES['imagen']['size'];
                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $extensionesPermitidas = ['jpg', 'jpeg', 'png'];

                    if (!in_array($fileExtension, $extensionesPermitidas)) {
                        return ['status' => 'error', 'msg' => 'Formato de imagen inválido. Solo JPG o PNG.', 'origen' => 'productos'];
                    }
                    if ($fileSize > 2097152) { // 2MB máximo
                        return ['status' => 'error', 'msg' => 'La imagen de la pizza supera el límite de 2MB.', 'origen' => 'productos'];
                    }

                    // Creación automatizada en cascada de la carpeta productos
                    $uploadFileDir = __DIR__ . '/../public/uploads/productos/';
                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }

                    // Renombrado único temporal contra problemas de caché
                    $nombre_imagen = 'prod_' . time() . '.' . $fileExtension;
                    $dest_path     = $uploadFileDir . $nombre_imagen;

                    if (!move_uploaded_file($fileTmpPath, $dest_path)) {
                        return ['status' => 'error', 'msg' => 'Error al mover la fotografía al servidor.', 'origen' => 'productos'];
                    }
                }

                // Enrutamiento final original con inyección perfecta de parámetros en el modelo modificado
                if ($accion === 'crear_producto') {
                    // 🌟 MODIFICADO: $proveedor_id se inyecta estrictamente en la segunda posición
                    if ($this->modelo->registrarProducto($categoria_id, $proveedor_id, $nombre, $area_produccion, $descripcion, $precio_costo, $precio_base, $unidad_medida, $maneja_stock, $stock_actual, $stock_minimo, $es_extra, $es_sabor_pizza, $nombre_imagen)) {
                        return ['status' => 'success', 'msg' => 'Producto añadido con éxito.', 'origen' => 'productos'];
                    }
                } else if ($accion === 'editar_producto' && $id > 0) {
                    // 🌟 MODIFICADO: $proveedor_id se inyecta estrictamente en la tercera posición
                    if ($this->modelo->actualizarProducto($id, $categoria_id, $proveedor_id, $nombre, $area_produccion, $descripcion, $precio_costo, $precio_base, $unidad_medida, $maneja_stock, $stock_actual, $stock_minimo, $es_extra, $es_sabor_pizza, $nombre_imagen)) {
                        return ['status' => 'success', 'msg' => 'Producto actualizado con éxito.', 'origen' => 'productos'];
                    }
                }
                return ['status' => 'error', 'msg' => 'No se pudieron salvar los cambios en el menú.', 'origen' => 'productos'];
            }
        }
        return null;
    }
}

// --- 🚀🚀 DISPARADOR INTEGRADO DE RUTAS DE MANTENIMIENTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' || ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']))) {
    $controller = new ProductoController();
    $resultado  = $controller->procesarPeticiones();

    if ($resultado) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host     = $_SERVER['HTTP_HOST'];
        $url_base = ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/";
        $tipo     = ($resultado['status'] === 'success') ? 'success' : 'error';

        // Redirección segura unificada hacia tu variable oficial del enrutador central
        if ($resultado['origen'] === 'productos') {
            header("Location: " . $url_base . "index.php?v=mantenimiento_productos&" . $tipo . "=" . urlencode($resultado['msg']));
        } else {
            header("Location: " . $url_base . "index.php?v=login&" . $tipo . "=" . urlencode($resultado['msg']));
        }
        exit;
    }
}
?>
