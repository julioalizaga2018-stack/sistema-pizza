<?php
// models/EmpresaModelo.php
require_once __DIR__ . '/../config/conexion.php';

class EmpresaModelo extends Conexion {
    private $db;

    public function __construct() {
        $this->db = $this->conectar();
    }

    // Obtiene los datos únicos de la pizzería (Siempre ID 1)
    public function obtenerDatos() {
        $sql = "SELECT * FROM empresa WHERE id = 1 AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }

    // Actualiza los campos de texto y conserva o cambia el nombre del logo
    public function actualizarEmpresa($nombre, $telefono, $direccion, $logo = null) {
        if ($logo !== null) {
            $sql = "UPDATE empresa SET nombre = :nombre, telefono = :telefono, direccion = :direccion, logo = :logo WHERE id = 1";
            $params = ['nombre' => $nombre, 'telefono' => $telefono, 'direccion' => $direccion, 'logo' => $logo];
        } else {
            $sql = "UPDATE empresa SET nombre = :nombre, telefono = :telefono, direccion = :direccion WHERE id = 1";
            $params = ['nombre' => $nombre, 'telefono' => $telefono, 'direccion' => $direccion];
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}
?>
