<?php
/**
 * Middleware de Autenticación, Control de Sesión y Permisos por Roles (CU-01)
 * Protege el acceso a rutas internas e implementa permisos dinámicos.
 */

require_once __DIR__ . '/AuditoriaHelper.php';

class AuthMiddleware
{
    /**
     * Inicia la sesión segura si aún no está activa
     */
    public static function iniciarSesion(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            session_start();
        }
    }

    /**
     * Verifica si el usuario cuenta con una sesión activa.
     * Si no está autenticado, bloquea el acceso y redirige a login.php.
     *
     * @param string $redirectUrl URL destino para redirección si no hay sesión
     */
    public static function verificarAutenticacion(string $redirectUrl = '/clinicaVidaSalud/views/auth/login.php'): void
    {
        self::iniciarSesion();

        if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario']['id_usuario'])) {
            $_SESSION['flash_error'] = 'Acceso denegado: Debe iniciar sesión para acceder a este módulo.';
            header("Location: " . $redirectUrl);
            exit();
        }

        // Verificar que la cuenta continúe activa
        if (isset($_SESSION['usuario']['estado']) && $_SESSION['usuario']['estado'] !== 'ACTIVO') {
            self::cerrarSesion();
            $_SESSION['flash_error'] = 'Su cuenta ha sido desactivada. Comuníquese con la administración.';
            header("Location: " . $redirectUrl);
            exit();
        }
    }

    /**
     * Verifica que el usuario pertenezca a uno de los roles permitidos
     *
     * @param array $rolesPermitidos Nombres de roles permitidos (ej. ['Administrador', 'Recepcionista'])
     * @param string $redirectUrl
     */
    public static function verificarRoles(array $rolesPermitidos, string $redirectUrl = '/clinicaVidaSalud/views/dashboard/index.php'): void
    {
        self::verificarAutenticacion();

        $rolUsuario = $_SESSION['usuario']['nombre_rol'] ?? '';

        if (!in_array($rolUsuario, $rolesPermitidos, true)) {
            $_SESSION['flash_error'] = "Permiso denegado: El rol '{$rolUsuario}' no tiene autorización para acceder a este recurso.";
            header("Location: " . $redirectUrl);
            exit();
        }
    }

    /**
     * Retorna los datos del usuario en sesión
     */
    public static function usuario(): ?array
    {
        self::iniciarSesion();
        return $_SESSION['usuario'] ?? null;
    }

    /**
     * Helpers de rol
     */
    public static function esAdmin(): bool
    {
        $u = self::usuario();
        return $u && ($u['nombre_rol'] === 'Administrador' || (int)($u['id_rol'] ?? 0) === 1);
    }

    public static function esRecepcionista(): bool
    {
        $u = self::usuario();
        return $u && ($u['nombre_rol'] === 'Recepcionista' || (int)($u['id_rol'] ?? 0) === 2);
    }

    public static function esMedico(): bool
    {
        $u = self::usuario();
        return $u && ($u['nombre_rol'] === 'Medico' || (int)($u['id_rol'] ?? 0) === 3);
    }

    public static function esPaciente(): bool
    {
        $u = self::usuario();
        return $u && ($u['nombre_rol'] === 'Paciente' || (int)($u['id_rol'] ?? 0) === 4);
    }

    /**
     * Inicia la sesión autenticando al usuario y registrando auditoría
     */
    public static function registrarInicioSesion(array $usuario): void
    {
        self::iniciarSesion();
        session_regenerate_id(true);

        $_SESSION['usuario'] = [
            'id_usuario'   => (int)$usuario['id_usuario'],
            'username'     => $usuario['username'],
            'email'        => $usuario['email'],
            'id_rol'       => (int)$usuario['id_rol'],
            'nombre_rol'   => $usuario['nombre_rol'] ?? '',
            'estado'       => $usuario['estado'] ?? 'ACTIVO',
            'id_medico'    => $usuario['id_medico'] ?? null,
            'id_paciente'  => $usuario['id_paciente'] ?? null,
            'nombre_completo' => $usuario['nombre_completo'] ?? $usuario['username']
        ];

        // Auditoría automática en tabla bitacoras
        AuditoriaHelper::registrar(
            (int)$usuario['id_usuario'],
            'LOGIN',
            'Autenticación',
            "Inicio de sesión exitoso del usuario {$usuario['username']} ({$usuario['nombre_rol']})"
        );
    }

    /**
     * Cierra la sesión activa y registra auditoría
     */
    public static function cerrarSesion(): void
    {
        self::iniciarSesion();

        if (isset($_SESSION['usuario']['id_usuario'])) {
            AuditoriaHelper::registrar(
                (int)$_SESSION['usuario']['id_usuario'],
                'LOGOUT',
                'Autenticación',
                "Cierre de sesión del usuario {$_SESSION['usuario']['username']}"
            );
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}
