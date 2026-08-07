<?php
// models/CajaModelo.php
require_once __DIR__ . '/../config/conexion.php';

class CajaModelo extends Conexion {
    private $db;

    public function __construct() {
        $this->db = $this->conectar();
    }

    // VERIFICAR: Busca si el cajero posee una caja 'abierta' en este instante
    public function obtenerTurnoActivo($usuario_id) {
        $sql = "SELECT * FROM caja_turnos WHERE usuario_id = :usuario_id AND estado = 'abierta' LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['usuario_id' => $usuario_id]);
        return $stmt->fetch();
    }
    // 🌟 NUEVO MÉTODO GLOBAL: Busca si el local ya tiene un turno abierto, independientemente del usuario
public function obtenerTurnoActivoGeneral() {
    $sql = "SELECT * FROM caja_turnos WHERE estado = 'abierta' LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute();
    return $stmt->fetch(); // Retorna el turno activo de la pizzería si existe
}


    // APERTURA: Inicia el turno con el fondo de caja inyectado en 'monto_inicial'
    public function abrirCaja($usuario_id, $monto_inicial) {
        if ($this->obtenerTurnoActivo($usuario_id)) {
            return false; 
        }
        $now = (new DateTime('now', new DateTimeZone('America/Managua')))->format('Y-m-d H:i:s');
        $sql = "INSERT INTO caja_turnos (usuario_id, monto_inicial, estado, fecha_apertura) VALUES (:usuario_id, :monto, 'abierta', :fecha_apertura)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['usuario_id' => $usuario_id, 'monto' => $monto_inicial, 'fecha_apertura' => $now]);
    }

    // CALCULAR VENTAS DEL TURNO: Suma todos los cobros reales efectuados en este turno
    public function calcularVentasDelTurno($caja_turno_id) {
        $sql = "SELECT 
                    SUM(CASE WHEN metodo_pago = 'efectivo' THEN monto ELSE 0 END) as calculado_efectivo,
                    SUM(CASE WHEN metodo_pago = 'tarjeta' THEN monto ELSE 0 END) as calculated_tarjeta,
                    SUM(CASE WHEN metodo_pago = 'transferencia' THEN monto ELSE 0 END) as calculado_transferencia
                FROM pedido_pagos 
                WHERE caja_turno_id = :turno_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['turno_id' => $caja_turno_id]);
        $res = $stmt->fetch();
        
        return [
            'calculado_efectivo'      => floatval($res['calculado_efectivo'] ?? 0),
            'calculado_tarjeta'       => floatval($res['calculated_tarjeta'] ?? 0),
            'calculado_transferencia' => floatval($res['calculado_transferencia'] ?? 0)
        ];
    }

    // REGISTRAR ENTRADA/SALIDA MANUAL DE EFECTIVO
    public function registrarMovimientoCaja($caja_turno_id, $tipo, $monto, $motivo) {
        $sql = "INSERT INTO caja_movimientos (caja_turno_id, tipo, monto, motivo) VALUES (:turno_id, :tipo, :monto, :motivo)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'turno_id' => $caja_turno_id,
            'tipo'     => $tipo,
            'monto'    => $monto,
            'motivo'   => $motivo
        ]);
    }

    // LISTAR LOS MOVIMIENTOS RECIENTES DEL TURNO ACTUAL
    public function obtenerMovimientosDelTurno($caja_turno_id) {
        $sql = "SELECT * FROM caja_movimientos WHERE caja_turno_id = :turno_id ORDER BY id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['turno_id' => $caja_turno_id]);
        return $stmt->fetchAll();
    }

    // CALCULAR EL NETO DE MOVIMIENTOS INTERNOS (Para la matemática de cuadre)
    public function obtenerTotalesMovimientos($caja_turno_id) {
        $sql = "SELECT 
                    SUM(CASE WHEN tipo = 'entrada' THEN monto ELSE 0 END) as total_entradas,
                    SUM(CASE WHEN tipo = 'salida' THEN monto ELSE 0 END) as total_salidas
                FROM caja_movimientos WHERE caja_turno_id = :turno_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['turno_id' => $caja_turno_id]);
        return $stmt->fetch();
    }

    // CIERRE Y ARQUEO FINAL: Procesa el cierre contable adaptado a tus columnas
    public function cerrarCaja($turno_id, $efectivo_entregado, $tarjeta_entregado, $transferencia_entregado, $observaciones = '') {
        $sqlTurno = "SELECT monto_inicial FROM caja_turnos WHERE id = :id";
        $stmtT = $this->db->prepare($sqlTurno);
        $stmtT->execute(['id' => $turno_id]);
        $turno = $stmtT->fetch();
        
        $ventas = $this->calcularVentasDelTurno($turno_id);
        $total_sistema_efectivo       = floatval($ventas['calculado_efectivo']);
        $total_sistema_tarjeta        = floatval($ventas['calculado_tarjeta']);
        $total_sistema_transferencia  = floatval($ventas['calculado_transferencia']);
        
        // Recuperamos el impacto de vales o inyecciones parciales
        $movs = $this->obtenerTotalesMovimientos($turno_id);
        $entradas_manuales = floatval($movs['total_entradas'] ?? 0);
        $salidas_manuales  = floatval($movs['total_salidas'] ?? 0);
        
        // Matemática: Inicial + Ventas Efectivo + Inyecciones - Gastos
        $monto_esperado_efectivo = floatval($turno['monto_inicial']) + $total_sistema_efectivo + $entradas_manuales - $salidas_manuales;
        $monto_final_real = floatval($efectivo_entregado) + floatval($tarjeta_entregado) + floatval($transferencia_entregado);
        $monto_esperado_sistema = $monto_esperado_efectivo + $total_sistema_tarjeta + $total_sistema_transferencia;
        
        $diferencia = $monto_final_real - $monto_esperado_sistema;

        $now = (new DateTime('now', new DateTimeZone('America/Managua')))->format('Y-m-d H:i:s');
        $sql = "UPDATE caja_turnos 
                SET fecha_cierre = :fecha_cierre,
                    total_efectivo = :t_efectivo,
                    total_tarjeta = :t_tarjeta,
                    total_transferencia = :t_trans,
                    monto_final_real = :m_real,
                    diferencia = :dif,
                    estado = 'cerrada',
                    observaciones = :obs
                WHERE id = :id";
                
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id'         => $turno_id,
            'fecha_cierre' => $now,
            't_efectivo' => $total_sistema_efectivo,
            't_tarjeta'  => $total_sistema_tarjeta,
            't_trans'    => $total_sistema_transferencia,
            'm_real'     => $monto_final_real,
            'dif'        => $diferencia,
            'obs'        => $observaciones
        ]);
    }
}
?>
