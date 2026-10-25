<?php
/**
 * Controlador: ConsultaAgendaController (CU-13)
 * Consulta de Agenda Médica Diaria (RF11) e Historial de Citas por Paciente (RF12)
 */

require_once __DIR__ . '/../models/CitaMedica.php';
require_once __DIR__ . '/../models/Paciente.php';
require_once __DIR__ . '/../helpers/AuthMiddleware.php';

class ConsultaAgendaController
{
    public function __construct()
    {
        AuthMiddleware::verificarAutenticacion();
    }

    /**
     * Consulta la agenda diaria ordenada cronológicamente (RF11)
     */
    public function obtenerAgendaDiaria(?int $id_medico, string $fecha, ?string $estado = null): array
    {
        $filtros = ['fecha_cita' => $fecha];
        if (!empty($id_medico)) {
            $filtros['id_medico'] = $id_medico;
        }
        if (!empty($estado)) {
            $filtros['estado'] = $estado;
        }
        return CitaMedica::listar($filtros);
    }

    /**
     * Consulta el historial completo de citas de un paciente (RF12)
     */
    public function obtenerHistorialPaciente(int $id_paciente): array
    {
        return CitaMedica::listar(['id_paciente' => $id_paciente]);
    }
}
