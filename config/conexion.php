<?php
// config/conexion.php
class Conexion {
    private $host = "localhost";
    private $db   = "pizza_db";
    private $user = "root";
    private $pass = ""; // Cambia según tus credenciales de MySQL
    private $charset = "utf8mb4";
    protected $pdo;

   public function conectar() {
    if ($this->pdo !== null) {
        return $this->pdo;
    }

    try {
        $dsn = "mysql:host={$this->host};dbname={$this->db};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // 🌟 INYECCIÓN QUIRÚRGICA: Obliga a MySQL en Hostinger a usar la hora de Nicaragua
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-06:00'" 
        ];
        
        $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
        return $this->pdo;
    } catch (PDOException $e) {
        throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
    }
}
}
?>
