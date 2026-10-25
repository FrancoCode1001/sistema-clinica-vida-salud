<?php
/**
 * API AJAX - Respuestas dinámicas en tiempo real (JSON)
 * Soporta CU-08 Consulta de Disponibilidad y filtrado dinámico de formularios
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../app/models/CitaMedica.php';
require_once __DIR__ . '/../app/models/Medico.php';

AuthMiddleware::iniciarSesion();
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['status' => false, 'message' => 'Sesión expirada o no autenticada.']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'disponibilidad':
        $id_medico  = (int)($_GET['id_medico'] ?? $_POST['id_medico'] ?? 0);
        $fecha_cita = trim($_GET['fecha_cita'] ?? $_POST['fecha_cita'] ?? '');

        if ($id_medico <= 0 || empty($fecha_cita)) {
            echo json_encode(['status' => false, 'message' => 'Parámetros incompletos (médico y fecha requeridos).', 'slots' => []]);
            exit();
        }

        $res = CitaMedica::consultarDisponibilidad($id_medico, $fecha_cita);
        echo json_encode($res);
        exit();

    case 'medicos_por_especialidad':
        $id_esp = (int)($_GET['id_especialidad'] ?? $_POST['id_especialidad'] ?? 0);
        $medicos = Medico::listarTodos(null, $id_esp > 0 ? $id_esp : null, true);
        echo json_encode(['status' => true, 'medicos' => $medicos]);
        exit();

    default:
        echo json_encode(['status' => false, 'message' => 'Acción no soportada.']);
        exit();
}
