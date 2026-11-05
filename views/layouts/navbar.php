<?php
/**
 * Layout: Top Navbar
 */
$usuario = AuthMiddleware::usuario();
?>
<div class="main-content">
    <header class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-flex align-items-center justify-content-center" 
                    type="button" id="sidebarToggle" title="Ocultar / Mostrar menú lateral" style="width: 36px; height: 36px;">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div class="d-none d-sm-block">
                <span class="text-muted" style="font-size: 0.82rem;">
                    <i class="bi bi-geo-alt-fill text-danger me-1"></i>Clínica Vida Salud &bull; Santa Cruz, Bolivia
                </span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <div class="text-end d-none d-md-block me-2">
                <div class="fw-semibold text-dark" style="font-size: 0.85rem;">
                    <?= htmlspecialchars($usuario['nombre_completo'] ?? $usuario['username'] ?? 'Usuario') ?>
                </div>
                <small class="text-muted" style="font-size: 0.75rem;">
                    Rol: <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($usuario['nombre_rol'] ?? '') ?></span>
                </small>
            </div>

            <!-- Sistema de Notificaciones Internas (Campana con Contador) -->
            <div class="dropdown me-1">
                <button class="btn btn-light rounded-circle border shadow-sm p-2 position-relative d-flex align-items-center justify-content-center" 
                        type="button" id="notifBellBtn" data-bs-toggle="dropdown" aria-expanded="false" style="width: 38px; height: 38px;" title="Notificaciones internas">
                    <i class="bi bi-bell-fill text-secondary fs-6" id="notifBellIcon"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notifBadgeCounter" style="font-size: 0.65rem; padding: 0.25em 0.5em;">
                        0
                    </span>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 p-0" aria-labelledby="notifBellBtn" style="width: 360px; max-width: 90vw; z-index: 1060;">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white rounded-top">
                        <div class="fw-bold text-dark small d-flex align-items-center gap-2">
                            <i class="bi bi-bell-fill text-primary"></i>
                            <span>Notificaciones del Sistema</span>
                        </div>
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small text-muted" onclick="marcarTodasNotificacionesLeidas(event)" style="font-size: 0.75rem;">
                            Marcar todas leídas
                        </button>
                    </div>
                    <div class="notificaciones-lista" id="notifItemsContainer" style="max-height: 380px; overflow-y: auto;">
                        <div class="p-4 text-center text-muted small">
                            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                            <div>Cargando notificaciones...</div>
                        </div>
                    </div>
                    <div class="p-2 text-center border-top bg-light rounded-bottom">
                        <a href="/clinicaVidaSalud/views/cuenta/index.php" class="text-decoration-none text-muted small" style="font-size: 0.75rem;">
                            <i class="bi bi-gear me-1"></i>Configurar notificaciones y privacidad
                        </a>
                    </div>
                </div>
            </div>

            <!-- Menú de Usuario -->
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
                            <i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard Principal
                        </a>
                    </li>
                    <?php if (AuthMiddleware::esPaciente()): ?>
                    <li>
                        <a class="dropdown-item" href="/clinicaVidaSalud/views/pacientes/perfil.php">
                            <i class="bi bi-person-badge me-2 text-info"></i>Mi Perfil y Expediente
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a class="dropdown-item" href="/clinicaVidaSalud/views/cuenta/index.php">
                            <i class="bi bi-gear-fill me-2 text-secondary"></i>Configuración de Cuenta
                        </a>
                    </li>
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
