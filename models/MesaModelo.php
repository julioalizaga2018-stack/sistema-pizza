<?php
// models/MesaModelo.php
require_once __DIR__ . '/../config/conexion.php';

class MesaModelo extends Conexion {
    private $db;

    public function __construct() {
        $this->db = $this->conectar();
    }

    // LISTAR MESAS: Obtiene todas las mesas activas para pintarlas en el mapa táctil
    public function listarMesasOperativas() {
        $sql = "SELECT m.*, a.nombre as nombre_area 
                FROM mesas m
                INNER JOIN areas a ON m.area_id = a.id
                WHERE m.deleted_at IS NULL AND a.deleted_at IS NULL
                ORDER BY a.nombre ASC, m.numero_mesa ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

       // 🌟 EL BOTÓN DE LIBERACIÓN: Limpia mesas físicas o cancela Deliverys/Retiros huérfanos
    public function liberarMesaForzado($id_referencia) {
        try {
            $this->db->beginTransaction();

            // 1. REGLA OPERATIVA A: Si es una Mesa Física (Buscamos si el ID existe en la tabla mesas)
            $sqlCheckMesa = "SELECT id FROM mesas WHERE id = :id LIMIT 1";
            $stmtCheck = $this->db->prepare($sqlCheckMesa);
            $stmtCheck->execute(['id' => $id_referencia]);
            $es_mesa_fisica = $stmtCheck->fetch();

            if ($es_mesa_fisica) {
                // Si el ID pertenece a una mesa, la regresamos a color VERDE disponible
                $sqlMesa = "UPDATE mesas SET estado = 'disponible' WHERE id = :id";
                $stmtMesa = $this->db->prepare($sqlMesa);
                $stmtMesa->execute(['id' => $id_referencia]);

                // Cancelamos los pedidos pendientes amarrados estrictamente a esa mesa física
                $sqlPedido = "UPDATE pedidos SET estado = 'cancelado' 
                              WHERE mesa_id = :mesa_id AND estado IN ('pendiente', 'cocina')";
                $stmtPedido = $this->db->prepare($sqlPedido);
                $stmtPedido->execute(['mesa_id' => $id_referencia]);
            } else {
                // 🌟 REGLA OPERATIVA B: Si el ID NO es una mesa, significa que es el ID directo del Pedido de Delivery/Retiro
                $sqlPedidoDirecto = "UPDATE pedidos SET estado = 'cancelado' 
                                     WHERE id = :pedido_id AND estado = 'pendiente'";
                $stmtPedidoDir = $this->db->prepare($sqlPedidoDirecto);
                $stmtPedidoDir->execute(['pedido_id' => $id_referencia]);
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
