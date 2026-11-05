<?php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = Database::getConnection();
    $sql = file_get_contents(__DIR__ . '/migration_notificaciones_cuenta.sql');
    $pdo->exec($sql);
    echo "MIGRACION_EXITOSA\n";
} catch (Exception $e) {
    echo "ERROR_MIGRACION: " . $e->getMessage() . "\n";
    exit(1);
}
