<?php

/**
 * Vista: Dashboard Principal
 * Resumen general y accesos rápidos según el rol autenticado
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../config/db.php';

// Validar sesión activa (CU-01)
AuthMiddleware::verificarAutenticacion();
$usuario = AuthMiddleware::usuario();

// Obtener métricas rápidas de la base de datos
$totalPacientes = 0;
$totalMedicos   = 0;
$citasHoy       = 0;
$citasAtendidas = 0;
$ultimasCitas   = [];

try {
    $pdo = Database::getConnection();

    // Pacientes activos
    $totalPacientes = (int)$pdo->query("SELECT COUNT(*) FROM pacientes WHERE estado = 'ACTIVO'")->fetchColumn();

    // Médicos activos
    $totalMedicos = (int)$pdo->query("SELECT COUNT(*) FROM medicos WHERE estado = 'ACTIVO'")->fetchColumn();

    // Citas para hoy
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM citas_medicas WHERE fecha_cita = CURRENT_DATE AND estado <> 'CANCELADA'");
    $stmt->execute();
    $citasHoy = (int)$stmt->fetchColumn();

    // Citas atendidas totales
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM citas_medicas WHERE estado = 'ATENDIDA'");
    $stmt->execute();
    $citasAtendidas = (int)$stmt->fetchColumn();

    // Últimas citas registradas
    $sql = "SELECT c.id_cita, c.codigo_cita, c.fecha_cita, c.hora_inicio, c.hora_fin, c.estado, c.modalidad,
                   p.nombres || ' ' || p.apellidos AS paciente,
                   m.nombres || ' ' || m.apellidos AS medico,
                   e.nombre AS especialidad
            FROM citas_medicas c
            JOIN pacientes p ON c.id_paciente = p.id_paciente
            JOIN medicos m ON c.id_medico = m.id_medico
            JOIN especialidades e ON c.id_especialidad = e.id_especialidad";

    if (AuthMiddleware::esMedico() && !empty($usuario['id_medico'])) {
        $sql .= " WHERE c.id_medico = " . (int)$usuario['id_medico'];
    } elseif (AuthMiddleware::esPaciente() && !empty($usuario['id_paciente'])) {
        $sql .= " WHERE c.id_paciente = " . (int)$usuario['id_paciente'];
    }

    $sql .= " ORDER BY c.fecha_cita DESC, c.hora_inicio DESC LIMIT 8";
    $ultimasCitas = $pdo->query($sql)->fetchAll();

    // Recordatorio de próxima consulta médica (Sección 3.4 / Perfil del Paciente y Médico)
    $proximaCita = null;
    if (AuthMiddleware::esPaciente() && !empty($usuario['id_paciente'])) {
        $stmtProx = $pdo->prepare("SELECT c.*, m.nombres || ' ' || m.apellidos AS medico, e.nombre AS especialidad
                                   FROM citas_medicas c
                                   JOIN medicos m ON c.id_medico = m.id_medico
                                   JOIN especialidades e ON c.id_especialidad = e.id_especialidad
                                   WHERE c.id_paciente = :id_p AND c.fecha_cita >= CURRENT_DATE AND c.estado IN ('PROGRAMADA', 'CONFIRMADA')
                                   ORDER BY c.fecha_cita ASC, c.hora_inicio ASC LIMIT 1");
        $stmtProx->execute([':id_p' => (int)$usuario['id_paciente']]);
        $proximaCita = $stmtProx->fetch();
    } elseif (AuthMiddleware::esMedico() && !empty($usuario['id_medico'])) {
        $stmtProx = $pdo->prepare("SELECT c.*, p.nombres || ' ' || p.apellidos AS paciente, e.nombre AS especialidad
                                   FROM citas_medicas c
                                   JOIN pacientes p ON c.id_paciente = p.id_paciente
                                   JOIN especialidades e ON c.id_especialidad = e.id_especialidad
                                   WHERE c.id_medico = :id_m AND c.fecha_cita >= CURRENT_DATE AND c.estado IN ('PROGRAMADA', 'CONFIRMADA')
                                   ORDER BY c.fecha_cita ASC, c.hora_inicio ASC LIMIT 1");
        $stmtProx->execute([':id_m' => (int)$usuario['id_medico']]);
        $proximaCita = $stmtProx->fetch();
    }
} catch (Exception $e) {
    // Si la base de datos aún no está migrada, se mantiene en 0
}

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>

<!-- Banner de Bienvenida -->
<div class="card card-custom p-4 mb-4 border-0 text-white" style="background: linear-gradient(135deg, #0284c7 0%, #0d9488 100%);">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-white text-primary fw-bold" style="font-size: 0.75rem;">
                    ROL: <?= strtoupper(htmlspecialchars($usuario['nombre_rol'] ?? 'USUARIO')) ?>
                </span>
                <span class="badge bg-light bg-opacity-25 text-white" style="font-size: 0.75rem;">
                    <i class="bi bi-clock me-1"></i><?= date('d/m/Y') ?>
                </span>
            </div>
            <h2 class="fw-bold mb-1">¡Bienvenido(a), <?= htmlspecialchars($usuario['nombre_completo'] ?? $usuario['username']) ?>!</h2>
            <p class="mb-0 text-white-50" style="font-size: 0.95rem;">
                Sistema Centralizado de Gestión y Control de Citas Médicas &bull; Clínica Vida Salud
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="/clinicaVidaSalud/views/citas/disponibilidad.php" class="btn btn-light text-primary fw-semibold shadow-sm">
                <i class="bi bi-calendar-check me-1"></i>Ver Disponibilidad
            </a>
            <?php if (AuthMiddleware::esAdmin() || AuthMiddleware::esRecepcionista()): ?>
                <a href="/clinicaVidaSalud/views/citas/programar.php" class="btn btn-outline-light fw-semibold">
                    <i class="bi bi-calendar-plus me-1"></i>Programar Cita
                </a>
            <?php endif; ?>
</div>

<?php if (!empty($proximaCita)): ?>
    <!-- Recordatorio Automático de Próxima Consulta (Sección 3.4) -->
    <div class="alert alert-info border-0 shadow-sm d-flex flex-wrap align-items-center justify-content-between p-3 mb-4 rounded-3" style="background: linear-gradient(90deg, #e0f2fe 0%, #f0fdf4 100%); border-left: 5px solid #0284c7 !important;">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 fs-4">
                <i class="bi bi-bell-fill"></i>
            </div>
            <div>
                <span class="badge bg-primary text-white fw-bold mb-1" style="font-size: 0.72rem;">RECORDATORIO DE PRÓXIMA CONSULTA</span>
                <h6 class="fw-bold text-dark mb-1">
                    <?php if (AuthMiddleware::esPaciente()): ?>
                        Tiene una cita agendada con <strong>Dr(a). <?= htmlspecialchars($proximaCita['medico']) ?></strong> (<?= htmlspecialchars($proximaCita['especialidad']) ?>)
                    <?php else: ?>
                        Próxima atención clínica para el paciente <strong><?= htmlspecialchars($proximaCita['paciente']) ?></strong> (<?= htmlspecialchars($proximaCita['especialidad']) ?>)
                    <?php endif; ?>
                </h6>
                <div class="small text-muted">
                    <i class="bi bi-calendar-event me-1 text-primary"></i><strong><?= date('d/m/Y', strtotime($proximaCita['fecha_cita'])) ?></strong>
                    &bull; <i class="bi bi-clock me-1 text-primary"></i><strong><?= substr($proximaCita['hora_inicio'], 0, 5) ?> - <?= substr($proximaCita['hora_fin'], 0, 5) ?></strong>
                    &bull; Código: <code class="text-primary"><?= htmlspecialchars($proximaCita['codigo_cita']) ?></code>
                    &bull; Modalidad: <strong><?= htmlspecialchars($proximaCita['modalidad']) ?></strong>
                    &bull; Estado: <span class="badge badge-estado badge-estado-<?= strtolower($proximaCita['estado']) ?>"><?= htmlspecialchars($proximaCita['estado']) ?></span>
                </div>
            </div>
        </div>
        <div class="mt-2 mt-md-0">
            <?php if (AuthMiddleware::esPaciente()): ?>
                <a href="/clinicaVidaSalud/views/consultas/historial.php" class="btn btn-sm btn-primary shadow-sm">
                    <i class="bi bi-clipboard2-pulse me-1"></i>Ver Mis Citas
                </a>
            <?php else: ?>
                <a href="/clinicaVidaSalud/views/consultas/agenda.php" class="btn btn-sm btn-primary shadow-sm">
                    <i class="bi bi-calendar-check me-1"></i>Ir a la Agenda
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Tarjetas de Métricas Rápidas (KPIs) -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small fw-semibold text-uppercase">Pacientes Activos</span>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($totalPacientes) ?></h3>
                <small class="text-success"><i class="bi bi-check-circle me-1"></i>Registrados</small>
            </div>
            <div class="kpi-icon-wrapper bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-people-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small fw-semibold text-uppercase">Plantel Médico</span>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($totalMedicos) ?></h3>
                <small class="text-info"><i class="bi bi-person-badge me-1"></i>Especialistas</small>
            </div>
            <div class="kpi-icon-wrapper bg-info bg-opacity-10 text-info">
                <i class="bi bi-heart-pulse-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small fw-semibold text-uppercase">Citas para Hoy</span>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($citasHoy) ?></h3>
                <small class="text-primary"><i class="bi bi-calendar-event me-1"></i>Agendadas</small>
            </div>
            <div class="kpi-icon-wrapper bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-calendar-week-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small fw-semibold text-uppercase">Citas Atendidas</span>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($citasAtendidas) ?></h3>
                <small class="text-success"><i class="bi bi-check2-all me-1"></i>Histórico</small>
            </div>
            <div class="kpi-icon-wrapper bg-success bg-opacity-10 text-success">
                <i class="bi bi-clipboard2-check-fill"></i>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de Actividad Reciente de Citas -->
<div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-primary fs-5"></i>
            <span class="fw-bold">Últimas Citas en el Sistema</span>
        </div>
        <div>
            <a href="/clinicaVidaSalud/views/consultas/agenda.php" class="btn btn-sm btn-outline-primary">
                Ver Agenda Completa <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-custom table-hover align-middle">
            <thead>
                <tr>
                    <th>Código Cita</th>
                    <th>Paciente</th>
                    <th>Médico</th>
                    <th>Especialidad</th>
                    <th>Fecha y Horario</th>
                    <th>Modalidad</th>
                    <th>Estado Actual</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ultimasCitas)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-calendar-x fs-3 d-block mb-1"></i>
                            No hay registros de citas médicas disponibles.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ultimasCitas as $c): ?>
                        <tr>
                            <td class="fw-bold text-primary font-monospace"><?= htmlspecialchars($c['codigo_cita']) ?></td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($c['paciente']) ?></td>
                            <td><?= htmlspecialchars($c['medico']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($c['especialidad']) ?></span></td>
                            <td>
                                <div><i class="bi bi-calendar me-1 text-muted"></i><?= date('d/m/Y', strtotime($c['fecha_cita'])) ?></div>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= substr($c['hora_inicio'], 0, 5) ?> - <?= substr($c['hora_fin'], 0, 5) ?></small>
                            </td>
                            <td><?= htmlspecialchars($c['modalidad']) ?></td>
                            <td>
                                <?php
                                $estadoClass = match ($c['estado']) {
                                    'PROGRAMADA'   => 'badge-estado-programada',
                                    'CONFIRMADA'   => 'badge-estado-confirmada',
                                    'ATENDIDA'     => 'badge-estado-atendida',
                                    'CANCELADA'    => 'badge-estado-cancelada',
                                    'INASISTENCIA' => 'badge-estado-inasistencia',
                                    default        => 'bg-secondary'
                                };
                                ?>
                                <span class="badge-estado <?= $estadoClass ?>">
                                    <?= htmlspecialchars($c['estado']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>