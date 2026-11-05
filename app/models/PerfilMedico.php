<?php
/**
 * Modelo: PerfilMedico (perfiles_medicos)
 * CU-07: Gestionar Perfil Clínico del Paciente (Sección 3.4)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/AuditoriaHelper.php';

class PerfilMedico
{
    /**
     * Obtiene el expediente clínico y contacto de emergencia de un paciente
     */
    public static function obtenerPorPaciente(int $id_paciente): ?array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT pm.*, p.codigo_paciente, p.ci, p.nombres, p.apellidos, p.fecha_nacimiento, p.sexo, p.telefono, p.email, p.direccion,
                       p.id_usuario, u.username, u.notificaciones_activas, u.privacidad_contacto_emergencia, u.privacidad_recordatorios
                FROM pacientes p
                LEFT JOIN perfiles_medicos pm ON p.id_paciente = pm.id_paciente
                LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
                WHERE p.id_paciente = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id_paciente]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Guarda o actualiza los antecedentes clínicos y contacto de emergencia (Upsert)
     */
    public static function guardarOActualizar(int $id_paciente, array $datos, ?int $id_usuario_auth = null): array
    {
        $pdo = Database::getConnection();

        $tipo_sangre     = trim($datos['tipo_sangre'] ?? 'O+');
        $alergias        = trim($datos['alergias'] ?? 'Ninguna');
        $enfermedades    = trim($datos['enfermedades_cronicas'] ?? 'Ninguna');
        $medicamentos    = trim($datos['medicamentos'] ?? 'Ninguno');
        $antecedentes    = trim($datos['antecedentes'] ?? 'Sin antecedentes relevantes');
        $emergencia_nom  = trim($datos['emergencia_nombre'] ?? '');
        $emergencia_par  = trim($datos['emergencia_parentesco'] ?? '');
        $emergencia_tel  = trim($datos['emergencia_telefono'] ?? '');

        try {
            $sql = "INSERT INTO perfiles_medicos (id_paciente, tipo_sangre, alergias, enfermedades_cronicas, medicamentos, antecedentes, emergencia_nombre, emergencia_parentesco, emergencia_telefono)
                    VALUES (:id_p, :sangre, :alergias, :enf, :meds, :ant, :em_nom, :em_par, :em_tel)
                    ON CONFLICT (id_paciente) DO UPDATE SET
                        tipo_sangre           = EXCLUDED.tipo_sangre,
                        alergias              = EXCLUDED.alergias,
                        enfermedades_cronicas = EXCLUDED.enfermedades_cronicas,
                        medicamentos          = EXCLUDED.medicamentos,
                        antecedentes          = EXCLUDED.antecedentes,
                        emergencia_nombre     = EXCLUDED.emergencia_nombre,
                        emergencia_parentesco = EXCLUDED.emergencia_parentesco,
                        emergencia_telefono   = EXCLUDED.emergencia_telefono";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_p'     => $id_paciente,
                ':sangre'   => $tipo_sangre,
                ':alergias' => !empty($alergias) ? $alergias : 'Ninguna',
                ':enf'      => !empty($enfermedades) ? $enfermedades : 'Ninguna',
                ':meds'     => !empty($medicamentos) ? $medicamentos : 'Ninguno',
                ':ant'      => !empty($antecedentes) ? $antecedentes : 'Sin antecedentes relevantes',
                ':em_nom'   => $emergencia_nom,
                ':em_par'   => $emergencia_par,
                ':em_tel'   => $emergencia_tel
            ]);

            AuditoriaHelper::registrar(
                $id_usuario_auth,
                'UPDATE',
                'perfiles_medicos',
                "Actualización del perfil clínico y contacto de emergencia del paciente ID {$id_paciente}"
            );

            return ['status' => true, 'message' => 'Perfil clínico y contacto de emergencia guardados exitosamente.'];

        } catch (PDOException $e) {
            return ['status' => false, 'message' => 'Error al guardar perfil clínico: ' . $e->getMessage()];
        }
    }
}
