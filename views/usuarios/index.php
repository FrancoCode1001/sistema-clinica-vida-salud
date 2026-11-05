<?php

require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Usuario.php';
require_once __DIR__ . '/../../app/models/Rol.php';
require_once __DIR__ . '/../../app/controllers/UsuarioController.php';

// Verificar permisos (solo Administrador)
AuthMiddleware::verificarRoles(['Administrador']);

// Procesar acciones POST si existen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new UsuarioController();
    $controller->handleRequest();
}

$busqueda = $_GET['busqueda'] ?? '';
$id_rol_filtro = !empty($_GET['id_rol']) ? (int)$_GET['id_rol'] : null;

$usuarios = Usuario::listarTodos($busqueda, $id_rol_filtro);
$roles = array_filter(Rol::listarTodos(true), function ($r) {
    return strcasecmp($r['nombre_rol'], 'Auditor') !== 0;
});

$pageTitle = 'Gestión de Usuarios y Roles';
$currentPage = 'usuarios';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>

<!-- Encabezado de Caso de Uso para Prototipos de UI (Sección 4.4) -->
<div class="page-header-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div>
                <span class="cu-package-badge">USUARIOS Y SEGURIDAD</span>
            </div>
            <h3 class="fw-bold mb-1 text-dark">Gestión de Usuarios y Roles</h3>
            <p class="text-muted mb-0 small">
                Administración integral de cuentas de acceso, asignación de privilegios y control de estados.
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
                <i class="bi bi-person-plus-fill"></i>
                <span>Nuevo Usuario</span>
            </button>
        </div>
    </div>
</div>

<!-- Filtros y Búsqueda -->
<div class="card card-custom p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="busqueda" class="form-control border-start-0" placeholder="Buscar por nombre de usuario o correo..." value="<?= htmlspecialchars($busqueda) ?>">
            </div>
        </div>
        <div class="col-md-4">
            <select name="id_rol" class="form-select">
                <option value="">-- Todos los roles --</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['id_rol'] ?>" <?= $id_rol_filtro === (int)$r['id_rol'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['nombre_rol']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1">
                <i class="bi bi-filter me-1"></i>Filtrar
            </button>
            <?php if (!empty($busqueda) || !empty($id_rol_filtro)): ?>
                <a href="/clinicaVidaSalud/views/usuarios/index.php" class="btn btn-outline-secondary" title="Limpiar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabla de Usuarios Registrados -->
<div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-people-fill text-primary me-2"></i>Cuentas de Usuario Registradas</span>
        <span class="badge bg-light text-dark border">Total: <?= count($usuarios) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-custom table-hover align-middle">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Usuario</th>
                    <th>Correo Electrónico</th>
                    <th>Rol Asignado</th>
                    <th>Vínculo Clínico</th>
                    <th>Estado</th>
                    <th>Creado</th>
                    <th class="text-end" style="width: 150px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-search fs-3 d-block mb-1"></i>
                            No se encontraron usuarios registrados para el criterio de búsqueda.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td class="fw-bold text-muted">#<?= $u['id_usuario'] ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.85rem;">
                                        <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                    </div>
                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($u['username']) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <?php
                                $badgeRol = match ($u['nombre_rol']) {
                                    'Administrador' => 'bg-danger-subtle text-danger border-danger-subtle',
                                    'Recepcionista' => 'bg-primary-subtle text-primary border-primary-subtle',
                                    'Medico'        => 'bg-info-subtle text-info border-info-subtle',
                                    'Paciente'      => 'bg-success-subtle text-success border-success-subtle',
                                    default         => 'bg-secondary-subtle text-secondary'
                                };
                                ?>
                                <span class="badge <?= $badgeRol ?> border" style="font-size: 0.78rem;">
                                    <?= htmlspecialchars($u['nombre_rol']) ?>
                                </span>
                            </td>
                            <td class="small text-muted">
                                <?= htmlspecialchars($u['nombre_vinculado']) ?>
                            </td>
                            <td>
                                <?php if ($u['estado'] === 'ACTIVO'): ?>
                                    <span class="badge badge-activo"><i class="bi bi-check-circle-fill me-1"></i>ACTIVO</span>
                                <?php else: ?>
                                    <span class="badge badge-inactivo"><i class="bi bi-slash-circle me-1"></i>INACTIVO</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary"
                                        onclick="editarUsuario(<?= htmlspecialchars(json_encode($u)) ?>)"
                                        title="Editar cuenta">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de cambiar el estado de este usuario?');">
                                        <input type="hidden" name="action" value="cambiar_estado">
                                        <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                                        <input type="hidden" name="nuevo_estado" value="<?= $u['estado'] === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO' ?>">
                                        <?php if ($u['estado'] === 'ACTIVO'): ?>
                                            <button type="submit" class="btn btn-outline-danger" title="Desactivar acceso (Baja lógica)">
                                                <i class="bi bi-toggle-on"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-outline-success" title="Reactivar acceso">
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

<!-- Modal: Crear Nuevo Usuario -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-labelledby="modalCrearUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="store">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalCrearUsuarioLabel">
                        <i class="bi bi-person-plus-fill me-2"></i>Registrar Nuevo Usuario
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre de Usuario (Username) <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" placeholder="ej. usuario.clinica" required>
                        <div class="invalid-feedback">El nombre de usuario es obligatorio.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Correo Electrónico <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="ej. correo@vidasalud.com" required>
                        <div class="invalid-feedback">Ingrese un correo electrónico válido.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contraseña Inicial <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Mínimo 6 caracteres" required minlength="6">
                        <small class="text-muted">Se cifrará con <code>password_hash()</code> mediante algoritmo Bcrypt seguro.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rol Asignado <span class="text-danger">*</span></label>
                        <select name="id_rol" class="form-select" required>
                            <option value="">-- Seleccionar Rol --</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id_rol'] ?>"><?= htmlspecialchars($r['nombre_rol']) ?> (<?= htmlspecialchars($r['descripcion']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Estado de la Cuenta</label>
                        <select name="estado" class="form-select">
                            <option value="ACTIVO" selected>ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-save me-1"></i>Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Editar Usuario -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-labelledby="modalEditarUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id_usuario" id="edit_id_usuario">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="modalEditarUsuarioLabel">
                        <i class="bi bi-pencil-square me-2"></i>Modificar Cuenta de Usuario
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre de Usuario (Username) <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Correo Electrónico <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nueva Contraseña (Opcional)</label>
                        <input type="password" name="password" class="form-control" placeholder="Dejar en blanco para conservar la actual">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rol Asignado <span class="text-danger">*</span></label>
                        <select name="id_rol" id="edit_id_rol" class="form-select" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id_rol'] ?>"><?= htmlspecialchars($r['nombre_rol']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Estado de la Cuenta</label>
                        <select name="estado" id="edit_estado" class="form-select">
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-check-lg me-1"></i>Actualizar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editarUsuario(data) {
        document.getElementById('edit_id_usuario').value = data.id_usuario;
        document.getElementById('edit_username').value = data.username;
        document.getElementById('edit_email').value = data.email;
        document.getElementById('edit_id_rol').value = data.id_rol;
        document.getElementById('edit_estado').value = data.estado;
        const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
        modal.show();
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>