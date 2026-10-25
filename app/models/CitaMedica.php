<?php
/**
 * Modelo: CitaMedica (citas_medicas e historial_estados_cita)
 * CU-08, CU-09, CU-10, CU-11, CU-12
 * Cumplimiento estricto de RF06, RF07, RF08, RF09, RF10, RN04, RN05, RN06, RN07, RN09, RN10, RN13
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/HorarioAtencion.php';
require_once __DIR__ . '/../helpers/AuditoriaHelper.php';

class CitaMedica
{
    /**
     * Consulta la disponibilidad horaria en tiempo real de un médico en una fecha específica (CU-08)
     */
    public static function consultarDisponibilidad(int $id_medico, string $fecha_cita): array
    {
        $pdo = Database::getConnection();

        // 1. Validar fecha no pasada (RN06)
        if ($fecha_cita < date('Y-m-d')) {
            return [
                'status'  => false,
                'message' => 'RN06: No se pueden consultar ni programar citas en fechas pasadas.',
                'slots'   => []
            ];
        }

        // 2. Validar que el médico esté ACTIVO (RN04)
        $stmtMed = $pdo->prepare("SELECT estado, nombres || ' ' || apellidos AS medico_nombre FROM medicos WHERE id_medico = :id");
        $stmtMed->execute([':id' => $id_medico]);
        $medico = $stmtMed->fetch();

        if (!$medico) {
            return ['status' => false, 'message' => 'El médico seleccionado no existe.', 'slots' => []];
        }

        if ($medico['estado'] !== 'ACTIVO') {
            return [
                'status'  => false,
                'message' => 'RN04: El médico seleccionado se encuentra inactivo y no puede recibir citas.',
                'slots'   => []
            ];
        }

        // 3. Obtener el día de la semana ISO (1=Lunes .. 7=Domingo)
        $diaSemana = (int)date('N', strtotime($fecha_cita));

        if ($diaSemana > 6) {
            return [
                'status'  => false,
                'message' => 'La clínica no atiende los días domingo.',
                'slots'   => []
            ];
        }

        // 4. Consultar los turnos configurados para dicho médico en ese día (RN05)
        $stmtTurno = $pdo->prepare("SELECT * FROM horarios_atencion WHERE id_medico = :id AND dia_semana = :dia AND estado = 'ACTIVO'");
        $stmtTurno->execute([':id' => $id_medico, ':dia' => $diaSemana]);
        $turnos = $stmtTurno->fetchAll();

        if (empty($turnos)) {
            $nombreDia = HorarioAtencion::$diasSemana[$diaSemana] ?? "Día {$diaSemana}";
            return [
                'status'  => false,
                'message' => "El Dr(a). {$medico['medico_nombre']} no tiene turnos de atención configurados para los días {$nombreDia}.",
                'slots'   => []
            ];
        }

        // 5. Consultar las citas ocupadas activas (Programada, Confirmada, Atendida)
        // NOTA: Las citas CANCELADAS NO ocupan horario (RN09)
        $stmtCitas = $pdo->prepare("SELECT hora_inicio, hora_fin, codigo_cita, estado FROM citas_medicas
                                    WHERE id_medico = :id AND fecha_cita = :fecha AND estado <> 'CANCELADA'");
        $stmtCitas->execute([':id' => $id_medico, ':fecha' => $fecha_cita]);
        $citasOcupadas = $stmtCitas->fetchAll();

        // 6. Generar bloques y cruzar disponibilidad
        $todosLosBloques = [];
        foreach ($turnos as $turno) {
            $intervalos = HorarioAtencion::generarIntervalos($turno['hora_inicio'], $turno['hora_fin'], (int)$turno['duracion_minutos']);

            foreach ($intervalos as $b) {
                $ocupado = false;
                $citaInfo = null;

                foreach ($citasOcupadas as $c) {
                    $cIni = substr($c['hora_inicio'], 0, 5);
                    $cFin = substr($c['hora_fin'], 0, 5);

                    // Si existe solapamiento: (b.inicio < c.fin AND b.fin > c.inicio)
                    if ($b['inicio'] < $cFin && $b['fin'] > $cIni) {
                        $ocupado = true;
                        $citaInfo = $c;
                        break;
                    }
                }

                $todosLosBloques[] = [
                    'hora_inicio' => $b['inicio'],
                    'hora_fin'    => $b['fin'],
                    'hora_inicio_sql' => $b['inicio_sql'],
                    'hora_fin_sql'    => $b['fin_sql'],
                    'disponible'  => !$ocupado,
                    'estado_cita' => $ocupado ? $citaInfo['estado'] : 'LIBRE',
                    'codigo_cita' => $ocupado ? $citaInfo['codigo_cita'] : null
                ];
            }
        }

        return [
            'status'  => true,
            'message' => 'Disponibilidad consultada con éxito.',
            'slots'   => $todosLosBloques,
            'medico'  => $medico['medico_nombre']
        ];
    }

    /**
     * Programa una nueva cita médica con prevención estricta de superposiciones (CU-09)
     */
    public static function programar(array $datos, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();

        $id_paciente     = (int)($datos['id_paciente'] ?? 0);
        $id_medico       = (int)($datos['id_medico'] ?? 0);
        $id_especialidad = (int)($datos['id_especialidad'] ?? 0);
        $fecha_cita      = trim($datos['fecha_cita'] ?? '');
        $hora_inicio     = trim($datos['hora_inicio'] ?? '');
        $hora_fin        = trim($datos['hora_fin'] ?? '');
        $modalidad       = trim($datos['modalidad'] ?? 'PRESENCIAL');
        $motivo          = trim($datos['motivo_consulta'] ?? '');

        // Formatear segundos si vienen en formato HH:MM
        if (strlen($hora_inicio) === 5) $hora_inicio .= ':00';
        if (strlen($hora_fin) === 5) $hora_fin .= ':00';

        // Validaciones básicas
        if ($id_paciente <= 0 || $id_medico <= 0 || $id_especialidad <= 0 || empty($fecha_cita) || empty($hora_inicio) || empty($hora_fin)) {
            return ['status' => false, 'message' => 'Todos los datos de la cita médica son obligatorios.'];
        }

        // RN06: Validar fecha no pasada
        if ($fecha_cita < date('Y-m-d')) {
            return ['status' => false, 'message' => 'RN06: No se permiten programar citas en fechas pasadas.'];
        }

        try {
            $pdo->beginTransaction();

            // RN04: Validar médico activo
            $stmtM = $pdo->prepare("SELECT estado FROM medicos WHERE id_medico = :id FOR UPDATE");
            $stmtM->execute([':id' => $id_medico]);
            $estadoMed = $stmtM->fetchColumn();
            if ($estadoMed !== 'ACTIVO') {
                $pdo->rollBack();
                return ['status' => false, 'message' => 'RN04: El médico seleccionado se encuentra inactivo.'];
            }

            // RF07 / RN07 / RN13: Verificar conflicto / superposición en servidor
            $stmtConflicto = $pdo->prepare(
                "SELECT id_cita, codigo_cita FROM citas_medicas
                 WHERE id_medico = :id_m
                   AND fecha_cita = :f
                   AND estado <> 'CANCELADA'
                   AND (:inicio < hora_fin AND :fin > hora_inicio)
                 FOR UPDATE"
            );
            $stmtConflicto->execute([
                ':id_m'   => $id_medico,
                ':f'      => $fecha_cita,
                ':inicio' => $hora_inicio,
                ':fin'    => $hora_fin
            ]);
            if ($stmtConflicto->fetch()) {
                $pdo->rollBack();
                return [
                    'status' => false,
                    'message' => 'RF07 / RN07: El médico ya tiene una cita reservada que se superpone en ese horario.'
                ];
            }

            // Generar código autoincremental de cita (ej. CM-0006)
            $stmtSeq = $pdo->query("SELECT COALESCE(MAX(id_cita), 0) + 1 FROM citas_medicas");
            $sigId = (int)$stmtSeq->fetchColumn();
            $codigo_cita = sprintf('CM-%04d', $sigId);

            // Insertar cita médica
            $sql = "INSERT INTO citas_medicas (codigo_cita, id_paciente, id_medico, id_especialidad, fecha_cita, hora_inicio, hora_fin, modalidad, motivo_consulta, estado, fecha_registro)
                    VALUES (:codigo, :id_p, :id_m, :id_e, :f, :inicio, :fin, :mod, :mot, 'PROGRAMADA', CURRENT_TIMESTAMP)
                    RETURNING id_cita";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':codigo' => $codigo_cita,
                ':id_p'   => $id_paciente,
                ':id_m'   => $id_medico,
                ':id_e'   => $id_especialidad,
                ':f'      => $fecha_cita,
                ':inicio' => $hora_inicio,
                ':fin'    => $hora_fin,
                ':mod'    => $modalidad,
                ':mot'    => !empty($motivo) ? $motivo : 'Consulta Médica General'
            ]);
            $id_cita = (int)$stmt->fetchColumn();

            // Insertar registro en historial de estados (Trazabilidad RF10)
            $stmtHist = $pdo->prepare(
                "INSERT INTO historial_estados_cita (id_cita, estado_anterior, estado_nuevo, observacion, id_usuario)
                 VALUES (:id_c, NULL, 'PROGRAMADA', 'Reserva inicial validada sin conflictos', :id_u)"
            );
            $stmtHist->execute([':id_c' => $id_cita, ':id_u' => $id_usuario_auth]);

            $pdo->commit();

            AuditoriaHelper::registrar(
                $id_usuario_auth,
                'INSERT',
                'citas_medicas',
                "Programación de cita médica {$codigo_cita} para el {$fecha_cita} a las {$hora_inicio}"
            );

            return [
                'status'      => true,
                'message'     => "Cita médica {$codigo_cita} programada exitosamente.",
                'codigo_cita' => $codigo_cita,
                'id_cita'     => $id_cita
            ];

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $msg = $e->getMessage();
            if (str_contains($msg, 'RF07') || str_contains($msg, 'idx_unico_cita_medico_horario') || str_contains($msg, 'superpone')) {
                return ['status' => false, 'message' => 'RF07: Conflicto detectado: El médico ya tiene una cita activa en ese bloque horario.'];
            }
            if (str_contains($msg, 'RN05')) {
                return ['status' => false, 'message' => 'RN05: El horario seleccionado está fuera del horario de atención del médico.'];
            }
            return ['status' => false, 'message' => 'Error al programar cita: ' . $msg];
        }
    }

    /**
     * Reprograma una cita médica cambiando fecha y hora, liberando el bloque previo (CU-10)
     */
    public static function reprogramar(int $id_cita, string $nueva_fecha, string $nuevo_inicio, string $nuevo_fin, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();

        if (strlen($nuevo_inicio) === 5) $nuevo_inicio .= ':00';
        if (strlen($nuevo_fin) === 5) $nuevo_fin .= ':00';

        if ($nueva_fecha < date('Y-m-d')) {
            return ['status' => false, 'message' => 'RN06: No se puede reprogramar a una fecha pasada.'];
        }

        try {
            $pdo->beginTransaction();

            // Validar estado de la cita
            $stmt = $pdo->prepare("SELECT estado, id_medico, fecha_cita, hora_inicio, hora_fin FROM citas_medicas WHERE id_cita = :id FOR UPDATE");
            $stmt->execute([':id' => $id_cita]);
            $cita = $stmt->fetch();

            if (!$cita) {
                $pdo->rollBack();
                return ['status' => false, 'message' => 'La cita no existe.'];
            }

            if (in_array($cita['estado'], ['ATENDIDA', 'CANCELADA'], true)) {
                $pdo->rollBack();
                return ['status' => false, 'message' => "RF08: No se puede reprogramar una cita con estado '{$cita['estado']}'."];
            }

            // Validar superposición en el nuevo horario
            $stmtConflicto = $pdo->prepare(
                "SELECT 1 FROM citas_medicas
                 WHERE id_medico = :id_m
                   AND fecha_cita = :f
                   AND id_cita <> :id_c
                   AND estado <> 'CANCELADA'
                   AND (:inicio < hora_fin AND :fin > hora_inicio)"
            );
            $stmtConflicto->execute([
                ':id_m'   => $cita['id_medico'],
                ':f'      => $nueva_fecha,
                ':id_c'   => $id_cita,
                ':inicio' => $nuevo_inicio,
                ':fin'    => $nuevo_fin
            ]);
            if ($stmtConflicto->fetch()) {
                $pdo->rollBack();
                return ['status' => false, 'message' => 'RF08: Conflicto detectado en el nuevo horario seleccionado.'];
            }

            $sql = "UPDATE citas_medicas SET fecha_cita = :f, hora_inicio = :ini, hora_fin = :fin WHERE id_cita = :id";
            $stmtUpdate = $pdo->prepare($sql);
            $stmtUpdate->execute([
                ':f'   => $nueva_fecha,
                ':ini' => $nuevo_inicio,
                ':fin' => $nuevo_fin,
                ':id'  => $id_cita
            ]);

            // Auditoría de transición
            $stmtHist = $pdo->prepare(
                "INSERT INTO historial_estados_cita (id_cita, estado_anterior, estado_nuevo, observacion, id_usuario)
                 VALUES (:id_c, :ant, :nue, :obs, :id_u)"
            );
            $obs = "Reprogramada de {$cita['fecha_cita']} {$cita['hora_inicio']} hacia {$nueva_fecha} {$nuevo_inicio}";
            $stmtHist->execute([
                ':id_c' => $id_cita,
                ':ant'  => $cita['estado'],
                ':nue'  => $cita['estado'],
                ':obs'  => $obs,
                ':id_u' => $id_usuario_auth
            ]);

            $pdo->commit();

            AuditoriaHelper::registrar(
                $id_usuario_auth,
                'UPDATE',
                'citas_medicas',
                "Reprogramación de cita ID {$id_cita} hacia {$nueva_fecha} {$nuevo_inicio}"
            );

            return ['status' => true, 'message' => "Cita médica reprogramada con éxito para el {$nueva_fecha} a las {$nuevo_inicio}."];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['status' => false, 'message' => 'Error al reprogramar: ' . $e->getMessage()];
        }
    }

    /**
     * Cancela lógicamente una cita registrando el motivo y liberando el horario (CU-11)
     */
    public static function cancelar(int $id_cita, string $motivo, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();
        $motivo = trim($motivo);

        if (empty($motivo)) {
            return ['status' => false, 'message' => 'Debe ingresar un motivo para la cancelación de la cita.'];
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT estado, codigo_cita FROM citas_medicas WHERE id_cita = :id FOR UPDATE");
            $stmt->execute([':id' => $id_cita]);
            $cita = $stmt->fetch();

            if (!$cita) {
                $pdo->rollBack();
                return ['status' => false, 'message' => 'La cita no existe.'];
            }

            if ($cita['estado'] === 'ATENDIDA') {
                $pdo->rollBack();
                return ['status' => false, 'message' => 'RF09 / RN10: Una cita ATENDIDA no puede cancelarse.'];
            }

            if ($cita['estado'] === 'CANCELADA') {
                $pdo->rollBack();
                return ['status' => false, 'message' => 'Esta cita ya se encuentra en estado CANCELADA.'];
            }

            $sql = "UPDATE citas_medicas SET estado = 'CANCELADA', motivo_cancelacion = :mot WHERE id_cita = :id";
            $stmtUpdate = $pdo->prepare($sql);
            $stmtUpdate->execute([':mot' => $motivo, ':id' => $id_cita]);

            $stmtHist = $pdo->prepare(
                "INSERT INTO historial_estados_cita (id_cita, estado_anterior, estado_nuevo, observacion, id_usuario)
                 VALUES (:id_c, :ant, 'CANCELADA', :obs, :id_u)"
            );
            $stmtHist->execute([
                ':id_c' => $id_cita,
                ':ant'  => $cita['estado'],
                ':obs'  => $motivo,
                ':id_u' => $id_usuario_auth
            ]);

            $pdo->commit();

            AuditoriaHelper::registrar(
                $id_usuario_auth,
                'UPDATE',
                'citas_medicas',
                "Cancelación lógica de cita {$cita['codigo_cita']}. Horario liberado (RN09). Motivo: {$motivo}"
            );

            return ['status' => true, 'message' => "Cita {$cita['codigo_cita']} cancelada exitosamente. El horario ha quedado liberado."];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['status' => false, 'message' => 'Error al cancelar cita: ' . $e->getMessage()];
        }
    }

    /**
     * Controla la transición de estado de una cita médica (CU-12)
     */
    public static function cambiarEstado(int $id_cita, string $nuevo_estado, ?string $observacion = null, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();
        $estadosPermitidos = ['PROGRAMADA', 'CONFIRMADA', 'ATENDIDA', 'CANCELADA', 'INASISTENCIA'];

        if (!in_array($nuevo_estado, $estadosPermitidos, true)) {
            return ['status' => false, 'message' => 'Estado de cita no válido.'];
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT estado, codigo_cita FROM citas_medicas WHERE id_cita = :id FOR UPDATE");
            $stmt->execute([':id' => $id_cita]);
            $cita = $stmt->fetch();

            if (!$cita) {
                $pdo->rollBack();
                return ['status' => false, 'message' => 'La cita no existe.'];
            }

            // Validar transiciones legales (RF10 / RN10)
            if ($cita['estado'] === 'CANCELADA' && $nuevo_estado === 'ATENDIDA') {
                $pdo->rollBack();
                return ['status' => false, 'message' => 'RF10: Una cita CANCELADA no puede marcarse directamente como ATENDIDA.'];
            }
            if ($cita['estado'] === 'ATENDIDA' && $nuevo_estado === 'CANCELADA') {
                $pdo->rollBack();
                return ['status' => false, 'message' => 'RF09 / RN10: Una cita ATENDIDA no puede cancelarse.'];
            }

            $sql = "UPDATE citas_medicas SET estado = :est WHERE id_cita = :id";
            $stmtUpdate = $pdo->prepare($sql);
            $stmtUpdate->execute([':est' => $nuevo_estado, ':id' => $id_cita]);

            $stmtHist = $pdo->prepare(
                "INSERT INTO historial_estados_cita (id_cita, estado_anterior, estado_nuevo, observacion, id_usuario)
                 VALUES (:id_c, :ant, :nue, :obs, :id_u)"
            );
            $stmtHist->execute([
                ':id_c' => $id_cita,
                ':ant'  => $cita['estado'],
                ':nue'  => $nuevo_estado,
                ':obs'  => $observacion ?: "Cambio de estado a {$nuevo_estado}",
                ':id_u' => $id_usuario_auth
            ]);

            $pdo->commit();

            AuditoriaHelper::registrar(
                $id_usuario_auth,
                'UPDATE',
                'citas_medicas',
                "Transición de estado de cita {$cita['codigo_cita']}: {$cita['estado']} -> {$nuevo_estado}"
            );

            return ['status' => true, 'message' => "Estado de la cita actualizado a '{$nuevo_estado}'."];

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['status' => false, 'message' => 'Error al cambiar estado: ' . $e->getMessage()];
        }
    }

    /**
     * Obtiene una cita médica por ID con todos sus detalles vinculados
     */
    public static function obtenerPorId(int $id_cita): ?array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT c.*,
                       p.codigo_paciente, p.ci AS paciente_ci, p.nombres || ' ' || p.apellidos AS paciente_nombre, p.telefono AS paciente_tel,
                       m.codigo_medico, m.nombres || ' ' || m.apellidos AS medico_nombre, m.telefono AS medico_tel,
                       e.nombre AS especialidad_nombre
                FROM citas_medicas c
                JOIN pacientes p ON c.id_paciente = p.id_paciente
                JOIN medicos m ON c.id_medico = m.id_medico
                JOIN especialidades e ON c.id_especialidad = e.id_especialidad
                WHERE c.id_cita = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id_cita]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Lista citas con filtros para módulos de control y reportes
     */
    public static function listar(array $filtros = []): array
    {
        $pdo = Database::getConnection();
        $where = [];
        $params = [];

        $sql = "SELECT c.*,
                       p.codigo_paciente, p.ci AS paciente_ci, p.nombres || ' ' || p.apellidos AS paciente_nombre, p.telefono AS paciente_tel,
                       m.codigo_medico, m.nombres || ' ' || m.apellidos AS medico_nombre,
                       e.nombre AS especialidad_nombre
                FROM citas_medicas c
                JOIN pacientes p ON c.id_paciente = p.id_paciente
                JOIN medicos m ON c.id_medico = m.id_medico
                JOIN especialidades e ON c.id_especialidad = e.id_especialidad";

        if (!empty($filtros['id_medico'])) {
            $where[] = "c.id_medico = :id_m";
            $params[':id_m'] = (int)$filtros['id_medico'];
        }

        if (!empty($filtros['id_paciente'])) {
            $where[] = "c.id_paciente = :id_p";
            $params[':id_p'] = (int)$filtros['id_paciente'];
        }

        if (!empty($filtros['fecha_cita'])) {
            $where[] = "c.fecha_cita = :fecha";
            $params[':fecha'] = $filtros['fecha_cita'];
        }

        if (!empty($filtros['fecha_inicio'])) {
            $where[] = "c.fecha_cita >= :f_ini";
            $params[':f_ini'] = $filtros['fecha_inicio'];
        }

        if (!empty($filtros['fecha_fin'])) {
            $where[] = "c.fecha_cita <= :f_fin";
            $params[':f_fin'] = $filtros['fecha_fin'];
        }

        if (!empty($filtros['id_especialidad'])) {
            $where[] = "c.id_especialidad = :id_esp";
            $params[':id_esp'] = (int)$filtros['id_especialidad'];
        }

        if (!empty($filtros['estado'])) {
            $where[] = "c.estado = :estado";
            $params[':estado'] = strtoupper(trim($filtros['estado']));
        }

        if (!empty($filtros['busqueda'])) {
            $where[] = "(LOWER(p.nombres || ' ' || p.apellidos) LIKE :busq OR p.ci LIKE :busq OR LOWER(c.codigo_cita) LIKE :busq)";
            $params[':busq'] = '%' . strtolower(trim($filtros['busqueda'])) . '%';
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY c.fecha_cita DESC, c.hora_inicio DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
