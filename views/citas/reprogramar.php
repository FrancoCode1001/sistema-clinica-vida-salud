<?php

/**
 * Vista: Reprogramar Cita Médica (CU-10)
 * Paquete 4: Programación y Control de Citas (RF08 / RN13)
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/CitaMedica.php';
require_once __DIR__ . '/../../app/controllers/CitaController.php';

AuthMiddleware::verificarRoles(['Administrador', 'Recepcionista']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new CitaController();
    $controller->handleRequest();
}

$id_cita_sel = !empty($_GET['id_cita']) ? (int)$_GET['id_cita'] : 0;
$citasReprogramables = CitaMedica::listar();

// Filtrar únicamente citas que estén en PROGRAMADA o CONFIRMADA (RF08)
$citasReprogramables = array_filter($citasReprogramables, function ($c) {
    return in_array($c['estado'], ['PROGRAMADA', 'CONFIRMADA'], true);
});

$citaActual = null;
if ($id_cita_sel > 0) {
    $citaActual = CitaMedica::obtenerPorId($id_cita_sel);
} elseif (!empty($citasReprogramables)) {
    $first = reset($citasReprogramables);
    $id_cita_sel = (int)$first['id_cita'];
    $citaActual = CitaMedica::obtenerPorId($id_cita_sel);
}

$pageTitle = 'Reprogramar Cita Médica';
$currentPage = 'reprogramar';
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
            <h3 class="fw-bold mb-1 text-dark">Reprogramación de Cita Médica</h3>
            <p class="text-muted mb-0 small">
                Modificación de fecha y horario de consulta conservando la integridad histórica y liberando el bloque previo.
            </p>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Selección y Detalle de la Cita Actual -->
    <div class="col-lg-5">
        <div class="card card-custom h-100">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <i class="bi bi-search text-primary fs-5"></i>
                <span class="fw-bold">1. Seleccionar Cita a Reprogramar</span>
            </div>
            <div class="card-body p-4">
                <form method="GET" class="mb-4">
                    <label class="form-label fw-semibold small">Citas Programadas o Confirmadas Activas</label>
                    <select name="id_cita" class="form-select" onchange="this.form.submit()">
                        <?php if (empty($citasReprogramables)): ?>
                            <option value="">No hay citas disponibles para reprogramar</option>
                        <?php else: ?>
                            <?php foreach ($citasReprogramables as $c): ?>
                                <option value="<?= $c['id_cita'] ?>" <?= $id_cita_sel === (int)$c['id_cita'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['codigo_cita'] . ' - ' . $c['paciente_nombre'] . ' (' . date('d/m/Y', strtotime($c['fecha_cita'])) . ' ' . substr($c['hora_inicio'], 0, 5) . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </form>

                <?php if ($citaActual): ?>
                    <div class="border rounded p-3 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-primary font-monospace"><?= htmlspecialchars($citaActual['codigo_cita']) ?></span>
                            <span class="badge badge-estado badge-estado-<?= strtolower($citaActual['estado']) ?>">
                                <?= htmlspecialchars($citaActual['estado']) ?>
                            </span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($citaActual['paciente_nombre']) ?></h6>
                        <div class="small text-muted mb-3">CI: <?= htmlspecialchars($citaActual['paciente_ci']) ?> &bull; Tel: <?= htmlspecialchars($citaActual['paciente_tel']) ?></div>

                        <div class="mb-2 small">
                            <strong>Médico Asignado:</strong> Dr(a). <?= htmlspecialchars($citaActual['medico_nombre']) ?>
                        </div>
                        <div class="mb-2 small">
                            <strong>Especialidad:</strong> <?= htmlspecialchars($citaActual['especialidad_nombre']) ?>
                        </div>
                        <div class="mb-3 small">
                            <strong>Horario Actual:</strong>
                            <span class="text-primary fw-semibold">
                                <i class="bi bi-calendar me-1"></i><?= date('d/m/Y', strtotime($citaActual['fecha_cita'])) ?>
                                (<?= substr($citaActual['hora_inicio'], 0, 5) ?> - <?= substr($citaActual['hora_fin'], 0, 5) ?>)
                            </span>
                        </div>
                        <div class="alert alert-info border-0 p-2 mb-0 small">
                            <i class="bi bi-info-circle me-1"></i>
                            Al confirmar el nuevo horario, el espacio anterior quedará liberado automáticamente para otros pacientes.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Formulario de Nueva Fecha y Selección de Bloque Horario -->
    <div class="col-lg-7">
        <div class="card card-custom h-100">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <i class="bi bi-calendar-event text-primary fs-5"></i>
                <span class="fw-bold">2. Definir Nueva Fecha y Horario Disponible</span>
            </div>
            <div class="card-body p-4">
                <?php if (!$citaActual): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x fs-1 text-primary mb-2"></i>
                        <h6>No hay cita seleccionada</h6>
                    </div>
                <?php else: ?>
                    <form action="/clinicaVidaSalud/views/citas/reprogramar.php" method="POST" class="needs-validation" novalidate id="formReprogramar">
                        <input type="hidden" name="action" value="reprogramar">
                        <input type="hidden" name="id_cita" value="<?= $citaActual['id_cita'] ?>">
                        <input type="hidden" name="nuevo_inicio" id="reprog_hora_inicio" required>
                        <input type="hidden" name="nuevo_fin" id="reprog_hora_fin" required>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nueva Fecha de Consulta <span class="text-danger">*</span></label>
                            <input type="date" name="nueva_fecha" id="reprog_fecha" class="form-control"
                                value="<?= date('Y-m-d', strtotime('+1 day')) ?>" min="<?= date('Y-m-d') ?>" required onchange="cargarNuevosSlots();">
                            <small class="text-muted">La nueva fecha no puede ser anterior al día de hoy.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Seleccionar Nuevo Bloque Horario Disponible (30 min) <span class="text-danger">*</span></label>
                            <div id="reprogSlotsContainer" class="p-3 border rounded bg-light">
                                <div class="text-muted small">Cargando turnos disponibles...</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                            <i class="bi bi-arrow-repeat me-1"></i>Confirmar Reprogramación
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="/clinicaVidaSalud/assets/js/citas.js"></script>
<script>
    function cargarNuevosSlots() {
        <?php if ($citaActual): ?>
            const idMed = <?= (int)$citaActual['id_medico'] ?>;
            const fec = document.getElementById('reprog_fecha').value;
            consultarDisponibilidadSlots(idMed, fec, 'reprogSlotsContainer', 'reprog_hora_inicio', 'reprog_hora_fin');
        <?php endif; ?>
    }

    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($citaActual): ?>
            cargarNuevosSlots();
        <?php endif; ?>
    });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>