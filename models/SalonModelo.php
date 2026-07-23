<?php
// models/SalonModelo.php
require_once __DIR__ . '/../config/conexion.php';

class SalonModelo extends Conexion {
    private $db;

    public function __construct() {
        $this->db = $this->conectar();
    }

    // ============================================================================
    // METODOS PARA AREAS
    // ============================================================================
    public function listarAreasTodas() {
        $sql = "SELECT * FROM areas WHERE deleted_at IS NULL ORDER BY nombre ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function registrarArea($nombre, $descripcion) {
        $sql = "INSERT INTO areas (nombre, descripcion) VALUES (:nombre, :descripcion)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['nombre' => $nombre, 'descripcion' => $descripcion]);
    }

    public function eliminarAreaLogico($id) {
        $sql = "UPDATE areas SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    // ============================================================================
    // METODOS PARA MESAS
    // ============================================================================
    public function listarMesasConAreas() {
        $sql = "SELECT m.*, a.nombre as nombre_area 
                FROM mesas m 
                INNER JOIN areas a ON m.area_id = a.id 
                WHERE m.deleted_at IS NULL AND a.deleted_at IS NULL
                ORDER BY a.nombre ASC, m.numero_mesa ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function registrarMesa($area_id, $numero_mesa, $capacidad) {
        // Validación contra duplicados de mesa en una misma área
        $sqlCheck = "SELECT COUNT(*) as total FROM mesas WHERE area_id = :area_id AND numero_mesa = :numero_mesa AND deleted_at IS NULL";
        $stmtCheck = $this->db->prepare($sqlCheck);
        $stmtCheck->execute(['area_id' => $area_id, 'numero_mesa' => $numero_mesa]);
        if ((int)$stmtCheck->fetch()['total'] > 0) return false;

        $sql = "INSERT INTO mesas (area_id, numero_mesa, capacity) VALUES (:area_id, :numero_mesa, :capacidad)";
        // Nota: Asegúrate si tu script SQL se creó con la columna capacity o capacidad
        $sql = "INSERT INTO mesas (area_id, numero_mesa, capacidad) VALUES (:area_id, :numero_mesa, :capacidad)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['area_id' => $area_id, 'numero_mesa' => $numero_mesa, 'capacidad' => $capacidad]);
    }

    public function eliminarMesaLogico($id) {
        $sql = "UPDATE mesas SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
}
?>
