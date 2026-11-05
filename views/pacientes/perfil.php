<?php

/**
 * Vista: Perfil Integral del Paciente (CU-07)
 * Sección 3.4 del Documento de Requisitos:
 * 1. Datos Personales (nombres, fecha de nacimiento, sexo - CI inmutable)
 * 2. Información de Contacto (teléfono, correo electrónico, dirección)
 * 3. Expediente Médico y Contacto de Emergencia
 * 4. Configuración de Cuenta (contraseña, notificaciones y privacidad)
 */
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Paciente.php';
require_once __DIR__ . '/../../app/models/PerfilMedico.php';
require_once __DIR__ . '/../../app/models/Usuario.php';
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

// Determinar la pestaña activa
$activeTab = $_GET['tab'] ?? 'datos';
if (!in_array($activeTab, ['datos', 'expediente', 'cuenta'], true)) {
    $activeTab = 'datos';
}

$pageTitle = 'Perfil y Expediente del Paciente';
$currentPage = 'perfil';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>

<!-- Encabezado de la Página -->
<div class="page-header-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div>
                <span class="cu-package-badge">GESTIÓN DE PACIENTES</span>
            </div>
            <h3 class="fw-bold mb-1 text-dark">Perfil Clínico y Configuración del Paciente</h3>
            <p class="text-muted mb-0 small">
                Gestión de datos personales, información de contacto, expediente médico y configuración de cuenta.
            </p>
        </div>
        <?php if ($id_paciente > 0): ?>
            <div class="d-flex flex-wrap gap-2">
                <a href="/clinicaVidaSalud/views/consultas/historial.php?id_paciente=<?= $id_paciente ?>" class="btn btn-outline-primary d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history"></i>
                    <span>Historial de Citas</span>
                </a>
                <a href="/clinicaVidaSalud/views/pacientes/ficha_medica_pdf.php?id_paciente=<?= $id_paciente ?>&download=1" class="btn btn-outline-danger d-flex align-items-center gap-2" target="_blank" title="Descargar Ficha Médica en formato PDF">
                    <i class="bi bi-file-earmark-pdf-fill"></i>
                    <span>Descargar PDF</span>
                </a>
                <button type="button" class="btn btn-danger d-flex align-items-center gap-2 shadow-sm" onclick="imprimirFichaMedica(<?= $id_paciente ?>)" title="Imprimir Ficha Médica de forma directa y limpia">
                    <i class="bi bi-printer-fill"></i>
                    <span>Imprimir Ficha</span>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Selector de Paciente (Staff: Admin, Recepcionista o Médico) -->
<?php if (!AuthMiddleware::esPaciente() && !empty($listaPacientes)): ?>
    <div class="card card-custom p-3 mb-4">
        <form method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
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
                    <i class="bi bi-person-check me-1"></i>Cargar Paciente
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if (!$perfil): ?>
    <div class="card card-custom p-5 text-center text-muted">
        <i class="bi bi-clipboard2-pulse fs-1 text-primary mb-2"></i>
        <h5>No se ha seleccionado ningún paciente</h5>
        <p class="small">Seleccione un paciente para consultar o actualizar su información.</p>
    </div>
