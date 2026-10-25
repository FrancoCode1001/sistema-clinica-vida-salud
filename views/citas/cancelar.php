<?php

/**
 * Vista: Cancelar Cita Médica (CU-11)
 * Paquete 4: Programación y Control de Citas (RF09 / RN09 / RN10)
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/CitaMedica.php';
require_once __DIR__ . '/../../app/controllers/CitaController.php';

AuthMiddleware::verificarAutenticacion();
$usuario = AuthMiddleware::usuario();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new CitaController();
    $controller->handleRequest();
}

$busqueda = $_GET['busqueda'] ?? '';
$filtros = ['busqueda' => $busqueda];

if (AuthMiddleware::esPaciente()) {
    $filtros['id_paciente'] = (int)$usuario['id_paciente'];
}

$todasCitas = CitaMedica::listar($filtros);

$pageTitle = 'Cancelar Cita Médica';
$currentPage = 'cancelar';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>

<!-- Encabezado de Caso de Uso para Prototipos de UI (Sección 4.4) -->
<div class="page-header-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div>
                <span class="cu-package-badge">PROGRAMACIÓN Y CONTROL DE CITAS</span>
            </div>
            <h3 class="fw-bold mb-1 text-dark">Cancelación Lógica de Cita Médica</h3>
            <p class="text-muted mb-0 small">
                Baja lógica de citas registrando motivo y liberando automáticamente el bloque de horario sin borrar el registro físico.
            </p>
        </div>
    </div>
</div>

<!-- Barra de Búsqueda -->
<div class="card card-custom p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-9">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="busqueda" class="form-control border-start-0" placeholder="Buscar por código de cita (ej. CM-0001) o nombre del paciente..." value="<?= htmlspecialchars($busqueda) ?>">
            </div>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1">
                <i class="bi bi-filter me-1"></i>Buscar
            </button>
            <?php if (!empty($busqueda)): ?>
                <a href="/clinicaVidaSalud/views/citas/cancelar.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabla de Citas y Acciones de Cancelación -->
<div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-calendar-x text-danger me-2"></i>Gestión de Cancelaciones de Citas</span>
        <span class="badge bg-light text-dark border">Total: <?= count($todasCitas) ?> citas</span>
    </div>
    <div class="table-responsive">
        <table class="table table-custom table-hover align-middle">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Paciente</th>
                    <th>Médico</th>
                    <th>Especialidad</th>
                    <th>Fecha y Horario</th>
                    <th>Estado Actual</th>
                    <th>Motivo de Cancelación</th>
                    <th class="text-end" style="width: 140px;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($todasCitas)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-calendar-check fs-3 d-block mb-1"></i>
                            No se encontraron citas médicas con los filtros especificados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($todasCitas as $c): ?>
                        <tr>
                            <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($c['codigo_cita']) ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($c['paciente_nombre']) ?></div>
                                <small class="text-muted">CI: <?= htmlspecialchars($c['paciente_ci']) ?></small>
                            </td>
                            <td>Dr(a). <?= htmlspecialchars($c['medico_nombre']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($c['especialidad_nombre']) ?></span></td>
                            <td>
                                <div><i class="bi bi-calendar me-1 text-muted"></i><?= date('d/m/Y', strtotime($c['fecha_cita'])) ?></div>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= substr($c['hora_inicio'], 0, 5) ?> - <?= substr($c['hora_fin'], 0, 5) ?></small>
                            </td>
                            <td>
                                <span class="badge badge-estado badge-estado-<?= strtolower($c['estado']) ?>">
                                    <?= htmlspecialchars($c['estado']) ?>
                                </span>
                            </td>
                            <td class="small text-muted">
                                <?= !empty($c['motivo_cancelacion']) ? htmlspecialchars($c['motivo_cancelacion']) : '<em>No aplica</em>' ?>
                            </td>
                            <td class="text-end">
                                <?php if ($c['estado'] === 'CANCELADA'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Ya Cancelada</span>
                                <?php elseif ($c['estado'] === 'ATENDIDA'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle" title="RF09 / RN10: Las citas atendidas no pueden cancelarse">
                                        <i class="bi bi-lock-fill me-1"></i>Protegida
                                    </span>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                        onclick="abrirModalCancelar(<?= $c['id_cita'] ?>, '<?= htmlspecialchars($c['codigo_cita']) ?>', '<?= htmlspecialchars($c['paciente_nombre']) ?>')">
                                        <i class="bi bi-x-circle me-1"></i>Cancelar
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Cancelar Cita -->
<div class="modal fade" id="modalCancelarCita" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form action="/clinicaVidaSalud/views/citas/cancelar.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="cancelar">
                <input type="hidden" name="id_cita" id="cancel_id_cita">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Cancelar Cita Médica (Baja Lógica)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-warning small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Esta acción registrará la baja lógica de la cita <strong id="cancel_cod_cita"></strong> del paciente <strong id="cancel_paciente_nombre"></strong>. El bloque de horario del médico quedará <strong>liberado de inmediato</strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Motivo de Cancelación <span class="text-danger">*</span></label>
                        <textarea name="motivo_cancelacion" class="form-control" rows="3" placeholder="Indique la justificación médica o solicitud del paciente (ej. Viaje imprevisto, reprogramación de común acuerdo...)" required></textarea>
                        <div class="invalid-feedback">El motivo de cancelación es obligatorio</div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Regresar</button>
                    <button type="submit" class="btn btn-danger fw-semibold">
                        <i class="bi bi-check-lg me-1"></i>Confirmar Cancelación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function abrirModalCancelar(idCita, codigo, paciente) {
        document.getElementById('cancel_id_cita').value = idCita;
        document.getElementById('cancel_cod_cita').textContent = codigo;
        document.getElementById('cancel_paciente_nombre').textContent = paciente;
        const modal = new bootstrap.Modal(document.getElementById('modalCancelarCita'));
        modal.show();
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>