<?php

/**
 * Vista: Consultar Disponibilidad de Citas (CU-08)
 * Paquete 4: Programación y Control de Citas (RF05 / RF06 / RN06)
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Especialidad.php';
require_once __DIR__ . '/../../app/models/Medico.php';
require_once __DIR__ . '/../../app/models/CitaMedica.php';

AuthMiddleware::verificarAutenticacion();

$especialidades = Especialidad::listarTodas(true);
$id_esp_sel     = !empty($_GET['id_especialidad']) ? (int)$_GET['id_especialidad'] : 0;
$id_medico_sel  = !empty($_GET['id_medico']) ? (int)$_GET['id_medico'] : 0;
$fecha_sel      = !empty($_GET['fecha_cita']) ? $_GET['fecha_cita'] : date('Y-m-d');

$medicosDisponibles = [];
if ($id_esp_sel > 0) {
    $medicosDisponibles = Medico::listarTodos(null, $id_esp_sel, true);
    if ($id_medico_sel === 0 && !empty($medicosDisponibles)) {
        $id_medico_sel = (int)$medicosDisponibles[0]['id_medico'];
    }
} else {
    $medicosDisponibles = Medico::listarTodos(null, null, true);
    if ($id_medico_sel === 0 && !empty($medicosDisponibles)) {
        $id_medico_sel = (int)$medicosDisponibles[0]['id_medico'];
    }
}

$resultadoDisponibilidad = null;
if ($id_medico_sel > 0 && !empty($fecha_sel)) {
    $resultadoDisponibilidad = CitaMedica::consultarDisponibilidad($id_medico_sel, $fecha_sel);
}

$pageTitle = 'Consultar Disponibilidad de Citas';
$currentPage = 'disponibilidad';
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
            <h3 class="fw-bold mb-1 text-dark">Consulta de Disponibilidad de Citas en Tiempo Real</h3>
            <p class="text-muted mb-0 small">
                Cálculo instantáneo de turnos médicos, división en bloques de 30 minutos y detección de disponibilidad libre o reservada.
            </p>
        </div>
        <div>
            <a href="/clinicaVidaSalud/views/citas/programar.php" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-calendar-plus-fill"></i>
                <span>Ir a Programar Cita</span>
            </a>
        </div>
    </div>
</div>

<!-- Filtros de Consulta de Disponibilidad -->
<div class="card card-custom p-4 mb-4">
    <form method="GET" class="row g-3 align-items-end" id="formDisponibilidad">
        <div class="col-md-4">
            <label class="form-label fw-semibold">1. Especialidad Médica</label>
            <select name="id_especialidad" id="id_especialidad" class="form-select" onchange="cargarMedicosPorEspecialidad(this.value, 'id_medico')">
                <option value="">-- Todas las Especialidades --</option>
                <?php foreach ($especialidades as $esp): ?>
                    <option value="<?= $esp['id_especialidad'] ?>" <?= $id_esp_sel === (int)$esp['id_especialidad'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($esp['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">2. Médico Especialista</label>
            <select name="id_medico" id="id_medico" class="form-select" required>
                <option value="">-- Seleccionar Médico --</option>
                <?php foreach ($medicosDisponibles as $m): ?>
                    <option value="<?= $m['id_medico'] ?>" <?= $id_medico_sel === (int)$m['id_medico'] ? 'selected' : '' ?>>
                        Dr(a). <?= htmlspecialchars($m['nombres'] . ' ' . $m['apellidos']) ?> (<?= htmlspecialchars($m['codigo_medico']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">3. Fecha de Consulta</label>
            <input type="date" name="fecha_cita" class="form-control" value="<?= htmlspecialchars($fecha_sel) ?>" min="<?= date('Y-m-d') ?>" required>
            <small class="text-muted">Fechas pasadas no permitidas.</small>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-search"></i>
            </button>
        </div>
    </form>
</div>

<!-- Grilla de Disponibilidad -->
<div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-calendar-range text-primary fs-5"></i>
            <span class="fw-bold">Grilla de Horarios para el <?= date('d/m/Y', strtotime($fecha_sel)) ?></span>
        </div>
        <div class="d-flex gap-3">
            <span class="small d-flex align-items-center gap-1">
                <span class="d-inline-block rounded-circle bg-primary" style="width: 10px; height: 10px;"></span> Libre / Disponible
            </span>
            <span class="small d-flex align-items-center gap-1 text-muted">
                <span class="d-inline-block rounded-circle bg-secondary" style="width: 10px; height: 10px;"></span> Ocupado
            </span>
        </div>
    </div>
    <div class="card-body p-4">
        <?php if ($resultadoDisponibilidad === null): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-calendar-week fs-1 text-primary mb-2"></i>
                <h5>Seleccione un médico y fecha</h5>
                <p class="small">Utilice los filtros superiores para desplegar los intervalos disponibles en tiempo real.</p>
            </div>
        <?php elseif (!$resultadoDisponibilidad['status']): ?>
            <div class="alert alert-warning border d-flex align-items-center gap-3 p-3">
                <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
                <div>
                    <h6 class="fw-bold mb-1">Información de Disponibilidad</h6>
                    <div style="font-size: 0.9rem;"><?= htmlspecialchars($resultadoDisponibilidad['message']) ?></div>
                </div>
            </div>
        <?php else: ?>
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold text-dark mb-0">Atención con Dr(a). <?= htmlspecialchars($resultadoDisponibilidad['medico']) ?></h5>
                    <small class="text-muted"><i class="bi bi-calendar-check me-1"></i>Día <?= date('l d/m/Y', strtotime($fecha_sel)) ?></small>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-3">
                <?php foreach ($resultadoDisponibilidad['slots'] as $slot): ?>
                    <?php if ($slot['disponible']): ?>
                        <div class="card border border-primary-subtle shadow-sm p-3 text-center" style="min-width: 180px;">
                            <div class="text-primary fw-bold fs-5 mb-1">
                                <i class="bi bi-clock me-1"></i><?= $slot['hora_inicio'] ?>
                            </div>
                            <small class="text-muted mb-3 d-block">Hasta <?= $slot['hora_fin'] ?></small>
                            <span class="badge bg-success-subtle text-success border border-success-subtle mb-3">DISPONIBLE</span>

                            <a href="/clinicaVidaSalud/views/citas/programar.php?id_medico=<?= $id_medico_sel ?>&fecha_cita=<?= $fecha_sel ?>&hora_inicio=<?= $slot['hora_inicio_sql'] ?>&hora_fin=<?= $slot['hora_fin_sql'] ?>&id_especialidad=<?= $id_esp_sel ?>"
                                class="btn btn-sm btn-primary fw-semibold">
                                <i class="bi bi-check2-circle me-1"></i>Reservar
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="card border bg-light text-center p-3 opacity-75" style="min-width: 180px;">
                            <div class="text-muted fw-bold fs-5 mb-1 text-decoration-line-through">
                                <i class="bi bi-lock-fill me-1"></i><?= $slot['hora_inicio'] ?>
                            </div>
                            <small class="text-muted mb-3 d-block">Hasta <?= $slot['hora_fin'] ?></small>
                            <span class="badge bg-secondary-subtle text-secondary border mb-3">
                                OCUPADO (<?= $slot['estado_cita'] ?>)
                            </span>
                            <button class="btn btn-sm btn-outline-secondary" disabled>No Disponible</button>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="/clinicaVidaSalud/assets/js/citas.js"></script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>