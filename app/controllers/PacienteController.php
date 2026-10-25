<?php
/**
 * Controlador: PacienteController (CU-06)
 * Gestión de Pacientes de la Clínica
 */

require_once __DIR__ . '/../models/Paciente.php';
require_once __DIR__ . '/../helpers/AuthMiddleware.php';

class PacienteController
{
    public function __construct()
    {
        AuthMiddleware::verificarRoles(['Administrador', 'Recepcionista']);
    }

    public function handleRequest(): void
    {
        $action = $_POST['action'] ?? $_GET['action'] ?? 'index';

        switch ($action) {
            case 'store':
                $this->store();
                break;
            case 'update':
                $this->update();
                break;
            case 'cambiar_estado':
                $this->cambiarEstado();
                break;
            default:
                break;
        }
    }

    private function store(): void
    {
        $uAuth = AuthMiddleware::usuario();
        $res = Paciente::crear($_POST, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: /clinicaVidaSalud/views/pacientes/index.php');
        exit();
    }

    private function update(): void
    {
        $id = (int)($_POST['id_paciente'] ?? 0);
        $uAuth = AuthMiddleware::usuario();
        $res = Paciente::actualizar($id, $_POST, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: /clinicaVidaSalud/views/pacientes/index.php');
        exit();
    }

    private function cambiarEstado(): void
    {
        $id          = (int)($_POST['id_paciente'] ?? 0);
        $nuevoEstado = $_POST['nuevo_estado'] ?? 'INACTIVO';
        $uAuth       = AuthMiddleware::usuario();

        $res = Paciente::cambiarEstado($id, $nuevoEstado, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: /clinicaVidaSalud/views/pacientes/index.php');
        exit();
    }
}
