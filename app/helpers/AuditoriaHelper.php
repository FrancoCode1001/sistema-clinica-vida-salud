<?php
/**
 * Helper de Auditoría y Bitácora del Sistema (CU-15)
 * Registra acciones críticas y eventos de seguridad en la tabla bitacoras.
 */

require_once __DIR__ . '/../../config/db.php';

class AuditoriaHelper
{
    /**
     * Obtiene la dirección IP real del cliente
     */
    public static function getClientIP(): string
    {
        $ip = '127.0.0.1';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Normalizar localhost IPv6 (::1) a IPv4 estándar (127.0.0.1)
        if ($ip === '::1' || $ip === '::ffff:127.0.0.1') {
            $ip = '127.0.0.1';
        }

        return $ip;
    }

    /**
     * Registra un evento en la tabla bitacoras
     *
     * @param int|null $id_usuario ID del usuario autenticado (o null)
     * @param string $accion INSERT, UPDATE, DELETE, LOGIN, LOGOUT
     * @param string $modulo Nombre del módulo o tabla afectada
     * @param string $descripcion Detalle descriptivo de la operación
     * @param string|null $ip Dirección IP personalizada (opcional)
     * @return bool
     */
    public static function registrar(?int $id_usuario, string $accion, string $modulo, string $descripcion, ?string $ip = null): bool
    {
        try {
            $pdo = Database::getConnection();
            $ip = $ip ?: self::getClientIP();

            $sql = "INSERT INTO bitacoras (id_usuario, accion, modulo, descripcion, ip_acceso, fecha_hora)
                    VALUES (:id_usuario, :accion, :modulo, :descripcion, :ip_acceso, CURRENT_TIMESTAMP)";
            
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                ':id_usuario'  => $id_usuario,
                ':accion'       => strtoupper(trim($accion)),
                ':modulo'      => trim($modulo),
                ':descripcion' => trim($descripcion),
                ':ip_acceso'   => $ip
            ]);
        } catch (Exception $e) {
            // Se silencia para evitar interrumpir la transacción principal, pero se registra en logs de PHP
            error_log("Error al registrar bitácora: " . $e->getMessage());
            return false;
        }
    }
}
