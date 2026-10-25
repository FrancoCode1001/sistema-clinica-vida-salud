<?php
/**
 * Modelo: Rol (roles)
 * Sistema de Gestión de Citas Médicas - Clínica Vida Salud
 */

require_once __DIR__ . '/../../config/db.php';

class Rol
{
    /**
     * Lista todos los roles registrados
     */
    public static function listarTodos(bool $soloActivos = true): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT id_rol, nombre_rol, descripcion, estado FROM roles";
        if ($soloActivos) {
            $sql .= " WHERE estado = 'ACTIVO'";
        }
        $sql .= " ORDER BY id_rol ASC";

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un rol por su identificador
     */
    public static function obtenerPorId(int $id_rol): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id_rol, nombre_rol, descripcion, estado FROM roles WHERE id_rol = :id");
        $stmt->execute([':id' => $id_rol]);
        $res = $stmt->fetch();
        return $res ?: null;
    }
}
