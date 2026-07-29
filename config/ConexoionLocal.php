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
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
            return $this->pdo;
        } catch (PDOException $e) {
            die("Error crítico de conexión: " . $e->getMessage());
        }
    }
}
?>
