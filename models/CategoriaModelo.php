<?php
// models/CategoriaModelo.php
require_once __DIR__ . '/../config/conexion.php';

class CategoriaModelo extends Conexion {
    private $db;

    public function __construct() {
        $this->db = $this->conectar();
    }

    // LISTAR TODO: Para los combos select sin paginar
    public function listarCategoriasTodas() {
        $sql = "SELECT * FROM categorias WHERE deleted_at IS NULL ORDER BY nombre ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    // CONTAR FILTRADOS: Para la matemática del paginador
    public function contarCategoriasFiltradas($buscar = '') {
        $sql = "SELECT COUNT(*) as total FROM categorias WHERE deleted_at IS NULL";
        $params = [];

        if (!empty($buscar)) {
            $sql .= " AND nombre LIKE :buscar";
            $params['buscar'] = '%' . $buscar . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $resultado = $stmt->fetch();
        return (int)$resultado['total'];
    }

    // LISTAR PAGINADO: Con buscador elástico y límites estrictos de rendimiento
    public function listarCategoriasPaginadas($buscar = '', $limite = 10, $offset = 0) {
        $sql = "SELECT * FROM categorias WHERE deleted_at IS NULL";
        $params = [];

        if (!empty($buscar)) {
            $sql .= " AND nombre LIKE :buscar";
            $params['buscar'] = '%' . $buscar . '%';
        }

        $sql .= " ORDER BY id DESC LIMIT :limite OFFSET :offset";
        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    // BUSCAR POR ID: Para cargar en el formulario al momento de editar
    public function obtenerCategoriaPorId($id) {
        $sql = "SELECT * FROM categorias WHERE id = :id AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    // CREAR: Inserta un nuevo registro
    public function registrarCategoria($nombre, $descripcion) {
        $sql = "INSERT INTO categorias (nombre, descripcion) VALUES (:nombre, :descripcion)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'nombre'      => $nombre,
            'descripcion' => $descripcion
        ]);
    }

    // ACTUALIZAR: Modifica los textos de una categoría existente
    public function actualizarCategoria($id, $nombre, $descripcion) {
        $sql = "UPDATE categorias SET nombre = :nombre, descripcion = :descripcion WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'nombre'      => $nombre,
            'descripcion' => $descripcion,
            'id'          => $id
        ]);
    }

    // BORRADO LÓGICO: Registra la fecha de baja para proteger el historial sin romper llaves foráneas
    public function eliminarCategoriaLogico($id) {
        $sql = "UPDATE categorias SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
}
?>
