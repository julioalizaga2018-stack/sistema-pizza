<?php
// models/Proveedor.php
require_once __DIR__ . '/../config/conexion.php';

class Proveedor extends Conexion {
    
    // 1. Registrar proveedor (solo empresa obligatoria)
    public function registrar($nombre_empresa, $nombre_contacto = null, $telefono = null) {
        $db = $this->conectar();
        $sql = "INSERT INTO proveedores (nombre_empresa, nombre_contacto, telefono) 
                VALUES (:empresa, :contacto, :telefono)";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            ':empresa'  => $nombre_empresa,
            ':contacto' => $nombre_contacto,
            ':telefono' => $telefono
        ]);
    }

    // 2. Listar solo proveedores activos (oculta los borrados lógicamente)
    public function listarActivos() {
        $db = $this->conectar();
        $sql = "SELECT id, nombre_empresa, nombre_contacto, telefono, fecha_registro 
                FROM proveedores 
                WHERE estado = 'activo' 
                ORDER BY nombre_empresa ASC";
        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    }

    // 3. ACTUALIZAR: Esta es la función que faltaba y causaba el Fatal Error
    public function actualizar($id, $nombre_empresa, $nombre_contacto, $telefono, $estado = 'activo') {
        $db = $this->conectar();
        $sql = "UPDATE proveedores 
                SET nombre_empresa = :empresa, 
                    nombre_contacto = :contacto, 
                    telefono = :telefono, 
                    estado = :estado 
                WHERE id = :id";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            ':id'       => $id,
            ':empresa'  => $nombre_empresa,
            ':contacto' => $nombre_contacto,
            ':telefono' => $telefono,
            ':estado'   => $estado
        ]);
    }

    // 4. Borrado Lógico: Cambia estado a inactivo y estampa la fecha de borrado
    public function eliminarLogico($id) {
        $db = $this->conectar();
        $sql = "UPDATE proveedores 
                SET estado = 'inactivo', 
                    fecha_borrado = CURRENT_TIMESTAMP 
                WHERE id = :id";
        $stmt = $db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
        // 5. Contar el total de proveedores activos (con soporte para el buscador)
    public function contarProveedoresFiltrados($buscar = '') {
        $db = $this->conectar();
        $sql = "SELECT COUNT(*) as total FROM proveedores WHERE estado = 'activo'";
        
        if (!empty($buscar)) {
            $sql .= " AND (nombre_empresa LIKE :buscar OR nombre_contacto LIKE :buscar)";
            $stmt = $db->prepare($sql);
            $stmt->execute([':buscar' => "%$buscar%"]);
        } else {
            $stmt = $db->query($sql);
        }
        
        $fila = $stmt->fetch();
        return (int)$fila['total'];
    }

    // 6. Listar proveedores activos de forma paginada y optimizada para el rendimiento
    public function listarProveedoresPaginados($buscar = '', $limite = 10, $offset = 0) {
        $db = $this->conectar();
        $sql = "SELECT id, nombre_empresa, nombre_contacto, telefono, fecha_registro 
                FROM proveedores 
                WHERE estado = 'activo'";
                
        if (!empty($buscar)) {
            $sql .= " AND (nombre_empresa LIKE :buscar OR nombre_contacto LIKE :buscar)";
        }
        
        $sql .= " ORDER BY nombre_empresa ASC LIMIT :limite OFFSET :offset";
        
        $stmt = $db->prepare($sql);
        
        // Es vital forzar el tipo de dato entero para LIMIT y OFFSET en PDO
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        if (!empty($buscar)) {
            $stmt->bindValue(':buscar', "%$buscar%", PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

}
?>
