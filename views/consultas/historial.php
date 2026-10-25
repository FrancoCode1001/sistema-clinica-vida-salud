<?php

/**
 * Vista: Historial de Citas por Paciente (CU-13 Flujo B)
 * Paquete 5: Consultas y Reportes (RF12)
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/CitaMedica.php';
require_once __DIR__ . '/../../app/models/Paciente.php';
require_once __DIR__ . '/../../app/models/PerfilMedico.php';
require_once __DIR__ . '/../../app/controllers/ConsultaAgendaController.php';

AuthMiddleware::verificarAutenticacion();
$usuario = AuthMiddleware::usuario();

$id_paciente = 0;
if (AuthMiddleware::esPaciente()) {
    $id_paciente = (int)($usuario['id_paciente'] ?? 0);
} else {
    $id_paciente = !empty($_GET['id_paciente']) ? (int)$_GET['id_paciente'] : 0;
}

$listaPacientes = [];
if (!AuthMiddleware::esPaciente()) {
    $listaPacientes = Paciente::listarTodos(null, true);
    if ($id_paciente === 0 && !empty($listaPacientes)) {
        $id_paciente = (int)$listaPacientes[0]['id_paciente'];
    }
}

$pacienteInfo = null;
$citasHistorial = [];
if ($id_paciente > 0) {
    $pacienteInfo = PerfilMedico::obtenerPorPaciente($id_paciente);
    $controller = new ConsultaAgendaController();
    $citasHistorial = $controller->obtenerHistorialPaciente($id_paciente);
}

$pageTitle = 'Historial de Citas por Paciente';
$currentPage = 'historial';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>

<!-- Encabezado de Caso de Uso para Prototipos de UI (Sección 4.4) -->
<div class="page-header-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div>
                <span class="cu-package-badge">CONSULTAS Y REPORTES</span>
            </div>
            <h3 class="fw-bold mb-1 text-dark">Historial Clínico de Citas por Paciente</h3>
            <p class="text-muted mb-0 small">
                Trazabilidad integral de citas pasadas, atendidas, inasistencias y canceladas de cada paciente.
            </p>
        </div>
        <?php if ($id_paciente > 0): ?>
            <div>
                <a href="/clinicaVidaSalud/views/pacientes/perfil.php?id_paciente=<?= $id_paciente ?>" class="btn btn-outline-info d-flex align-items-center gap-2">
                    <i class="bi bi-clipboard2-pulse"></i>
                    <span>Ver Perfil Clínico</span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Selector de Paciente -->
<?php if (!AuthMiddleware::esPaciente() && !empty($listaPacientes)): ?>
    <div class="card card-custom p-3 mb-4">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-person-fill text-primary"></i></span>
                    <select name="id_paciente" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($listaPacientes as $p): ?>
                            <option value="<?= $p['id_paciente'] ?>" <?= $id_paciente === (int)$p['id_paciente'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['codigo_paciente'] . ' - ' . $p['nombres'] . ' ' . $p['apellidos'] . ' (CI: ' . $p['ci'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search me-1"></i>Consultar Historial
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if (!$pacienteInfo): ?>
    <div class="card card-custom p-5 text-center text-muted">
        <i class="bi bi-folder2-open fs-1 text-primary mb-2"></i>
        <h5>No se ha seleccionado ningún paciente</h5>
    </div>
<?php else: ?>
    <!-- Resumen del Paciente -->
    <div class="card card-custom p-3 mb-4 border-start border-4 border-primary">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <span class="badge bg-light text-dark font-monospace border me-2"><?= htmlspecialchars($pacienteInfo['codigo_paciente']) ?></span>
                <span class="fw-bold fs-5 text-dark"><?= htmlspecialchars($pacienteInfo['nombres'] . ' ' . $pacienteInfo['apellidos']) ?></span>
                <span class="text-muted ms-2 small">CI: <?= htmlspecialchars($pacienteInfo['ci']) ?></span>
            </div>
            <div class="small text-muted">
                <span class="me-3"><strong>Grupo Sanguíneo:</strong> <span class="badge bg-danger-subtle text-danger"><?= htmlspecialchars($pacienteInfo['tipo_sangre'] ?? 'N/D') ?></span></span>
                <span><strong>Total Citas Registradas:</strong> <span class="badge bg-primary"><?= count($citasHistorial) ?></span></span>
            </div>
        </div>
    </div>

    <!-- Tabla Histórica de Citas -->
    <div class="card card-custom">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-clock-history text-primary me-2"></i>Registro Histórico de Consultas</span>
            <span class="badge bg-light text-dark border">Citas: <?= count($citasHistorial) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle">
                <thead>
                    <tr>
                        <th>Código Cita</th>
                        <th>Fecha de Cita</th>
                        <th>Horario</th>
                        <th>Médico Especialista</th>
                        <th>Especialidad</th>
                        <th>Motivo de Consulta</th>
                        <th>Estado</th>
                        <th>Fecha Registro</th>
                        <th class="text-end">Trazabilidad</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($citasHistorial)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x fs-2 text-primary d-block mb-2"></i>
                                Este paciente no cuenta con citas médicas registradas en su historial.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($citasHistorial as $c): ?>
                            <tr>
                                <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($c['codigo_cita']) ?></td>
                                <td>
                                    <i class="bi bi-calendar me-1 text-muted"></i>
                                    <strong><?= date('d/m/Y', strtotime($c['fecha_cita'])) ?></strong>
                                </td>
                                <td>
                                    <i class="bi bi-clock me-1 text-muted"></i>
                                    <?= substr($c['hora_inicio'], 0, 5) ?> - <?= substr($c['hora_fin'], 0, 5) ?>
                                </td>
                                <td>Dr(a). <?= htmlspecialchars($c['medico_nombre']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($c['especialidad_nombre']) ?></span></td>
                                <td class="small">
                                    <div><?= htmlspecialchars($c['motivo_consulta'] ?? 'Consulta') ?></div>
                                    <?php if ($c['estado'] === 'CANCELADA' && !empty($c['motivo_cancelacion'])): ?>
                                        <small class="text-danger"><i class="bi bi-x-circle me-1"></i>Motivo: <?= htmlspecialchars($c['motivo_cancelacion']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-estado badge-estado-<?= strtolower($c['estado']) ?>">
                                        <?= htmlspecialchars($c['estado']) ?>
                                    </span>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?= date('d/m/Y H:i', strtotime($c['fecha_registro'])) ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="verHistorialEstados(<?= $c['id_cita'] ?>, '<?= htmlspecialchars($c['codigo_cita']) ?>')">
                                        <i class="bi bi-clock-history me-1"></i>Estados
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Modal: Trazabilidad de Estados -->
<div class="modal fade" id="modalTrazabilidad" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-clock-history me-2"></i>Historial de Transiciones - <span id="modal_hist_codigo"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="modal_hist_body"></div>
        </div>
    </div>
</div>

<script>
    function verHistorialEstados(idCita, codigo) {
        document.getElementById('modal_hist_codigo').textContent = codigo;
        const body = document.getElementById('modal_hist_body');
        body.innerHTML = '<div class="text-center py-3 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Cargando trazabilidad...</div>';

        const modal = new bootstrap.Modal(document.getElementById('modalTrazabilidad'));
        modal.show();

        fetch(`/clinicaVidaSalud/views/consultas/historial_ajax.php?id_cita=${idCita}`)
            .then(r => r.text())
            .then(html => {
                body.innerHTML = html;
            })
            .catch(() => {
                body.innerHTML = '<div class="alert alert-danger">Error al cargar la trazabilidad.</div>';
            });
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>