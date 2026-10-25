<?php

/**
 * Vista: Bitácora de Auditoría (CU-15)
 * Sección de Auditoría General del Sistema
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Bitacora.php';
require_once __DIR__ . '/../../app/models/Usuario.php';

// Verificar permisos (solo Administrador)
AuthMiddleware::verificarRoles(['Administrador']);

// Procesar exportación a CSV si se solicita
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filtros = [
        'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
        'fecha_fin'    => $_GET['fecha_fin'] ?? '',
        'id_usuario'   => $_GET['id_usuario'] ?? '',
        'accion'       => $_GET['accion'] ?? '',
        'modulo'       => $_GET['modulo'] ?? '',
    ];
    $registros = Bitacora::listar($filtros, 1000);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=bitacora_auditoria_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
    fputcsv($output, ['ID', 'Fecha y Hora', 'Usuario', 'Rol', 'Acción', 'Módulo', 'Descripción', 'IP Acceso'], ';');
    foreach ($registros as $row) {
        fputcsv($output, [
            $row['id_bitacora'],
            $row['fecha_hora'],
            $row['username'],
            $row['nombre_rol'],
            $row['accion'],
            $row['modulo'],
            $row['descripcion'],
            $row['ip_acceso']
        ], ';');
    }
    fclose($output);
    exit();
}

$filtros = [
    'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
    'fecha_fin'    => $_GET['fecha_fin'] ?? '',
    'id_usuario'   => $_GET['id_usuario'] ?? '',
    'accion'       => $_GET['accion'] ?? '',
    'modulo'       => $_GET['modulo'] ?? '',
];

$bitacoras = Bitacora::listar($filtros, 200);
$accionesDisponibles = Bitacora::obtenerAcciones();
$modulosDisponibles  = Bitacora::obtenerModulos();
$usuarios = Usuario::listarTodos();

$pageTitle = 'Bitácora de Auditoría';
$currentPage = 'bitacora';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>

<!-- Encabezado de Caso de Uso para Prototipos de UI (Sección 4.4) -->
<div class="page-header-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div>
                <span class="cu-package-badge">AUDITORÍA GENERAL DEL SISTEMA</span>
            </div>
            <h3 class="fw-bold mb-1 text-dark">Bitácora de Auditoría y Trazabilidad</h3>
            <p class="text-muted mb-0 small">
                Registro cronológico inmutable de operaciones (INSERT, UPDATE, DELETE, LOGIN, LOGOUT), IP de origen y usuario responsable.
            </p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-2" onclick="window.print()">
                <i class="bi bi-printer"></i>
                <span class="d-none d-md-inline">Imprimir</span>
            </button>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-success d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-spreadsheet"></i>
                <span>Exportar CSV</span>
            </a>
        </div>
    </div>
</div>

<!-- Filtros de Auditoría -->
<div class="card card-custom p-3 mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Fecha Desde</label>
            <input type="date" name="fecha_inicio" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['fecha_inicio']) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Fecha Hasta</label>
            <input type="date" name="fecha_fin" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['fecha_fin']) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Usuario</label>
            <select name="id_usuario" class="form-select form-select-sm">
                <option value="">-- Todos --</option>
                <?php foreach ($usuarios as $u): ?>
                    <option value="<?= $u['id_usuario'] ?>" <?= ($filtros['id_usuario'] == $u['id_usuario']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['username']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Acción</label>
            <select name="accion" class="form-select form-select-sm">
                <option value="">-- Todas --</option>
                <?php foreach ($accionesDisponibles as $acc): ?>
                    <option value="<?= $acc ?>" <?= ($filtros['accion'] === $acc) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($acc) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Módulo / Tabla</label>
            <select name="modulo" class="form-select form-select-sm">
                <option value="">-- Todos --</option>
                <?php foreach ($modulosDisponibles as $mod): ?>
                    <option value="<?= $mod ?>" <?= (strtolower($filtros['modulo']) === strtolower($mod)) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($mod) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                <i class="bi bi-funnel me-1"></i>Filtrar
            </button>
            <a href="/clinicaVidaSalud/views/auditoria/bitacora.php" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>
    </form>
</div>

<!-- Tabla de Registros de Bitácora -->
<div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-journal-text text-primary me-2"></i>Registros de Operaciones y Seguridad</span>
        <span class="badge bg-light text-dark border">Mostrando: <?= count($bitacoras) ?> eventos</span>
    </div>
    <div class="table-responsive">
        <table class="table table-custom table-hover align-middle">
            <thead>
                <tr>
                    <th style="width: 70px;">#</th>
                    <th style="width: 160px;">Fecha y Hora</th>
                    <th style="width: 140px;">Usuario</th>
                    <th style="width: 100px;">Acción</th>
                    <th style="width: 130px;">Módulo</th>
                    <th>Descripción de la Operación</th>
                    <th style="width: 120px;">IP Origen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bitacoras)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                            No se encontraron registros de auditoría para los filtros indicados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bitacoras as $b): ?>
                        <tr>
                            <td class="text-muted fw-bold">#<?= $b['id_bitacora'] ?></td>
                            <td class="small">
                                <i class="bi bi-clock me-1 text-muted"></i>
                                <?= date('d/m/Y H:i:s', strtotime($b['fecha_hora'])) ?>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark" style="font-size: 0.85rem;">
                                    <?= htmlspecialchars($b['username']) ?>
                                </div>
                                <small class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($b['nombre_rol']) ?></small>
                            </td>
                            <td>
                                <?php
                                $badgeAccion = match ($b['accion']) {
                                    'LOGIN'  => 'bg-info-subtle text-info border-info-subtle',
                                    'LOGOUT' => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                                    'INSERT' => 'bg-success-subtle text-success border-success-subtle',
                                    'UPDATE' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                    'DELETE' => 'bg-danger-subtle text-danger border-danger-subtle',
                                    default  => 'bg-light text-dark'
                                };
                                ?>
                                <span class="badge <?= $badgeAccion ?> border fw-bold" style="font-size: 0.75rem;">
                                    <?= htmlspecialchars($b['accion']) ?>
                                </span>
                            </td>
                            <td>
                                <code class="text-dark bg-light px-2 py-1 rounded" style="font-size: 0.8rem;">
                                    <?= htmlspecialchars($b['modulo']) ?>
                                </code>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <?= htmlspecialchars($b['descripcion']) ?>
                            </td>
                            <td class="small text-muted font-monospace">
                                <?= htmlspecialchars($b['ip_acceso'] ?? '127.0.0.1') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>