<?php

/**
 * Vista: Pacientes (CU-06)
 * Paquete 3: Gestión de Pacientes
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Paciente.php';
require_once __DIR__ . '/../../app/models/Usuario.php';
require_once __DIR__ . '/../../app/controllers/PacienteController.php';

AuthMiddleware::verificarRoles(['Administrador', 'Recepcionista']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new PacienteController();
    $controller->handleRequest();
}

$busqueda = $_GET['busqueda'] ?? '';
$pacientes = Paciente::listarTodos($busqueda);
$usuariosPacientes = Usuario::listarTodos(null, 4); // Rol Paciente

$pageTitle = 'Gestión de Pacientes';
$currentPage = 'pacientes';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>

<!-- Encabezado de Caso de Uso para Prototipos de UI (Sección 4.4) -->
<div class="page-header-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div>
                <span class="cu-package-badge">GESTIÓN DE PACIENTES</span>
            </div>
            <h3 class="fw-bold mb-1 text-dark">Gestión de Pacientes</h3>
            <p class="text-muted mb-0 small">
                Padrón de pacientes con validación estricta de CI único y baja lógica sin pérdida de historial.
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearPaciente">
                <i class="bi bi-person-plus-fill"></i>
                <span>Nuevo Paciente</span>
            </button>
        </div>
    </div>
</div>

<!-- Barra de Búsqueda por CI o Nombre -->
<div class="card card-custom p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-9">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="busqueda" class="form-control border-start-0" placeholder="Buscar paciente por Documento de Identidad (CI), Nombres o Apellidos..." value="<?= htmlspecialchars($busqueda) ?>">
            </div>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1">
                <i class="bi bi-filter me-1"></i>Buscar Paciente
            </button>
            <?php if (!empty($busqueda)): ?>
                <a href="/clinicaVidaSalud/views/pacientes/index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabla de Pacientes -->
<div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-people-fill text-primary me-2"></i>Padrón General de Pacientes</span>
        <span class="badge bg-light text-dark border">Total: <?= count($pacientes) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-custom table-hover align-middle">
            <thead>
                <tr>
                    <th style="width: 105px;">Código</th>
                    <th style="width: 100px;">CI</th>
                    <th>Nombres y Apellidos</th>
                    <th>Fecha Nac. / Edad</th>
                    <th>Sexo</th>
                    <th>Contacto</th>
                    <th class="text-center" style="width: 90px;">Citas</th>
                    <th style="width: 100px;">Estado</th>
                    <th class="text-end" style="width: 180px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pacientes)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            <i class="bi bi-person-exclamation fs-3 d-block mb-1"></i>
                            No se encontraron pacientes para el criterio de búsqueda ingresado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pacientes as $p): ?>
                        <?php
                        $edad = date_diff(date_create($p['fecha_nacimiento']), date_create('today'))->y;
                        ?>
                        <tr>
                            <td><span class="badge bg-light text-dark font-monospace border"><?= htmlspecialchars($p['codigo_paciente']) ?></span></td>
                            <td class="fw-bold text-primary font-monospace"><?= htmlspecialchars($p['ci']) ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($p['nombres'] . ' ' . $p['apellidos']) ?></div>
                                <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($p['direccion'] ?? 'Sin dirección') ?></small>
                            </td>
                            <td>
                                <div><?= date('d/m/Y', strtotime($p['fecha_nacimiento'])) ?></div>
                                <small class="text-muted"><?= $edad ?> años</small>
                            </td>
                            <td>
                                <span class="badge <?= $p['sexo'] === 'M' ? 'bg-primary-subtle text-primary' : 'bg-danger-subtle text-danger' ?> border">
                                    <?= $p['sexo'] === 'M' ? 'Masc' : 'Fem' ?>
                                </span>
                            </td>
                            <td class="small">
                                <div><i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($p['telefono']) ?></div>
                                <?php if (!empty($p['email'])): ?>
                                    <div class="text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($p['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    <?= $p['total_citas'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($p['estado'] === 'ACTIVO'): ?>
                                    <span class="badge badge-activo"><i class="bi bi-check-circle-fill me-1"></i>ACTIVO</span>
                                <?php else: ?>
                                    <span class="badge badge-inactivo"><i class="bi bi-slash-circle me-1"></i>INACTIVO</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <!-- Ver Perfil Clínico CU-07 -->
                                    <a href="/clinicaVidaSalud/views/pacientes/perfil.php?id_paciente=<?= $p['id_paciente'] ?>" class="btn btn-outline-info" title="Expediente y Perfil Clínico (CU-07)">
                                        <i class="bi bi-clipboard2-pulse"></i>
                                    </a>

                                    <!-- Ver Historial de Citas CU-13 -->
                                    <a href="/clinicaVidaSalud/views/consultas/historial.php?id_paciente=<?= $p['id_paciente'] ?>" class="btn btn-outline-secondary" title="Historial de Citas (CU-13)">
                                        <i class="bi bi-clock-history"></i>
                                    </a>

                                    <!-- Imprimir Ficha Médica PDF -->
                                    <button type="button" class="btn btn-outline-danger" onclick="imprimirFichaMedica(<?= $p['id_paciente'] ?>)" title="Imprimir Ficha Médica (PDF)">
                                        <i class="bi bi-printer"></i>
                                    </button>

                                    <!-- Editar Paciente -->
                                    <button type="button" class="btn btn-outline-primary"
                                        onclick='editarPaciente(<?= htmlspecialchars(json_encode($p)) ?>)'
                                        title="Editar datos personales">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <!-- Baja Lógica RN11 -->
                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Confirma cambiar el estado de este paciente? (Baja Lógica RN11)');">
                                        <input type="hidden" name="action" value="cambiar_estado">
                                        <input type="hidden" name="id_paciente" value="<?= $p['id_paciente'] ?>">
                                        <input type="hidden" name="nuevo_estado" value="<?= $p['estado'] === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO' ?>">
                                        <?php if ($p['estado'] === 'ACTIVO'): ?>
                                            <button type="submit" class="btn btn-outline-danger" title="Desactivar (Baja lógica RN11)">
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
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Registrar Paciente -->
<div class="modal fade" id="modalCrearPaciente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="store">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-person-plus-fill me-2"></i>Registrar Nuevo Paciente
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Documento de Identidad (CI) <span class="text-danger">*</span></label>
                            <input type="text" name="ci" class="form-control" placeholder="ej. 8451230" required>
                            <small class="text-muted">El CI debe ser único en la base de datos.</small>
                            <div class="invalid-feedback">El CI es obligatorio.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Fecha de Nacimiento <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_nacimiento" class="form-control" max="<?= date('Y-m-d') ?>" required>
                            <div class="invalid-feedback">Ingrese una fecha de nacimiento válida.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nombres <span class="text-danger">*</span></label>
                            <input type="text" name="nombres" class="form-control" placeholder="ej. María" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Apellidos <span class="text-danger">*</span></label>
                            <input type="text" name="apellidos" class="form-control" placeholder="ej. López Ríos" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Sexo Demográfico <span class="text-danger">*</span></label>
                            <select name="sexo" class="form-select" required>
                                <option value="">-- Seleccionar --</option>
                                <option value="F">Femenino (F)</option>
                                <option value="M">Masculino (M)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Teléfono de Contacto <span class="text-danger">*</span></label>
                            <input type="text" name="telefono" class="form-control" placeholder="ej. 70011223" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Correo Electrónico (Opcional)</label>
                            <input type="email" name="email" class="form-control" placeholder="ej. paciente@correo.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Dirección Domiciliaria (Opcional)</label>
                            <input type="text" name="direccion" class="form-control" placeholder="ej. Av. Beni #240">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Vincular a Cuenta de Usuario del Sistema (Opcional)</label>
                            <select name="id_usuario" class="form-select">
                                <option value="">-- Sin cuenta web asociada --</option>
                                <?php foreach ($usuariosPacientes as $u): ?>
                                    <option value="<?= $u['id_usuario'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-save me-1"></i>Guardar Paciente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Editar Paciente -->
<div class="modal fade" id="modalEditarPaciente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id_paciente" id="edit_id_paciente">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-pencil-square me-2"></i>Modificar Información del Paciente
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Documento de Identidad (CI) <span class="text-danger">*</span></label>
                            <input type="text" name="ci" id="edit_ci" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Fecha de Nacimiento <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_nacimiento" id="edit_fecha_nac" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nombres <span class="text-danger">*</span></label>
                            <input type="text" name="nombres" id="edit_nombres" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Apellidos <span class="text-danger">*</span></label>
                            <input type="text" name="apellidos" id="edit_apellidos" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Sexo</label>
                            <select name="sexo" id="edit_sexo" class="form-select" required>
                                <option value="F">Femenino (F)</option>
                                <option value="M">Masculino (M)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Teléfono</label>
                            <input type="text" name="telefono" id="edit_telefono" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Estado</label>
                            <select name="estado" id="edit_estado" class="form-select">
                                <option value="ACTIVO">ACTIVO</option>
                                <option value="INACTIVO">INACTIVO</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Correo Electrónico</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cuenta de Usuario</label>
                            <select name="id_usuario" id="edit_id_usuario" class="form-select">
                                <option value="">-- Sin cuenta web asociada --</option>
                                <?php foreach ($usuariosPacientes as $u): ?>
                                    <option value="<?= $u['id_usuario'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Dirección</label>
                            <input type="text" name="direccion" id="edit_direccion" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-check-lg me-1"></i>Actualizar Paciente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editarPaciente(data) {
        document.getElementById('edit_id_paciente').value = data.id_paciente;
        document.getElementById('edit_ci').value = data.ci;
        document.getElementById('edit_fecha_nac').value = data.fecha_nacimiento;
        document.getElementById('edit_nombres').value = data.nombres;
        document.getElementById('edit_apellidos').value = data.apellidos;
        document.getElementById('edit_sexo').value = data.sexo;
        document.getElementById('edit_telefono').value = data.telefono;
        document.getElementById('edit_email').value = data.email ?? '';
        document.getElementById('edit_direccion').value = data.direccion ?? '';
        document.getElementById('edit_estado').value = data.estado;
        document.getElementById('edit_id_usuario').value = data.id_usuario ?? '';

        const modal = new bootstrap.Modal(document.getElementById('modalEditarPaciente'));
        modal.show();
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>