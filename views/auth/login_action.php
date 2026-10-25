<?php
/**
 * Procesa la acción de Login llamando a AuthController (CU-01)
 */
require_once __DIR__ . '/../../app/controllers/AuthController.php';

$auth = new AuthController();
$auth->login();
