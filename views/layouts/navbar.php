<?php
/**
 * Layout: Top Navbar
 */
$usuario = AuthMiddleware::usuario();
?>
<div class="main-content">
    <header class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" id="sidebarToggle">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div class="d-none d-sm-block">
                <span class="text-muted" style="font-size: 0.82rem;">
                    <i class="bi bi-geo-alt-fill text-danger me-1"></i>Clínica Vida Salud &bull; Santa Cruz, Bolivia
                </span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-md-block">
                <div class="fw-semibold text-dark" style="font-size: 0.85rem;">
                    <?= htmlspecialchars($usuario['nombre_completo'] ?? $usuario['username'] ?? 'Usuario') ?>
                </div>
                <small class="text-muted" style="font-size: 0.75rem;">
                    Rol: <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($usuario['nombre_rol'] ?? '') ?></span>
                </small>
            </div>
            <div class="dropdown">
                <button class="btn btn-light rounded-circle border shadow-sm p-2 d-flex align-items-center justify-content-center" 
                        type="button" id="userMenuBtn" data-bs-toggle="dropdown" aria-expanded="false" style="width: 38px; height: 38px;">
                    <i class="bi bi-person-fill text-primary"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="userMenuBtn">
                    <li class="dropdown-header">
                        <strong><?= htmlspecialchars($usuario['username'] ?? '') ?></strong>
                        <div class="text-muted small"><?= htmlspecialchars($usuario['email'] ?? '') ?></div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="/clinicaVidaSalud/views/dashboard/index.php">
                            <i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard
                        </a>
                    </li>
                    <?php if (AuthMiddleware::esPaciente()): ?>
                    <li>
                        <a class="dropdown-item" href="/clinicaVidaSalud/views/pacientes/perfil.php">
                            <i class="bi bi-person-badge me-2 text-info"></i>Mi Perfil Clínico
                        </a>
                    </li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="/clinicaVidaSalud/views/auth/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>
    <main class="content-body">
