<?php
// models/RecetaModelo.php
require_once __DIR__ . '/../config/conexion.php';

class RecetaModelo extends Conexion {
    private $db;

    public function __construct() {
        $this->db = $this->conectar();
    }

    // 1. LISTAR PLATOS FINALES: Obtiene solo los productos que NO manejan stock (Menú del Cliente)
    public function listarPlatosMenu() {
        $sql = "SELECT id, nombre, unidad_medida FROM productos 
                WHERE maneja_stock = 0 AND deleted_at IS NULL ORDER BY nombre ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

   // 2. LISTAR INSUMOS BODEGA CORREGIDO: Fuerza la inclusión del Borde de Queso (ID 22)
    public function listarMateriasPrimas() {
        $sql = "SELECT id, nombre, unidad_medida FROM productos 
                WHERE (
                        (maneja_stock = 1 AND area_produccion = 'bodega') 
                        OR id = 22
                      )
                  AND deleted_at IS NULL 
                ORDER BY nombre ASC";
                
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

       // 3. READ: Carga los ingredientes actuales asignados a un plato específico para el visor web
    public function obtenerIngredientesDePlato($producto_final_id) {
        $sql = "SELECT r.*, p.nombre as insumo_nombre, p.unidad_medida 
                FROM recetas r
                INNER JOIN productos p ON r.insumo_materia_prima_id = p.id
                WHERE r.producto_final_id = :id 
                ORDER BY p.nombre ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => (int)$producto_final_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // 4. CREATE / UPDATE TRANSACCIONAL: Limpia la receta previa y estampa la nueva fórmula unificada
    public function guardarFormulaReceta($producto_final_id, $ingredientes) {
        try {
            $this->db->beginTransaction();

            // A. Removemos cualquier ingrediente previo asignado a este plato para evitar duplicados
            $sqlDel = "DELETE FROM recetas WHERE producto_final_id = :id";
            $stmtDel = $this->db->prepare($sqlDel);
            $stmtDel->execute(['id' => $producto_final_id]);

            // B. Si la nueva lista no está vacía, inyectamos los renglones en masa
            if (!empty($ingredientes)) {
                $sqlIns = "INSERT INTO recetas (producto_final_id, insumo_materia_prima_id, cantidad_porcion) 
                           VALUES (:final_id, :insumo_id, :cantidad)";
                $stmtIns = $this->db->prepare($sqlIns);

                foreach ($ingredientes as $ing) {
                    $insumo_id = (int)$ing['insumo_materia_prima_id'];
                    $cantidad  = floatval($ing['cantidad_porcion']);

                    if ($insumo_id <= 0 || $cantidad <= 0) continue;

                    $stmtIns->execute([
                        'final_id'  => $producto_final_id,
                        'insumo_id' => $insumo_id,
                        'cantidad'  => $cantidad
                    ]);
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
}
?>
