<?php
/**
 * Modelo: Bitacora (bitacoras)
 * CU-15: Consultar Bitácora de Auditoría del Sistema
 */

require_once __DIR__ . '/../../config/db.php';

class Bitacora
{
    /**
     * Consulta registros de auditoría con filtros avanzados
     */
    public static function listar(array $filtros = [], int $limite = 100): array
    {
        $pdo = Database::getConnection();
        $where = [];
        $params = [];

        $sql = "SELECT b.id_bitacora, b.id_usuario, b.accion, b.modulo, b.descripcion, b.ip_acceso, b.fecha_hora,
                       COALESCE(u.username, 'Trigger / Sistema') AS username,
                       COALESCE(r.nombre_rol, 'Sistema') AS nombre_rol
                FROM bitacoras b
                LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario
                LEFT JOIN roles r ON u.id_rol = r.id_rol";

        if (!empty($filtros['fecha_inicio'])) {
            $where[] = "b.fecha_hora >= :fecha_inicio";
            $params[':fecha_inicio'] = $filtros['fecha_inicio'] . ' 00:00:00';
        }

        if (!empty($filtros['fecha_fin'])) {
            $where[] = "b.fecha_hora <= :fecha_fin";
            $params[':fecha_fin'] = $filtros['fecha_fin'] . ' 23:59:59';
        }

        if (!empty($filtros['id_usuario'])) {
            $where[] = "b.id_usuario = :id_usuario";
            $params[':id_usuario'] = (int)$filtros['id_usuario'];
        }

        if (!empty($filtros['accion'])) {
            $where[] = "b.accion = :accion";
            $params[':accion'] = strtoupper(trim($filtros['accion']));
        }

        if (!empty($filtros['modulo'])) {
            $where[] = "LOWER(b.modulo) = LOWER(:modulo)";
            $params[':modulo'] = trim($filtros['modulo']);
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY b.fecha_hora DESC LIMIT " . (int)$limite;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene el listado de acciones únicas para los filtros
     */
    public static function obtenerAcciones(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT DISTINCT accion FROM bitacoras ORDER BY accion ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Obtiene el listado de módulos únicos para los filtros
     */
    public static function obtenerModulos(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT DISTINCT modulo FROM bitacoras ORDER BY modulo ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
