<?php
require_once __DIR__ . '/../config/db.php';
$pdo = Database::getConnection();
$pdo->exec("UPDATE pacientes SET nombres = 'María' WHERE id_paciente = 1");
$pdo->exec("UPDATE usuarios SET notificaciones_activas = TRUE, privacidad_contacto_emergencia = TRUE, privacidad_recordatorios = TRUE WHERE id_usuario = 5");
echo "RESTAURADO_CORRECTAMENTE\n";
