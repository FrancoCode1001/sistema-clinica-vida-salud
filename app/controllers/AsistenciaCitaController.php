<?php
/**
 * Controlador: AsistenciaCitaController (CU-12)
 * Control de Asistencia y Estados de Cita (RF10 / RN10 / RN12)
 */

require_once __DIR__ . '/../models/CitaMedica.php';
require_once __DIR__ . '/../helpers/AuthMiddleware.php';

class AsistenciaCitaController
{
    public function __construct()
    {
        AuthMiddleware::verificarRoles(['Administrador', 'Recepcionista', 'Medico']);
    }

    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $uAuth        = AuthMiddleware::usuario();
            $id_cita      = (int)($_POST['id_cita'] ?? 0);
            $nuevo_estado = $_POST['nuevo_estado'] ?? '';
            $observacion  = $_POST['observacion'] ?? '';

            // Si es médico, solo puede marcar como Atendida
            if (AuthMiddleware::esMedico() && $nuevo_estado !== 'ATENDIDA') {
                $_SESSION['flash_error'] = 'RN12: El profesional médico solo tiene permisos para registrar la atención de sus consultas.';
                header('Location: /clinicaVidaSalud/views/citas/asistencia.php');
                exit();
            }

            $res = CitaMedica::cambiarEstado($id_cita, $nuevo_estado, $observacion, (int)$uAuth['id_usuario']);

            if ($res['status']) {
                $_SESSION['flash_success'] = $res['message'];
            } else {
                $_SESSION['flash_error'] = $res['message'];
            }

            $redirect = $_POST['redirect'] ?? '/clinicaVidaSalud/views/citas/asistencia.php';
            header('Location: ' . $redirect);
            exit();
        }
    }
}
