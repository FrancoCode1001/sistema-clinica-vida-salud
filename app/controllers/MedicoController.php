<?php
/**
 * Controlador: MedicoController (CU-04)
 * Gestión de Médicos y Especialidades Asociadas
 */

require_once __DIR__ . '/../models/Medico.php';
require_once __DIR__ . '/../helpers/AuthMiddleware.php';

class MedicoController
{
    public function __construct()
    {
        // Solo administradores pueden crear/modificar médicos
        AuthMiddleware::verificarRoles(['Administrador']);
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
        $especialidades = $_POST['especialidades'] ?? [];
        $uAuth = AuthMiddleware::usuario();

        $res = Medico::crear($_POST, $especialidades, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: /clinicaVidaSalud/views/medicos/index.php');
        exit();
    }

    private function update(): void
    {
        $id = (int)($_POST['id_medico'] ?? 0);
        $especialidades = $_POST['especialidades'] ?? [];
        $uAuth = AuthMiddleware::usuario();

        $res = Medico::actualizar($id, $_POST, $especialidades, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: /clinicaVidaSalud/views/medicos/index.php');
        exit();
    }

    private function cambiarEstado(): void
    {
        $id          = (int)($_POST['id_medico'] ?? 0);
        $nuevoEstado = $_POST['nuevo_estado'] ?? 'INACTIVO';
        $uAuth       = AuthMiddleware::usuario();

        $res = Medico::cambiarEstado($id, $nuevoEstado, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: /clinicaVidaSalud/views/medicos/index.php');
        exit();
    }
}
