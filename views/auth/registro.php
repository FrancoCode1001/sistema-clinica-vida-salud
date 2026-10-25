<?php

/**
 * Vista: Registro de Nuevos Pacientes (Sección 3.4 / CU-01)
 * Permite a los pacientes crear su cuenta de usuario y expediente personal
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Si ya tiene sesión, redirigir al Dashboard
if (isset($_SESSION['usuario']['id_usuario'])) {
    header('Location: /clinicaVidaSalud/views/dashboard/index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Paciente | Clínica Vida Salud</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/clinicaVidaSalud/assets/css/custom.css">
</head>

<body class="login-page-wrapper py-5">

    <div class="login-card" style="max-width: 680px;">
        <div class="login-header">
            <div class="d-inline-flex p-3 rounded-circle bg-white bg-opacity-25 mb-2">
                <i class="bi bi-person-plus-fill fs-2 text-white"></i>
            </div>
            <h3 class="fw-bold mb-1">Clínica Vida Salud</h3>
            <p class="mb-1 text-white-50" style="font-size: 0.9rem;">Registro de Nuevo Paciente &bull; Portal de Autogestión</p>
            <span class="badge bg-light text-dark fw-bold" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                CUENTA Y EXPEDIENTE DEL PACIENTE
            </span>
        </div>

        <div class="p-4 p-md-5">
            <!-- Alertas de sesión -->
            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div style="font-size: 0.88rem;"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <form action="/clinicaVidaSalud/views/auth/registro_action.php" method="POST" class="needs-validation" novalidate id="formRegistroPaciente">
                <!-- 1. Datos Personales del Paciente -->
                <h6 class="fw-bold text-primary mb-3 pb-2 border-bottom">
                    <i class="bi bi-person-badge me-2"></i>1. Datos Personales
                </h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Documento de Identidad (CI) <span class="text-danger">*</span></label>
                        <input type="text" name="ci" class="form-control" placeholder="ej. 8451230" required>
                        <div class="invalid-feedback">El CI es obligatorio y debe ser único (RN01).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Fecha de Nacimiento <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_nacimiento" class="form-control" max="<?= date('Y-m-d') ?>" required>
                        <div class="invalid-feedback">Seleccione su fecha de nacimiento.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Nombres <span class="text-danger">*</span></label>
                        <input type="text" name="nombres" class="form-control" placeholder="ej. María" required>
                        <div class="invalid-feedback">Ingrese sus nombres.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Apellidos <span class="text-danger">*</span></label>
                        <input type="text" name="apellidos" class="form-control" placeholder="ej. López Ríos" required>
                        <div class="invalid-feedback">Ingrese sus apellidos.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Sexo Demográfico <span class="text-danger">*</span></label>
                        <select name="sexo" class="form-select" required>
                            <option value="">-- Seleccionar --</option>
                            <option value="F">Femenino (F)</option>
                            <option value="M">Masculino (M)</option>
                        </select>
                        <div class="invalid-feedback">Seleccione su sexo demográfico.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Teléfono de Contacto <span class="text-danger">*</span></label>
                        <input type="text" name="telefono" class="form-control" placeholder="ej. 70011223" required>
                        <div class="invalid-feedback">El número de teléfono es obligatorio.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Dirección Domiciliaria (Opcional)</label>
                        <input type="text" name="direccion" class="form-control" placeholder="ej. Av. Beni #240">
                    </div>
                </div>

                <!-- 2. Credenciales de la Cuenta de Acceso -->
                <h6 class="fw-bold text-primary mb-3 pb-2 border-bottom">
                    <i class="bi bi-shield-lock me-2"></i>2. Credenciales de Acceso al Sistema
                </h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Nombre de Usuario (Username) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-person text-muted"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="ej. maria.lopez" required>
                        </div>
                        <div class="invalid-feedback">El nombre de usuario es obligatorio.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Correo Electrónico <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-envelope text-muted"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="ej. maria.lopez@gmail.com" required>
                        </div>
                        <div class="invalid-feedback">Ingrese un correo electrónico válido.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Contraseña <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="reg_pass" class="form-control" placeholder="Mínimo 6 caracteres" minlength="6" required>
                        <div class="invalid-feedback">La contraseña debe tener al menos 6 caracteres.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Confirmar Contraseña <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" id="reg_confirm_pass" class="form-control" placeholder="Repita la contraseña" minlength="6" required>
                        <div class="invalid-feedback">Confirme su contraseña.</div>
                    </div>
                </div>

                <div class="alert alert-light border small text-muted mb-4">
                    <i class="bi bi-info-circle me-1 text-primary"></i>
                    Al registrarte se creará automáticamente tu expediente clínico digital, permitiéndote reservar citas en tiempo real y consultar tu historial.
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm mb-3">
                    <i class="bi bi-check-circle-fill me-1"></i>Crear Cuenta de Paciente
                </button>

                <div class="text-center pt-3 border-top">
                    <span class="text-muted small">¿Ya tienes una cuenta registrada? </span>
                    <a href="/clinicaVidaSalud/views/auth/login.php" class="fw-semibold text-primary text-decoration-none">
                        Iniciar Sesión aquí
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('formRegistroPaciente').addEventListener('submit', function(event) {
            const p1 = document.getElementById('reg_pass').value;
            const p2 = document.getElementById('reg_confirm_pass').value;
            if (p1 !== p2) {
                alert('Las contraseñas ingresadas no coinciden. Por favor verifique.');
                event.preventDefault();
                event.stopPropagation();
                return false;
            }
        });
    </script>
</body>

</html>