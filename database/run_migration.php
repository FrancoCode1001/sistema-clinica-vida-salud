<?php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = Database::getConnection();
    $sql = file_get_contents(__DIR__ . '/migration_notificaciones_cuenta.sql');
    $pdo->exec($sql);
    echo "[MIGRATION] Migración de notificaciones y cuenta ejecutada exitosamente.\n";
} catch (\Throwable $e) {
    echo "[MIGRATION WARNING] " . $e->getMessage() . "\n";
}

