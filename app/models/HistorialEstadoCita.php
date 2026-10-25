<?php
/**
 * Modelo: HistorialEstadoCita (historial_estados_cita)
 * Trazabilidad de Estados de Citas Médicas (RF10 / RF12 / RN12)
 */

require_once __DIR__ . '/../../config/db.php';

class HistorialEstadoCita
{
    /**
     * Lista los cambios de estado de una cita médica
     */
    public static function listarPorCita(int $id_cita): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT h.id_historial, h.id_cita, h.estado_anterior, h.estado_nuevo, h.observacion, h.fecha_cambio,
                       COALESCE(u.username, 'Sistema / Automático') AS usuario_nombre,
                       r.nombre_rol
                FROM historial_estados_cita h
                LEFT JOIN usuarios u ON h.id_usuario = u.id_usuario
                LEFT JOIN roles r ON u.id_rol = r.id_rol
                WHERE h.id_cita = :id
                ORDER BY h.fecha_cambio ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id_cita]);
        return $stmt->fetchAll();
    }
}
