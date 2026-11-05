<?php
/**
 * Modelo: Notificacion (notificaciones)
 * Sistema de Notificaciones Internas en Tiempo Real
 * Satisface: Notificaciones por cita nueva, confirmada, reprogramada, cancelada, recordatorios médicos.
 */

require_once __DIR__ . '/../../config/db.php';

class Notificacion
{
    /**
     * Crea una notificación individual respetando las preferencias del usuario y evitando duplicados
     */
    public static function crear(int $id_usuario, string $tipo, string $titulo, string $mensaje, ?string $enlace = null): bool
    {
        $pdo = Database::getConnection();

        // 1. Respetar la configuración de notificaciones del usuario
        $stmtPref = $pdo->prepare("SELECT notificaciones_activas FROM usuarios WHERE id_usuario = :id");
        $stmtPref->execute([':id' => $id_usuario]);
        $activas = $stmtPref->fetchColumn();

        if ($activas === false || (bool)$activas === false) {
            // Usuario tiene desactivadas las notificaciones
            return false;
        }

        // 2. Evitar notificaciones duplicadas idénticas en una ventana reciente (10 minutos)
        $stmtDup = $pdo->prepare(
            "SELECT 1 FROM notificaciones 
             WHERE id_usuario = :id AND tipo = :tipo AND titulo = :titulo 
               AND fecha_creacion > (CURRENT_TIMESTAMP - INTERVAL '10 minutes')"
        );
        $stmtDup->execute([
            ':id'     => $id_usuario,
            ':tipo'   => $tipo,
            ':titulo' => $titulo
        ]);
        if ($stmtDup->fetch()) {
            return false;
        }

        // 3. Insertar notificación
        $sql = "INSERT INTO notificaciones (id_usuario, tipo, titulo, mensaje, enlace, leida, fecha_creacion)
                VALUES (:id_u, :tipo, :titulo, :msg, :enl, FALSE, CURRENT_TIMESTAMP)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':id_u'   => $id_usuario,
            ':tipo'   => $tipo,
            ':titulo' => $titulo,
            ':msg'    => $mensaje,
            ':enl'    => $enlace
        ]);
    }

    /**
     * Notifica a todos los usuarios que pertenezcan a un rol específico (ej. Recepcionistas o Administradores)
     */
    public static function notificarRol(int $id_rol, string $tipo, string $titulo, string $mensaje, ?string $enlace = null): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE id_rol = :rol AND estado = 'ACTIVO' AND notificaciones_activas = TRUE");
        $stmt->execute([':rol' => $id_rol]);
        $usuarios = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($usuarios as $id_u) {
            self::crear((int)$id_u, $tipo, $titulo, $mensaje, $enlace);
        }
    }

    /**
     * Obtiene las notificaciones más recientes de un usuario (ordenadas por fecha descendente)
     */
    public static function listarPorUsuario(int $id_usuario, int $limite = 15): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT id_notificacion, tipo, titulo, mensaje, enlace, leida, fecha_creacion,
                    TO_CHAR(fecha_creacion, 'DD/MM/YYYY HH24:MI') AS fecha_formateada
             FROM notificaciones
             WHERE id_usuario = :id
             ORDER BY fecha_creacion DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':id', $id_usuario, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Cuenta el total de notificaciones no leídas de un usuario
     */
    public static function contarNoLeidas(int $id_usuario): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE id_usuario = :id AND leida = FALSE");
        $stmt->execute([':id' => $id_usuario]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Marca una notificación específica como leída
     */
    public static function marcarLeida(int $id_notificacion, int $id_usuario): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE notificaciones SET leida = TRUE WHERE id_notificacion = :id AND id_usuario = :id_u");
        return $stmt->execute([':id' => $id_notificacion, ':id_u' => $id_usuario]);
    }

    /**
     * Marca todas las notificaciones de un usuario como leídas
     */
    public static function marcarTodasLeidas(int $id_usuario): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE notificaciones SET leida = TRUE WHERE id_usuario = :id_u AND leida = FALSE");
        return $stmt->execute([':id_u' => $id_usuario]);
    }

    /**
     * Dispara notificaciones cuando se registra una nueva cita médica
     */
    public static function notificarNuevaCita(int $id_cita): void
    {
        $pdo = Database::getConnection();
        $sql = "SELECT c.codigo_cita, c.fecha_cita, c.hora_inicio,
                       p.id_usuario AS id_usuario_paciente, p.nombres || ' ' || p.apellidos AS paciente_nombre,
                       m.id_usuario AS id_usuario_medico, m.nombres || ' ' || m.apellidos AS medico_nombre,
                       e.nombre AS especialidad
                FROM citas_medicas c
                JOIN pacientes p ON c.id_paciente = p.id_paciente
                JOIN medicos m ON c.id_medico = m.id_medico
                JOIN especialidades e ON c.id_especialidad = e.id_especialidad
                WHERE c.id_cita = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id_cita]);
        $cita = $stmt->fetch();

        if (!$cita) return;

        $hora = substr($cita['hora_inicio'], 0, 5);
        $fecha = date('d/m/Y', strtotime($cita['fecha_cita']));

        // Notificar al paciente (si tiene usuario)
        if (!empty($cita['id_usuario_paciente'])) {
            self::crear(
                (int)$cita['id_usuario_paciente'],
                'CITA_NUEVA',
                'Cita Médica Reservada',
                "Su cita ({$cita['codigo_cita']}) con el Dr(a). {$cita['medico_nombre']} ({$cita['especialidad']}) fue agendada para el {$fecha} a las {$hora}.",
                "/clinicaVidaSalud/views/consultas/historial.php"
            );
        }

        // Notificar al médico (si tiene usuario)
        if (!empty($cita['id_usuario_medico'])) {
            self::crear(
                (int)$cita['id_usuario_medico'],
                'CITA_NUEVA',
                'Nueva Consulta Asignada',
                "Se le asignó una nueva cita ({$cita['codigo_cita']}) con el paciente {$cita['paciente_nombre']} para el {$fecha} a las {$hora}.",
                "/clinicaVidaSalud/views/consultas/agenda.php?fecha={$cita['fecha_cita']}"
            );
        }

        // Notificar al rol Recepcionista (rol 2)
        self::notificarRol(
            2,
            'CITA_NUEVA',
            'Nueva Cita Registrada',
            "Se agendó la cita {$cita['codigo_cita']} para {$cita['paciente_nombre']} con Dr(a). {$cita['medico_nombre']} ({$fecha} {$hora}).",
            "/clinicaVidaSalud/views/consultas/agenda.php?fecha={$cita['fecha_cita']}"
        );
    }

    /**
     * Dispara notificaciones cuando se confirma la asistencia de una cita
     */
    public static function notificarConfirmacionCita(int $id_cita): void
    {
        $pdo = Database::getConnection();
        $sql = "SELECT c.codigo_cita, c.fecha_cita, c.hora_inicio,
                       p.id_usuario AS id_usuario_paciente, p.nombres || ' ' || p.apellidos AS paciente_nombre,
                       m.id_usuario AS id_usuario_medico, m.nombres || ' ' || m.apellidos AS medico_nombre
                FROM citas_medicas c
                JOIN pacientes p ON c.id_paciente = p.id_paciente
                JOIN medicos m ON c.id_medico = m.id_medico
                WHERE c.id_cita = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id_cita]);
        $cita = $stmt->fetch();

        if (!$cita) return;

        $hora = substr($cita['hora_inicio'], 0, 5);
        $fecha = date('d/m/Y', strtotime($cita['fecha_cita']));

        if (!empty($cita['id_usuario_paciente'])) {
            self::crear(
                (int)$cita['id_usuario_paciente'],
                'CITA_CONFIRMADA',
                'Cita Confirmada',
                "Su cita médica {$cita['codigo_cita']} para el {$fecha} a las {$hora} ha sido confirmada exitosamente.",
                "/clinicaVidaSalud/views/consultas/historial.php"
            );
        }

        if (!empty($cita['id_usuario_medico'])) {
            self::crear(
                (int)$cita['id_usuario_medico'],
                'CITA_CONFIRMADA',
                'Paciente Confirmado',
                "El paciente {$cita['paciente_nombre']} confirmó asistencia a la cita {$cita['codigo_cita']} ({$fecha} {$hora}).",
                "/clinicaVidaSalud/views/consultas/agenda.php?fecha={$cita['fecha_cita']}"
            );
        }
    }

    /**
     * Dispara notificaciones al reprogramar una cita
     */
    public static function notificarReprogramacionCita(int $id_cita, string $nuevaFecha, string $nuevoInicio): void
    {
        $pdo = Database::getConnection();
        $sql = "SELECT c.codigo_cita,
                       p.id_usuario AS id_usuario_paciente, p.nombres || ' ' || p.apellidos AS paciente_nombre,
                       m.id_usuario AS id_usuario_medico, m.nombres || ' ' || m.apellidos AS medico_nombre
                FROM citas_medicas c
                JOIN pacientes p ON c.id_paciente = p.id_paciente
                JOIN medicos m ON c.id_medico = m.id_medico
                WHERE c.id_cita = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id_cita]);
        $cita = $stmt->fetch();

        if (!$cita) return;

        $hora = substr($nuevoInicio, 0, 5);
        $fecha = date('d/m/Y', strtotime($nuevaFecha));

        if (!empty($cita['id_usuario_paciente'])) {
            self::crear(
                (int)$cita['id_usuario_paciente'],
                'CITA_REPROGRAMADA',
                'Cita Médica Reprogramada',
                "Su cita {$cita['codigo_cita']} con Dr(a). {$cita['medico_nombre']} fue reprogramada al {$fecha} a las {$hora}.",
                "/clinicaVidaSalud/views/consultas/historial.php"
            );
        }

        if (!empty($cita['id_usuario_medico'])) {
            self::crear(
                (int)$cita['id_usuario_medico'],
                'CITA_REPROGRAMADA',
                'Cita Reprogramada en su Agenda',
                "La cita {$cita['codigo_cita']} con {$cita['paciente_nombre']} cambió al {$fecha} a las {$hora}.",
                "/clinicaVidaSalud/views/consultas/agenda.php?fecha={$nuevaFecha}"
            );
        }

        // Avisar a recepción
        self::notificarRol(
            2,
            'CITA_REPROGRAMADA',
            'Cita Reprogramada',
            "La cita {$cita['codigo_cita']} fue movida para el {$fecha} {$hora}.",
            "/clinicaVidaSalud/views/consultas/agenda.php?fecha={$nuevaFecha}"
        );
    }

    /**
     * Dispara notificaciones al cancelar una cita
     */
    public static function notificarCancelacionCita(int $id_cita, string $motivo): void
    {
        $pdo = Database::getConnection();
        $sql = "SELECT c.codigo_cita, c.fecha_cita, c.hora_inicio,
                       p.id_usuario AS id_usuario_paciente, p.nombres || ' ' || p.apellidos AS paciente_nombre,
                       m.id_usuario AS id_usuario_medico, m.nombres || ' ' || m.apellidos AS medico_nombre
                FROM citas_medicas c
                JOIN pacientes p ON c.id_paciente = p.id_paciente
                JOIN medicos m ON c.id_medico = m.id_medico
                WHERE c.id_cita = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id_cita]);
        $cita = $stmt->fetch();

        if (!$cita) return;

        $hora = substr($cita['hora_inicio'], 0, 5);
        $fecha = date('d/m/Y', strtotime($cita['fecha_cita']));

        if (!empty($cita['id_usuario_paciente'])) {
            self::crear(
                (int)$cita['id_usuario_paciente'],
                'CITA_CANCELADA',
                'Cita Médica Cancelada',
                "Su cita {$cita['codigo_cita']} del {$fecha} {$hora} fue cancelada. Motivo: {$motivo}",
                "/clinicaVidaSalud/views/consultas/historial.php"
            );
        }

        if (!empty($cita['id_usuario_medico'])) {
            self::crear(
                (int)$cita['id_usuario_medico'],
                'CITA_CANCELADA',
                'Consulta Cancelada',
                "La cita {$cita['codigo_cita']} con {$cita['paciente_nombre']} ({$fecha} {$hora}) fue cancelada. Motivo: {$motivo}",
                "/clinicaVidaSalud/views/consultas/agenda.php?fecha={$cita['fecha_cita']}"
            );
        }

        self::notificarRol(
            2,
            'CITA_CANCELADA',
            'Cancelación de Cita',
            "Cita {$cita['codigo_cita']} cancelada. Motivo: {$motivo}",
            "/clinicaVidaSalud/views/citas/cancelar.php"
        );
    }

    /**
     * Genera recordatorio diario para un médico si tiene citas programadas hoy
     */
    public static function verificarRecordatorioDiarioMedico(int $id_usuario_medico, int $id_medico): void
    {
        $pdo = Database::getConnection();
        $hoy = date('Y-m-d');

        // Contar citas para hoy que no estén canceladas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM citas_medicas WHERE id_medico = :id_m AND fecha_cita = :f AND estado NOT IN ('CANCELADA', 'ATENDIDA')");
        $stmt->execute([':id_m' => $id_medico, ':f' => $hoy]);
        $totalCitasHoy = (int)$stmt->fetchColumn();

        if ($totalCitasHoy > 0) {
            $titulo = "Recordatorio de Agenda del Día ({$totalCitasHoy} citas)";
            $mensaje = "Doctor(a), usted tiene {$totalCitasHoy} consulta(s) médica(s) programada(s) para la jornada de hoy ({$hoy}).";
            self::crear($id_usuario_medico, 'CITA_RECORDATORIO_MEDICO', $titulo, $mensaje, "/clinicaVidaSalud/views/consultas/agenda.php?fecha={$hoy}");
        }
    }
}
