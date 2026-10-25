<?php

/**
 * Vista: Agenda Médica Diaria (CU-13 Flujo A)
 * Paquete 5: Consultas y Reportes (RF11)
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/CitaMedica.php';
require_once __DIR__ . '/../../app/models/Medico.php';
require_once __DIR__ . '/../../app/controllers/ConsultaAgendaController.php';

AuthMiddleware::verificarRoles(['Administrador', 'Recepcionista', 'Medico']);
$usuario = AuthMiddleware::usuario();

$id_medico_filtro = !empty($_GET['id_medico']) ? (int)$_GET['id_medico'] : null;
if (AuthMiddleware::esMedico() && !empty($usuario['id_medico'])) {
    $id_medico_filtro = (int)$usuario['id_medico'];
}

$fecha_filtro  = $_GET['fecha'] ?? date('Y-m-d');
$estado_filtro = $_GET['estado'] ?? '';

$controller = new ConsultaAgendaController();
$citas = $controller->obtenerAgendaDiaria($id_medico_filtro, $fecha_filtro, $estado_filtro);
$medicos = Medico::listarTodos(null, null, true);

$pageTitle = 'Agenda Médica Diaria';
$currentPage = 'agenda';
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
            <h3 class="fw-bold mb-1 text-dark">Agenda Médica Diaria</h3>
            <p class="text-muted mb-0 small">
                Consulta cronológica de citas programadas por médico y fecha específica.
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-2" onclick="window.print()">
                <i class="bi bi-printer"></i>
                <span>Imprimir Agenda</span>
            </button>
        </div>
    </div>
</div>

<!-- Filtros de Agenda -->
<div class="card card-custom p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <?php if (!AuthMiddleware::esMedico()): ?>
            <div class="col-md-5">
                <label class="form-label small fw-semibold">Médico Especialista</label>
                <select name="id_medico" class="form-select form-select-sm" onchange="this.form.submit()">
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
                <label class="form-label small fw-semibold">Médico</label>
                <input type="text" class="form-control form-control-sm bg-light" value="Dr(a). <?= htmlspecialchars($usuario['nombre_completo']) ?>" readonly>
            </div>
        <?php endif; ?>

        <div class="col-md-3">
            <label class="form-label small fw-semibold">Fecha de Consulta</label>
            <input type="date" name="fecha" class="form-control form-control-sm" value="<?= htmlspecialchars($fecha_filtro) ?>" onchange="this.form.submit()">
        </div>

        <div class="col-md-2">
            <label class="form-label small fw-semibold">Estado</label>
            <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- Todos --</option>
                <option value="PROGRAMADA" <?= $estado_filtro === 'PROGRAMADA' ? 'selected' : '' ?>>Programada</option>
                <option value="CONFIRMADA" <?= $estado_filtro === 'CONFIRMADA' ? 'selected' : '' ?>>Confirmada</option>
                <option value="ATENDIDA" <?= $estado_filtro === 'ATENDIDA' ? 'selected' : '' ?>>Atendida</option>
                <option value="CANCELADA" <?= $estado_filtro === 'CANCELADA' ? 'selected' : '' ?>>Cancelada</option>
                <option value="INASISTENCIA" <?= $estado_filtro === 'INASISTENCIA' ? 'selected' : '' ?>>Inasistencia</option>
            </select>
        </div>

        <div class="col-md-2 d-flex gap-2 align-self-end">
            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                <i class="bi bi-filter me-1"></i>Filtrar
            </button>
            <a href="/clinicaVidaSalud/views/consultas/agenda.php" class="btn btn-outline-secondary btn-sm" title="Hoy">
                <i class="bi bi-calendar-event"></i>
            </a>
        </div>
    </form>
</div>

<!-- Listado Cronológico de Citas -->
<div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-calendar2-day text-primary me-2"></i>Agenda Cronológica: <?= date('d/m/Y', strtotime($fecha_filtro)) ?></span>
        <span class="badge bg-light text-dark border">Citas en agenda: <?= count($citas) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-custom table-hover align-middle">
            <thead>
                <tr>
                    <th style="width: 110px;">Horario</th>
                    <th style="width: 100px;">Código</th>
                    <th>Paciente</th>
                    <th>Médico</th>
                    <th>Especialidad</th>
                    <th>Motivo de Consulta</th>
                    <th>Estado</th>
                    <th class="text-end" style="width: 120px;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($citas)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x fs-2 text-primary d-block mb-2"></i>
                            No existen citas registradas para la fecha y filtros seleccionados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($citas as $c): ?>
                        <tr>
                            <td>
                                <span class="fw-bold text-primary">
                                    <i class="bi bi-clock me-1"></i><?= substr($c['hora_inicio'], 0, 5) ?>
                                </span>
                                <small class="text-muted d-block"><?= substr($c['hora_fin'], 0, 5) ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark font-monospace border"><?= htmlspecialchars($c['codigo_cita']) ?></span></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($c['paciente_nombre']) ?></div>
                                <small class="text-muted">CI: <?= htmlspecialchars($c['paciente_ci']) ?> &bull; Tel: <?= htmlspecialchars($c['paciente_tel']) ?></small>
                            </td>
                            <td>Dr(a). <?= htmlspecialchars($c['medico_nombre']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($c['especialidad_nombre']) ?></span></td>
                            <td class="small text-muted">
                                <?= !empty($c['motivo_consulta']) ? htmlspecialchars($c['motivo_consulta']) : '<em>Consulta general</em>' ?>
                            </td>
                            <td>
                                <span class="badge badge-estado badge-estado-<?= strtolower($c['estado']) ?>">
                                    <?= htmlspecialchars($c['estado']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="/clinicaVidaSalud/views/pacientes/perfil.php?id_paciente=<?= $c['id_paciente'] ?>" class="btn btn-sm btn-outline-info" title="Ver Expediente Clínico (CU-07)">
                                    <i class="bi bi-clipboard2-pulse"></i>
                                </a>
                                <?php if (!AuthMiddleware::esPaciente()): ?>
                                    <a href="/clinicaVidaSalud/views/citas/asistencia.php?fecha=<?= $c['fecha_cita'] ?>&id_medico=<?= $c['id_medico'] ?>" class="btn btn-sm btn-outline-primary" title="Gestionar Asistencia (CU-12)">
                                        <i class="bi bi-check2-square"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>