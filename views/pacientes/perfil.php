<?php

/**
 * Vista: Perfil Clínico del Paciente (CU-07)
 * Paquete 3: Gestión de Pacientes (Sección 3.4)
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Paciente.php';
require_once __DIR__ . '/../../app/models/PerfilMedico.php';
require_once __DIR__ . '/../../app/controllers/PerfilPacienteController.php';

AuthMiddleware::verificarAutenticacion();
$usuario = AuthMiddleware::usuario();

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new PerfilPacienteController();
    $controller->handleRequest();
}

// Determinar el paciente a visualizar
$id_paciente = 0;
if (AuthMiddleware::esPaciente()) {
    $id_paciente = (int)($usuario['id_paciente'] ?? 0);
} else {
    $id_paciente = !empty($_GET['id_paciente']) ? (int)$_GET['id_paciente'] : 0;
}

// Lista de pacientes para el selector si es Admin, Recepcionista o Médico
$listaPacientes = [];
if (!AuthMiddleware::esPaciente()) {
    $listaPacientes = Paciente::listarTodos(null, true);
    if ($id_paciente === 0 && !empty($listaPacientes)) {
        $id_paciente = (int)$listaPacientes[0]['id_paciente'];
    }
}

$perfil = null;
if ($id_paciente > 0) {
    $perfil = PerfilMedico::obtenerPorPaciente($id_paciente);
}

$pageTitle = 'Perfil Clínico del Paciente';
$currentPage = 'perfil';
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
            <h3 class="fw-bold mb-1 text-dark">Perfil Clínico del Paciente y Emergencias</h3>
            <p class="text-muted mb-0 small">
                Gestión de antecedentes clínicos, alergias, enfermedades crónicas, medicación y contacto de emergencia.
            </p>
        </div>
        <?php if ($id_paciente > 0): ?>
            <div>
                <a href="/clinicaVidaSalud/views/consultas/historial.php?id_paciente=<?= $id_paciente ?>" class="btn btn-outline-primary d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history"></i>
                    <span>Ver Historial de Citas</span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Selector de Paciente (Solo visible para Staff de la clínica) -->
<?php if (!AuthMiddleware::esPaciente() && !empty($listaPacientes)): ?>
    <div class="card card-custom p-3 mb-4">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-person-circle text-primary"></i></span>
                    <select name="id_paciente" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($listaPacientes as $pac): ?>
                            <option value="<?= $pac['id_paciente'] ?>" <?= $id_paciente === (int)$pac['id_paciente'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pac['codigo_paciente'] . ' - ' . $pac['nombres'] . ' ' . $pac['apellidos'] . ' (CI: ' . $pac['ci'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-person-check me-1"></i>Cargar Expediente
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if (!$perfil): ?>
    <div class="card card-custom p-5 text-center text-muted">
        <i class="bi bi-clipboard2-pulse fs-1 text-primary mb-2"></i>
        <h5>No se ha seleccionado ningún paciente</h5>
        <p class="small">Seleccione un paciente de la lista superior para consultar o actualizar su perfil clínico.</p>
    </div>
<?php else: ?>
    <?php
    $edad = date_diff(date_create($perfil['fecha_nacimiento']), date_create('today'))->y;
    ?>

    <!-- Ficha Demográfica del Paciente -->
    <div class="card card-custom p-4 mb-4 border-start border-4 border-info">
        <div class="row g-3 align-items-center">
            <div class="col-auto">
                <div class="rounded-circle bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center fw-bold" style="width: 58px; height: 58px; font-size: 1.5rem;">
                    <i class="bi bi-person-fill"></i>
                </div>
            </div>
            <div class="col">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="badge bg-light text-dark font-monospace border"><?= htmlspecialchars($perfil['codigo_paciente']) ?></span>
                    <h4 class="fw-bold text-dark mb-0"><?= htmlspecialchars($perfil['nombres'] . ' ' . $perfil['apellidos']) ?></h4>
                    <span class="badge <?= $perfil['sexo'] === 'M' ? 'bg-primary-subtle text-primary' : 'bg-danger-subtle text-danger' ?>">
                        <?= $perfil['sexo'] === 'M' ? 'Masculino' : 'Femenino' ?>
                    </span>
                </div>
                <div class="text-muted small d-flex flex-wrap gap-3">
                    <span><strong>CI:</strong> <?= htmlspecialchars($perfil['ci']) ?></span>
                    <span><strong>Edad:</strong> <?= $edad ?> años (<?= date('d/m/Y', strtotime($perfil['fecha_nacimiento'])) ?>)</span>
                    <span><strong>Teléfono:</strong> <?= htmlspecialchars($perfil['telefono']) ?></span>
                    <span><strong>Email:</strong> <?= htmlspecialchars($perfil['email'] ?? 'No registrado') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario de Antecedentes Médicos y Contacto de Emergencia -->
    <form method="POST" class="needs-validation" novalidate>
        <input type="hidden" name="id_paciente" value="<?= $perfil['id_paciente'] ?>">

        <div class="row g-4 mb-4">
            <!-- Bloque 1: Ficha Médica y Factores Clínicos -->
            <div class="col-lg-7">
                <div class="card card-custom h-100">
                    <div class="card-header bg-white d-flex align-items-center gap-2">
                        <i class="bi bi-heart-pulse-fill text-danger fs-5"></i>
                        <span class="fw-bold">Información y Antecedentes Médicos</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Grupo Sanguíneo y Rh</label>
                                <select name="tipo_sangre" class="form-select">
                                    <?php
                                    $tipos = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];
                                    foreach ($tipos as $t):
                                    ?>
                                        <option value="<?= $t ?>" <?= ($perfil['tipo_sangre'] ?? '') === $t ? 'selected' : '' ?>>
                                            Grupo <?= $t ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-exclamation-triangle text-warning me-1"></i>Alergias a Medicamentos o Alimentos
                                </label>
                                <textarea name="alergias" class="form-control" rows="2" placeholder="ej. Penicilina, Sulfas, Ninguna..."><?= htmlspecialchars($perfil['alergias'] ?? 'Ninguna') ?></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-activity text-info me-1"></i>Enfermedades Crónicas o Patologías de Base
                                </label>
                                <textarea name="enfermedades_cronicas" class="form-control" rows="2" placeholder="ej. Hipertensión arterial, Diabetes tipo 2..."><?= htmlspecialchars($perfil['enfermedades_cronicas'] ?? 'Ninguna') ?></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-capsule text-primary me-1"></i>Medicamentos Actuales / Tratamiento Habitual
                                </label>
                                <textarea name="medicamentos" class="form-control" rows="2" placeholder="ej. Losartán 50mg cada 12h, Ninguno..."><?= htmlspecialchars($perfil['medicamentos'] ?? 'Ninguno') ?></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-journal-medical text-secondary me-1"></i>Antecedentes Médicos o Quirúrgicos Relevantes
                                </label>
                                <textarea name="antecedentes" class="form-control" rows="3" placeholder="ej. Apendicectomía en 2019, antecedente familiar diabético..."><?= htmlspecialchars($perfil['antecedentes'] ?? 'Sin antecedentes relevantes') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bloque 2: Contacto de Emergencia -->
            <div class="col-lg-5">
                <div class="card card-custom h-100">
                    <div class="card-header bg-white d-flex align-items-center gap-2">
                        <i class="bi bi-telephone-fill text-success fs-5"></i>
                        <span class="fw-bold">Contacto de Emergencia</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-light border small text-muted mb-3">
                            <i class="bi bi-info-circle me-1 text-primary"></i>
                            Persona a la que la clínica contactará en caso de urgencias o contingencias médicas.
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nombre Completo del Contacto</label>
                            <input type="text" name="emergencia_nombre" class="form-control"
                                value="<?= htmlspecialchars($perfil['emergencia_nombre'] ?? '') ?>"
                                placeholder="ej. Elena Roca">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Parentesco o Relación</label>
                            <input type="text" name="emergencia_parentesco" class="form-control"
                                value="<?= htmlspecialchars($perfil['emergencia_parentesco'] ?? '') ?>"
                                placeholder="ej. Madre, Padre, Cónyuge, Hermano/a">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Teléfono de Emergencia</label>
                            <input type="text" name="emergencia_telefono" class="form-control"
                                value="<?= htmlspecialchars($perfil['emergencia_telefono'] ?? '') ?>"
                                placeholder="ej. 71099882">
                        </div>

                        <div class="pt-3 border-top">
                            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                                <i class="bi bi-check-circle-fill me-1"></i>Guardar Expediente Clínico
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>