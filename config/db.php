<?php

/**
 * Configuración de Conexión a Base de Datos PostgreSQL
 * Sistema de Gestión de Citas Médicas - Clínica Vida Salud
 * Tecnologías: PHP 8.x, PDO (pdo_pgsql), PostgreSQL
 */

// 1. Configuración de Zona Horaria Oficial de Bolivia (para citas médicas, agendas y bitácora)
date_default_timezone_set('America/La_Paz');

// 2. Procesar variables de entorno de producción (Render PostgreSQL / PaaS / Docker)
$databaseUrl = getenv('DATABASE_URL');
$parsedUrl = !empty($databaseUrl) ? parse_url($databaseUrl) : false;

$envHost = getenv('DB_HOST') ?: ($parsedUrl['host'] ?? null);
$envPort = getenv('DB_PORT') ?: (isset($parsedUrl['port']) ? (string)$parsedUrl['port'] : null);
$envName = getenv('DB_NAME') ?: (isset($parsedUrl['path']) ? ltrim($parsedUrl['path'], '/') : null);
$envUser = getenv('DB_USER') ?: ($parsedUrl['user'] ?? null);
$envPass = getenv('DB_PASSWORD') !== false && getenv('DB_PASSWORD') !== ''
    ? getenv('DB_PASSWORD')
    : (getenv('DB_PASS') !== false && getenv('DB_PASS') !== ''
        ? getenv('DB_PASS')
        : ($parsedUrl['pass'] ?? null));

// Soporte para sslmode
$envSsl = getenv('DB_SSLMODE');
if (!$envSsl && isset($parsedUrl['query'])) {
    parse_str($parsedUrl['query'], $queryParts);
    if (!empty($queryParts['sslmode'])) {
        $envSsl = $queryParts['sslmode'];
    }
}

// 3. Cargar configuración local si existe
if (file_exists(__DIR__ . '/local_config.php')) {
    require_once __DIR__ . '/local_config.php';
}

// 4. Parámetros de conexión finales con fallback para entorno local (XAMPP / Desarrollo)
if (!defined('DB_HOST')) define('DB_HOST', $envHost ?: '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', $envPort ?: '5432');
if (!defined('DB_NAME')) define('DB_NAME', $envName ?: 'db_clinica_vida_salud');
if (!defined('DB_USER')) define('DB_USER', $envUser ?: 'postgres');
if (!defined('DB_PASS')) define('DB_PASS', $envPass !== null ? $envPass : 'postgres');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', DB_PASS);
if (!defined('DB_SSLMODE')) define('DB_SSLMODE', $envSsl ?: (getenv('RENDER') ? 'require' : 'prefer'));
if (!defined('GEMINI_API_KEY')) define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');

class Database
{
    private static ?PDO $instance = null;
    private static ?string $lastError = null;

    /**
     * Obtiene la instancia única de conexión PDO (Patrón Singleton)
     *
     * @return PDO
     * @throws RuntimeException
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                "pgsql:host=%s;port=%s;dbname=%s;sslmode=%s;options='--client_encoding=UTF8'",
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_SSLMODE
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                self::$lastError = $e->getMessage();
                // Registrar el detalle técnico en los logs del servidor (Apache/Docker/Render)
                error_log("[Clínica Vida Salud - Database Connection Error] " . $e->getMessage());

                // Mensaje genérico seguro al usuario sin exponer credenciales ni estructura interna
                throw new RuntimeException("Error al conectar con la base de datos del sistema. Por favor, verifique la configuración o contacte al administrador.");
            }
        }

        return self::$instance;
    }

    /**
     * Prueba el estado de la conexión y retorna información detallada
     *
     * @return array
     */
    public static function testConnection(): array
    {
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->query("SELECT version() AS version, current_database() AS db, current_user AS usuario");
            $info = $stmt->fetch();
            return [
                'status'  => true,
                'message' => 'Conexión exitosa a PostgreSQL mediante PDO (pdo_pgsql)',
                'data'    => $info
            ];
        } catch (\Throwable $e) {
            error_log("[Clínica Vida Salud - Test Connection Failed] " . (self::$lastError ?? $e->getMessage()));
            return [
                'status'  => false,
                'message' => 'Error de conexión: No se pudo establecer comunicación con el servidor PostgreSQL.',
                'config'  => [
                    'host' => DB_HOST,
                    'port' => DB_PORT,
                    'db'   => DB_NAME,
                    'user' => DB_USER
                ]
            ];
        }
    }

    /**
     * Retorna el último mensaje de error registrado (para depuración interna)
     */
    public static function getLastError(): ?string
    {
        return self::$lastError;
    }
}
