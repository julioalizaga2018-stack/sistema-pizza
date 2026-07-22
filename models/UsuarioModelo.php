<?php
// models/UsuarioModelo.php
require_once __DIR__ . '/../config/conexion.php';

class UsuarioModelo extends Conexion {
    private $db;

    public function __construct() {
        $this->db = $this->conectar();
    }

    // Cuenta cuántos usuarios hay en total para saber si el sistema es nuevo
    public function contarUsuarios() {
        $sql = "SELECT COUNT(*) as total FROM usuarios WHERE deleted_at IS NULL";
        $stmt = $this->db->query($sql);
        $resultado = $stmt->fetch();
        return (int)$resultado['total'];
    }

    // Busca un usuario por su nombre único para el inicio de sesión
    public function buscarPorUsuario($usuario) {
        $sql = "SELECT * FROM usuarios WHERE usuario = :usuario AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['usuario' => $usuario]);
        return $stmt->fetch();
    }

    // ACCIÓN 1: Registra al primerísimo usuario del sistema como Superadmin (rol_id = 1)
    public function registrarPrimerAdmin($nombre, $apellido, $usuario, $password) {
        $sql = "INSERT INTO usuarios (rol_id, nombre, apellido, usuario, password) 
                VALUES (1, :nombre, :apellido, :usuario, :password)";
        
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'nombre'   => $nombre,
            'apellido' => $apellido,
            'usuario'  => $usuario,
            'password' => $passwordHash
        ]);
    }

    // ==========================================
    // NUEVO MANTENIMIENTO DE USUARIOS (CRUD)
    // ==========================================

    // NUEVO: Obtiene los roles operativos activos para el combo select (Excluye Superadmin ID 1)
    public function obtenerRolesOperativos() {
        $sql = "SELECT id, nombre, descripcion FROM roles WHERE id <> 1 AND deleted_at IS NULL ORDER BY nombre ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    // MODIFICADO: Agrega un INNER JOIN para traer el nombre del rol visible en la lista de usuarios
    public function listarUsuarios() {
        $sql = "SELECT u.id, u.nombre, u.apellido, u.usuario, u.rol_id, r.nombre as nombre_rol 
                FROM usuarios u 
                INNER JOIN roles r ON u.rol_id = r.id 
                WHERE u.rol_id <> 1 AND u.deleted_at IS NULL 
                ORDER BY u.id DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    // BUSCAR POR ID: Obtiene un usuario para editarlo, protegiendo que no sea rol_id = 1
    public function obtenerUsuarioPorId($id) {
        $sql = "SELECT id, nombre, apellido, usuario, rol_id FROM usuarios WHERE id = :id AND rol_id <> 1 AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    // MODIFICADO: Ahora recibe dinámicamente el $rol_id del combo select en lugar de clavar el ID 2
    public function registrarUsuarioComun($rol_id, $nombre, $apellido, $usuario, $password) {
        $sql = "INSERT INTO usuarios (rol_id, nombre, apellido, usuario, password) 
                VALUES (:rol_id, :nombre, :apellido, :usuario, :password)";
        
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'rol_id'   => $rol_id,
            'nombre'   => $nombre,
            'apellido' => $apellido,
            'usuario'  => $usuario,
            'password' => $passwordHash
        ]);
    }

    // MODIFICADO: Incluye la actualización del rol_id dinámico elegido en el formulario de edición
    public function actualizarUsuario($id, $rol_id, $nombre, $apellido, $usuario, $password = null) {
        // Validación de seguridad para que nunca afecte al rol protegido o inexistente
        $usuarioActual = $this->obtenerUsuarioPorId($id);
        if (!$usuarioActual) return false;

        // Regla de control: Evita que por manipulación forzada de HTML asignen el rol Superadmin (1)
        if ((int)$rol_id === 1) return false;

        if (!empty($password)) {
            $sql = "UPDATE usuarios SET rol_id = :rol_id, nombre = :nombre, apellido = :apellido, usuario = :usuario, password = :password WHERE id = :id";
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $params = ['rol_id' => $rol_id, 'nombre' => $nombre, 'apellido' => $apellido, 'usuario' => $usuario, 'password' => $passwordHash, 'id' => $id];
        } else {
            $sql = "UPDATE usuarios SET rol_id = :rol_id, nombre = :nombre, apellido = :apellido, usuario = :usuario WHERE id = :id";
            $params = ['rol_id' => $rol_id, 'nombre' => $nombre, 'apellido' => $apellido, 'usuario' => $usuario, 'id' => $id];
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
        // BORRADO LÓGICO: Registra la fecha actual de baja omitiendo al superadmin por seguridad
    public function eliminarUsuarioLogico($id) {
        // Regla estricta: No permitir borrar cuentas raíz mediante manipulación del ID
        $usuarioActual = $this->obtenerUsuarioPorId($id);
        if (!$usuarioActual) return false;

        $sql = "UPDATE usuarios SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id AND rol_id <> 1";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

}
?>
