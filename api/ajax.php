<?php
/**
 * API AJAX - Respuestas dinámicas en tiempo real (JSON)
 * Soporta CU-08 Consulta de Disponibilidad y filtrado dinámico de formularios
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../app/models/CitaMedica.php';
require_once __DIR__ . '/../app/models/Medico.php';
require_once __DIR__ . '/../app/models/Notificacion.php';

AuthMiddleware::iniciarSesion();
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['status' => false, 'message' => 'Sesión expirada o no autenticada.']);
    exit();
}

$id_usuario_auth = (int)($_SESSION['usuario']['id_usuario'] ?? 0);
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

    case 'notificaciones_obtener':
        if (!empty($_SESSION['usuario']['id_medico'])) {
            Notificacion::verificarRecordatorioDiarioMedico($id_usuario_auth, (int)$_SESSION['usuario']['id_medico']);
        }
        $notificaciones = Notificacion::listarPorUsuario($id_usuario_auth, 12);
        $noLeidas = Notificacion::contarNoLeidas($id_usuario_auth);
        echo json_encode([
            'status'         => true,
            'no_leidas'      => $noLeidas,
            'notificaciones' => $notificaciones
        ]);
        exit();

    case 'notificaciones_marcar_leida':
        $id_notif = (int)($_POST['id_notificacion'] ?? $_GET['id_notificacion'] ?? 0);
        if ($id_notif > 0) {
            Notificacion::marcarLeida($id_notif, $id_usuario_auth);
        }
        echo json_encode(['status' => true]);
        exit();

    case 'notificaciones_marcar_todas':
        Notificacion::marcarTodasLeidas($id_usuario_auth);
        echo json_encode(['status' => true]);
        exit();

    default:
        echo json_encode(['status' => false, 'message' => 'Acción no soportada.']);
        exit();
}
