<?php
/**
 * Controlador: ReporteController (CU-14)
 * Generador de Reportes Administrativos y Estadísticos
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/AuthMiddleware.php';

class ReporteController
{
    public function __construct()
    {
        AuthMiddleware::verificarRoles(['Administrador', 'Recepcionista']);
    }

    /**
     * Resumen estadístico por estado en un rango de fechas
     */
    public static function reportePorEstado(string $fechaInicio, string $fechaFin): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT estado, COUNT(id_cita) AS total_citas
                FROM citas_medicas
                WHERE fecha_cita BETWEEN :f_ini AND :f_fin
                GROUP BY estado
                ORDER BY total_citas DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':f_ini' => $fechaInicio, ':f_fin' => $fechaFin]);
        $rows = $stmt->fetchAll();

        $totalGeneral = array_sum(array_column($rows, 'total_citas'));

        foreach ($rows as &$r) {
            $r['porcentaje'] = $totalGeneral > 0 ? round(($r['total_citas'] / $totalGeneral) * 100, 1) : 0;
        }

        return ['filas' => $rows, 'total_general' => $totalGeneral];
    }

    /**
     * Reporte de citas por especialidad
     */
    public static function reportePorEspecialidad(?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        $pdo = Database::getConnection();
        $params = [];
        $where = "";

        if (!empty($fechaInicio) && !empty($fechaFin)) {
            $where = " AND c.fecha_cita BETWEEN :f_ini AND :f_fin";
            $params[':f_ini'] = $fechaInicio;
            $params[':f_fin'] = $fechaFin;
        }

        $sql = "SELECT e.nombre AS especialidad, COUNT(c.id_cita) AS total_citas
                FROM especialidades e
                LEFT JOIN citas_medicas c ON e.id_especialidad = c.id_especialidad {$where}
                GROUP BY e.id_especialidad, e.nombre
                ORDER BY total_citas DESC, e.nombre ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Reporte de atenciones por médico
     */
    public static function reportePorMedico(?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        $pdo = Database::getConnection();
        $params = [];
        $where = "";

        if (!empty($fechaInicio) && !empty($fechaFin)) {
            $where = " AND c.fecha_cita BETWEEN :f_ini AND :f_fin";
            $params[':f_ini'] = $fechaInicio;
            $params[':f_fin'] = $fechaFin;
        }

        $sql = "SELECT m.codigo_medico, m.nombres || ' ' || m.apellidos AS medico,
                       COUNT(c.id_cita) AS total_citas,
                       COUNT(CASE WHEN c.estado = 'ATENDIDA' THEN 1 END) AS atendidas,
                       COUNT(CASE WHEN c.estado = 'CANCELADA' THEN 1 END) AS canceladas,
                       COUNT(CASE WHEN c.estado = 'INASISTENCIA' THEN 1 END) AS inasistencias
                FROM medicos m
                LEFT JOIN citas_medicas c ON m.id_medico = c.id_medico {$where}
                GROUP BY m.id_medico, m.codigo_medico, m.nombres, m.apellidos
                ORDER BY total_citas DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
