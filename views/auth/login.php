<?php

/**
 * Vista: Login (CU-01: Iniciar Sesión y Control de Acceso)
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
    <title>Iniciar Sesión | Clínica Vida Salud</title>
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

<body class="login-page-wrapper">

    <div class="login-card">
        <div class="login-header">
            <div class="d-inline-flex p-3 rounded-circle bg-white bg-opacity-25 mb-3">
                <i class="bi bi-hospital fs-1 text-white"></i>
            </div>
            <h3 class="fw-bold mb-1">Clínica Vida Salud</h3>
            <p class="mb-0 text-white-50" style="font-size: 0.9rem;">Sistema de Gestión y Control de Citas Médicas</p>
            <div class="mt-2">
                <span class="badge bg-light text-dark fw-bold" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                    INICIAR SESIÓN Y CONTROL DE ACCESO
                </span>
            </div>
        </div>

        <div class="p-4 p-md-5">
            <!-- Alertas de sesión -->
            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div style="font-size: 0.88rem;"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['flash_info'])): ?>
                <div class="alert alert-info d-flex align-items-center gap-2 alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle-fill fs-5"></i>
                    <div style="font-size: 0.88rem;"><?= htmlspecialchars($_SESSION['flash_info']) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['flash_info']); ?>
            <?php endif; ?>

            <form action="/clinicaVidaSalud/views/auth/login_action.php" method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="credencial" class="form-label fw-semibold" style="font-size: 0.85rem;">
                        <i class="bi bi-person-fill text-primary me-1"></i>Usuario o Correo Electrónico
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" id="credencial" name="credencial"
                            placeholder="ej. admin o admin@vidasalud.com" required autofocus>
                    </div>
                    <div class="invalid-feedback">Por favor ingrese su usuario o correo.</div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold" style="font-size: 0.85rem;">
                        <i class="bi bi-lock-fill text-primary me-1"></i>Contraseña
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-key text-muted"></i></span>
                        <input type="password" class="form-control border-start-0" id="password" name="password"
                            placeholder="••••••••" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassBtn" onclick="togglePasswordVisibility()">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback">Por favor ingrese su contraseña.</div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm" style="font-size: 0.95rem;">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Ingresar al Sistema
                </button>
            </form>

            <!-- Sección para que los nuevos pacientes creen su cuenta (Sección 3.4) -->
            <div class="mt-4 pt-3 border-top text-center">
                <p class="text-muted small mb-2">¿No tienes cuenta?</p>
                <a href="/clinicaVidaSalud/views/auth/registro.php" class="btn btn-outline-success w-100 py-2 fw-semibold">
                    <i class="bi bi-person-plus-fill me-1"></i>Crear Cuenta
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePasswordVisibility() {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>
</body>

</html>