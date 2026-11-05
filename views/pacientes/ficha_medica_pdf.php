<?php
/**
 * Vista de Exportación e Impresión: Ficha Médica del Paciente en PDF (CU-07)
 * Generada mediante Dompdf
 * Sistema de Gestión de Citas Médicas - Clínica Vida Salud
 */

require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/helpers/PdfHelper.php';
require_once __DIR__ . '/../../app/models/PerfilMedico.php';
require_once __DIR__ . '/../../app/models/CitaMedica.php';

AuthMiddleware::verificarAutenticacion();

$usuarioAuth    = AuthMiddleware::usuario();
$idUsuarioAuth  = (int)($usuarioAuth['id_usuario'] ?? 0);
$idPacienteAuth = (int)($usuarioAuth['id_paciente'] ?? 0);

$id_paciente = isset($_GET['id_paciente']) ? (int)$_GET['id_paciente'] : 0;
$descargar   = isset($_GET['download']) && $_GET['download'] === '1';

if ($id_paciente <= 0) {
    if (AuthMiddleware::esPaciente()) {
        $id_paciente = $idPacienteAuth;
        if ($id_paciente <= 0 && $idUsuarioAuth > 0) {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT id_paciente FROM pacientes WHERE id_usuario = :u LIMIT 1");
            $stmt->execute([':u' => $idUsuarioAuth]);
            $id_paciente = (int)$stmt->fetchColumn();
        }
    }
}

$perfil = PerfilMedico::obtenerPorPaciente($id_paciente);

if (!$perfil) {
    http_response_code(404);
    die('Ficha médica no encontrada o paciente no registrado.');
}

// Control de acceso: los pacientes solo pueden ver su propia ficha
if (AuthMiddleware::esPaciente() && (int)$perfil['id_usuario'] !== $idUsuarioAuth) {
    http_response_code(403);
    die('Acceso denegado: no tiene permisos para consultar esta ficha médica.');
}

// Calcular edad
$edadTexto = 'No especificada';
if (!empty($perfil['fecha_nacimiento'])) {
    try {
        $fechaNac = new DateTime($perfil['fecha_nacimiento']);
        $hoy = new DateTime();
        $edadTexto = $fechaNac->diff($hoy)->y . ' años';
    } catch (Exception $e) {
        $edadTexto = 'N/D';
    }
}

// Obtener exclusivamente próximas citas médicas programadas (vigentes a partir de hoy)
$hoy = date('Y-m-d');
$horaActual = date('H:i:s');
$citasProgramadas = CitaMedica::listar([
    'id_paciente'  => $id_paciente,
    'estado'       => 'PROGRAMADA',
    'fecha_inicio' => $hoy
]);

// Ordenar cronológicamente (las citas más próximas primero)
usort($citasProgramadas, function ($a, $b) {
    return strcmp($a['fecha_cita'] . ' ' . $a['hora_inicio'], $b['fecha_cita'] . ' ' . $b['hora_inicio']);
});

// Excluir citas de la fecha actual cuyo horario de atención ya haya finalizado
$citasProgramadas = array_values(array_filter($citasProgramadas, function ($c) use ($hoy, $horaActual) {
    if ($c['fecha_cita'] === $hoy && $c['hora_fin'] < $horaActual) {
        return false;
    }
    return true;
}));

$fechaEmision = date('d/m/Y H:i');
$estilosBase = PdfHelper::getEstilosBase();

$html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha Médica - {$perfil['codigo_paciente']}</title>
    {$estilosBase}
