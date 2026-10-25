<?php

/**
 * Vista: Médicos (CU-04)
 * Paquete 2: Gestión Médica y Horarios
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Medico.php';
require_once __DIR__ . '/../../app/models/Especialidad.php';
require_once __DIR__ . '/../../app/models/Usuario.php';
require_once __DIR__ . '/../../app/controllers/MedicoController.php';

AuthMiddleware::verificarRoles(['Administrador', 'Recepcionista']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && AuthMiddleware::esAdmin()) {
    $controller = new MedicoController();
    $controller->handleRequest();
}

$busqueda = $_GET['busqueda'] ?? '';
$id_esp_filtro = !empty($_GET['id_especialidad']) ? (int)$_GET['id_especialidad'] : null;

$medicos = Medico::listarTodos($busqueda, $id_esp_filtro);
$especialidades = Especialidad::listarTodas(true);
$usuariosMedicos = Usuario::listarTodos(null, 3); // Rol Médico

$pageTitle = 'Gestión de Médicos';
$currentPage = 'medicos';
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
            <h3 class="fw-bold mb-1 text-dark">Gestión del Plantel de Médicos</h3>
            <p class="text-muted mb-0 small">
                Registro de especialistas, matrícula profesional, asignación de especialidades y baja lógica sin pérdida de histórico.
            </p>
        </div>
        <?php if (AuthMiddleware::esAdmin()): ?>
            <div>
                <button type="button" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearMedico">
                    <i class="bi bi-person-plus-fill"></i>
                    <span>Nuevo Médico</span>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Filtros y Búsqueda -->
<div class="card card-custom p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="busqueda" class="form-control border-start-0" placeholder="Buscar por nombre, código o matrícula..." value="<?= htmlspecialchars($busqueda) ?>">
            </div>
        </div>
        <div class="col-md-4">
            <select name="id_especialidad" class="form-select">
                <option value="">-- Todas las especialidades --</option>
                <?php foreach ($especialidades as $esp): ?>
                    <option value="<?= $esp['id_especialidad'] ?>" <?= $id_esp_filtro === (int)$esp['id_especialidad'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($esp['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1">
                <i class="bi bi-filter me-1"></i>Filtrar
            </button>
            <?php if (!empty($busqueda) || !empty($id_esp_filtro)): ?>
                <a href="/clinicaVidaSalud/views/medicos/index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabla de Médicos -->
<div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-heart-pulse text-primary me-2"></i>Especialistas Clínicos Registrados</span>
        <span class="badge bg-light text-dark border">Total: <?= count($medicos) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-custom table-hover align-middle">
            <thead>
                <tr>
                    <th style="width: 100px;">Código</th>
                    <th>Médico</th>
                    <th>Matrícula Prof.</th>
                    <th>Especialidad(es)</th>
                    <th>Contacto</th>
                    <th class="text-center" style="width: 90px;">Citas</th>
                    <th style="width: 100px;">Estado</th>
                    <th class="text-end" style="width: 160px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($medicos)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-person-x fs-3 d-block mb-1"></i>
                            No se encontraron médicos registrados para el criterio seleccionado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($medicos as $m): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark font-monospace border"><?= htmlspecialchars($m['codigo_medico']) ?></span></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px;">
                                        Dr
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($m['nombres'] . ' ' . $m['apellidos']) ?></div>
                                        <small class="text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($m['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><code class="text-primary"><?= htmlspecialchars($m['nro_registro_profesional']) ?></code></td>
                            <td>
                                <?php
                                $arrEsp = explode(', ', $m['especialidades'] ?? 'Sin asignar');
                                foreach ($arrEsp as $esp):
                                ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle mb-1 me-1">
                                        <?= htmlspecialchars($esp) ?>
                                    </span>
                                <?php endforeach; ?>
                            </td>
                            <td class="small">
                                <i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($m['telefono']) ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    <?= $m['total_citas'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($m['estado'] === 'ACTIVO'): ?>
                                    <span class="badge badge-activo"><i class="bi bi-check-circle-fill me-1"></i>ACTIVO</span>
                                <?php else: ?>
                                    <span class="badge badge-inactivo"><i class="bi bi-slash-circle me-1"></i>INACTIVO</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <!-- Ver / Configurar Horarios CU-05 -->
                                    <a href="/clinicaVidaSalud/views/horarios/index.php?id_medico=<?= $m['id_medico'] ?>" class="btn btn-outline-info" title="Configurar Turnos Horarios (CU-05)">
                                        <i class="bi bi-clock-history"></i>
                                    </a>

                                    <!-- Consultar Agenda Médica RF03 / RF11 -->
                                    <a href="/clinicaVidaSalud/views/consultas/agenda.php?id_medico=<?= $m['id_medico'] ?>" class="btn btn-outline-secondary" title="Consultar Agenda Médica (RF03 / RF11)">
                                        <i class="bi bi-calendar-week"></i>
                                    </a>

                                    <?php if (AuthMiddleware::esAdmin()): ?>
                                        <!-- Editar Médico -->
                                        <button type="button" class="btn btn-outline-primary"
                                            onclick='editarMedico(<?= htmlspecialchars(json_encode($m)) ?>, <?= htmlspecialchars(json_encode(Medico::obtenerEspecialidadesIds((int)$m['id_medico']))) ?>)'
                                            title="Editar información">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <!-- Baja Lógica RN04 / RN11 -->
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Confirma cambiar el estado del médico? Si tiene citas no se borrará físicamente (Baja Lógica RN11).');">
                                            <input type="hidden" name="action" value="cambiar_estado">
                                            <input type="hidden" name="id_medico" value="<?= $m['id_medico'] ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?= $m['estado'] === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO' ?>">
                                            <?php if ($m['estado'] === 'ACTIVO'): ?>
                                                <button type="submit" class="btn btn-outline-danger" title="Desactivar (Baja lógica RN11)">
                                                    <i class="bi bi-toggle-on"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-outline-success" title="Reactivar">
                                                    <i class="bi bi-toggle-off"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Crear Nuevo Médico -->
<div class="modal fade" id="modalCrearMedico" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="store">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-person-plus-fill me-2"></i>Registrar Nuevo Médico Especialista
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Código Único del Médico <span class="text-danger">*</span></label>
                            <input type="text" name="codigo_medico" class="form-control" placeholder="ej. MED-006" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nro. Matrícula / Registro Prof. <span class="text-danger">*</span></label>
                            <input type="text" name="nro_registro_profesional" class="form-control" placeholder="ej. MP-60111" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nombres <span class="text-danger">*</span></label>
                            <input type="text" name="nombres" class="form-control" placeholder="ej. Juan Carlos" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Apellidos <span class="text-danger">*</span></label>
                            <input type="text" name="apellidos" class="form-control" placeholder="ej. Pérez Salvatierra" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Teléfono de Contacto <span class="text-danger">*</span></label>
                            <input type="text" name="telefono" class="form-control" placeholder="ej. 70911111" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Correo Electrónico <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="ej. medico@vidasalud.com" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Especialidad(es) que Atiende <span class="text-danger">*</span></label>
                            <div class="border rounded p-3 bg-light d-flex flex-wrap gap-3">
                                <?php foreach ($especialidades as $esp): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="especialidades[]" value="<?= $esp['id_especialidad'] ?>" id="esp_crear_<?= $esp['id_especialidad'] ?>">
                                        <label class="form-check-label fw-semibold" for="esp_crear_<?= $esp['id_especialidad'] ?>">
                                            <?= htmlspecialchars($esp['nombre']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Vincular a Cuenta de Usuario (Opcional)</label>
                            <select name="id_usuario" class="form-select">
                                <option value="">-- Sin cuenta asignada --</option>
                                <?php foreach ($usuariosMedicos as $u): ?>
                                    <option value="<?= $u['id_usuario'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-save me-1"></i>Guardar Médico
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Editar Médico -->
<div class="modal fade" id="modalEditarMedico" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id_medico" id="edit_id_medico">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-pencil-square me-2"></i>Modificar Información del Médico
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Código Único del Médico <span class="text-danger">*</span></label>
                            <input type="text" name="codigo_medico" id="edit_codigo_medico" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nro. Matrícula / Registro Prof. <span class="text-danger">*</span></label>
                            <input type="text" name="nro_registro_profesional" id="edit_matricula" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nombres <span class="text-danger">*</span></label>
                            <input type="text" name="nombres" id="edit_nombres" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Apellidos <span class="text-danger">*</span></label>
                            <input type="text" name="apellidos" id="edit_apellidos" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Teléfono <span class="text-danger">*</span></label>
                            <input type="text" name="telefono" id="edit_telefono" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Correo Electrónico <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Estado</label>
                            <select name="estado" id="edit_estado_medico" class="form-select">
                                <option value="ACTIVO">ACTIVO</option>
                                <option value="INACTIVO">INACTIVO</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Usuario Asociado</label>
                            <select name="id_usuario" id="edit_id_usuario_medico" class="form-select">
                                <option value="">-- Sin cuenta asignada --</option>
                                <?php foreach ($usuariosMedicos as $u): ?>
                                    <option value="<?= $u['id_usuario'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Especialidad(es) <span class="text-danger">*</span></label>
                            <div class="border rounded p-3 bg-light d-flex flex-wrap gap-3">
                                <?php foreach ($especialidades as $esp): ?>
                                    <div class="form-check">
                                        <input class="form-check-input edit-esp-chk" type="checkbox" name="especialidades[]" value="<?= $esp['id_especialidad'] ?>" id="edit_esp_<?= $esp['id_especialidad'] ?>">
                                        <label class="form-check-label fw-semibold" for="edit_esp_<?= $esp['id_especialidad'] ?>">
                                            <?= htmlspecialchars($esp['nombre']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-check-lg me-1"></i>Actualizar Médico
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editarMedico(data, espIds) {
        document.getElementById('edit_id_medico').value = data.id_medico;
        document.getElementById('edit_codigo_medico').value = data.codigo_medico;
        document.getElementById('edit_matricula').value = data.nro_registro_profesional;
        document.getElementById('edit_nombres').value = data.nombres;
        document.getElementById('edit_apellidos').value = data.apellidos;
        document.getElementById('edit_telefono').value = data.telefono;
        document.getElementById('edit_email').value = data.email;
        document.getElementById('edit_estado_medico').value = data.estado;
        document.getElementById('edit_id_usuario_medico').value = data.id_usuario ?? '';

        // Marcar checkboxes de especialidades asignadas
        document.querySelectorAll('.edit-esp-chk').forEach(chk => {
            chk.checked = espIds.includes(parseInt(chk.value));
        });

        const modal = new bootstrap.Modal(document.getElementById('modalEditarMedico'));
        modal.show();
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>