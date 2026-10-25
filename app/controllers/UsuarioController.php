<?php
/**
 * Controlador: UsuarioController (CU-02)
 * Gestión de Cuentas de Usuario y Roles del Sistema
 */

require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Rol.php';
require_once __DIR__ . '/../helpers/AuthMiddleware.php';

class UsuarioController
{
    public function __construct()
    {
        // Solo administradores pueden gestionar usuarios
        AuthMiddleware::verificarRoles(['Administrador']);
    }

    /**
     * Procesa peticiones de acción para usuarios
     */
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
        $res = Usuario::crear($_POST, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: /clinicaVidaSalud/views/usuarios/index.php');
        exit();
    }

    private function update(): void
    {
        $id = (int)($_POST['id_usuario'] ?? 0);
        $uAuth = AuthMiddleware::usuario();
        $res = Usuario::actualizar($id, $_POST, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: /clinicaVidaSalud/views/usuarios/index.php');
        exit();
    }

    private function cambiarEstado(): void
    {
        $id = (int)($_POST['id_usuario'] ?? 0);
        $nuevoEstado = $_POST['nuevo_estado'] ?? 'INACTIVO';
        $uAuth = AuthMiddleware::usuario();

        $res = Usuario::cambiarEstado($id, $nuevoEstado, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: /clinicaVidaSalud/views/usuarios/index.php');
        exit();
    }
}
