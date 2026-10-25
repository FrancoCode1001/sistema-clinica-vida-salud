<?php
/**
 * Instalador y Asistente de Base de Datos - Clínica Vida Salud
 * Permite ejecutar la migración inicial de database.sql con un solo clic
 */

require_once __DIR__ . '/db.php';

$mensaje = null;
$tipoMensaje = 'info';
$logs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbPass = $_POST['db_pass'] ?? DB_PASS;
    $dbHost = $_POST['db_host'] ?? DB_HOST;
    $dbPort = $_POST['db_port'] ?? DB_PORT;
    $dbUser = $_POST['db_user'] ?? DB_USER;
    $dbName = $_POST['db_name'] ?? DB_NAME;

    // Guardar en config/local_config.php para persistir las credenciales locales
    $localConfigContent = "<?php\n"
        . "define('DB_HOST', " . var_export($dbHost, true) . ");\n"
        . "define('DB_PORT', " . var_export($dbPort, true) . ");\n"
        . "define('DB_NAME', " . var_export($dbName, true) . ");\n"
        . "define('DB_USER', " . var_export($dbUser, true) . ");\n"
        . "define('DB_PASS', " . var_export($dbPass, true) . ");\n";
    file_put_contents(__DIR__ . '/local_config.php', $localConfigContent);

    try {
        // 1. Conectar a PostgreSQL (a la base por defecto 'postgres') para verificar/crear la BD
        $dsnBase = "pgsql:host={$dbHost};port={$dbPort};dbname=postgres";
        $pdoInit = new PDO($dsnBase, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $logs[] = "Conexión con el servidor PostgreSQL establecida.";

        // Verificar si la base de datos existe
        $stmt = $pdoInit->prepare("SELECT 1 FROM pg_database WHERE datname = :dbname");
        $stmt->execute([':dbname' => $dbName]);
        if (!$stmt->fetch()) {
            $pdoInit->exec("CREATE DATABASE \"{$dbName}\" WITH ENCODING 'UTF8'");
            $logs[] = "Base de datos '{$dbName}' creada exitosamente.";
        } else {
            $logs[] = "La base de datos '{$dbName}' ya existe.";
        }

        // 2. Conectar a la base de datos de la clínica
        $dsnClinica = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";
        $pdo = new PDO($dsnClinica, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $logs[] = "Conectado a la base de datos '{$dbName}'.";

        // 3. Leer y ejecutar database.sql
        $sqlPath = __DIR__ . '/../database/database.sql';
        if (!file_exists($sqlPath)) {
            throw new Exception("No se encontró el archivo {$sqlPath}");
        }

        $sqlContent = file_get_contents($sqlPath);
        $pdo->exec($sqlContent);
        $logs[] = "Script database.sql ejecutado correctamente (11 tablas, Triggers, SPs y Datos Semilla).";

        $mensaje = "¡Base de datos migrada y configurada exitosamente!";
        $tipoMensaje = "success";

    } catch (Exception $e) {
        $mensaje = "Error durante la instalación: " . $e->getMessage();
        $tipoMensaje = "danger";
        $logs[] = "ERROR: " . $e->getMessage();
    }
}

// Probar conexión actual
$estadoConexion = Database::testConnection();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador de Base de Datos | Clínica Vida Salud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/clinicaVidaSalud/assets/css/custom.css">
</head>
<body class="bg-light py-5">
<div class="container" style="max-width: 760px;">
    <div class="card card-custom p-4 shadow-sm border-0">
        <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
            <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 fs-3">
                <i class="bi bi-database-gear"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-1 text-dark">Instalador & Asistente de Base de Datos</h3>
                <p class="text-muted mb-0 small">Clínica Vida Salud &bull; PostgreSQL & PDO pdo_pgsql</p>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipoMensaje ?> d-flex align-items-center gap-2 mb-4" role="alert">
                <i class="bi bi-<?= $tipoMensaje === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> fs-4"></i>
                <div><?= htmlspecialchars($mensaje) ?></div>
            </div>
        <?php endif; ?>

        <!-- Estado actual de la conexión -->
        <div class="card p-3 mb-4 <?= $estadoConexion['status'] ? 'border-success bg-success-subtle' : 'border-warning bg-warning-subtle' ?>">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-bold mb-1">
                        <i class="bi bi-<?= $estadoConexion['status'] ? 'check2-circle text-success' : 'exclamation-circle text-warning' ?> me-2"></i>
                        Estado de Conexión: <?= $estadoConexion['status'] ? '<span class="text-success">Conectado</span>' : '<span class="text-danger">Pendiente</span>' ?>
                    </h6>
                    <small class="text-muted"><?= htmlspecialchars($estadoConexion['message']) ?></small>
                </div>
                <?php if ($estadoConexion['status']): ?>
                    <a href="/clinicaVidaSalud/views/auth/login.php" class="btn btn-success fw-semibold shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Ir al Login
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulario de Instalación / Parámetros -->
        <form method="POST" class="mb-4">
            <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-sliders me-2 text-primary"></i>Parámetros de Conexión a PostgreSQL</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Host</label>
                    <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars(DB_HOST) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Puerto</label>
                    <input type="text" name="db_port" class="form-control" value="<?= htmlspecialchars(DB_PORT) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Base de Datos</label>
                    <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars(DB_NAME) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Usuario PostgreSQL</label>
                    <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars(DB_USER) ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">Contraseña de PostgreSQL (usuario postgres)</label>
                    <input type="password" name="db_pass" class="form-control" placeholder="Ingrese la contraseña que definió al instalar PostgreSQL" value="<?= htmlspecialchars(DB_PASS) ?>">
                    <small class="text-muted">Si definió una contraseña al instalar PostgreSQL 16 (ej. <code>postgres</code>, <code>123456</code>, o su contraseña personalizada), ingrésela aquí.</small>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top d-flex gap-2 justify-content-end">
                <button type="submit" class="btn btn-primary fw-semibold px-4 py-2 shadow-sm">
                    <i class="bi bi-arrow-repeat me-2"></i>Crear BD y Ejecutar database.sql
                </button>
            </div>
        </form>

        <?php if (!empty($logs)): ?>
            <div class="mt-3">
                <h6 class="fw-bold text-muted small text-uppercase">Registro de Ejecución (Logs):</h6>
                <div class="bg-dark text-light p-3 rounded font-monospace small" style="max-height: 180px; overflow-y: auto;">
                    <?php foreach ($logs as $log): ?>
                        <div>&gt; <?= htmlspecialchars($log) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
