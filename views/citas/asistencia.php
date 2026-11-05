<?php

/**
 * Vista: Control de Asistencia y Estados de Cita (CU-12)
 * Paquete 4: Programación y Control de Citas (RF10 / RN10 / RN12)
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/CitaMedica.php';
require_once __DIR__ . '/../../app/models/Medico.php';
require_once __DIR__ . '/../../app/models/HistorialEstadoCita.php';
require_once __DIR__ . '/../../app/controllers/AsistenciaCitaController.php';

AuthMiddleware::verificarRoles(['Administrador', 'Recepcionista', 'Medico']);
$usuario = AuthMiddleware::usuario();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new AsistenciaCitaController();
    $controller->handleRequest();
}

$fecha_filtro     = $_GET['fecha'] ?? date('Y-m-d');
$id_medico_filtro = !empty($_GET['id_medico']) ? (int)$_GET['id_medico'] : null;

// Si el usuario autenticado es Médico, filtrar obligatoriamente por su propio ID
if (AuthMiddleware::esMedico() && !empty($usuario['id_medico'])) {
    $id_medico_filtro = (int)$usuario['id_medico'];
}

$filtros = [
    'fecha_cita' => $fecha_filtro,
    'id_medico'  => $id_medico_filtro
];

$citas = CitaMedica::listar($filtros);
$medicos = Medico::listarTodos(null, null, true);

$pageTitle = 'Control de Asistencia de Citas';
$currentPage = 'asistencia';
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
            <h3 class="fw-bold mb-1 text-dark">Control de Asistencia y Ciclo de Vida de Citas</h3>
            <p class="text-muted mb-0 small">
                Gestión estricta de transiciones legales de estado (Programada, Confirmada, Atendida, Inasistencia) con trazabilidad.
            </p>
        </div>
        <div>
            <a href="/clinicaVidaSalud/views/consultas/agenda.php" class="btn btn-outline-primary d-flex align-items-center gap-2">
                <i class="bi bi-calendar-week"></i>
                <span>Ver Agenda Diaria (CU-13)</span>
            </a>
        </div>
    </div>
</div>

<!-- Filtros de Asistencia Diaria -->
<div class="card card-custom p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-calendar text-primary"></i></span>
                <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($fecha_filtro) ?>" onchange="this.form.submit()">
            </div>
        </div>

        <?php if (!AuthMiddleware::esMedico()): ?>
            <div class="col-md-5">
                <select name="id_medico" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Todos los Médicos --</option>
                    <?php foreach ($medicos as $m): ?>
                        <option value="<?= $m['id_medico'] ?>" <?= $id_medico_filtro === (int)$m['id_medico'] ? 'selected' : '' ?>>
                            Dr(a). <?= htmlspecialchars($m['nombres'] . ' ' . $m['apellidos']) ?> (<?= htmlspecialchars($m['codigo_medico']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <div class="col-md-5">
                <input type="text" class="form-control bg-light" value="Mi Agenda Médica: Dr(a). <?= htmlspecialchars($usuario['nombre_completo']) ?>" readonly>
            </div>
        <?php endif; ?>

        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1">
                <i class="bi bi-filter me-1"></i>Actualizar Vista
            </button>
            <a href="/clinicaVidaSalud/views/citas/asistencia.php" class="btn btn-outline-secondary" title="Hoy">
                <i class="bi bi-arrow-clockwise"></i>
            </a>
        </div>
    </form>
</div>

<!-- Grilla de Citas para Control de Estados -->
<div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-check2-square text-primary me-2"></i>Citas Programadas para el <?= date('d/m/Y', strtotime($fecha_filtro)) ?></span>
        <span class="badge bg-light text-dark border">Total: <?= count($citas) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-custom table-hover align-middle">
            <thead>
                <tr>
                    <th style="width: 100px;">Horario</th>
                    <th style="width: 100px;">Código</th>
                    <th>Paciente</th>
                    <th>Médico Especialista</th>
                    <th>Especialidad</th>
                    <th>Estado de Cita</th>
                    <th class="text-end" style="width: 280px;">Transiciones de Asistencia</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($citas)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-check fs-2 text-primary d-block mb-2"></i>
                            No hay citas médicas registradas para el <?= date('d/m/Y', strtotime($fecha_filtro)) ?> con los filtros aplicados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($citas as $c): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-primary font-monospace">
                                    <i class="bi bi-clock me-1"></i><?= substr($c['hora_inicio'], 0, 5) ?>
                                </div>
                                <small class="text-muted"><?= substr($c['hora_fin'], 0, 5) ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark font-monospace border"><?= htmlspecialchars($c['codigo_cita']) ?></span></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($c['paciente_nombre']) ?></div>
                                <small class="text-muted">CI: <?= htmlspecialchars($c['paciente_ci']) ?></small>
                            </td>
                            <td>Dr(a). <?= htmlspecialchars($c['medico_nombre']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($c['especialidad_nombre']) ?></span></td>
                            <td>
                                <span class="badge badge-estado badge-estado-<?= strtolower($c['estado']) ?>">
                                    <?= htmlspecialchars($c['estado']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <!-- Si está PROGRAMADA -> Confirmar Asistencia -->
                                    <?php if ($c['estado'] === 'PROGRAMADA' && (AuthMiddleware::esAdmin() || AuthMiddleware::esRecepcionista())): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="id_cita" value="<?= $c['id_cita'] ?>">
                                            <input type="hidden" name="nuevo_estado" value="CONFIRMADA">
                                            <input type="hidden" name="observacion" value="Paciente confirmó asistencia a su cita">
                                            <button type="submit" class="btn btn-outline-info" title="Ratificar Asistencia (Confirmada)">
                                                <i class="bi bi-check-lg me-1"></i>Confirmar
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Si está CONFIRMADA o PROGRAMADA -> Marcar Atendida con Modal de Observación / Diagnóstico Clínico -->
                                    <?php if (in_array($c['estado'], ['PROGRAMADA', 'CONFIRMADA'], true)): ?>
                                        <button type="button" class="btn btn-outline-success"
                                            onclick="abrirModalAtender(<?= $c['id_cita'] ?>, '<?= htmlspecialchars($c['codigo_cita']) ?>', '<?= htmlspecialchars($c['paciente_nombre']) ?>')"
                                            title="Registrar Atención Médica">
                                            <i class="bi bi-check2-all me-1"></i>Atender
                                        </button>
                                    <?php endif; ?>

                                    <!-- Si no vino -> Inasistencia -->
                                    <?php if (in_array($c['estado'], ['PROGRAMADA', 'CONFIRMADA'], true) && (AuthMiddleware::esAdmin() || AuthMiddleware::esRecepcionista())): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Registrar inasistencia del paciente?');">
                                            <input type="hidden" name="id_cita" value="<?= $c['id_cita'] ?>">
                                            <input type="hidden" name="nuevo_estado" value="INASISTENCIA">
                                            <input type="hidden" name="observacion" value="El paciente no se presentó en su horario asignado">
                                            <button type="submit" class="btn btn-outline-warning" title="Marcar Inasistencia">
                                                <i class="bi bi-person-x"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Ver Trazabilidad de Estados -->
                                    <button type="button" class="btn btn-outline-secondary"
                                        onclick="verHistorialEstados(<?= $c['id_cita'] ?>, '<?= htmlspecialchars($c['codigo_cita']) ?>')"
                                        title="Ver historial de transiciones">
                                        <i class="bi bi-clock-history"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Registrar Atención Médica con Observación Clínica (RF10) -->
<div class="modal fade" id="modalAtenderCita" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-clipboard2-check-fill me-2"></i>Registrar Consulta Atendida
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id_cita" id="modal_atender_id_cita">
                    <input type="hidden" name="nuevo_estado" value="ATENDIDA">

                    <div class="alert alert-light border small text-muted mb-3">
                        <div><strong>Cita:</strong> <span class="font-monospace text-primary fw-bold" id="modal_atender_codigo"></span></div>
                        <div><strong>Paciente:</strong> <span class="fw-semibold text-dark" id="modal_atender_paciente"></span></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">
                            <i class="bi bi-file-earmark-medical text-primary me-1"></i>Observación / Diagnóstico Clínico Breve (Opcional):
                        </label>
                        <textarea name="observacion" id="modal_atender_observacion" class="form-control" rows="3"
                            placeholder="ej. Paciente refiere mejoría sintomática. Se indica medicación de soporte y reposo por 48 horas."></textarea>
                        <div class="form-text small text-muted">
                            Esta nota quedará registrada en la trazabilidad histórica de la consulta médica (RF10 / RF12).
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-semibold shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i>Confirmar Atención
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Trazabilidad de Cambios de Estado (RF10 / RF12) -->
<div class="modal fade" id="modalTrazabilidad" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-clock-history me-2"></i>Trazabilidad de la Cita <span id="modal_hist_codigo"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="modal_hist_body">
                <div class="text-center py-3 text-muted">Cargando trazabilidad...</div>
            </div>
        </div>
    </div>
</div>

<script>
    function abrirModalAtender(idCita, codigo, paciente) {
        document.getElementById('modal_atender_id_cita').value = idCita;
        document.getElementById('modal_atender_codigo').textContent = codigo;
        document.getElementById('modal_atender_paciente').textContent = paciente;
        document.getElementById('modal_atender_observacion').value = 'Consulta médica efectuada exitosamente';
        const modal = new bootstrap.Modal(document.getElementById('modalAtenderCita'));
        modal.show();
    }

    function verHistorialEstados(idCita, codigo) {
        document.getElementById('modal_hist_codigo').textContent = codigo;
        const body = document.getElementById('modal_hist_body');
        body.innerHTML = '<div class="text-center py-3 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Cargando historial de estados...</div>';

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