<?php
/**
 * Endpoint HTML/AJAX: Trazabilidad de una Cita Médica (RF10 / RF12)
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/HistorialEstadoCita.php';

AuthMiddleware::iniciarSesion();
if (!isset($_SESSION['usuario'])) {
    echo '<div class="alert alert-danger">No autenticado.</div>';
    exit();
}

$id_cita = (int)($_GET['id_cita'] ?? 0);
$historial = HistorialEstadoCita::listarPorCita($id_cita);

if (empty($historial)) {
    echo '<div class="alert alert-info small mb-0">No se registran cambios de estado para esta cita.</div>';
    exit();
}
?>
<div class="timeline">
    <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
            <tr class="small">
                <th>Fecha y Hora</th>
                <th>Estado Previo</th>
                <th>Nuevo Estado</th>
                <th>Observación / Motivo</th>
                <th>Responsable</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($historial as $h): ?>
                <tr>
                    <td class="small"><i class="bi bi-clock me-1 text-muted"></i><?= date('d/m/Y H:i:s', strtotime($h['fecha_cambio'])) ?></td>
                    <td>
                        <span class="badge bg-secondary-subtle text-secondary small">
                            <?= htmlspecialchars($h['estado_anterior'] ?? 'INICIAL') ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-primary-subtle text-primary fw-bold small">
                            <?= htmlspecialchars($h['estado_nuevo']) ?>
                        </span>
                    </td>
                    <td class="small"><?= htmlspecialchars($h['observacion'] ?? '-') ?></td>
                    <td class="small">
                        <strong><?= htmlspecialchars($h['usuario_nombre']) ?></strong>
                        <?php if (!empty($h['nombre_rol'])): ?>
                            <small class="text-muted d-block"><?= htmlspecialchars($h['nombre_rol']) ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
