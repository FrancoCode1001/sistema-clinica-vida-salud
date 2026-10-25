<?php
/**
 * Modelo: Especialidad (especialidades)
 * CU-03: Gestionar Especialidades Médicas (RF04)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/AuditoriaHelper.php';

class Especialidad
{
    /**
     * Lista las especialidades registradas
     */
    public static function listarTodas(bool $soloActivas = false, ?string $busqueda = null): array
    {
        $pdo = Database::getConnection();
        $where = [];
        $params = [];

        $sql = "SELECT e.id_especialidad, e.nombre, e.descripcion, e.estado,
                       (SELECT COUNT(*) FROM medico_especialidad me WHERE me.id_especialidad = e.id_especialidad) AS total_medicos,
                       (SELECT COUNT(*) FROM citas_medicas cm WHERE cm.id_especialidad = e.id_especialidad) AS total_citas
                FROM especialidades e";

        if ($soloActivas) {
            $where[] = "e.estado = 'ACTIVO'";
        }

        if (!empty($busqueda)) {
            $where[] = "(LOWER(e.nombre) LIKE :busq OR LOWER(e.descripcion) LIKE :busq)";
            $params[':busq'] = '%' . strtolower(trim($busqueda)) . '%';
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY e.nombre ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene una especialidad por su ID
     */
    public static function obtenerPorId(int $id_especialidad): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id_especialidad, nombre, descripcion, estado FROM especialidades WHERE id_especialidad = :id");
        $stmt->execute([':id' => $id_especialidad]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Registra una nueva especialidad validando nombre normalizado único
     */
    public static function crear(string $nombre, ?string $descripcion, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();
        $nombre = trim($nombre);

        if (empty($nombre)) {
            return ['status' => false, 'message' => 'El nombre de la especialidad es obligatorio.'];
        }

        try {
            // Se utiliza el procedimiento almacenado sp_registrar_especialidad de PostgreSQL
            $stmt = $pdo->prepare("CALL sp_registrar_especialidad(:nombre, :desc)");
            $stmt->execute([
                ':nombre' => $nombre,
                ':desc'   => $descripcion
            ]);

            AuditoriaHelper::registrar(
                $id_usuario_auth,
                'INSERT',
                'especialidades',
                "Registro de especialidad médica: '{$nombre}'"
            );

            return ['status' => true, 'message' => "Especialidad '{$nombre}' registrada correctamente."];

        } catch (PDOException $e) {
            // Manejar excepciones de reglas de negocio arrojadas por PL/pgSQL
            $msg = $e->getMessage();
            if (str_contains($msg, 'RF04')) {
                return ['status' => false, 'message' => "RF04: Ya existe una especialidad con el nombre '{$nombre}'."];
            }
            return ['status' => false, 'message' => 'Error al guardar especialidad: ' . $msg];
        }
    }

    /**
     * Actualiza una especialidad existente
     */
    public static function actualizar(int $id_especialidad, string $nombre, ?string $descripcion, string $estado, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();
        $nombre = trim($nombre);

        if (empty($nombre)) {
            return ['status' => false, 'message' => 'El nombre de la especialidad es obligatorio.'];
        }

        try {
            $sql = "UPDATE especialidades SET nombre = :nombre, descripcion = :desc, estado = :estado WHERE id_especialidad = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nombre' => $nombre,
                ':desc'   => $descripcion,
                ':estado' => $estado,
                ':id'     => $id_especialidad
            ]);

            AuditoriaHelper::registrar(
                $id_usuario_auth,
                'UPDATE',
                'especialidades',
                "Actualización de especialidad ID {$id_especialidad} ('{$nombre}')"
            );

            return ['status' => true, 'message' => "Especialidad '{$nombre}' actualizada exitosamente."];

        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'RF04') || str_contains($msg, 'unique') || str_contains($msg, 'duplicada')) {
                return ['status' => false, 'message' => "RF04: Ya existe otra especialidad con el nombre '{$nombre}'."];
            }
            return ['status' => false, 'message' => 'Error al actualizar: ' . $msg];
        }
    }

    /**
     * Cambia el estado (ACTIVO / INACTIVO) de una especialidad
     */
    public static function cambiarEstado(int $id_especialidad, string $nuevoEstado, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();
        $nuevoEstado = strtoupper($nuevoEstado) === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO';

        $stmt = $pdo->prepare("UPDATE especialidades SET estado = :estado WHERE id_especialidad = :id");
        $stmt->execute([':estado' => $nuevoEstado, ':id' => $id_especialidad]);

        AuditoriaHelper::registrar(
            $id_usuario_auth,
            'UPDATE',
            'especialidades',
            "Cambio de estado de especialidad ID {$id_especialidad} a '{$nuevoEstado}'"
        );

        return ['status' => true, 'message' => "Estado de la especialidad actualizado a {$nuevoEstado}."];
    }
}
