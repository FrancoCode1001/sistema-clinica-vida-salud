<?php

/**
 * Vista: Horarios de Atención Médica (CU-05)
 * Paquete 2: Gestión Médica y Horarios
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/HorarioAtencion.php';
require_once __DIR__ . '/../../app/models/Medico.php';
require_once __DIR__ . '/../../app/controllers/HorarioController.php';

AuthMiddleware::verificarRoles(['Administrador']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new HorarioController();
    $controller->handleRequest();
}

$id_medico_filtro = !empty($_GET['id_medico']) ? (int)$_GET['id_medico'] : null;
$medicos = Medico::listarTodos(null, null, true); // Solo médicos activos
$horarios = HorarioAtencion::listar($id_medico_filtro);

$pageTitle = 'Horarios de Atención Médica';
$currentPage = 'horarios';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>

<!-- Encabezado de Caso de Uso para Prototipos de UI (Sección 4.4) -->
<div class="page-header-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div>
                <span class="cu-package-badge">GESTIÓN MÉDICA Y HORARIOS</span>
            </div>
            <h3 class="fw-bold mb-1 text-dark">Gestión de Horarios de Atención Médica</h3>
            <p class="text-muted mb-0 small">
                Parametrización semanal de turnos, rangos horarios y cálculo dinámico de bloques de consulta de 30 min.
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearHorario">
                <i class="bi bi-clock-fill"></i>
                <span>Nuevo Turno Horario</span>
            </button>
        </div>
    </div>
</div>

<!-- Filtro por Médico -->
<div class="card card-custom p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-9">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-person-badge text-primary"></i></span>
                <select name="id_medico" class="form-select">
                    <option value="">-- Todos los Médicos y sus Turnos --</option>
                    <?php foreach ($medicos as $m): ?>
                        <option value="<?= $m['id_medico'] ?>" <?= $id_medico_filtro === (int)$m['id_medico'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['codigo_medico'] . ' - Dr(a). ' . $m['nombres'] . ' ' . $m['apellidos']) ?> (<?= htmlspecialchars($m['especialidades'] ?? 'General') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1">
                <i class="bi bi-filter me-1"></i>Ver Turnos
            </button>
            <?php if (!empty($id_medico_filtro)): ?>
                <a href="/clinicaVidaSalud/views/horarios/index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Listado de Turnos Configurados -->
<div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-calendar-range text-primary me-2"></i>Turnos Semanales Parametrizados</span>
        <span class="badge bg-light text-dark border">Total: <?= count($horarios) ?> turnos</span>
    </div>
    <div class="table-responsive">
        <table class="table table-custom table-hover align-middle">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Médico Asignado</th>
                    <th>Día de Atención</th>
                    <th>Rango Horario</th>
                    <th>Duración Bloque</th>
                    <th>Bloques Calculados</th>
                    <th>Estado</th>
                    <th class="text-end" style="width: 100px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($horarios)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-clock-history fs-3 d-block mb-1"></i>
                            No hay turnos horarios configurados para el criterio seleccionado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($horarios as $h): ?>
                        <?php
                        $bloques = HorarioAtencion::generarIntervalos($h['hora_inicio'], $h['hora_fin'], (int)$h['duracion_minutos']);
                        ?>
                        <tr>
                            <td class="text-muted fw-bold">#<?= $h['id_horario'] ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($h['medico_nombre']) ?></div>
                                <small class="text-muted font-monospace"><?= htmlspecialchars($h['codigo_medico']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-primary text-white px-2 py-1">
                                    <i class="bi bi-calendar-day me-1"></i><?= HorarioAtencion::$diasSemana[$h['dia_semana']] ?? 'Día ' . $h['dia_semana'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-primary">
                                    <i class="bi bi-clock me-1"></i><?= substr($h['hora_inicio'], 0, 5) ?> - <?= substr($h['hora_fin'], 0, 5) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= $h['duracion_minutos'] ?> minutos
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="collapse" data-bs-target="#bloques_<?= $h['id_horario'] ?>">
                                    <i class="bi bi-eye me-1"></i><?= count($bloques) ?> espacios
                                </button>
                                <div class="collapse mt-2" id="bloques_<?= $h['id_horario'] ?>">
                                    <div class="d-flex flex-wrap gap-1 p-2 bg-light rounded border" style="max-width: 320px;">
                                        <?php foreach ($bloques as $b): ?>
                                            <span class="badge bg-white text-dark border" style="font-size: 0.72rem;">
                                                <?= $b['inicio'] ?> - <?= $b['fin'] ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($h['estado'] === 'ACTIVO'): ?>
                                    <span class="badge badge-activo"><i class="bi bi-check-circle-fill me-1"></i>ACTIVO</span>
                                <?php else: ?>
                                    <span class="badge badge-inactivo">INACTIVO</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este turno de atención?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_horario" value="<?= $h['id_horario'] ?>">
                                    <input type="hidden" name="id_medico" value="<?= $h['id_medico'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar turno">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Configurar Nuevo Horario de Atención -->
<div class="modal fade" id="modalCrearHorario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST" class="needs-validation" novalidate id="formNuevoHorario">
                <input type="hidden" name="action" value="store">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-clock-history me-2"></i>Registrar Turno de Atención Médica
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Médico Especialista <span class="text-danger">*</span></label>
                        <select name="id_medico" class="form-select" required>
                            <option value="">-- Seleccionar Médico --</option>
                            <?php foreach ($medicos as $m): ?>
                                <option value="<?= $m['id_medico'] ?>" <?= ($id_medico_filtro === (int)$m['id_medico']) ? 'selected' : '' ?>>
                                    Dr(a). <?= htmlspecialchars($m['nombres'] . ' ' . $m['apellidos']) ?> (<?= htmlspecialchars($m['codigo_medico']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Debe seleccionar un médico.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Día de la Semana (Lunes a Sábado) <span class="text-danger">*</span></label>
                        <select name="dia_semana" class="form-select" required>
                            <option value="">-- Seleccionar Día --</option>
                            <?php foreach (HorarioAtencion::$diasSemana as $num => $nombre): ?>
                                <option value="<?= $num ?>"><?= $nombre ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Seleccione el día de atención.</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Hora Inicio <span class="text-danger">*</span></label>
                            <input type="time" name="hora_inicio" id="modal_hora_inicio" class="form-control" required value="08:00">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Hora Fin <span class="text-danger">*</span></label>
                            <input type="time" name="hora_fin" id="modal_hora_fin" class="form-control" required value="12:00">
                        </div>
                        <small class="text-muted">La hora fin debe ser estrictamente mayor a la hora inicio.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Duración de Cada Consulta (Minutos)</label>
                        <input type="number" name="duracion_minutos" class="form-control" value="30" min="15" max="120" step="5" required>
                        <small class="text-muted">Por defecto: 30 minutos (permite calcular los bloques automáticamente).</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-save me-1"></i>Guardar Turno
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>