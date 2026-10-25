<?php

/**
 * Vista: Especialidades Médicas (CU-03)
 * Paquete 2: Gestión Médica y Horarios
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Especialidad.php';
require_once __DIR__ . '/../../app/controllers/EspecialidadController.php';

AuthMiddleware::verificarRoles(['Administrador', 'Recepcionista']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && AuthMiddleware::esAdmin()) {
    $controller = new EspecialidadController();
    $controller->handleRequest();
}

$busqueda = $_GET['busqueda'] ?? '';
$especialidades = Especialidad::listarTodas(false, $busqueda);

$pageTitle = 'Gestión de Especialidades Médicas';
$currentPage = 'especialidades';
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
            <h3 class="fw-bold mb-1 text-dark">Gestión de Especialidades Médicas</h3>
            <p class="text-muted mb-0 small">
                Catálogo institucional de especialidades clínicas con normalización automática de nombres.
            </p>
        </div>
        <?php if (AuthMiddleware::esAdmin()): ?>
            <div>
                <button type="button" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearEsp">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>Nueva Especialidad</span>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Barra de Búsqueda -->
<div class="card card-custom p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-8">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="busqueda" class="form-control border-start-0" placeholder="Buscar por nombre o descripción de la especialidad..." value="<?= htmlspecialchars($busqueda) ?>">
            </div>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1">
                <i class="bi bi-filter me-1"></i>Buscar
            </button>
            <?php if (!empty($busqueda)): ?>
                <a href="/clinicaVidaSalud/views/especialidades/index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabla de Especialidades -->
<div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-tags-fill text-primary me-2"></i>Catálogo de Especialidades Clínicas</span>
        <span class="badge bg-light text-dark border">Total: <?= count($especialidades) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-custom table-hover align-middle">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Especialidad</th>
                    <th>Descripción Médica</th>
                    <th class="text-center" style="width: 130px;">Médicos</th>
                    <th class="text-center" style="width: 130px;">Citas</th>
                    <th style="width: 110px;">Estado</th>
                    <?php if (AuthMiddleware::esAdmin()): ?>
                        <th class="text-end" style="width: 140px;">Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($especialidades)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-tags fs-3 d-block mb-1"></i>
                            No se encontraron especialidades registradas.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($especialidades as $e): ?>
                        <tr>
                            <td class="fw-bold text-muted">#<?= $e['id_especialidad'] ?></td>
                            <td>
                                <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($e['nombre']) ?></span>
                            </td>
                            <td class="small text-muted">
                                <?= htmlspecialchars($e['descripcion'] ?? 'Sin descripción') ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info-subtle text-info border border-info-subtle">
                                    <i class="bi bi-people me-1"></i><?= $e['total_medicos'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    <i class="bi bi-calendar-event me-1"></i><?= $e['total_citas'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($e['estado'] === 'ACTIVO'): ?>
                                    <span class="badge badge-activo"><i class="bi bi-check-circle-fill me-1"></i>ACTIVO</span>
                                <?php else: ?>
                                    <span class="badge badge-inactivo"><i class="bi bi-slash-circle me-1"></i>INACTIVO</span>
                                <?php endif; ?>
                            </td>
                            <?php if (AuthMiddleware::esAdmin()): ?>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary"
                                            onclick="editarEsp(<?= htmlspecialchars(json_encode($e)) ?>)"
                                            title="Editar especialidad">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de cambiar el estado de esta especialidad?');">
                                            <input type="hidden" name="action" value="cambiar_estado">
                                            <input type="hidden" name="id_especialidad" value="<?= $e['id_especialidad'] ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?= $e['estado'] === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO' ?>">
                                            <?php if ($e['estado'] === 'ACTIVO'): ?>
                                                <button type="submit" class="btn btn-outline-danger" title="Desactivar">
                                                    <i class="bi bi-toggle-on"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-outline-success" title="Reactivar">
                                                    <i class="bi bi-toggle-off"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Crear Especialidad -->
<div class="modal fade" id="modalCrearEsp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="store">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-plus-circle me-2"></i>Registrar Nueva Especialidad
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre de la Especialidad <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" placeholder="ej. Cardiología, Pediatría..." required>
                        <small class="text-muted">El sistema aplica <code>INITCAP(TRIM())</code> y valida que no existan duplicados.</small>
                        <div class="invalid-feedback">El nombre de la especialidad es obligatorio.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción o Alcance Clínico</label>
                        <textarea name="descripcion" class="form-control" rows="3" placeholder="Breve descripción del alcance clínico..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-save me-1"></i>Guardar Especialidad
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Editar Especialidad -->
<div class="modal fade" id="modalEditarEsp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id_especialidad" id="edit_id_esp">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-pencil-square me-2"></i>Modificar Especialidad
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre de la Especialidad <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" id="edit_nombre_esp" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea name="descripcion" id="edit_desc_esp" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Estado</label>
                        <select name="estado" id="edit_estado_esp" class="form-select">
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-check-lg me-1"></i>Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editarEsp(data) {
        document.getElementById('edit_id_esp').value = data.id_especialidad;
        document.getElementById('edit_nombre_esp').value = data.nombre;
        document.getElementById('edit_desc_esp').value = data.descripcion ?? '';
        document.getElementById('edit_estado_esp').value = data.estado;
        const modal = new bootstrap.Modal(document.getElementById('modalEditarEsp'));
        modal.show();
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>