<?php

/**
 * Vista: Programar Cita Médica (CU-09)
 * Paquete 4: Programación y Control de Citas (RF06 / RF07 / RN07 / RN13)
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Paciente.php';
require_once __DIR__ . '/../../app/models/Medico.php';
require_once __DIR__ . '/../../app/models/Especialidad.php';
require_once __DIR__ . '/../../app/controllers/CitaController.php';

AuthMiddleware::verificarAutenticacion();
$usuario = AuthMiddleware::usuario();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new CitaController();
    $controller->handleRequest();
}

$pacientes = [];
if (!AuthMiddleware::esPaciente()) {
    $pacientes = Paciente::listarTodos(null, true);
}

$especialidades = Especialidad::listarTodas(true);
$medicos = Medico::listarTodos(null, null, true);

// Parámetros preseleccionados si vienen desde la pantalla de disponibilidad (CU-08)
$preIdMedico       = !empty($_GET['id_medico']) ? (int)$_GET['id_medico'] : 0;
$preFecha          = !empty($_GET['fecha_cita']) ? $_GET['fecha_cita'] : date('Y-m-d');
$preHoraInicio     = $_GET['hora_inicio'] ?? '';
$preHoraFin        = $_GET['hora_fin'] ?? '';
$preIdEspecialidad = !empty($_GET['id_especialidad']) ? (int)$_GET['id_especialidad'] : 0;

$pageTitle = 'Programar Cita Médica';
$currentPage = 'programar';
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
            <h3 class="fw-bold mb-1 text-dark">Programación de Cita Médica</h3>
            <p class="text-muted mb-0 small">
                Reserva de citas médicas con validación estricta de disponibilidad en servidor e índice único parcial contra superposiciones (RF06 / RF07 / RN07).
            </p>
        </div>
        <div>
            <a href="/clinicaVidaSalud/views/citas/disponibilidad.php" class="btn btn-outline-primary d-flex align-items-center gap-2">
                <i class="bi bi-calendar2-check"></i>
                <span>Ver Disponibilidad Completa</span>
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card card-custom">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <i class="bi bi-calendar-plus text-primary fs-5"></i>
                <span class="fw-bold">Formulario de Reserva de Cita</span>
            </div>
            <div class="card-body p-4">
                <form action="/clinicaVidaSalud/views/citas/programar.php" method="POST" class="needs-validation" novalidate id="formProgramarCita">
                    <input type="hidden" name="action" value="store">
                    <input type="hidden" name="hora_inicio" id="input_hora_inicio" value="<?= htmlspecialchars($preHoraInicio) ?>" required>
                    <input type="hidden" name="hora_fin" id="input_hora_fin" value="<?= htmlspecialchars($preHoraFin) ?>" required>

                    <!-- Paciente -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Paciente <span class="text-danger">*</span></label>
                        <?php if (AuthMiddleware::esPaciente()): ?>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($usuario['nombre_completo']) ?>" readonly>
                            <input type="hidden" name="id_paciente" value="<?= (int)$usuario['id_paciente'] ?>">
                        <?php else: ?>
                            <select name="id_paciente" class="form-select" required>
                                <option value="">-- Seleccionar Paciente Registrado --</option>
                                <?php foreach ($pacientes as $pac): ?>
                                    <option value="<?= $pac['id_paciente'] ?>">
                                        <?= htmlspecialchars($pac['codigo_paciente'] . ' - ' . $pac['nombres'] . ' ' . $pac['apellidos'] . ' (CI: ' . $pac['ci'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Debe seleccionar un paciente.</div>
                        <?php endif; ?>
                    </div>

                    <!-- Especialidad y Médico -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Especialidad Médica <span class="text-danger">*</span></label>
                            <select name="id_especialidad" id="select_especialidad" class="form-select" required onchange="cargarMedicosPorEspecialidad(this.value, 'select_medico'); consultarSlots();">
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($especialidades as $esp): ?>
                                    <option value="<?= $esp['id_especialidad'] ?>" <?= $preIdEspecialidad === (int)$esp['id_especialidad'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($esp['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Médico Especialista <span class="text-danger">*</span></label>
                            <select name="id_medico" id="select_medico" class="form-select" required onchange="consultarSlots();">
                                <option value="">-- Seleccionar Médico --</option>
                                <?php foreach ($medicos as $m): ?>
                                    <option value="<?= $m['id_medico'] ?>" <?= $preIdMedico === (int)$m['id_medico'] ? 'selected' : '' ?>>
                                        Dr(a). <?= htmlspecialchars($m['nombres'] . ' ' . $m['apellidos']) ?> (<?= htmlspecialchars($m['codigo_medico']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Fecha y Modalidad -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Fecha de la Cita <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_cita" id="input_fecha_cita" class="form-control"
                                value="<?= htmlspecialchars($preFecha) ?>" min="<?= date('Y-m-d') ?>" required onchange="consultarSlots();">
                            <small class="text-muted">Regla RN06: No se permiten fechas pasadas.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Modalidad de Atención</label>
                            <select name="modalidad" class="form-select">
                                <option value="PRESENCIAL" selected>Presencial en Consultorio</option>
                                <option value="CONSULTA">Consulta de Control</option>
                            </select>
                        </div>
                    </div>

                    <!-- Motivo de Consulta -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Motivo Breve de Consulta (Opcional)</label>
                        <textarea name="motivo_consulta" class="form-control" rows="2" placeholder="ej. Chequeo general, dolor lumbar, revisión de análisis..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm" id="btnConfirmarCita">
                        <i class="bi bi-calendar-check-fill me-1"></i>Confirmar y Guardar Cita Médica
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Panel Lateral: Selección Dinámica de Horario Libre (CU-08) -->
    <div class="col-lg-5">
        <div class="card card-custom h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between">
                <span class="fw-bold"><i class="bi bi-clock-fill text-primary me-2"></i>Horarios Disponibles (30 min)</span>
                <span class="badge bg-light text-muted border">Selección interactiva</span>
            </div>
            <div class="card-body p-4" id="slotsContainer">
                <!-- Se poblará vía AJAX mediante citas.js -->
                <div class="alert alert-light border text-muted small p-3">
                    <i class="bi bi-info-circle me-1"></i>Seleccione el médico y la fecha para cargar los bloques libres.
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/clinicaVidaSalud/assets/js/citas.js"></script>
<script>
    function consultarSlots() {
        const idMedico = document.getElementById('select_medico').value;
        const fecha = document.getElementById('input_fecha_cita').value;
        consultarDisponibilidadSlots(idMedico, fecha, 'slotsContainer', 'input_hora_inicio', 'input_hora_fin');
    }

    document.addEventListener('DOMContentLoaded', function() {
        const medVal = document.getElementById('select_medico').value;
        const fecVal = document.getElementById('input_fecha_cita').value;
        if (medVal && fecVal) {
            consultarSlots();
        }
    });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>