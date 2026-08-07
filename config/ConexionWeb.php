<?php
// config/conexionWeb.php
date_default_timezone_set('America/Managua');

class Conexion {
    private $host = "localhost";
    private $db   = 'u914600357_junglep';
    private $user = 'u914600357_usuario';
    private $pass = 'junglepizzA2026**@'; // Cambia según tus credenciales de MySQL
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
            $this->pdo->exec("SET time_zone = '-06:00'");
            return $this->pdo;
        } catch (PDOException $e) {
            die("Error crítico de conexión: " . $e->getMessage());
        }
    }
}
?>