</head>
<body>

    <!-- Encabezado Institucional -->
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <div class="clinic-brand">Clínica Vida Salud</div>
                <div class="clinic-tagline">Centro Médico Integral &middot; Especialidades Ambulatorias</div>
                <div class="clinic-tagline">Dirección: Av. Principal #123, Zona Central &middot; Teléfono: (+591) 4-4567890</div>
            </td>
            <td class="doc-title-container" style="width: 40%;">
                <div class="doc-title">Expediente Clínico</div>
                <div class="doc-meta"><strong>CÓDIGO:</strong> {$perfil['codigo_paciente']}</div>
                <div class="doc-meta"><strong>FECHA EMISIÓN:</strong> {$fechaEmision}</div>
            </td>
        </tr>
    </table>

    <!-- 1. Datos Generales del Paciente -->
    <div class="section-title">1. Datos Generales del Paciente</div>
    <table class="info-grid">
        <tr>
            <td class="label">Nombre Completo:</td>
            <td class="value"><strong>{$perfil['nombres']} {$perfil['apellidos']}</strong></td>
            <td class="label">Documento C.I.:</td>
            <td class="value"><strong>{$perfil['ci']}</strong></td>
        </tr>
        <tr>
            <td class="label">Fecha Nacimiento:</td>
            <td class="value">{$perfil['fecha_nacimiento']} ({$edadTexto})</td>
            <td class="label">Sexo Biológico:</td>
            <td class="value">{$perfil['sexo']}</td>
        </tr>
        <tr>
            <td class="label">Teléfono / Celular:</td>
            <td class="value">{$perfil['telefono']}</td>
            <td class="label">Correo Electrónico:</td>
            <td class="value">{$perfil['email']}</td>
        </tr>
        <tr>
            <td class="label">Dirección:</td>
            <td class="value" colspan="3">{$perfil['direccion']}</td>
        </tr>
    </table>

    <!-- 2. Antecedentes Médicos y Factores Clínicos -->
    <div class="section-title">2. Factores Clínicos y Antecedentes Médicos</div>
    <table class="info-grid" style="margin-bottom: 6px;">
        <tr>
            <td class="label" style="width: 25%;">Grupo Sanguíneo y Rh:</td>
            <td class="value" style="width: 75%;">
                <span class="badge badge-danger" style="font-size: 11px; padding: 3px 8px;">
                    GRUPO {$perfil['tipo_sangre']}
                </span>
            </td>
        </tr>
    </table>

    <div class="box-alerta">
        <strong style="color: #92400e;">ALERGIAS IDENTIFICADAS:</strong><br>
        <span style="color: #78350f;">{$perfil['alergias']}</span>
    </div>

    <table class="info-grid">
        <tr>
            <td class="label">Patologías / Enfermedades Crónicas:</td>
            <td class="value">{$perfil['enfermedades_cronicas']}</td>
        </tr>
        <tr>
            <td class="label">Medicamentos en Uso Habitual:</td>
            <td class="value">{$perfil['medicamentos']}</td>
        </tr>
        <tr>
            <td class="label">Antecedentes Quirúrgicos / Médicos:</td>
            <td class="value">{$perfil['antecedentes']}</td>
        </tr>
    </table>

    <!-- 3. Contacto de Emergencia -->
    <div class="section-title">3. Contacto en Caso de Emergencia</div>
    <table class="info-grid">
        <tr>
            <td class="label">Nombre de Contacto:</td>
            <td class="value">{$perfil['emergencia_nombre']}</td>
            <td class="label">Parentesco:</td>
            <td class="value">{$perfil['emergencia_parentesco']}</td>
        </tr>
        <tr>
            <td class="label">Teléfono de Emergencia:</td>
            <td class="value" colspan="3"><strong>{$perfil['emergencia_telefono']}</strong></td>
        </tr>
    </table>

    <!-- 4. Próximas Citas Médicas Programadas -->
    <div class="section-title">4. Próximas Citas Médicas Programadas</div>
HTML;

if (!empty($citasProgramadas)) {
    $html .= '
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 14%;">Código</th>
                <th style="width: 15%;">Fecha</th>
                <th style="width: 15%;">Horario</th>
                <th style="width: 22%;">Especialidad</th>
                <th style="width: 22%;">Médico Especialista</th>
                <th style="width: 12%; text-align: center;">Estado</th>
            </tr>
        </thead>
        <tbody>';
    foreach ($citasProgramadas as $c) {
        $fechaFormateada = date('d/m/Y', strtotime($c['fecha_cita']));
        $horarioFormateado = substr($c['hora_inicio'], 0, 5) . ' - ' . substr($c['hora_fin'], 0, 5);

        $html .= '<tr>
            <td><strong style="color: #0284c7;">' . htmlspecialchars($c['codigo_cita']) . '</strong></td>
            <td>' . $fechaFormateada . '</td>
            <td>' . $horarioFormateado . '</td>
            <td>' . htmlspecialchars($c['especialidad_nombre']) . '</td>
            <td>Dr(a). ' . htmlspecialchars($c['medico_nombre']) . '</td>
            <td style="text-align: center;"><span class="badge badge-primary">PROGRAMADA</span></td>
        </tr>';
    }
    $html .= '</tbody></table>';
} else {
    $html .= '<p style="color: #64748b; font-style: italic; margin: 6px 0 14px 4px;">El paciente no tiene citas médicas programadas pendientes en este momento.</p>';
}

$nombreUsuarioEmisor = htmlspecialchars($usuarioAuth['nombre_completo'] ?? ($usuarioAuth['username'] ?? 'Sistema Vida Salud'));

$html .= <<<HTML
    <!-- Área de Firmas -->
    <table class="signature-area">
        <tr>
            <td style="width: 50%; vertical-align: bottom;">
                <div style="font-size: 8.5px; color: #64748b;">
                    Documento emitido por: <strong>{$nombreUsuarioEmisor}</strong><br>
                    Clínica Vida Salud &middot; Sistema de Gestión Médica
                </div>
            </td>
            <td style="width: 50%; text-align: right; vertical-align: bottom;">
                <div style="display: inline-block; width: 220px; text-align: center; border-top: 1px solid #334155; padding-top: 5px;">
                    <span style="font-size: 9px; font-weight: bold; color: #1e293b;">SELLO Y FIRMA MÉDICA</span><br>
                    <span style="font-size: 8px; color: #64748b;">Médico Tratante / Responsable</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer-legal">
        Este documento es un extracto clínico confidencial emitido bajo la Ley de Protección de Datos y Deontología Médica. Queda prohibida su reproducción no autorizada.
    </div>

</body>
</html>
HTML;

$nombreArchivo = 'Ficha_Medica_' . preg_replace('/[^A-Za-z0-9_-]/', '', $perfil['codigo_paciente']) . '.pdf';
PdfHelper::streamHtml($html, $nombreArchivo, $descargar, 'A4', 'portrait');

