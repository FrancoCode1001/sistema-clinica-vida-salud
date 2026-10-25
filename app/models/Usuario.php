<?php
/**
 * Modelo: Usuario (usuarios)
 * Capa de Acceso a Datos con PDO pdo_pgsql y consultas preparadas
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/AuditoriaHelper.php';

class Usuario
{
    /**
     * Busca un usuario por nombre de usuario o correo electrónico
     */
    public static function buscarPorCredencial(string $credencial): ?array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT u.id_usuario, u.username, u.email, u.password_hash, u.id_rol, u.estado, u.created_at,
                       r.nombre_rol,
                       COALESCE(m.id_medico, NULL) AS id_medico,
                       COALESCE(p.id_paciente, NULL) AS id_paciente,
                       COALESCE(m.nombres || ' ' || m.apellidos, p.nombres || ' ' || p.apellidos, u.username) AS nombre_completo
                FROM usuarios u
                JOIN roles r ON u.id_rol = r.id_rol
                LEFT JOIN medicos m ON u.id_usuario = m.id_usuario
                LEFT JOIN pacientes p ON u.id_usuario = p.id_usuario
                WHERE LOWER(u.username) = LOWER(:cred1) OR LOWER(u.email) = LOWER(:cred2)
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cred1' => trim($credencial),
            ':cred2' => trim($credencial)
        ]);

        $usuario = $stmt->fetch();
        return $usuario ?: null;
    }

    /**
     * Verifica la contraseña utilizando password_verify
     */
    public static function verificarPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Lista todos los usuarios con su rol asociado
     */
    public static function listarTodos(?string $busqueda = null, ?int $id_rol = null): array
    {
        $pdo = Database::getConnection();
        $params = [];
        $where = [];

        $sql = "SELECT u.id_usuario, u.username, u.email, u.id_rol, u.estado, u.created_at,
                       r.nombre_rol,
                       COALESCE(m.nombres || ' ' || m.apellidos, p.nombres || ' ' || p.apellidos, 'Sin vincular') AS nombre_vinculado
                FROM usuarios u
                JOIN roles r ON u.id_rol = r.id_rol
                LEFT JOIN medicos m ON u.id_usuario = m.id_usuario
                LEFT JOIN pacientes p ON u.id_usuario = p.id_usuario";

        if (!empty($busqueda)) {
            $where[] = "(LOWER(u.username) LIKE :busq OR LOWER(u.email) LIKE :busq)";
            $params[':busq'] = '%' . strtolower(trim($busqueda)) . '%';
        }

        if (!empty($id_rol)) {
            $where[] = "u.id_rol = :id_rol";
            $params[':id_rol'] = $id_rol;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY u.id_usuario ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un usuario por ID
     */
    public static function obtenerPorId(int $id_usuario): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id_usuario, username, email, id_rol, estado, created_at FROM usuarios WHERE id_usuario = :id");
        $stmt->execute([':id' => $id_usuario]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Registra un nuevo usuario con contraseña cifrada (password_hash)
     */
    public static function crear(array $datos, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();
        $username = trim($datos['username'] ?? '');
        $email    = trim($datos['email'] ?? '');
        $password = $datos['password'] ?? '';
        $id_rol   = (int)($datos['id_rol'] ?? 0);
        $estado   = $datos['estado'] ?? 'ACTIVO';

        // Validaciones obligatorias
        if (empty($username) || empty($email) || empty($password) || $id_rol <= 0) {
            return ['status' => false, 'message' => 'Todos los campos obligatorios deben ser completados.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'message' => 'El formato del correo electrónico es inválido.'];
        }

        // Validar unicidad
        $stmt = $pdo->prepare("SELECT 1 FROM usuarios WHERE LOWER(username) = LOWER(:u) OR LOWER(email) = LOWER(:e)");
        $stmt->execute([':u' => $username, ':e' => $email]);
        if ($stmt->fetch()) {
            return ['status' => false, 'message' => 'El nombre de usuario o correo electrónico ya se encuentra registrado.'];
        }

        // Cifrado de contraseña
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $sql = "INSERT INTO usuarios (username, email, password_hash, id_rol, estado, created_at)
                VALUES (:username, :email, :password_hash, :id_rol, :estado, CURRENT_TIMESTAMP)
                RETURNING id_usuario";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':username'      => $username,
            ':email'         => $email,
            ':password_hash' => $password_hash,
            ':id_rol'        => $id_rol,
            ':estado'        => $estado
        ]);

        $nuevoId = (int)$stmt->fetchColumn();

        // Auditoría
        AuditoriaHelper::registrar(
            $id_usuario_auth,
            'INSERT',
            'usuarios',
            "Creación de usuario '{$username}' con rol ID {$id_rol}"
        );

        return ['status' => true, 'message' => 'Usuario registrado exitosamente.', 'id_usuario' => $nuevoId];
    }

    /**
     * Actualiza los datos de un usuario
     */
    public static function actualizar(int $id_usuario, array $datos, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();
        $username = trim($datos['username'] ?? '');
        $email    = trim($datos['email'] ?? '');
        $id_rol   = (int)($datos['id_rol'] ?? 0);
        $estado   = $datos['estado'] ?? 'ACTIVO';
        $password = $datos['password'] ?? null;

        if (empty($username) || empty($email) || $id_rol <= 0) {
            return ['status' => false, 'message' => 'Campos obligatorios incompletos.'];
        }

        // Verificar duplicados en otros usuarios
        $stmt = $pdo->prepare("SELECT 1 FROM usuarios WHERE (LOWER(username) = LOWER(:u) OR LOWER(email) = LOWER(:e)) AND id_usuario <> :id");
        $stmt->execute([':u' => $username, ':e' => $email, ':id' => $id_usuario]);
        if ($stmt->fetch()) {
            return ['status' => false, 'message' => 'El nombre de usuario o correo electrónico ya está en uso por otra cuenta.'];
        }

        // Validar que no se desactive el último Administrador activo
        if ($estado === 'INACTIVO' || $id_rol !== 1) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_rol = 1 AND estado = 'ACTIVO' AND id_usuario <> :id");
            $stmt->execute([':id' => $id_usuario]);
            $otrosAdmins = (int)$stmt->fetchColumn();
            if ($otrosAdmins === 0) {
                return ['status' => false, 'message' => 'Operación rechazada: El sistema no puede quedarse sin al menos un Administrador activo.'];
            }
        }

        $params = [
            ':username' => $username,
            ':email'    => $email,
            ':id_rol'   => $id_rol,
            ':estado'   => $estado,
            ':id'       => $id_usuario
        ];

        if (!empty($password)) {
            $sql = "UPDATE usuarios SET username = :username, email = :email, password_hash = :hash, id_rol = :id_rol, estado = :estado WHERE id_usuario = :id";
            $params[':hash'] = password_hash($password, PASSWORD_BCRYPT);
        } else {
            $sql = "UPDATE usuarios SET username = :username, email = :email, id_rol = :id_rol, estado = :estado WHERE id_usuario = :id";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        AuditoriaHelper::registrar(
            $id_usuario_auth,
            'UPDATE',
            'usuarios',
            "Actualización de datos del usuario ID {$id_usuario} ({$username})"
        );

        return ['status' => true, 'message' => 'Usuario actualizado correctamente.'];
    }

    /**
     * Cambia el estado (ACTIVO / INACTIVO) de un usuario (Baja lógica)
     */
    public static function cambiarEstado(int $id_usuario, string $nuevoEstado, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();
        $nuevoEstado = strtoupper($nuevoEstado) === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO';

        // Validar no desactivar último Administrador
        if ($nuevoEstado === 'INACTIVO') {
            $stmt = $pdo->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = :id");
            $stmt->execute([':id' => $id_usuario]);
            $rol = (int)$stmt->fetchColumn();

            if ($rol === 1) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_rol = 1 AND estado = 'ACTIVO' AND id_usuario <> :id");
                $stmt->execute([':id' => $id_usuario]);
                if ((int)$stmt->fetchColumn() === 0) {
                    return ['status' => false, 'message' => 'No es posible desactivar al único Administrador activo del sistema.'];
                }
            }
        }

        $stmt = $pdo->prepare("UPDATE usuarios SET estado = :estado WHERE id_usuario = :id");
        $stmt->execute([':estado' => $nuevoEstado, ':id' => $id_usuario]);

        AuditoriaHelper::registrar(
            $id_usuario_auth,
            'UPDATE',
            'usuarios',
            "Cambio de estado del usuario ID {$id_usuario} a '{$nuevoEstado}'"
        );

        return ['status' => true, 'message' => "Estado de usuario actualizado a {$nuevoEstado}."];
    }
}
