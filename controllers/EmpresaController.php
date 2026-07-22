<?php
// controllers/EmpresaController.php
require_once __DIR__ . '/../models/EmpresaModelo.php';

class EmpresaController {
    private $modelo;

    public function __construct() {
        $this->modelo = new EmpresaModelo();
    }

    public function cargarDatos() {
        return $this->modelo->obtenerDatos();
    }

    public function procesarPeticiones() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accion = $_POST['accion'] ?? '';

            if ($accion === 'guardar_empresa') {
                $nombre    = trim($_POST['nombre']);
                $telefono  = trim($_POST['telefono']);
                $direccion = trim($_POST['direccion']);
                $nombre_logo = null;

                if (empty($nombre) || empty($telefono) || empty($direccion)) {
                    return ['status' => 'error', 'msg' => 'Todos los campos de texto son obligatorios.'];
                }

                // 📁 PROCESAMIENTO TÁCTIL Y SEGURO DEL LOGOTIPO
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['logo']['tmp_name'];
                    $fileName    = $_FILES['logo']['name'];
                    $fileSize    = $_FILES['logo']['size'];
                    
                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $extensionesPermitidas = ['jpg', 'jpeg', 'png'];

                    // Validación 1: Formatos de imagen estándar
                    if (!in_array($fileExtension, $extensionesPermitidas)) {
                        return ['status' => 'error', 'msg' => 'Formato no permitido. Solo JPG, JPEG o PNG.'];
                    }

                    // Validación 2: Tamaño máximo de 2MB para no saturar facturas
                    if ($fileSize > 2097152) {
                        return ['status' => 'error', 'msg' => 'El logo es muy pesado. Máximo 2MB.'];
                    }

                    // Creamos la carpeta de subidas si no existe en tu estructura public
                    $uploadFileDir = __DIR__ . '/../public/uploads/';
                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }

                    // Renombramos el archivo de forma única para evitar problemas de caché en Hostinger
                    $nombre_logo = 'logo_jungle_' . time() . '.' . $fileExtension;
                    $dest_path = $uploadFileDir . $nombre_logo;

                    // Movemos el archivo temporal al destino real
                    if (!move_uploaded_file($fileTmpPath, $dest_path)) {
                        return ['status' => 'error', 'msg' => 'Error al guardar la imagen en el servidor.'];
                    }
                }

                // Guardamos en la base de datos
                if ($this->modelo->actualizarEmpresa($nombre, $telefono, $direccion, $nombre_logo)) {
                    return ['status' => 'success', 'msg' => 'Configuración de la empresa guardada con éxito.'];
                }
                return ['status' => 'error', 'msg' => 'No se realizaron cambios o hubo un error.'];
            }
        }
        return null;
    }
}

// Disparador del Controlador para procesar el envío por POST de forma aislada
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new EmpresaController();
    $resultado = $controller->procesarPeticiones();

    if ($resultado) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $url_base = ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/";
        
        $tipo = ($resultado['status'] === 'success') ? 'success' : 'error';
        header("Location: " . $url_base . "index.php?v=config_empresa&" . $tipo . "=" . urlencode($resultado['msg']));
        exit;
    }
}
?>
