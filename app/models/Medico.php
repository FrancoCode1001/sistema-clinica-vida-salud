<?php
/**
 * Modelo: Medico (medicos y medico_especialidad)
 * CU-04: Gestionar Médicos (RF03 / RN02 / RN04 / RN11)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/AuditoriaHelper.php';

class Medico
{
    /**
     * Lista médicos con sus especialidades asignadas
     */
    public static function listarTodos(?string $busqueda = null, ?int $id_especialidad = null, bool $soloActivos = false): array
    {
        $pdo = Database::getConnection();
        $params = [];
        $where = [];

        $sql = "SELECT m.id_medico, m.codigo_medico, m.nro_registro_profesional, m.nombres, m.apellidos,
                       m.telefono, m.email, m.estado, m.id_usuario,
                       STRING_AGG(e.nombre, ', ' ORDER BY e.nombre) AS especialidades,
                       (SELECT COUNT(*) FROM citas_medicas cm WHERE cm.id_medico = m.id_medico) AS total_citas
                FROM medicos m
                LEFT JOIN medico_especialidad me ON m.id_medico = me.id_medico
                LEFT JOIN especialidades e ON me.id_especialidad = e.id_especialidad";

        if ($soloActivos) {
            $where[] = "m.estado = 'ACTIVO'";
        }

        if (!empty($busqueda)) {
            $where[] = "(LOWER(m.nombres) LIKE :busq OR LOWER(m.apellidos) LIKE :busq OR LOWER(m.codigo_medico) LIKE :busq OR LOWER(m.nro_registro_profesional) LIKE :busq)";
            $params[':busq'] = '%' . strtolower(trim($busqueda)) . '%';
        }

        if (!empty($id_especialidad)) {
            $where[] = "m.id_medico IN (SELECT id_medico FROM medico_especialidad WHERE id_especialidad = :id_esp)";
            $params[':id_esp'] = $id_especialidad;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " GROUP BY m.id_medico, m.codigo_medico, m.nro_registro_profesional, m.nombres, m.apellidos, m.telefono, m.email, m.estado, m.id_usuario";
        $sql .= " ORDER BY m.nombres ASC, m.apellidos ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene los datos de un médico por su ID
     */
    public static function obtenerPorId(int $id_medico): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM medicos WHERE id_medico = :id");
        $stmt->execute([':id' => $id_medico]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Obtiene los IDs de las especialidades asignadas a un médico
     */
    public static function obtenerEspecialidadesIds(int $id_medico): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id_especialidad FROM medico_especialidad WHERE id_medico = :id");
        $stmt->execute([':id' => $id_medico]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Registra un nuevo médico y asocia sus especialidades dentro de una transacción
     */
    public static function crear(array $datos, array $especialidadesIds, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();

        $codigo    = trim($datos['codigo_medico'] ?? '');
        $matricula = trim($datos['nro_registro_profesional'] ?? '');
        $nombres   = trim($datos['nombres'] ?? '');
        $apellidos = trim($datos['apellidos'] ?? '');
        $telefono  = trim($datos['telefono'] ?? '');
        $email     = trim($datos['email'] ?? '');
        $id_usuario = !empty($datos['id_usuario']) ? (int)$datos['id_usuario'] : null;

        if (empty($codigo) || empty($matricula) || empty($nombres) || empty($apellidos) || empty($email) || empty($telefono)) {
            return ['status' => false, 'message' => 'Todos los campos obligatorios del médico deben ser llenados.'];
        }

        if (empty($especialidadesIds)) {
            return ['status' => false, 'message' => 'Debe asociar al menos una especialidad médica al profesional.'];
        }

        try {
            $pdo->beginTransaction();

            // Validar unicidad de código, matrícula y correo
            $stmt = $pdo->prepare("SELECT 1 FROM medicos WHERE codigo_medico = :c OR nro_registro_profesional = :m OR LOWER(email) = LOWER(:e)");
            $stmt->execute([':c' => $codigo, ':m' => $matricula, ':e' => $email]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                return ['status' => false, 'message' => 'RN02: El código de médico, matrícula o correo electrónico ya existe registrado.'];
            }

            $sql = "INSERT INTO medicos (codigo_medico, nro_registro_profesional, nombres, apellidos, telefono, email, estado, id_usuario)
                    VALUES (:codigo, :matricula, :nombres, :apellidos, :telefono, :email, 'ACTIVO', :id_usuario)
                    RETURNING id_medico";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':codigo'     => $codigo,
                ':matricula'  => $matricula,
                ':nombres'    => $nombres,
                ':apellidos'  => $apellidos,
                ':telefono'   => $telefono,
                ':email'      => $email,
                ':id_usuario' => $id_usuario
            ]);
            $id_medico = (int)$stmt->fetchColumn();

            // Insertar especialidades asociadas
            $stmtEsp = $pdo->prepare("INSERT INTO medico_especialidad (id_medico, id_especialidad, fecha_asignacion) VALUES (:id_m, :id_e, CURRENT_DATE)");
            foreach ($especialidadesIds as $idEsp) {
                $stmtEsp->execute([':id_m' => $id_medico, ':id_e' => (int)$idEsp]);
            }

            $pdo->commit();

            AuditoriaHelper::registrar(
                $id_usuario_auth,
                'INSERT',
                'medicos',
                "Registro de médico {$codigo} (Dr(a). {$nombres} {$apellidos})"
            );

            return ['status' => true, 'message' => "Médico {$nombres} {$apellidos} registrado exitosamente.", 'id_medico' => $id_medico];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['status' => false, 'message' => 'Error al registrar médico: ' . $e->getMessage()];
        }
    }

    /**
     * Actualiza la información de un médico y sus especialidades
     */
    public static function actualizar(int $id_medico, array $datos, array $especialidadesIds, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();

        $codigo    = trim($datos['codigo_medico'] ?? '');
        $matricula = trim($datos['nro_registro_profesional'] ?? '');
        $nombres   = trim($datos['nombres'] ?? '');
        $apellidos = trim($datos['apellidos'] ?? '');
        $telefono  = trim($datos['telefono'] ?? '');
        $email     = trim($datos['email'] ?? '');
        $estado    = $datos['estado'] ?? 'ACTIVO';
        $id_usuario = !empty($datos['id_usuario']) ? (int)$datos['id_usuario'] : null;

        if (empty($codigo) || empty($matricula) || empty($nombres) || empty($apellidos) || empty($email) || empty($telefono)) {
            return ['status' => false, 'message' => 'Campos obligatorios incompletos.'];
        }

        if (empty($especialidadesIds)) {
            return ['status' => false, 'message' => 'Debe asociar al menos una especialidad médica.'];
        }

        try {
            $pdo->beginTransaction();

            // Validar unicidad excluyendo el registro actual
            $stmt = $pdo->prepare("SELECT 1 FROM medicos WHERE (codigo_medico = :c OR nro_registro_profesional = :m OR LOWER(email) = LOWER(:e)) AND id_medico <> :id");
            $stmt->execute([':c' => $codigo, ':m' => $matricula, ':e' => $email, ':id' => $id_medico]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                return ['status' => false, 'message' => 'RN02: Conflicto de unicidad en código, matrícula o correo con otro médico.'];
            }

            $sql = "UPDATE medicos SET codigo_medico = :c, nro_registro_profesional = :m,
                                       nombres = :nom, apellidos = :ape, telefono = :tel,
                                       email = :em, estado = :est, id_usuario = :id_u
                    WHERE id_medico = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':c'    => $codigo,
                ':m'    => $matricula,
                ':nom'  => $nombres,
                ':ape'  => $apellidos,
                ':tel'  => $telefono,
                ':em'   => $email,
                ':est'  => $estado,
                ':id_u' => $id_usuario,
                ':id'   => $id_medico
            ]);

            // Sincronizar especialidades
            $stmtDel = $pdo->prepare("DELETE FROM medico_especialidad WHERE id_medico = :id");
            $stmtDel->execute([':id' => $id_medico]);

            $stmtIns = $pdo->prepare("INSERT INTO medico_especialidad (id_medico, id_especialidad, fecha_asignacion) VALUES (:id_m, :id_e, CURRENT_DATE)");
            foreach ($especialidadesIds as $idEsp) {
                $stmtIns->execute([':id_m' => $id_medico, ':id_e' => (int)$idEsp]);
            }

            $pdo->commit();

            AuditoriaHelper::registrar(
                $id_usuario_auth,
                'UPDATE',
                'medicos',
                "Actualización de datos del médico ID {$id_medico} ({$codigo})"
            );

            return ['status' => true, 'message' => "Datos del médico actualizados correctamente."];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['status' => false, 'message' => 'Error al actualizar médico: ' . $e->getMessage()];
        }
    }

    /**
     * Aplica baja lógica al médico impidiendo nuevas citas pero conservando histórico (RN04, RN11)
     */
    public static function cambiarEstado(int $id_medico, string $nuevoEstado, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();
        $nuevoEstado = strtoupper($nuevoEstado) === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO';

        $stmt = $pdo->prepare("UPDATE medicos SET estado = :estado WHERE id_medico = :id");
        $stmt->execute([':estado' => $nuevoEstado, ':id' => $id_medico]);

        AuditoriaHelper::registrar(
            $id_usuario_auth,
            'UPDATE',
            'medicos',
            "Baja lógica / cambio de estado del médico ID {$id_medico} a '{$nuevoEstado}' (RN04/RN11)"
        );

        return ['status' => true, 'message' => "Estado del médico actualizado a {$nuevoEstado}."];
    }
}
