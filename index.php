<?php

/**
 * Enrutador Principal del Sistema - Clínica Vida Salud
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['usuario']['id_usuario'])) {
    header('Location: /clinicaVidaSalud/views/dashboard/index.php');
} else {
    header('Location: /clinicaVidaSalud/views/auth/login.php');
}
exit();
