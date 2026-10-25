<?php
/**
 * Procesa el cierre de sesión (CU-01)
 */
require_once __DIR__ . '/../../app/controllers/AuthController.php';

$auth = new AuthController();
$auth->logout();
