<?php
/**
 * Modelo: Paciente (pacientes)
 * CU-06: Gestionar Pacientes (RF02 / RN01 / RN11)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/AuditoriaHelper.php';

class Paciente
{
    /**
     * Lista pacientes con búsqueda por CI, nombres o apellidos
     */
    public static function listarTodos(?string $busqueda = null, bool $soloActivos = false): array
    {
        $pdo = Database::getConnection();
        $params = [];
        $where = [];

        $sql = "SELECT p.id_paciente, p.codigo_paciente, p.ci, p.nombres, p.apellidos,
                       p.fecha_nacimiento, p.sexo, p.telefono, p.email, p.direccion, p.estado, p.id_usuario,
                       (SELECT COUNT(*) FROM citas_medicas cm WHERE cm.id_paciente = p.id_paciente) AS total_citas
                FROM pacientes p";

        if ($soloActivos) {
            $where[] = "p.estado = 'ACTIVO'";
        }

        if (!empty($busqueda)) {
            $where[] = "(LOWER(p.nombres) LIKE :busq OR LOWER(p.apellidos) LIKE :busq OR p.ci LIKE :busq OR LOWER(p.codigo_paciente) LIKE :busq)";
            $params[':busq'] = '%' . strtolower(trim($busqueda)) . '%';
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY p.nombres ASC, p.apellidos ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene paciente por ID
     */
    public static function obtenerPorId(int $id_paciente): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM pacientes WHERE id_paciente = :id");
        $stmt->execute([':id' => $id_paciente]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Obtiene paciente por CI
     */
    public static function obtenerPorCi(string $ci): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM pacientes WHERE ci = :ci");
        $stmt->execute([':ci' => trim($ci)]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Registra un nuevo paciente validando CI único (RN01) y generando código PAC-XXXX
     */
    public static function crear(array $datos, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();

        $ci         = trim($datos['ci'] ?? '');
        $nombres    = trim($datos['nombres'] ?? '');
        $apellidos  = trim($datos['apellidos'] ?? '');
        $fecha_nac  = $datos['fecha_nacimiento'] ?? '';
        $sexo       = strtoupper(trim($datos['sexo'] ?? ''));
        $telefono   = trim($datos['telefono'] ?? '');
        $email      = trim($datos['email'] ?? '');
        $direccion  = trim($datos['direccion'] ?? '');
        $id_usuario = !empty($datos['id_usuario']) ? (int)$datos['id_usuario'] : null;

        if (empty($ci) || empty($nombres) || empty($apellidos) || empty($fecha_nac) || empty($sexo) || empty($telefono)) {
            return ['status' => false, 'message' => 'Los campos CI, Nombres, Apellidos, Fecha de Nacimiento, Sexo y Teléfono son obligatorios.'];
        }

        if (!in_array($sexo, ['M', 'F'], true)) {
            return ['status' => false, 'message' => "El sexo debe ser 'M' o 'F'."];
        }

        // Generar código autoincremental PAC-XXXX
        $stmtSeq = $pdo->query("SELECT COALESCE(MAX(id_paciente), 0) + 1 FROM pacientes");
        $sigId = (int)$stmtSeq->fetchColumn();
        $codigo_paciente = sprintf('PAC-%04d', $sigId);

        try {
            // Verificar duplicado de CI
            $stmt = $pdo->prepare("SELECT 1 FROM pacientes WHERE ci = :ci");
            $stmt->execute([':ci' => $ci]);
            if ($stmt->fetch()) {
                return ['status' => false, 'message' => "RN01: Ya existe un paciente registrado con el CI '{$ci}'."];
            }

            $sql = "INSERT INTO pacientes (codigo_paciente, ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, email, direccion, estado, id_usuario)
                    VALUES (:codigo, :ci, :nom, :ape, :fnac, :sexo, :tel, :email, :dir, 'ACTIVO', :id_u)
                    RETURNING id_paciente";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':codigo' => $codigo_paciente,
                ':ci'     => $ci,
                ':nom'    => $nombres,
                ':ape'    => $apellidos,
                ':fnac'   => $fecha_nac,
                ':sexo'   => $sexo,
                ':tel'    => $telefono,
                ':email'  => !empty($email) ? $email : null,
                ':dir'    => !empty($direccion) ? $direccion : null,
                ':id_u'   => $id_usuario
            ]);

            $nuevoId = (int)$stmt->fetchColumn();

            AuditoriaHelper::registrar(
                $id_usuario_auth,
                'INSERT',
                'pacientes',
                "Registro de paciente {$codigo_paciente} ({$nombres} {$apellidos}, CI: {$ci})"
            );

            return [
                'status' => true,
                'message' => "Paciente {$nombres} {$apellidos} registrado exitosamente con código {$codigo_paciente}.",
                'id_paciente' => $nuevoId
            ];

        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'RN01') || str_contains($msg, 'pacientes_ci_key')) {
                return ['status' => false, 'message' => "RN01: Ya existe un paciente registrado con el CI '{$ci}'."];
            }
            return ['status' => false, 'message' => 'Error al registrar paciente: ' . $msg];
        }
    }

    /**
     * Actualiza datos personales y de contacto de un paciente
     */
    public static function actualizar(int $id_paciente, array $datos, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();

        $ci         = trim($datos['ci'] ?? '');
        $nombres    = trim($datos['nombres'] ?? '');
        $apellidos  = trim($datos['apellidos'] ?? '');
        $fecha_nac  = $datos['fecha_nacimiento'] ?? '';
        $sexo       = strtoupper(trim($datos['sexo'] ?? ''));
        $telefono   = trim($datos['telefono'] ?? '');
        $email      = trim($datos['email'] ?? '');
        $direccion  = trim($datos['direccion'] ?? '');
        $estado     = $datos['estado'] ?? 'ACTIVO';
        $id_usuario = !empty($datos['id_usuario']) ? (int)$datos['id_usuario'] : null;

        if (empty($ci) || empty($nombres) || empty($apellidos) || empty($fecha_nac) || empty($sexo) || empty($telefono)) {
            return ['status' => false, 'message' => 'Campos obligatorios incompletos.'];
        }

        try {
            // Validar unicidad de CI
            $stmt = $pdo->prepare("SELECT 1 FROM pacientes WHERE ci = :ci AND id_paciente <> :id");
            $stmt->execute([':ci' => $ci, ':id' => $id_paciente]);
            if ($stmt->fetch()) {
                return ['status' => false, 'message' => "RN01: El CI '{$ci}' ya pertenece a otro paciente."];
            }

            $sql = "UPDATE pacientes SET ci = :ci, nombres = :nom, apellidos = :ape,
                                         fecha_nacimiento = :fnac, sexo = :sexo, telefono = :tel,
                                         email = :em, direccion = :dir, estado = :est, id_usuario = :id_u
                    WHERE id_paciente = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':ci'   => $ci,
                ':nom'  => $nombres,
                ':ape'  => $apellidos,
                ':fnac' => $fecha_nac,
                ':sexo' => $sexo,
                ':tel'  => $telefono,
                ':em'   => !empty($email) ? $email : null,
                ':dir'  => !empty($direccion) ? $direccion : null,
                ':est'  => $estado,
                ':id_u' => $id_usuario,
                ':id'   => $id_paciente
            ]);

            AuditoriaHelper::registrar(
                $id_usuario_auth,
                'UPDATE',
                'pacientes',
                "Actualización de datos del paciente ID {$id_paciente} ({$nombres} {$apellidos})"
            );

            return ['status' => true, 'message' => 'Datos del paciente actualizados exitosamente.'];

        } catch (PDOException $e) {
            return ['status' => false, 'message' => 'Error al actualizar paciente: ' . $e->getMessage()];
        }
    }

    /**
     * Aplica baja lógica al paciente sin eliminar su historial de citas asociadas (RN11)
     */
    public static function cambiarEstado(int $id_paciente, string $nuevoEstado, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();
        $nuevoEstado = strtoupper($nuevoEstado) === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO';

        $stmt = $pdo->prepare("UPDATE pacientes SET estado = :estado WHERE id_paciente = :id");
        $stmt->execute([':estado' => $nuevoEstado, ':id' => $id_paciente]);

        AuditoriaHelper::registrar(
            $id_usuario_auth,
            'UPDATE',
            'pacientes',
            "Baja lógica / cambio de estado del paciente ID {$id_paciente} a '{$nuevoEstado}' (RN11)"
        );

        return ['status' => true, 'message' => "Estado del paciente actualizado a {$nuevoEstado}."];
    }

    /**
     * Actualiza datos personales y de contacto desde el perfil del paciente
     * REGLA ESTRICTA: El CI es inmutable y no se modifica bajo ninguna circunstancia.
     */
    public static function actualizarDatosPersonalesYContacto(int $id_paciente, array $datos, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();

        // 1. Obtener paciente actual para asegurar existencia y mantener su CI inalterable
        $stmtActual = $pdo->prepare("SELECT ci, id_usuario, nombres, apellidos FROM pacientes WHERE id_paciente = :id");
        $stmtActual->execute([':id' => $id_paciente]);
        $pacienteActual = $stmtActual->fetch();

        if (!$pacienteActual) {
            return ['status' => false, 'message' => 'El paciente no existe en el registro clínico.'];
        }

        $nombres   = trim($datos['nombres'] ?? '');
        $apellidos = trim($datos['apellidos'] ?? '');
        $fecha_nac = trim($datos['fecha_nacimiento'] ?? '');
        $sexo      = strtoupper(trim($datos['sexo'] ?? ''));
        $telefono  = trim($datos['telefono'] ?? '');
        $email     = trim($datos['email'] ?? '');
        $direccion = trim($datos['direccion'] ?? '');

        // 2. Validaciones obligatorias
        if (empty($nombres) || empty($apellidos) || empty($fecha_nac) || empty($sexo) || empty($telefono)) {
            return ['status' => false, 'message' => 'Los campos Nombres, Apellidos, Fecha de Nacimiento, Sexo y Teléfono son obligatorios.'];
        }

        if (!in_array($sexo, ['M', 'F'], true)) {
            return ['status' => false, 'message' => "El sexo demográfico debe ser 'M' o 'F'."];
        }

        if (strtotime($fecha_nac) > time()) {
            return ['status' => false, 'message' => 'La fecha de nacimiento no puede ser una fecha futura.'];
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'message' => 'El formato del correo electrónico ingresado no es válido.'];
        }

        try {
            // 3. Actualizar tabla pacientes (El CI NUNCA se actualiza para garantizar trazabilidad e inmutabilidad)
            $sql = "UPDATE pacientes 
                    SET nombres = :nom, apellidos = :ape, fecha_nacimiento = :fnac,
                        sexo = :sexo, telefono = :tel, email = :em, direccion = :dir
                    WHERE id_paciente = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nom'  => $nombres,
                ':ape'  => $apellidos,
                ':fnac' => $fecha_nac,
                ':sexo' => $sexo,
                ':tel'  => $telefono,
                ':em'   => !empty($email) ? $email : null,
                ':dir'  => !empty($direccion) ? $direccion : null,
                ':id'   => $id_paciente
            ]);

            // 4. Si el paciente tiene cuenta de usuario vinculada, sincronizar correo si no colisiona
            if (!empty($pacienteActual['id_usuario']) && !empty($email)) {
                $stmtCheckEmail = $pdo->prepare("SELECT 1 FROM usuarios WHERE LOWER(email) = LOWER(:em) AND id_usuario <> :id_u");
                $stmtCheckEmail->execute([':em' => $email, ':id_u' => $pacienteActual['id_usuario']]);
                if (!$stmtCheckEmail->fetch()) {
                    $stmtUserEmail = $pdo->prepare("UPDATE usuarios SET email = :em WHERE id_usuario = :id_u");
                    $stmtUserEmail->execute([':em' => $email, ':id_u' => $pacienteActual['id_usuario']]);
                }
            }

            // 5. Sincronizar sesión si el usuario actual es este paciente
            if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['usuario']['id_paciente']) && (int)$_SESSION['usuario']['id_paciente'] === $id_paciente) {
                $_SESSION['usuario']['nombre_completo'] = $nombres . ' ' . $apellidos;
                if (!empty($email)) {
                    $_SESSION['usuario']['email'] = $email;
                }
            }

            // 6. Auditoría
            AuditoriaHelper::registrar(
                $id_usuario_auth,
                'UPDATE',
                'pacientes',
                "Actualización de datos personales y contacto del paciente {$pacienteActual['ci']} ({$nombres} {$apellidos})"
            );

            return ['status' => true, 'message' => '¡Datos personales y de contacto actualizados exitosamente!'];

        } catch (PDOException $e) {
            return ['status' => false, 'message' => 'Error al actualizar datos: ' . $e->getMessage()];
        }
    }
}
