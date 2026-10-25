<?php
/**
 * Controlador: CitaController (CU-09, CU-10, CU-11)
 * Programación, Reprogramación y Cancelación de Citas Médicas
 */

require_once __DIR__ . '/../models/CitaMedica.php';
require_once __DIR__ . '/../helpers/AuthMiddleware.php';

class CitaController
{
    public function __construct()
    {
        AuthMiddleware::verificarAutenticacion();
    }

    public function handleRequest(): void
    {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        switch ($action) {
            case 'store':
                $this->store();
                break;
            case 'reprogramar':
                $this->reprogramar();
                break;
            case 'cancelar':
                $this->cancelar();
                break;
            default:
                break;
        }
    }

    private function store(): void
    {
        $uAuth = AuthMiddleware::usuario();
        $datos = $_POST;

        // Si es rol Paciente, forzar que la cita sea para sí mismo
        if (AuthMiddleware::esPaciente()) {
            $datos['id_paciente'] = $uAuth['id_paciente'];
        }

        $res = CitaMedica::programar($datos, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
            header('Location: /clinicaVidaSalud/views/consultas/historial.php?id_paciente=' . (int)$datos['id_paciente']);
        } else {
            $_SESSION['flash_error'] = $res['message'];
            header('Location: /clinicaVidaSalud/views/citas/programar.php');
        }
        exit();
    }

    private function reprogramar(): void
    {
        $uAuth = AuthMiddleware::usuario();
        $id_cita      = (int)($_POST['id_cita'] ?? 0);
        $nueva_fecha  = trim($_POST['nueva_fecha'] ?? '');
        $nuevo_inicio = trim($_POST['nuevo_inicio'] ?? '');
        $nuevo_fin    = trim($_POST['nuevo_fin'] ?? '');

        $res = CitaMedica::reprogramar($id_cita, $nueva_fecha, $nuevo_inicio, $nuevo_fin, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: /clinicaVidaSalud/views/citas/reprogramar.php');
        exit();
    }

    private function cancelar(): void
    {
        $uAuth = AuthMiddleware::usuario();
        $id_cita = (int)($_POST['id_cita'] ?? 0);
        $motivo  = trim($_POST['motivo_cancelacion'] ?? '');

        $res = CitaMedica::cancelar($id_cita, $motivo, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }

        $redirect = $_POST['redirect'] ?? '/clinicaVidaSalud/views/citas/cancelar.php';
        header('Location: ' . $redirect);
        exit();
    }
}
