<?php
/**
 * Controlador: PerfilPacienteController (CU-07)
 * Gestión Integral de Perfil Clínico, Datos Personales, Contacto y Cuenta
 */

require_once __DIR__ . '/../models/PerfilMedico.php';
require_once __DIR__ . '/../models/Paciente.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../helpers/AuthMiddleware.php';

class PerfilPacienteController
{
    public function __construct()
    {
        AuthMiddleware::verificarAutenticacion();
    }

    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? 'update_expediente';

            switch ($action) {
                case 'update_datos_personales':
                    $this->updateDatosPersonales();
                    break;
                case 'update_expediente':
                    $this->updateExpediente();
                    break;
                case 'cambiar_password':
                    $this->cambiarPassword();
                    break;
                case 'update_preferencias':
                    $this->updatePreferencias();
                    break;
                default:
                    $this->updateExpediente();
                    break;
            }
        }
    }

    /**
     * 1 y 2. Actualizar Datos Personales e Información de Contacto
     */
    private function updateDatosPersonales(): void
    {
        $uAuth = AuthMiddleware::usuario();
        $id_paciente = (int)($_POST['id_paciente'] ?? 0);

        if (AuthMiddleware::esPaciente()) {
            $id_paciente = (int)($uAuth['id_paciente'] ?? 0);
        }

        // Restricción por rol: El médico tiene acceso clínico, no administrativo de filiación
        if (AuthMiddleware::esMedico()) {
            $_SESSION['flash_error'] = 'Permiso denegado: El personal médico no está autorizado a modificar los datos personales o de contacto del paciente. Para cambios de filiación, derivar a Recepción o Admisión.';
            header('Location: /clinicaVidaSalud/views/pacientes/perfil.php?id_paciente=' . $id_paciente . '&tab=datos');
            exit();
        }

        if ($id_paciente <= 0) {
            $_SESSION['flash_error'] = 'Identificador de paciente no válido.';
            header('Location: /clinicaVidaSalud/views/pacientes/perfil.php');
            exit();
        }

        $res = Paciente::actualizarDatosPersonalesYContacto($id_paciente, $_POST, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }

        header('Location: /clinicaVidaSalud/views/pacientes/perfil.php?id_paciente=' . $id_paciente . '&tab=datos');
        exit();
    }

    /**
     * Actualizar Expediente Clínico y Contacto de Emergencia
     */
    private function updateExpediente(): void
    {
        $uAuth = AuthMiddleware::usuario();
        $id_paciente = (int)($_POST['id_paciente'] ?? 0);

        if (AuthMiddleware::esPaciente()) {
            $id_paciente = (int)($uAuth['id_paciente'] ?? 0);
        }

        if ($id_paciente <= 0) {
            $_SESSION['flash_error'] = 'Identificador de paciente no válido.';
            header('Location: /clinicaVidaSalud/views/pacientes/perfil.php');
            exit();
        }

        $res = PerfilMedico::guardarOActualizar($id_paciente, $_POST, (int)$uAuth['id_usuario']);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }

        header('Location: /clinicaVidaSalud/views/pacientes/perfil.php?id_paciente=' . $id_paciente . '&tab=expediente');
        exit();
    }

    /**
     * 3. Configuración de Cuenta: Cambio de Contraseña
     */
    private function cambiarPassword(): void
    {
        $uAuth = AuthMiddleware::usuario();
        $id_paciente = (int)($_POST['id_paciente'] ?? 0);

        if (AuthMiddleware::esPaciente()) {
            $id_paciente = (int)($uAuth['id_paciente'] ?? 0);
        }

        if (AuthMiddleware::esMedico()) {
            $_SESSION['flash_error'] = 'Permiso denegado: El personal médico no está autorizado a modificar credenciales de pacientes.';
            header('Location: /clinicaVidaSalud/views/pacientes/perfil.php?id_paciente=' . $id_paciente . '&tab=datos');
            exit();
        }

        $id_usuario = (int)$uAuth['id_usuario'];
        $oldPass    = $_POST['old_password'] ?? '';
        $newPass    = $_POST['new_password'] ?? '';
        $confirm    = $_POST['confirm_password'] ?? '';

        if (empty($oldPass) || empty($newPass) || empty($confirm)) {
            $_SESSION['flash_error'] = 'Debe completar todos los campos de contraseña.';
            header('Location: /clinicaVidaSalud/views/pacientes/perfil.php?id_paciente=' . $id_paciente . '&tab=cuenta');
            exit();
        }

        if ($newPass !== $confirm) {
            $_SESSION['flash_error'] = 'La nueva contraseña y su confirmación no coinciden.';
            header('Location: /clinicaVidaSalud/views/pacientes/perfil.php?id_paciente=' . $id_paciente . '&tab=cuenta');
            exit();
        }

        $res = Usuario::cambiarPassword($id_usuario, $oldPass, $newPass, $id_usuario);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }

        header('Location: /clinicaVidaSalud/views/pacientes/perfil.php?id_paciente=' . $id_paciente . '&tab=cuenta');
        exit();
    }

    /**
     * 3. Configuración de Cuenta: Notificaciones y Privacidad
     */
    private function updatePreferencias(): void
    {
        $uAuth = AuthMiddleware::usuario();
        $id_paciente = (int)($_POST['id_paciente'] ?? 0);

        if (AuthMiddleware::esPaciente()) {
            $id_paciente = (int)($uAuth['id_paciente'] ?? 0);
        }

        if (AuthMiddleware::esMedico()) {
            $_SESSION['flash_error'] = 'Permiso denegado: El personal médico no está autorizado a modificar preferencias de cuenta de pacientes.';
            header('Location: /clinicaVidaSalud/views/pacientes/perfil.php?id_paciente=' . $id_paciente . '&tab=datos');
            exit();
        }

        $id_usuario = (int)$uAuth['id_usuario'];
        $notif = isset($_POST['notificaciones_activas']);
        $em    = isset($_POST['privacidad_contacto_emergencia']);
        $rec   = isset($_POST['privacidad_recordatorios']);

        $res = Usuario::actualizarPreferencias($id_usuario, $notif, $em, $rec, $id_usuario);

        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }

        header('Location: /clinicaVidaSalud/views/pacientes/perfil.php?id_paciente=' . $id_paciente . '&tab=cuenta');
        exit();
    }
}
