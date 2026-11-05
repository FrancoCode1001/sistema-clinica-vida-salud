<?php

/**
 * Vista: Configuración de Cuenta de Usuario
 * Permite cambiar contraseña, gestionar notificaciones y preferencias de privacidad
 * Accesible para todos los roles (Administrador, Recepcionista, Médico, Paciente)
 */

require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/models/Usuario.php';

AuthMiddleware::verificarAutenticacion();
$usuario = AuthMiddleware::usuario();
$id_usuario = (int)$usuario['id_usuario'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'cambiar_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($oldPass) || empty($newPass) || empty($confirm)) {
            $_SESSION['flash_error'] = 'Debe completar todos los campos requeridos para cambiar su contraseña.';
        } elseif ($newPass !== $confirm) {
            $_SESSION['flash_error'] = 'La nueva contraseña y su confirmación no coinciden.';
        } else {
            $res = Usuario::cambiarPassword($id_usuario, $oldPass, $newPass, $id_usuario);
            if ($res['status']) {
                $_SESSION['flash_success'] = $res['message'];
            } else {
                $_SESSION['flash_error'] = $res['message'];
            }
        }
        header('Location: /clinicaVidaSalud/views/cuenta/index.php');
        exit();
    }

    if ($action === 'update_preferencias') {
        $notif = isset($_POST['notificaciones_activas']);
        $em    = isset($_POST['privacidad_contacto_emergencia']);
        $rec   = isset($_POST['privacidad_recordatorios']);

        $res = Usuario::actualizarPreferencias($id_usuario, $notif, $em, $rec, $id_usuario);
        if ($res['status']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: /clinicaVidaSalud/views/cuenta/index.php');
        exit();
    }
}

$preferencias = Usuario::obtenerPreferencias($id_usuario);

$pageTitle = 'Configuración de Cuenta';
$currentPage = 'cuenta';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>

<div class="page-header-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div>
                <span class="cu-package-badge">SEGURIDAD Y CUENTA</span>
            </div>
            <h3 class="fw-bold mb-1 text-dark">Configuración de Cuenta y Privacidad</h3>
            <p class="text-muted mb-0 small">
                Gestión de credenciales de acceso, notificaciones internas y opciones de privacidad del usuario.
            </p>
        </div>
        <?php if (AuthMiddleware::esPaciente()): ?>
            <div>
                <a href="/clinicaVidaSalud/views/pacientes/perfil.php" class="btn btn-outline-info d-flex align-items-center gap-2">
                    <i class="bi bi-person-badge"></i>
                    <span>Ver Expediente del Paciente</span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Columna 1: Cambio de Contraseña -->
    <div class="col-lg-6">
        <div class="card card-custom h-100 shadow-sm border-0">
            <div class="card-header bg-white d-flex align-items-center gap-2 py-3">
                <i class="bi bi-shield-lock-fill text-primary fs-5"></i>
                <span class="fw-bold">Seguridad: Cambio de Contraseña</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="cambiar_password">

                    <div class="alert alert-light border small text-muted mb-4">
                        <i class="bi bi-info-circle me-1 text-primary"></i>
                        Para modificar su contraseña, verifique primero su contraseña actual. La nueva clave se almacenará cifrada con <strong>BCRYPT</strong>.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Contraseña Actual <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-key-fill text-muted"></i></span>
                            <input type="password" name="old_password" class="form-control" placeholder="Ingrese su contraseña vigente" required>
                        </div>
                        <div class="invalid-feedback">Ingrese su contraseña actual.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nueva Contraseña <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-lock-fill text-muted"></i></span>
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
                        <i class="bi bi-arrow-repeat me-1"></i>Actualizar Contraseña
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Columna 2: Notificaciones y Privacidad -->
    <div class="col-lg-6">
        <div class="card card-custom h-100 shadow-sm border-0">
            <div class="card-header bg-white d-flex align-items-center gap-2 py-3">
                <i class="bi bi-sliders text-success fs-5"></i>
                <span class="fw-bold">Notificaciones y Privacidad</span>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="action" value="update_preferencias">

                    <!-- Sección Notificaciones -->
                    <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-bell-fill text-warning"></i>
                        <span>Notificaciones Internas</span>
                    </h6>

                    <div class="form-check form-switch p-3 bg-light rounded border mb-4">
                        <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" id="notifSwitch"
                            name="notificaciones_activas" <?= ($preferencias['notificaciones_activas'] ?? true) ? 'checked' : '' ?> style="cursor: pointer; transform: scale(1.2);">
                        <label class="form-check-label fw-semibold text-dark" for="notifSwitch" style="cursor: pointer;">
                            Activar Notificaciones en el Sistema
                        </label>
                        <div class="text-muted small mt-1 ms-1">
                            Reciba alertas inmediatas al registrarse, confirmarse, reprogramarse o cancelarse citas médicas en su cuenta.
                        </div>
                    </div>

                    <!-- Sección Privacidad -->
                    <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-eye-slash-fill text-info"></i>
                        <span>Preferencias de Privacidad</span>
                    </h6>

                    <div class="form-check form-switch p-3 bg-light rounded border mb-3">
                        <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" id="privContactoSwitch"
                            name="privacidad_contacto_emergencia" <?= ($preferencias['privacidad_contacto_emergencia'] ?? true) ? 'checked' : '' ?> style="cursor: pointer; transform: scale(1.2);">
                        <label class="form-check-label fw-semibold text-dark" for="privContactoSwitch" style="cursor: pointer;">
                            Visibilidad del Contacto de Emergencia
                        </label>
                        <div class="text-muted small mt-1 ms-1">
                            Permitir que el personal médico de guardia y recepción consulte su contacto de emergencia ante imprevistos.
                        </div>
                    </div>

                    <div class="form-check form-switch p-3 bg-light rounded border mb-4">
                        <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" id="privRecordatoriosSwitch"
                            name="privacidad_recordatorios" <?= ($preferencias['privacidad_recordatorios'] ?? true) ? 'checked' : '' ?> style="cursor: pointer; transform: scale(1.2);">
                        <label class="form-check-label fw-semibold text-dark" for="privRecordatoriosSwitch" style="cursor: pointer;">
                            Recordatorios de Consultas en Pantalla
                        </label>
                        <div class="text-muted small mt-1 ms-1">
                            Mostrar avisos destacados de próximas citas programadas en el panel principal del Dashboard.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100 py-2 fw-semibold shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i>Guardar Preferencias
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>