<?php
/**
 * Controlador: EspecialidadController (CU-03)
 * Gestión de Especialidades Médicas
 */

require_once __DIR__ . '/../models/Especialidad.php';
require_once __DIR__ . '/../helpers/AuthMiddleware.php';

class EspecialidadController
{
    public function __construct()
    {
        // Solo administradores pueden gestionar catálogo de especialidades
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
        $nombre = $_POST['nombre'] ?? '';
        $desc   = $_POST['descripcion'] ?? '';
        $uAuth  = AuthMiddleware::usuario();

        $res = Especialidad::crear($nombre, $desc, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: /clinicaVidaSalud/views/especialidades/index.php');
        exit();
    }

    private function update(): void
    {
        $id     = (int)($_POST['id_especialidad'] ?? 0);
        $nombre = $_POST['nombre'] ?? '';
        $desc   = $_POST['descripcion'] ?? '';
        $estado = $_POST['estado'] ?? 'ACTIVO';
        $uAuth  = AuthMiddleware::usuario();

        $res = Especialidad::actualizar($id, $nombre, $desc, $estado, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: /clinicaVidaSalud/views/especialidades/index.php');
        exit();
    }

    private function cambiarEstado(): void
    {
        $id          = (int)($_POST['id_especialidad'] ?? 0);
        $nuevoEstado = $_POST['nuevo_estado'] ?? 'INACTIVO';
        $uAuth       = AuthMiddleware::usuario();

        $res = Especialidad::cambiarEstado($id, $nuevoEstado, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: /clinicaVidaSalud/views/especialidades/index.php');
        exit();
    }
}
