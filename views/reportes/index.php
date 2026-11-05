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

// Exportación a PDF con Dompdf (CU-14)
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once __DIR__ . '/../../app/helpers/PdfHelper.php';
    $descargar = isset($_GET['download']) && $_GET['download'] === '1';
    $fechaEmision = date('d/m/Y H:i');
    $estilosBase = PdfHelper::getEstilosBase();
    $usuarioEmisor = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Administración');
    $periodoTexto = date('d/m/Y', strtotime($fechaInicio)) . ' al ' . date('d/m/Y', strtotime($fechaFin));
    $orientacion = ($tipoReporte === 'detallado') ? 'landscape' : 'portrait';

    $tituloReporte = 'Reporte de Citas Médicas';
    $tablaHtml = '';

    if ($tipoReporte === 'estados') {
        $tituloReporte = 'Reporte Estadístico de Citas por Estado';
        $data = ReporteController::reportePorEstado($fechaInicio, $fechaFin);
        $tablaHtml = '<table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Estado de Cita</th>
                    <th style="width: 25%; text-align: center;">Total Citas</th>
                    <th style="width: 25%; text-align: right;">Porcentaje</th>
                </tr>
            </thead>
            <tbody>';
        foreach ($data['filas'] as $r) {
            $tablaHtml .= '<tr>
                <td><strong>' . htmlspecialchars($r['estado']) . '</strong></td>
                <td style="text-align: center;">' . (int)$r['total_citas'] . '</td>
                <td style="text-align: right;">' . $r['porcentaje'] . '%</td>
            </tr>';
        }
        $tablaHtml .= '<tr style="font-weight: bold; background-color: #f1f5f9;">
            <td>TOTAL GENERAL</td>
            <td style="text-align: center;">' . (int)$data['total_general'] . '</td>
            <td style="text-align: right;">100%</td>
        </tr></tbody></table>';

    } elseif ($tipoReporte === 'especialidades') {
        $tituloReporte = 'Reporte de Productividad por Especialidad Médica';
        $rows = ReporteController::reportePorEspecialidad($fechaInicio, $fechaFin);
        $totalGeneral = array_sum(array_column($rows, 'total_citas'));
        $tablaHtml = '<table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Especialidad Médica</th>
                    <th style="width: 25%; text-align: center;">Total Citas</th>
                    <th style="width: 25%; text-align: right;">Participación</th>
                </tr>
            </thead>
            <tbody>';
        foreach ($rows as $r) {
            $pct = $totalGeneral > 0 ? round(($r['total_citas'] / $totalGeneral) * 100, 1) : 0;
            $tablaHtml .= '<tr>
                <td><strong>' . htmlspecialchars($r['especialidad']) . '</strong></td>
                <td style="text-align: center;">' . (int)$r['total_citas'] . '</td>
                <td style="text-align: right;">' . $pct . '%</td>
            </tr>';
        }
        $tablaHtml .= '<tr style="font-weight: bold; background-color: #f1f5f9;">
            <td>TOTAL GENERAL DE CITAS</td>
            <td style="text-align: center;">' . (int)$totalGeneral . '</td>
            <td style="text-align: right;">100%</td>
        </tr></tbody></table>';

    } elseif ($tipoReporte === 'medicos') {
        $tituloReporte = 'Reporte de Atenciones por Médico Especialista';
        $rows = ReporteController::reportePorMedico($fechaInicio, $fechaFin);
        $tablaHtml = '<table class="data-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Código</th>
                    <th style="width: 35%;">Médico Especialista</th>
                    <th style="width: 13%; text-align: center;">Total</th>
                    <th style="width: 12%; text-align: center;">Atendidas</th>
                    <th style="width: 12%; text-align: center;">Canceladas</th>
                    <th style="width: 13%; text-align: center;">Inasistencias</th>
                </tr>
            </thead>
            <tbody>';
        foreach ($rows as $r) {
            $tablaHtml .= '<tr>
                <td>' . htmlspecialchars($r['codigo_medico']) . '</td>
                <td><strong>Dr(a). ' . htmlspecialchars($r['medico']) . '</strong></td>
                <td style="text-align: center;">' . (int)$r['total_citas'] . '</td>
                <td style="text-align: center; color: #16a34a; font-weight: bold;">' . (int)$r['atendidas'] . '</td>
                <td style="text-align: center; color: #dc2626;">' . (int)$r['canceladas'] . '</td>
                <td style="text-align: center; color: #d97706;">' . (int)$r['inasistencias'] . '</td>
            </tr>';
        }
        $tablaHtml .= '</tbody></table>';

    } else {
        $tituloReporte = 'Reporte Detallado de Citas Médicas';
        $citas = CitaMedica::listar([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
            'id_medico'    => $id_medico,
            'id_especialidad' => $id_esp,
            'estado'       => $estado
        ]);
        $tablaHtml = '<table class="data-table">
            <thead>
                <tr>
                    <th style="width: 10%;">Código</th>
                    <th style="width: 11%;">Fecha / Hora</th>
                    <th style="width: 18%;">Paciente</th>
                    <th style="width: 10%;">C.I.</th>
                    <th style="width: 18%;">Médico</th>
                    <th style="width: 15%;">Especialidad</th>
                    <th style="width: 8%;">Mod.</th>
                    <th style="width: 10%; text-align: center;">Estado</th>
                </tr>
            </thead>
            <tbody>';
        foreach ($citas as $c) {
            $badgeClass = 'badge-secondary';
            if ($c['estado'] === 'ATENDIDA') $badgeClass = 'badge-success';
            elseif ($c['estado'] === 'PROGRAMADA' || $c['estado'] === 'CONFIRMADA') $badgeClass = 'badge-primary';
            elseif ($c['estado'] === 'CANCELADA') $badgeClass = 'badge-danger';
            elseif ($c['estado'] === 'INASISTENCIA') $badgeClass = 'badge-warning';

            $tablaHtml .= '<tr>
                <td>' . htmlspecialchars($c['codigo_cita']) . '</td>
                <td>' . date('d/m/Y', strtotime($c['fecha_cita'])) . ' ' . substr($c['hora_inicio'], 0, 5) . '</td>
                <td>' . htmlspecialchars($c['paciente_nombre']) . '</td>
                <td>' . htmlspecialchars($c['paciente_ci']) . '</td>
                <td>Dr(a). ' . htmlspecialchars($c['medico_nombre']) . '</td>
                <td>' . htmlspecialchars($c['especialidad_nombre']) . '</td>
                <td>' . htmlspecialchars($c['modalidad']) . '</td>
                <td style="text-align: center;"><span class="badge ' . $badgeClass . '">' . htmlspecialchars($c['estado']) . '</span></td>
            </tr>';
        }
        $tablaHtml .= '</tbody></table>';
    }

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{$tituloReporte}</title>
    {$estilosBase}
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <div class="clinic-brand">Clínica Vida Salud</div>
                <div class="clinic-tagline">Centro Médico Integral &middot; Especialidades Ambulatorias</div>
                <div class="clinic-tagline">Dirección: Av. Principal #123, Zona Central &middot; Teléfono: (+591) 4-4567890</div>
            </td>
            <td class="doc-title-container" style="width: 40%;">
                <div class="doc-title">{$tituloReporte}</div>
                <div class="doc-meta"><strong>PERÍODO:</strong> {$periodoTexto}</div>
                <div class="doc-meta"><strong>EMITIDO:</strong> {$fechaEmision}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Consolidado y Resultados del Reporte</div>
    {$tablaHtml}

    <table class="signature-area">
        <tr>
            <td style="width: 50%; vertical-align: bottom;">
                <div style="font-size: 8.5px; color: #64748b;">
                    Generado por: <strong>{$usuarioEmisor}</strong><br>
                    Clínica Vida Salud &middot; Módulo de Consultas y Reportes
                </div>
            </td>
            <td style="width: 50%; text-align: right; vertical-align: bottom;">
                <div style="display: inline-block; width: 220px; text-align: center; border-top: 1px solid #334155; padding-top: 5px;">
                    <span style="font-size: 9px; font-weight: bold; color: #1e293b;">DIRECCIÓN MÉDICA / ADMINISTRACIÓN</span><br>
                    <span style="font-size: 8px; color: #64748b;">Firma y Sello de Validación</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer-legal">
        Informe generado automáticamente por el Sistema de Gestión de Citas Médicas de Clínica Vida Salud.
    </div>
</body>
</html>
HTML;

    $nombreArchivo = 'reporte_' . $tipoReporte . '_' . date('Ymd_His') . '.pdf';
    PdfHelper::streamHtml($html, $nombreArchivo, $descargar, 'A4', $orientacion);
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
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-2" onclick="imprimirReporteActual()">
                <i class="bi bi-printer"></i>
                <span>Imprimir Reporte</span>
            </button>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'pdf', 'download' => '1'])) ?>" class="btn btn-outline-danger d-flex align-items-center gap-2" target="_blank">
                <i class="bi bi-file-earmark-pdf"></i>
                <span>Exportar PDF</span>
            </a>
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

<script>
function imprimirReporteActual() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'pdf');
    params.delete('download');
    const url = window.location.pathname + '?' + params.toString();
    imprimirDocumentoPdf(url, 'Reporte Administrativo - Vida Salud');
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>