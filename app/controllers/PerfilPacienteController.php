<?php
/**
 * Controlador: PerfilPacienteController (CU-07)
 * Gestión de Perfil Clínico y Contacto de Emergencia
 */

require_once __DIR__ . '/../models/PerfilMedico.php';
require_once __DIR__ . '/../models/Paciente.php';
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
            $this->update();
        }
    }

    private function update(): void
    {
        $uAuth = AuthMiddleware::usuario();
        $id_paciente = (int)($_POST['id_paciente'] ?? 0);

        // Si es rol Paciente, solo puede editar su propio perfil
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

        header('Location: /clinicaVidaSalud/views/pacientes/perfil.php?id_paciente=' . $id_paciente);
        exit();
    }
}
