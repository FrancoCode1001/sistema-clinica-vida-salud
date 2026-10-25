<?php
/**
 * Modelo: HorarioAtencion (horarios_atencion)
 * CU-05: Gestionar Horarios de Atención Médica (RF05 / RN08)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/AuditoriaHelper.php';

class HorarioAtencion
{
    public static array $diasSemana = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado'
    ];

    /**
     * Lista horarios de atención configurados, opcionalmente filtrados por médico
     */
    public static function listar(?int $id_medico = null): array
    {
        $pdo = Database::getConnection();
        $params = [];
        $sql = "SELECT h.id_horario, h.id_medico, h.dia_semana, h.hora_inicio, h.hora_fin, h.duracion_minutos, h.estado,
                       m.codigo_medico, m.nombres || ' ' || m.apellidos AS medico_nombre
                FROM horarios_atencion h
                JOIN medicos m ON h.id_medico = m.id_medico";

        if (!empty($id_medico)) {
            $sql .= " WHERE h.id_medico = :id_m";
            $params[':id_m'] = $id_medico;
        }

        $sql .= " ORDER BY m.nombres ASC, h.dia_semana ASC, h.hora_inicio ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Registra un horario de atención utilizando validación de rangos y solapamientos (RF05)
     */
    public static function crear(int $id_medico, int $dia_semana, string $hora_inicio, string $hora_fin, int $duracion = 30, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();

        if ($dia_semana < 1 || $dia_semana > 6) {
            return ['status' => false, 'message' => 'El día de atención debe ser entre Lunes (1) y Sábado (6).'];
        }

        if (strtotime($hora_fin) <= strtotime($hora_inicio)) {
            return ['status' => false, 'message' => 'RF05: La hora final debe ser estrictamente posterior a la hora inicial.'];
        }

        if ($duracion <= 0) {
            $duracion = 30;
        }

        try {
            // Invocar el procedimiento almacenado sp_registrar_horario_medico
            $stmt = $pdo->prepare("CALL sp_registrar_horario_medico(:id_m, :dia, :inicio, :fin, :dur)");
            $stmt->execute([
                ':id_m'   => $id_medico,
                ':dia'    => $dia_semana,
                ':inicio' => $hora_inicio,
                ':fin'    => $hora_fin,
                ':dur'    => $duracion
            ]);

            $nombreDia = self::$diasSemana[$dia_semana] ?? "Día {$dia_semana}";
            AuditoriaHelper::registrar(
                $id_usuario_auth,
                'INSERT',
                'horarios_atencion',
                "Configuración de turno médico ID {$id_medico}: {$nombreDia} {$hora_inicio}-{$hora_fin} (bloques de {$duracion} min)"
            );

            return ['status' => true, 'message' => "Horario de atención registrado exitosamente para el {$nombreDia}."];

        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'RF05') || str_contains($msg, 'superpone')) {
                return ['status' => false, 'message' => 'RF05: El turno ingresado se solapa con otro horario ya configurado para ese médico.'];
            }
            return ['status' => false, 'message' => 'Error al registrar horario: ' . $msg];
        }
    }

    /**
     * Elimina o desactiva un turno horario
     */
    public static function eliminar(int $id_horario, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare("DELETE FROM horarios_atencion WHERE id_horario = :id");
            $stmt->execute([':id' => $id_horario]);

            AuditoriaHelper::registrar(
                $id_usuario_auth,
                'DELETE',
                'horarios_atencion',
                "Eliminación de turno horario ID {$id_horario}"
            );

            return ['status' => true, 'message' => 'Turno de atención eliminado correctamente.'];
        } catch (Exception $e) {
            return ['status' => false, 'message' => 'Error al eliminar turno: ' . $e->getMessage()];
        }
    }

    /**
     * Calcula dinámicamente los intervalos (bloques) de citas a partir de un turno
     *
     * @return array Array de intervalos [['inicio' => '08:00', 'fin' => '08:30'], ...]
     */
    public static function generarIntervalos(string $hora_inicio, string $hora_fin, int $duracionMinutos = 30): array
    {
        $bloques = [];
        $inicioTs = strtotime($hora_inicio);
        $finTs    = strtotime($hora_fin);
        $pasoSegundos = $duracionMinutos * 60;

        while (($inicioTs + $pasoSegundos) <= $finTs) {
            $bloqueFinTs = $inicioTs + $pasoSegundos;
            $bloques[] = [
                'inicio' => date('H:i', $inicioTs),
                'fin'    => date('H:i', $bloqueFinTs),
                'inicio_sql' => date('H:i:s', $inicioTs),
                'fin_sql'    => date('H:i:s', $bloqueFinTs)
            ];
            $inicioTs = $bloqueFinTs;
        }

        return $bloques;
    }
}