<?php else: ?>
    <?php
    $edad = date_diff(date_create($perfil['fecha_nacimiento']), date_create('today'))->y;
    ?>

    <!-- Resumen Demográfico en Cabecera -->
    <div class="card card-custom p-4 mb-4 border-start border-4 border-primary">
        <div class="row g-3 align-items-center">
            <div class="col-auto">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 58px; height: 58px; font-size: 1.5rem;">
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
                    <span class="badge bg-info-subtle text-info border border-info-subtle">
                        <?= $edad ?> años (<?= date('d/m/Y', strtotime($perfil['fecha_nacimiento'])) ?>)
                    </span>
                </div>
                <div class="text-muted small d-flex flex-wrap gap-3">
                    <span><strong>CI:</strong> <code class="text-primary fw-bold"><?= htmlspecialchars($perfil['ci']) ?></code></span>
                    <span><strong>Teléfono:</strong> <?= htmlspecialchars($perfil['telefono']) ?></span>
                    <span><strong>Email:</strong> <?= htmlspecialchars($perfil['email'] ?? 'No registrado') ?></span>
                    <span><strong>Dirección:</strong> <?= htmlspecialchars($perfil['direccion'] ?? 'Sin dirección especificada') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Navegación por Pestañas (Nav Tabs) -->
    <ul class="nav nav-pills custom-nav-pills mb-4 gap-2" id="perfilTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'datos' ? 'active' : '' ?>" id="tab-datos-btn" data-bs-toggle="tab" data-bs-target="#tab-datos" type="button" role="tab" aria-selected="<?= $activeTab === 'datos' ? 'true' : 'false' ?>">
                <i class="bi bi-person-lines-fill me-2"></i>1. Datos Personales y Contacto
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'expediente' ? 'active' : '' ?>" id="tab-expediente-btn" data-bs-toggle="tab" data-bs-target="#tab-expediente" type="button" role="tab" aria-selected="<?= $activeTab === 'expediente' ? 'true' : 'false' ?>">
                <i class="bi bi-heart-pulse-fill me-2"></i>2. Expediente Médico y Emergencia
            </button>
        </li>
        <?php if (!AuthMiddleware::esMedico()): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'cuenta' ? 'active' : '' ?>" id="tab-cuenta-btn" data-bs-toggle="tab" data-bs-target="#tab-cuenta" type="button" role="tab" aria-selected="<?= $activeTab === 'cuenta' ? 'true' : 'false' ?>">
                    <i class="bi bi-shield-lock-fill me-2"></i>3. Configuración de Cuenta y Privacidad
                </button>
            </li>
        <?php endif; ?>
    </ul>

    <div class="tab-content" id="perfilTabsContent">
        <!-- ============================================================== -->
        <!-- PESTAÑA 1: DATOS PERSONALES Y CONTACTO                          -->
        <!-- ============================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'datos' ? 'show active' : '' ?>" id="tab-datos" role="tabpanel" aria-labelledby="tab-datos-btn">
            <div class="card card-custom p-4 shadow-sm border-0">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-person-badge text-primary"></i>
                        <span><?= AuthMiddleware::esMedico() ? 'Filiación del Paciente (Sólo Lectura)' : 'Actualización de Datos Personales y de Contacto' ?></span>
                    </h5>
                    <span class="badge bg-light text-muted border">Sección 3.4</span>
                </div>

                <?php if (AuthMiddleware::esMedico()): ?>
                    <div class="alert alert-info border small text-dark mb-4 d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle-fill text-primary fs-5"></i>
                        <div>
                            <strong>Modo de Consulta Clínica:</strong> Los médicos tienen acceso de sólo lectura a los datos personales y de filiación del paciente. Para correcciones de nombres, fecha de nacimiento o contacto, derivar al área de Recepción / Admisión.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light border small text-muted mb-4 d-flex align-items-center gap-2">
                        <i class="bi bi-shield-check text-success fs-5"></i>
                        <div>
                            <strong>Regla de Integridad Clínica:</strong> El Cédula de Identidad (CI) es el identificador único oficial inmutable y no puede modificarse. Puede editar sus nombres, fecha de nacimiento, sexo, teléfono, correo y dirección.
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate id="formDatosPersonales">
                    <input type="hidden" name="action" value="update_datos_personales">
                    <input type="hidden" name="id_paciente" value="<?= (int)$perfil['id_paciente'] ?>">

                    <div class="row g-3 mb-4">
                        <!-- Identificadores -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Documento de Identidad (CI) <span class="badge bg-secondary-subtle text-secondary ms-1"><i class="bi bi-lock-fill me-1"></i>Inmutable</span></label>
                            <input type="text" class="form-control bg-light fw-bold font-monospace" value="<?= htmlspecialchars($perfil['ci']) ?>" readonly disabled>
                            <small class="text-muted" style="font-size: 0.75rem;">Protegido contra cambios conforme a la norma médica RN01.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Código Institucional del Paciente</label>
                            <input type="text" class="form-control bg-light font-monospace" value="<?= htmlspecialchars($perfil['codigo_paciente']) ?>" readonly disabled>
                        </div>

                        <!-- Datos Personales -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nombres <?= AuthMiddleware::esMedico() ? '' : '<span class="text-danger">*</span>' ?></label>
                            <input type="text" name="nombres" class="form-control <?= AuthMiddleware::esMedico() ? 'bg-light' : '' ?>" value="<?= htmlspecialchars($perfil['nombres']) ?>" placeholder="ej. María" <?= AuthMiddleware::esMedico() ? 'readonly disabled' : 'required' ?>>
                            <div class="invalid-feedback">Ingrese los nombres del paciente.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Apellidos <?= AuthMiddleware::esMedico() ? '' : '<span class="text-danger">*</span>' ?></label>
                            <input type="text" name="apellidos" class="form-control <?= AuthMiddleware::esMedico() ? 'bg-light' : '' ?>" value="<?= htmlspecialchars($perfil['apellidos']) ?>" placeholder="ej. López Ríos" <?= AuthMiddleware::esMedico() ? 'readonly disabled' : 'required' ?>>
                            <div class="invalid-feedback">Ingrese los apellidos del paciente.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Fecha de Nacimiento <?= AuthMiddleware::esMedico() ? '' : '<span class="text-danger">*</span>' ?></label>
                            <input type="date" name="fecha_nacimiento" class="form-control <?= AuthMiddleware::esMedico() ? 'bg-light' : '' ?>" value="<?= htmlspecialchars($perfil['fecha_nacimiento']) ?>" max="<?= date('Y-m-d') ?>" <?= AuthMiddleware::esMedico() ? 'readonly disabled' : 'required' ?>>
                            <div class="invalid-feedback">Seleccione una fecha de nacimiento válida (no futura).</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Sexo Demográfico <?= AuthMiddleware::esMedico() ? '' : '<span class="text-danger">*</span>' ?></label>
                            <select name="sexo" class="form-select <?= AuthMiddleware::esMedico() ? 'bg-light' : '' ?>" <?= AuthMiddleware::esMedico() ? 'disabled' : 'required' ?>>
                                <option value="">-- Seleccionar --</option>
                                <option value="F" <?= $perfil['sexo'] === 'F' ? 'selected' : '' ?>>Femenino (F)</option>
                                <option value="M" <?= $perfil['sexo'] === 'M' ? 'selected' : '' ?>>Masculino (M)</option>
                            </select>
                            <div class="invalid-feedback">Seleccione el sexo demográfico.</div>
                        </div>

                        <!-- Información de Contacto -->
                        <div class="col-12 mt-4 pt-3 border-top">
                            <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-telephone-fill text-primary"></i>
                                <span>Información de Contacto y Residencia</span>
                            </h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Teléfono de Contacto <?= AuthMiddleware::esMedico() ? '' : '<span class="text-danger">*</span>' ?></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-telephone text-muted"></i></span>
                                <input type="text" name="telefono" class="form-control <?= AuthMiddleware::esMedico() ? 'bg-light' : '' ?>" value="<?= htmlspecialchars($perfil['telefono']) ?>" placeholder="ej. 70011999" <?= AuthMiddleware::esMedico() ? 'readonly disabled' : 'required' ?>>
                            </div>
                            <div class="invalid-feedback">El número de teléfono es obligatorio.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Correo Electrónico de Contacto</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-envelope text-muted"></i></span>
                                <input type="email" name="email" class="form-control <?= AuthMiddleware::esMedico() ? 'bg-light' : '' ?>" value="<?= htmlspecialchars($perfil['email'] ?? '') ?>" placeholder="ej. maria.lopez@gmail.com" <?= AuthMiddleware::esMedico() ? 'readonly disabled' : '' ?>>
                            </div>
                            <div class="invalid-feedback">Ingrese un correo electrónico con formato válido.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Dirección Domiciliaria</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-geo-alt text-muted"></i></span>
                                <textarea name="direccion" class="form-control <?= AuthMiddleware::esMedico() ? 'bg-light' : '' ?>" rows="2" placeholder="ej. Av. Beni #250, Condominio Norte, Piso 3" <?= AuthMiddleware::esMedico() ? 'readonly disabled' : '' ?>><?= htmlspecialchars($perfil['direccion'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <?php if (!AuthMiddleware::esMedico()): ?>
                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                            <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm">
                                <i class="bi bi-save2-fill me-1"></i>Guardar Datos Personales y Contacto
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- PESTAÑA 2: EXPEDIENTE MÉDICO Y CONTACTO DE EMERGENCIA           -->
        <!-- ============================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'expediente' ? 'show active' : '' ?>" id="tab-expediente" role="tabpanel" aria-labelledby="tab-expediente-btn">
            <form method="POST" class="needs-validation" novalidate id="formExpedienteClinico">
                <input type="hidden" name="action" value="update_expediente">
                <input type="hidden" name="id_paciente" value="<?= (int)$perfil['id_paciente'] ?>">

                <div class="row g-4 mb-4">
                    <!-- Ficha Médica y Factores Clínicos -->
                    <div class="col-lg-7">
                        <div class="card card-custom h-100 shadow-sm border-0">
                            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-heart-pulse-fill text-danger fs-5"></i>
                                    <span class="fw-bold">Información y Antecedentes Médicos</span>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 shadow-xs" onclick="imprimirFichaMedica(<?= (int)$perfil['id_paciente'] ?>)" title="Imprimir Ficha Médica">
                                    <i class="bi bi-printer-fill"></i>
                                    <span>Imprimir Ficha</span>
                                </button>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Grupo Sanguíneo y Factor Rh</label>
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
                                        <label class="form-label fw-semibold small">
                                            <i class="bi bi-exclamation-triangle text-warning me-1"></i>Alergias a Medicamentos o Alimentos
                                        </label>
                                        <textarea name="alergias" class="form-control" rows="2" placeholder="ej. Penicilina, Sulfas, Ninguna..."><?= htmlspecialchars($perfil['alergias'] ?? 'Ninguna') ?></textarea>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold small">
                                            <i class="bi bi-activity text-info me-1"></i>Enfermedades Crónicas o Patologías de Base
                                        </label>
                                        <textarea name="enfermedades_cronicas" class="form-control" rows="2" placeholder="ej. Hipertensión arterial, Diabetes tipo 2, Asma..."><?= htmlspecialchars($perfil['enfermedades_cronicas'] ?? 'Ninguna') ?></textarea>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold small">
                                            <i class="bi bi-capsule text-primary me-1"></i>Medicamentos Actuales / Tratamiento Habitual
                                        </label>
                                        <textarea name="medicamentos" class="form-control" rows="2" placeholder="ej. Losartán 50mg cada 12h, Ninguno..."><?= htmlspecialchars($perfil['medicamentos'] ?? 'Ninguno') ?></textarea>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold small">
                                            <i class="bi bi-journal-medical text-secondary me-1"></i>Antecedentes Médicos o Quirúrgicos Relevantes
                                        </label>
                                        <textarea name="antecedentes" class="form-control" rows="3" placeholder="ej. Apendicectomía en 2019, antecedentes de cesárea..."><?= htmlspecialchars($perfil['antecedentes'] ?? 'Sin antecedentes relevantes') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contacto de Emergencia -->
                    <div class="col-lg-5">
                        <div class="card card-custom h-100 shadow-sm border-0">
                            <div class="card-header bg-white d-flex align-items-center gap-2 py-3">
                                <i class="bi bi-telephone-fill text-success fs-5"></i>
                                <span class="fw-bold">Contacto de Emergencia</span>
                            </div>
                            <div class="card-body p-4">
                                <div class="alert alert-light border small text-muted mb-3">
                                    <i class="bi bi-info-circle me-1 text-primary"></i>
                                    Persona a la que la clínica contactará en caso de urgencias médicas inmediatas.
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Nombre Completo del Contacto</label>
                                    <input type="text" name="emergencia_nombre" class="form-control"
                                        value="<?= htmlspecialchars($perfil['emergencia_nombre'] ?? '') ?>"
                                        placeholder="ej. Pedro López">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Parentesco o Relación</label>
                                    <input type="text" name="emergencia_parentesco" class="form-control"
                                        value="<?= htmlspecialchars($perfil['emergencia_parentesco'] ?? '') ?>"
                                        placeholder="ej. Padre, Madre, Cónyuge, Hermano/a">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold small">Teléfono de Emergencia</label>
                                    <input type="text" name="emergencia_telefono" class="form-control"
                                        value="<?= htmlspecialchars($perfil['emergencia_telefono'] ?? '') ?>"
                                        placeholder="ej. 70099881">
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
        </div>

        <!-- ============================================================== -->
        <!-- PESTAÑA 3: CONFIGURACIÓN DE CUENTA Y PRIVACIDAD                 -->
        <!-- ============================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'cuenta' ? 'show active' : '' ?>" id="tab-cuenta" role="tabpanel" aria-labelledby="tab-cuenta-btn">
            <div class="row g-4">
                <!-- Subsección A: Cambio de Contraseña -->
                <div class="col-lg-6">
                    <div class="card card-custom h-100 shadow-sm border-0">
                        <div class="card-header bg-white d-flex align-items-center gap-2 py-3">
                            <i class="bi bi-shield-lock-fill text-primary fs-5"></i>
                            <span class="fw-bold">Seguridad: Cambio de Contraseña</span>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" class="needs-validation" novalidate id="formCambiarPassword">
                                <input type="hidden" name="action" value="cambiar_password">
                                <input type="hidden" name="id_paciente" value="<?= (int)$perfil['id_paciente'] ?>">

                                <div class="alert alert-light border small text-muted mb-4">
                                    <i class="bi bi-info-circle me-1 text-primary"></i>
                                    Para proteger su cuenta médica, verifique su contraseña actual antes de registrar la nueva clave. Se utilizará encriptación <strong>BCRYPT</strong>.
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Contraseña Actual <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-key text-muted"></i></span>
                                        <input type="password" name="old_password" class="form-control" placeholder="Ingrese su contraseña actual" required>
                                    </div>
                                    <div class="invalid-feedback">Ingrese su contraseña actual.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Nueva Contraseña <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-lock text-muted"></i></span>
                                        <input type="password" name="new_password" class="form-control" placeholder="Mínimo 6 caracteres" minlength="6" required>
                                    </div>
                                    <div class="invalid-feedback">La nueva contraseña debe tener al menos 6 caracteres.</div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold small">Confirmar Nueva Contraseña <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-check2-circle text-muted"></i></span>
                                        <input type="password" name="confirm_password" class="form-control" placeholder="Repita la nueva contraseña" minlength="6" required>
                                    </div>
                                    <div class="invalid-feedback">Confirme la nueva contraseña.</div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                                    <i class="bi bi-arrow-repeat me-1"></i>Actualizar Mi Contraseña
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Subsección B: Notificaciones y Privacidad -->
                <div class="col-lg-6">
                    <div class="card card-custom h-100 shadow-sm border-0">
                        <div class="card-header bg-white d-flex align-items-center gap-2 py-3">
                            <i class="bi bi-sliders text-success fs-5"></i>
                            <span class="fw-bold">Notificaciones y Privacidad</span>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" id="formPreferenciasCuenta">
                                <input type="hidden" name="action" value="update_preferencias">
                                <input type="hidden" name="id_paciente" value="<?= (int)$perfil['id_paciente'] ?>">

                                <!-- Notificaciones -->
                                <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                    <i class="bi bi-bell-fill text-warning"></i>
                                    <span>Notificaciones Internas del Sistema</span>
                                </h6>

                                <div class="form-check form-switch p-3 bg-light rounded border mb-4">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" id="notifSwitchPerfil"
                                        name="notificaciones_activas" <?= ($perfil['notificaciones_activas'] ?? true) ? 'checked' : '' ?> style="cursor: pointer; transform: scale(1.2);">
                                    <label class="form-check-label fw-semibold text-dark" for="notifSwitchPerfil" style="cursor: pointer;">
                                        Activar Alertas de Citas Médicas
                                    </label>
                                    <div class="text-muted small mt-1 ms-1">
                                        Muestra avisos inmediatos en la campana al agendarse, confirmarse, reprogramarse o cancelarse citas vinculadas a su perfil.
                                    </div>
                                </div>

                                <!-- Privacidad -->
                                <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                    <i class="bi bi-eye-slash-fill text-info"></i>
                                    <span>Opciones de Privacidad</span>
                                </h6>

                                <div class="form-check form-switch p-3 bg-light rounded border mb-3">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" id="privContactoSwitchPerfil"
                                        name="privacidad_contacto_emergencia" <?= ($perfil['privacidad_contacto_emergencia'] ?? true) ? 'checked' : '' ?> style="cursor: pointer; transform: scale(1.2);">
                                    <label class="form-check-label fw-semibold text-dark" for="privContactoSwitchPerfil" style="cursor: pointer;">
                                        Compartir Contacto de Emergencia con Médicos
                                    </label>
                                    <div class="text-muted small mt-1 ms-1">
                                        Permite que el médico tratante visualice los datos de su persona de contacto durante la consulta clínica.
                                    </div>
                                </div>

                                <div class="form-check form-switch p-3 bg-light rounded border mb-4">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" id="privRecordatoriosSwitchPerfil"
                                        name="privacidad_recordatorios" <?= ($perfil['privacidad_recordatorios'] ?? true) ? 'checked' : '' ?> style="cursor: pointer; transform: scale(1.2);">
                                    <label class="form-check-label fw-semibold text-dark" for="privRecordatoriosSwitchPerfil" style="cursor: pointer;">
                                        Mostrar Recordatorio en Panel Principal
                                    </label>
                                    <div class="text-muted small mt-1 ms-1">
                                        Muestra tarjeta de aviso de su próxima consulta en el Dashboard inicial.
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success w-100 py-2 fw-semibold shadow-sm">
                                    <i class="bi bi-check2-circle me-1"></i>Guardar Preferencias de Cuenta
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>