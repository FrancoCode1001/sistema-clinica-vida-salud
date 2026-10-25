<?php
/**
 * Controlador: AuthController (CU-01)
 * Iniciar Sesión, Control de Acceso y Cierre de Sesión
 */

require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../helpers/AuthMiddleware.php';

class AuthController
{
    /**
     * Procesa la solicitud de inicio de sesión
     */
    public function login(): void
    {
        AuthMiddleware::iniciarSesion();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /clinicaVidaSalud/views/auth/login.php');
            exit();
        }

        $credencial = trim($_POST['credencial'] ?? '');
        $password   = $_POST['password'] ?? '';

        if (empty($credencial) || empty($password)) {
            $_SESSION['flash_error'] = 'Por favor ingrese su usuario/correo y contraseña.';
            header('Location: /clinicaVidaSalud/views/auth/login.php');
            exit();
        }

        try {
            $usuario = Usuario::buscarPorCredencial($credencial);

            if (!$usuario) {
                $_SESSION['flash_error'] = 'Credenciales incorrectas: El usuario o correo no existe.';
                header('Location: /clinicaVidaSalud/views/auth/login.php');
                exit();
            }

            if (!Usuario::verificarPassword($password, $usuario['password_hash'])) {
                $_SESSION['flash_error'] = 'Credenciales incorrectas: Contraseña no válida.';
                header('Location: /clinicaVidaSalud/views/auth/login.php');
                exit();
            }

            if ($usuario['estado'] !== 'ACTIVO') {
                $_SESSION['flash_error'] = 'Acceso bloqueado: Su cuenta de usuario se encuentra inactiva.';
                header('Location: /clinicaVidaSalud/views/auth/login.php');
                exit();
            }

            // Iniciar sesión y registrar en bitácora
            AuthMiddleware::registrarInicioSesion($usuario);
            $_SESSION['flash_success'] = "¡Bienvenido/a al sistema, {$usuario['username']}!";

            header('Location: /clinicaVidaSalud/views/dashboard/index.php');
            exit();

        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Error en el servidor de base de datos: ' . $e->getMessage();
            header('Location: /clinicaVidaSalud/views/auth/login.php');
            exit();
        }
    }

    /**
     * Cierra la sesión activa
     */
    public function logout(): void
    {
        AuthMiddleware::cerrarSesion();
        AuthMiddleware::iniciarSesion();
        $_SESSION['flash_info'] = 'Sesión cerrada correctamente. ¡Hasta pronto!';
        header('Location: /clinicaVidaSalud/views/auth/login.php');
        exit();
    }
}
