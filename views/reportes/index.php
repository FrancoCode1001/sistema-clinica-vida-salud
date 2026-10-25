<?php

/**
 * Vista: Reportes Administrativos (CU-14)
 * Paquete 5: Consultas y Reportes (RF13)
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/controllers/ReporteController.php';
require_once __DIR__ . '/../../app/models/CitaMedica.php';
require_once __DIR__ . '/../../app/models/Medico.php';
require_once __DIR__ . '/../../app/models/Especialidad.php';

AuthMiddleware::verificarRoles(['Administrador', 'Recepcionista']);

$tipoReporte = $_GET['tipo_reporte'] ?? 'estados';
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01'); // Primer día del mes actual
$fechaFin    = $_GET['fecha_fin'] ?? date('Y-m-t');    // Último día del mes actual
$id_medico   = !empty($_GET['id_medico']) ? (int)$_GET['id_medico'] : null;
$id_esp      = !empty($_GET['id_especialidad']) ? (int)$_GET['id_especialidad'] : null;
$estado      = $_GET['estado'] ?? null;

// Validación de fechas
if ($fechaFin < $fechaInicio) {
    $_SESSION['flash_error'] = 'Error de validación: La fecha final no puede ser menor a la fecha inicial.';
    $fechaFin = $fechaInicio;
}

// Exportación a CSV (CU-14)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=reporte_' . $tipoReporte . '_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

    if ($tipoReporte === 'estados') {
        $data = ReporteController::reportePorEstado($fechaInicio, $fechaFin);
        fputcsv($output, ['Estado de Cita', 'Total Citas', 'Porcentaje (%)'], ';');
        foreach ($data['filas'] as $r) {
            fputcsv($output, [$r['estado'], $r['total_citas'], $r['porcentaje'] . '%'], ';');
        }
        fputcsv($output, ['TOTAL GENERAL', $data['total_general'], '100%'], ';');
    } elseif ($tipoReporte === 'especialidades') {
        $rows = ReporteController::reportePorEspecialidad($fechaInicio, $fechaFin);
        fputcsv($output, ['Especialidad Médica', 'Total Citas Registradas'], ';');
        foreach ($rows as $r) {
            fputcsv($output, [$r['especialidad'], $r['total_citas']], ';');
        }
    } elseif ($tipoReporte === 'medicos') {
        $rows = ReporteController::reportePorMedico($fechaInicio, $fechaFin);
        fputcsv($output, ['Código', 'Médico Especialista', 'Total Citas', 'Atendidas', 'Canceladas', 'Inasistencias'], ';');
        foreach ($rows as $r) {
            fputcsv($output, [$r['codigo_medico'], $r['medico'], $r['total_citas'], $r['atendidas'], $r['canceladas'], $r['inasistencias']], ';');
        }
    } else {
        $citas = CitaMedica::listar([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
            'id_medico'    => $id_medico,
            'id_especialidad' => $id_esp,
            'estado'       => $estado
        ]);
        fputcsv($output, ['Código', 'Fecha Cita', 'Hora Inicio', 'Hora Fin', 'Paciente', 'CI', 'Médico', 'Especialidad', 'Modalidad', 'Estado'], ';');
        foreach ($citas as $c) {
            fputcsv($output, [
                $c['codigo_cita'],
                $c['fecha_cita'],
                $c['hora_inicio'],
                $c['hora_fin'],
                $c['paciente_nombre'],
                $c['paciente_ci'],
                $c['medico_nombre'],
                $c['especialidad_nombre'],
                $c['modalidad'],
                $c['estado']
            ], ';');
        }
    }

    fclose($output);
    exit();
}

$medicos = Medico::listarTodos(null, null, true);
$especialidades = Especialidad::listarTodas(true);

$pageTitle = 'Reportes Administrativos';
$currentPage = 'reportes';
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
            <h3 class="fw-bold mb-1 text-dark">Generación de Reportes Administrativos</h3>
            <p class="text-muted mb-0 small">
                Consolidación estadística por rango de fechas, estados, médicos y especialidades con exportación a PDF y CSV.
            </p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-2" onclick="window.print()">
                <i class="bi bi-printer"></i>
                <span>Imprimir / PDF</span>
            </button>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-success d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-spreadsheet"></i>
                <span>Exportar CSV</span>
            </a>
        </div>
    </div>
</div>

<!-- Filtros del Reporte -->
<div class="card card-custom p-4 mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Tipo de Reporte Administrativo</label>
            <select name="tipo_reporte" class="form-select" onchange="this.form.submit()">
                <option value="estados" <?= $tipoReporte === 'estados' ? 'selected' : '' ?>>1. Resumen Estadístico por Estado</option>
                <option value="especialidades" <?= $tipoReporte === 'especialidades' ? 'selected' : '' ?>>2. Productividad por Especialidad</option>
                <option value="medicos" <?= $tipoReporte === 'medicos' ? 'selected' : '' ?>>3. Rendimiento por Médico</option>
                <option value="detallado" <?= $tipoReporte === 'detallado' ? 'selected' : '' ?>>4. Detalle de Citas del Período</option>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label small fw-semibold">Fecha Desde <span class="text-danger">*</span></label>
            <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fechaInicio) ?>" required>
        </div>

        <div class="col-md-2">
            <label class="form-label small fw-semibold">Fecha Hasta <span class="text-danger">*</span></label>
            <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fechaFin) ?>" required>
        </div>

        <?php if ($tipoReporte === 'detallado'): ?>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Médico</label>
                <select name="id_medico" class="form-select">
                    <option value="">-- Todos --</option>
                    <?php foreach ($medicos as $m): ?>
                        <option value="<?= $m['id_medico'] ?>" <?= $id_medico === (int)$m['id_medico'] ? 'selected' : '' ?>>
                            Dr(a). <?= htmlspecialchars($m['nombres'] . ' ' . $m['apellidos']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-funnel-fill me-1"></i>Generar
                </button>
            </div>
        <?php else: ?>
            <div class="col-md-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1 py-2 fw-semibold">
                    <i class="bi bi-bar-chart-fill me-1"></i>Generar Reporte
                </button>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Visualización de Resultados según el Tipo de Reporte -->
<?php if ($tipoReporte === 'estados'): ?>
    <?php
    $resEstados = ReporteController::reportePorEstado($fechaInicio, $fechaFin);
    ?>
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card card-custom h-100">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-pie-chart text-primary me-2"></i>Distribución de Citas por Estado (<?= date('d/m/Y', strtotime($fechaInicio)) ?> al <?= date('d/m/Y', strtotime($fechaFin)) ?>)
                </div>
                <div class="table-responsive">
                    <table class="table table-custom table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Estado de la Cita</th>
                                <th class="text-center">Total Citas</th>
                                <th class="text-end">Participación (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($resEstados['filas'])): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">No se registran citas en este período.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($resEstados['filas'] as $fila): ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-estado badge-estado-<?= strtolower($fila['estado']) ?>">
                                                <?= htmlspecialchars($fila['estado']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center fw-bold fs-6"><?= $fila['total_citas'] ?></td>
                                        <td class="text-end">
                                            <span class="fw-semibold text-dark"><?= $fila['porcentaje'] ?>%</span>
                                            <div class="progress mt-1" style="height: 6px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $fila['porcentaje'] ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td>TOTAL GENERAL</td>
                                <td class="text-center"><?= $resEstados['total_general'] ?></td>
                                <td class="text-end">100.0%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card card-custom h-100 p-4">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Resumen Ejecutivo de Indicadores</h6>
                <div class="p-3 bg-light rounded border mb-3">
                    <div class="text-muted small">Citas Efectivamente Atendidas</div>
                    <?php
                    $atendidasFila = array_filter($resEstados['filas'], fn($f) => $f['estado'] === 'ATENDIDA');
                    $atendida = !empty($atendidasFila) ? reset($atendidasFila) : ['total_citas' => 0, 'porcentaje' => 0];
                    ?>
                    <h3 class="fw-bold text-success mb-0"><?= $atendida['total_citas'] ?> <small class="fs-6 text-muted">(<?= $atendida['porcentaje'] ?>%)</small></h3>
                </div>
                <div class="p-3 bg-light rounded border mb-3">
                    <div class="text-muted small">Índice de Cancelación (Liberación de Turnos)</div>
                    <?php
                    $cancFila = array_filter($resEstados['filas'], fn($f) => $f['estado'] === 'CANCELADA');
                    $canc = !empty($cancFila) ? reset($cancFila) : ['total_citas' => 0, 'porcentaje' => 0];
                    ?>
                    <h3 class="fw-bold text-danger mb-0"><?= $canc['total_citas'] ?> <small class="fs-6 text-muted">(<?= $canc['porcentaje'] ?>%)</small></h3>
                </div>
                <div class="p-3 bg-light rounded border">
                    <div class="text-muted small">Índice de Ausentismo / Inasistencias</div>
                    <?php
                    $inasFila = array_filter($resEstados['filas'], fn($f) => $f['estado'] === 'INASISTENCIA');
                    $inas = !empty($inasFila) ? reset($inasFila) : ['total_citas' => 0, 'porcentaje' => 0];
                    ?>
                    <h3 class="fw-bold text-warning mb-0"><?= $inas['total_citas'] ?> <small class="fs-6 text-muted">(<?= $inas['porcentaje'] ?>%)</small></h3>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tipoReporte === 'especialidades'): ?>
    <?php
    $resEsp = ReporteController::reportePorEspecialidad($fechaInicio, $fechaFin);
    ?>
    <div class="card card-custom">
        <div class="card-header bg-white fw-bold">
            <i class="bi bi-tags text-primary me-2"></i>Demanda por Especialidad Médica (<?= date('d/m/Y', strtotime($fechaInicio)) ?> al <?= date('d/m/Y', strtotime($fechaFin)) ?>)
        </div>
        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle">
                <thead>
                    <tr>
                        <th>Especialidad</th>
                        <th class="text-center" style="width: 180px;">Total Citas Registradas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resEsp as $e): ?>
                        <tr>
                            <td class="fw-bold text-dark fs-6"><?= htmlspecialchars($e['especialidad']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-primary fs-6 px-3 py-2"><?= $e['total_citas'] ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tipoReporte === 'medicos'): ?>
    <?php
    $resMed = ReporteController::reportePorMedico($fechaInicio, $fechaFin);
    ?>
    <div class="card card-custom">
        <div class="card-header bg-white fw-bold">
            <i class="bi bi-person-badge text-primary me-2"></i>Rendimiento del Plantel Médico (<?= date('d/m/Y', strtotime($fechaInicio)) ?> al <?= date('d/m/Y', strtotime($fechaFin)) ?>)
        </div>
        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 100px;">Código</th>
                        <th>Médico Especialista</th>
                        <th class="text-center">Total Citas</th>
                        <th class="text-center">Atendidas</th>
                        <th class="text-center">Canceladas</th>
                        <th class="text-center">Inasistencias</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resMed as $m): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark font-monospace border"><?= htmlspecialchars($m['codigo_medico']) ?></span></td>
                            <td class="fw-bold text-dark fs-6"><?= htmlspecialchars($m['medico']) ?></td>
                            <td class="text-center fw-bold fs-6 text-primary"><?= $m['total_citas'] ?></td>
                            <td class="text-center"><span class="badge badge-estado-atendida"><?= $m['atendidas'] ?></span></td>
                            <td class="text-center"><span class="badge badge-estado-cancelada"><?= $m['canceladas'] ?></span></td>
                            <td class="text-center"><span class="badge badge-estado-inasistencia"><?= $m['inasistencias'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <?php
    $citasDetalle = CitaMedica::listar([
        'fecha_inicio' => $fechaInicio,
        'fecha_fin'    => $fechaFin,
        'id_medico'    => $id_medico,
        'id_especialidad' => $id_esp,
        'estado'       => $estado
    ]);
    ?>
    <div class="card card-custom">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-list-ul text-primary me-2"></i>Padrón Detallado de Citas</span>
            <span class="badge bg-light text-dark border">Total: <?= count($citasDetalle) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Fecha Cita</th>
                        <th>Horario</th>
                        <th>Paciente</th>
                        <th>Médico</th>
                        <th>Especialidad</th>
                        <th>Modalidad</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($citasDetalle)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No se encontraron citas en el rango seleccionado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($citasDetalle as $c): ?>
                            <tr>
                                <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($c['codigo_cita']) ?></td>
                                <td><?= date('d/m/Y', strtotime($c['fecha_cita'])) ?></td>
                                <td><?= substr($c['hora_inicio'], 0, 5) ?> - <?= substr($c['hora_fin'], 0, 5) ?></td>
                                <td><?= htmlspecialchars($c['paciente_nombre']) ?></td>
                                <td>Dr(a). <?= htmlspecialchars($c['medico_nombre']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($c['especialidad_nombre']) ?></span></td>
                                <td><?= htmlspecialchars($c['modalidad']) ?></td>
                                <td>
                                    <span class="badge badge-estado badge-estado-<?= strtolower($c['estado']) ?>">
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
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>