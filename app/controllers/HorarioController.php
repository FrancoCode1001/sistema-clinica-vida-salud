<?php
/**
 * Controlador: HorarioController (CU-05)
 * Configuración de Horarios de Atención Médica
 */

require_once __DIR__ . '/../models/HorarioAtencion.php';
require_once __DIR__ . '/../helpers/AuthMiddleware.php';

class HorarioController
{
    public function __construct()
    {
        AuthMiddleware::verificarRoles(['Administrador']);
    }

    public function handleRequest(): void
    {
        $action = $_POST['action'] ?? $_GET['action'] ?? 'index';

        switch ($action) {
            case 'store':
                $this->store();
                break;
            case 'delete':
                $this->delete();
                break;
            default:
                break;
        }
    }

    private function store(): void
    {
        $id_medico   = (int)($_POST['id_medico'] ?? 0);
        $dia_semana  = (int)($_POST['dia_semana'] ?? 0);
        $hora_inicio = $_POST['hora_inicio'] ?? '';
        $hora_fin    = $_POST['hora_fin'] ?? '';
        $duracion    = (int)($_POST['duracion_minutos'] ?? 30);
        $uAuth       = AuthMiddleware::usuario();

        $res = HorarioAtencion::crear($id_medico, $dia_semana, $hora_inicio, $hora_fin, $duracion, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }

        $redirect = '/clinicaVidaSalud/views/horarios/index.php';
        if ($id_medico > 0) {
            $redirect .= '?id_medico=' . $id_medico;
        }
        header('Location: ' . $redirect);
        exit();
    }

    private function delete(): void
    {
        $id_horario = (int)($_POST['id_horario'] ?? 0);
        $id_medico  = (int)($_POST['id_medico'] ?? 0);
        $uAuth      = AuthMiddleware::usuario();

        $res = HorarioAtencion::eliminar($id_horario, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }

        $redirect = '/clinicaVidaSalud/views/horarios/index.php';
        if ($id_medico > 0) {
            $redirect .= '?id_medico=' . $id_medico;
        }
        header('Location: ' . $redirect);
        exit();
    }
}
