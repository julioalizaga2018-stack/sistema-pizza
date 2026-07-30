<?php
// controllers/RecetaController.php
require_once __DIR__ . '/../models/RecetaModelo.php';

class RecetaController {
    private $modelo;

    public function __construct() {
        $this->modelo = new RecetaModelo();
    }

    // Retorna la lista de platos que se venden al cliente (maneja_stock = 0)
    public function obtenerPlatosMenu() {
        return $this->modelo->listarPlatosMenu();
    }

    // Retorna la lista de materias primas que se compran al proveedor (maneja_stock = 1)
    public function obtenerMateriasPrimasBodega() {
        return $this->modelo->listarMateriasPrimas();
    }

    // Procesa el guardado masivo de la fórmula de un plato
    public function procesarGuardadoReceta() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }

            // 1. Filtro estricto de seguridad para roles administrativos
            $rolSesion = isset($_SESSION['rol_id']) ? (int)$_SESSION['rol_id'] : 0;
            if ($rolSesion !== 1 && $rolSesion !== 2) {
                return ['status' => 'error', 'msg' => 'Acción no autorizada para su nivel de usuario.'];
            }

            $producto_final_id = filter_var($_POST['producto_final_id'] ?? null, FILTER_VALIDATE_INT);
            $insumos_ids        = $_POST['insumo_id'] ?? [];
            $cantidades         = $_POST['cantidad_porcion'] ?? [];

            if (!$producto_final_id || $producto_final_id <= 0) {
                return ['status' => 'error', 'msg' => 'Debe seleccionar un platillo válido del menú para asignarle una receta.'];
            }

            // Estructuramos el desglose limpio para la transacción contable
            $receta_detalle = [];

            foreach ($insumos_ids as $index => $insumo_id) {
                $materia_id = intval($insumo_id);
                $cant_g     = floatval($cantidades[$index] ?? 0);

                // Ignorar renglones vacíos o con porciones en cero
                if ($materia_id <= 0 || $cant_g <= 0) {
                    continue;
                }

                $receta_detalle[] = [
                    'insumo_materia_prima_id' => $materia_id,
                    'cantidad_porcion'        => $cant_g
                ];
            }

            // Ejecución transaccional: Limpia la fórmula vieja y graba la nueva lista
            $resultado = $this->modelo->guardarFormulaReceta($producto_final_id, $receta_detalle);

            if ($resultado) {
                return ['status' => 'success', 'msg' => '¡Fórmula de receta actualizada y guardada correctamente en el sistema!'];
            } else {
                return ['status' => 'error', 'msg' => 'Error crítico al intentar guardar la receta en la base de datos.'];
            }
        }
        return null;
    }
}

// --- DISPARADOR INTEGRADO DE RUTAS DE RECETAS POR POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_formula_receta') {
    $controller = new RecetaController();
    $resultado  = $controller->procesarGuardadoReceta();

    if ($resultado) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host     = $_SERVER['HTTP_HOST'];
        $url_base = ($host === 'localhost') ? $protocol . $host . "/pizzeria/" : $protocol . $host . "/";
        $tipo     = ($resultado['status'] === 'success') ? 'success' : 'error';

        header("Location: " . $url_base . "index.php?v=recetas_lista&" . $tipo . "=" . urlencode($resultado['msg']));
        exit;
    }
}
?>
