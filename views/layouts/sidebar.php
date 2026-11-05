<?php

/**
 * Layout: Sidebar lateral oscuro (Dark Sidebar)
 * Organizado según los 5 Paquetes del Sistema + Sección Auditoría (CU-15)
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';

$usuario = AuthMiddleware::usuario();
$rol = $usuario['nombre_rol'] ?? 'Invitado';
$currentPage = $currentPage ?? '';
?>
<aside class="sidebar" id="appSidebar">
    <!-- Encabezado de Marca -->
    <div class="sidebar-header d-flex align-items-center justify-content-between">
        <a href="/clinicaVidaSalud/views/dashboard/index.php" class="sidebar-brand">
            <span class="brand-badge"><i class="bi bi-hospital"></i></span>
            <div>
                <div class="fw-bold text-white lh-1">Vida Salud</div>
                <small class="text-white-50" style="font-size: 0.7rem; font-weight: 400;">Gestión de Citas</small>
            </div>
        </a>
        <button type="button" class="btn btn-sm btn-link text-white-50 p-1 d-md-none" id="sidebarCloseBtn" title="Cerrar menú">
            <i class="bi bi-x-lg fs-5"></i>
        </button>
    </div>

    <!-- Perfil de Usuario en Sesión -->
    <div class="sidebar-user-card">
        <div class="sidebar-user-avatar">
            <?= strtoupper(substr($usuario['username'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="overflow-hidden">
            <div class="fw-semibold text-white text-truncate" style="font-size: 0.88rem;">
                <?= htmlspecialchars($usuario['nombre_completo'] ?? $usuario['username'] ?? 'Usuario') ?>
            </div>
            <span class="badge bg-primary-subtle text-info border border-info-subtle" style="font-size: 0.68rem;">
                <i class="bi bi-shield-check me-1"></i><?= htmlspecialchars($rol) ?>
            </span>
        </div>
    </div>

    <!-- Menú de Navegación por Paquetes -->
    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <a href="/clinicaVidaSalud/views/dashboard/index.php" class="sidebar-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard Principal</span>
        </a>

        <!-- PAQUETE 1: USUARIOS Y SEGURIDAD -->
        <?php if (AuthMiddleware::esAdmin()): ?>
            <div class="nav-section-title">Usuarios y Seguridad</div>
            <a href="/clinicaVidaSalud/views/usuarios/index.php" class="sidebar-link <?= $currentPage === 'usuarios' ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i>
                <span>Usuarios y Roles</span>
            </a>
        <?php endif; ?>

        <!-- PAQUETE 2: GESTIÓN MÉDICA Y HORARIOS -->
        <?php if (AuthMiddleware::esAdmin() || AuthMiddleware::esRecepcionista()): ?>
            <div class="nav-section-title">Gestión Médica</div>
            <a href="/clinicaVidaSalud/views/especialidades/index.php" class="sidebar-link <?= $currentPage === 'especialidades' ? 'active' : '' ?>">
                <i class="bi bi-tags"></i>
                <span>Especialidades</span>
            </a>
            <a href="/clinicaVidaSalud/views/medicos/index.php" class="sidebar-link <?= $currentPage === 'medicos' ? 'active' : '' ?>">
                <i class="bi bi-person-badge"></i>
                <span>Médicos</span>
            </a>
            <?php if (AuthMiddleware::esAdmin()): ?>
                <a href="/clinicaVidaSalud/views/horarios/index.php" class="sidebar-link <?= $currentPage === 'horarios' ? 'active' : '' ?>">
                    <i class="bi bi-clock-history"></i>
                    <span>Horarios de Atención</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <!-- PAQUETE 3: GESTIÓN DE PACIENTES -->
        <div class="nav-section-title"><?= AuthMiddleware::esMedico() ? 'Fichas Clínicas' : 'Pacientes' ?></div>
        <?php if (AuthMiddleware::esAdmin() || AuthMiddleware::esRecepcionista()): ?>
            <a href="/clinicaVidaSalud/views/pacientes/index.php" class="sidebar-link <?= $currentPage === 'pacientes' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Gestionar Pacientes</span>
            </a>
        <?php endif; ?>
        <a href="/clinicaVidaSalud/views/pacientes/perfil.php" class="sidebar-link <?= $currentPage === 'perfil' ? 'active' : '' ?>">
            <i class="bi bi-clipboard2-pulse"></i>
            <span><?= AuthMiddleware::esMedico() ? 'Ficha Clínica' : 'Perfil Clínico' ?></span>
        </a>

        <!-- PAQUETE 4: PROGRAMACIÓN Y CONTROL DE CITAS -->
        <div class="nav-section-title">Programación de Citas</div>
        <a href="/clinicaVidaSalud/views/citas/disponibilidad.php" class="sidebar-link <?= $currentPage === 'disponibilidad' ? 'active' : '' ?>">
            <i class="bi bi-calendar2-check"></i>
            <span>Disponibilidad</span>
        </a>
        <?php if (AuthMiddleware::esPaciente()): ?>
            <a href="javascript:void(0)" onclick="document.getElementById('btnAsistenteVozFlotante')?.click()" class="sidebar-link text-info">
                <i class="bi bi-mic-fill text-info"></i>
                <span>Asistente por Voz <span class="badge bg-danger ms-1" style="font-size: 0.6rem;">IA</span></span>
            </a>
        <?php endif; ?>
        <?php if (AuthMiddleware::esAdmin() || AuthMiddleware::esRecepcionista() || AuthMiddleware::esPaciente()): ?>
            <a href="/clinicaVidaSalud/views/citas/programar.php" class="sidebar-link <?= $currentPage === 'programar' ? 'active' : '' ?>">
                <i class="bi bi-calendar-plus"></i>
                <span>Programar Cita</span>
            </a>
        <?php endif; ?>
        <?php if (AuthMiddleware::esAdmin() || AuthMiddleware::esRecepcionista()): ?>
            <a href="/clinicaVidaSalud/views/citas/reprogramar.php" class="sidebar-link <?= $currentPage === 'reprogramar' ? 'active' : '' ?>">
                <i class="bi bi-arrow-repeat"></i>
                <span>Reprogramar Cita</span>
            </a>
        <?php endif; ?>
        <?php if (AuthMiddleware::esAdmin() || AuthMiddleware::esRecepcionista() || AuthMiddleware::esPaciente()): ?>
            <a href="/clinicaVidaSalud/views/citas/cancelar.php" class="sidebar-link <?= $currentPage === 'cancelar' ? 'active' : '' ?>">
                <i class="bi bi-calendar-x"></i>
                <span>Cancelar Cita</span>
            </a>
        <?php endif; ?>
        <?php if (AuthMiddleware::esAdmin() || AuthMiddleware::esRecepcionista()): ?>
            <a href="/clinicaVidaSalud/views/citas/asistencia.php" class="sidebar-link <?= $currentPage === 'asistencia' ? 'active' : '' ?>">
                <i class="bi bi-check2-square"></i>
                <span>Control de Asistencia</span>
            </a>
        <?php elseif (AuthMiddleware::esMedico()): ?>
            <a href="/clinicaVidaSalud/views/citas/asistencia.php" class="sidebar-link <?= $currentPage === 'asistencia' ? 'active' : '' ?>">
                <i class="bi bi-check2-square"></i>
                <span>Atender Consultas</span>
            </a>
        <?php endif; ?>

        <!-- PAQUETE 5: CONSULTAS Y REPORTES -->
        <div class="nav-section-title">Consultas y Reportes</div>
        <?php if (AuthMiddleware::esAdmin() || AuthMiddleware::esRecepcionista() || AuthMiddleware::esMedico()): ?>
            <a href="/clinicaVidaSalud/views/consultas/agenda.php" class="sidebar-link <?= $currentPage === 'agenda' ? 'active' : '' ?>">
                <i class="bi bi-calendar-week"></i>
                <span>Agenda Médica Diaria</span>
            </a>
        <?php endif; ?>
        <a href="/clinicaVidaSalud/views/consultas/historial.php" class="sidebar-link <?= $currentPage === 'historial' ? 'active' : '' ?>">
            <i class="bi bi-folder2-open"></i>
            <span>Historial por Paciente</span>
        </a>
        <?php if (AuthMiddleware::esAdmin() || AuthMiddleware::esRecepcionista()): ?>
            <a href="/clinicaVidaSalud/views/reportes/index.php" class="sidebar-link <?= $currentPage === 'reportes' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph"></i>
                <span>Reportes Admin</span>
            </a>
        <?php endif; ?>

        <!-- SECCIÓN AUDITORÍA -->
        <?php if (AuthMiddleware::esAdmin()): ?>
            <div class="nav-section-title">Auditoría General</div>
            <a href="/clinicaVidaSalud/views/auditoria/bitacora.php" class="sidebar-link <?= $currentPage === 'bitacora' ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i>
                <span>Bitácora</span>
            </a>
        <?php endif; ?>

        <!-- CUENTA Y PREFERENCIAS -->
        <div class="nav-section-title">Mi Cuenta</div>
        <a href="/clinicaVidaSalud/views/cuenta/index.php" class="sidebar-link <?= $currentPage === 'cuenta' ? 'active' : '' ?>">
            <i class="bi bi-gear"></i>
            <span>Seguridad y Privacidad</span>
        </a>
    </nav>

    <!-- Footer del Sidebar con Cerrar Sesión -->
    <div class="sidebar-footer">
        <a href="/clinicaVidaSalud/views/auth/logout.php" class="btn btn-outline-danger btn-sm w-100 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-box-arrow-right"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</aside>
<!-- Telón de fondo (Backdrop) para cerrar sidebar en pantallas móviles -->
<div class="sidebar-backdrop d-md-none" id="sidebarBackdrop"></div>