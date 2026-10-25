<?php
/**
 * Procesa el registro de nuevos pacientes (Sección 3.4 / CU-01)
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../../app/helpers/AuditoriaHelper.php';

AuthMiddleware::iniciarSesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /clinicaVidaSalud/views/auth/registro.php');
    exit();
}

$ci         = trim($_POST['ci'] ?? '');
$nombres    = trim($_POST['nombres'] ?? '');
$apellidos  = trim($_POST['apellidos'] ?? '');
$fecha_nac  = trim($_POST['fecha_nacimiento'] ?? '');
$sexo       = strtoupper(trim($_POST['sexo'] ?? ''));
$telefono   = trim($_POST['telefono'] ?? '');
$direccion  = trim($_POST['direccion'] ?? '');
$username   = trim($_POST['username'] ?? '');
$email      = trim($_POST['email'] ?? '');
$password   = $_POST['password'] ?? '';
$confirm    = $_POST['confirm_password'] ?? '';

// Validaciones básicas
if (empty($ci) || empty($nombres) || empty($apellidos) || empty($fecha_nac) || empty($sexo) || empty($telefono) || empty($username) || empty($email) || empty($password)) {
    $_SESSION['flash_error'] = 'Por favor complete todos los campos obligatorios.';
    header('Location: /clinicaVidaSalud/views/auth/registro.php');
    exit();
}

if ($password !== $confirm) {
    $_SESSION['flash_error'] = 'Las contraseñas no coinciden.';
    header('Location: /clinicaVidaSalud/views/auth/registro.php');
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'El correo electrónico no es válido.';
    header('Location: /clinicaVidaSalud/views/auth/registro.php');
    exit();
}

try {
    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    // 1. Validar que no exista el CI (RN01)
    $stmt = $pdo->prepare("SELECT 1 FROM pacientes WHERE ci = :ci");
    $stmt->execute([':ci' => $ci]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = "RN01: Ya existe un paciente registrado con el CI '{$ci}'.";
        header('Location: /clinicaVidaSalud/views/auth/registro.php');
        exit();
    }

    // 2. Validar que no exista el username o email
    $stmt = $pdo->prepare("SELECT 1 FROM usuarios WHERE LOWER(username) = LOWER(:u) OR LOWER(email) = LOWER(:e)");
    $stmt->execute([':u' => $username, ':e' => $email]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = "El nombre de usuario o correo electrónico ya se encuentra registrado.";
        header('Location: /clinicaVidaSalud/views/auth/registro.php');
        exit();
    }

    // 3. Crear el usuario con id_rol = 4 (Paciente)
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $sqlUser = "INSERT INTO usuarios (username, email, password_hash, id_rol, estado, created_at)
                VALUES (:u, :e, :hash, 4, 'ACTIVO', CURRENT_TIMESTAMP)
                RETURNING id_usuario";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute([
        ':u'    => $username,
        ':e'    => $email,
        ':hash' => $hash
    ]);
    $id_usuario = (int)$stmtUser->fetchColumn();

    // 4. Generar código correlativo único PAC-XXXX
    $stmtSeq = $pdo->query("SELECT COALESCE(MAX(id_paciente), 0) + 1 FROM pacientes");
    $sigId = (int)$stmtSeq->fetchColumn();
    $codigo_paciente = sprintf('PAC-%04d', $sigId);

    // 5. Insertar paciente vinculado a su usuario único
    $sqlPac = "INSERT INTO pacientes (codigo_paciente, ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, email, direccion, estado, id_usuario)
               VALUES (:cod, :ci, :nom, :ape, :fnac, :sexo, :tel, :em, :dir, 'ACTIVO', :id_u)
               RETURNING id_paciente";
    $stmtPac = $pdo->prepare($sqlPac);
    $stmtPac->execute([
        ':cod'  => $codigo_paciente,
        ':ci'   => $ci,
        ':nom'  => $nombres,
        ':ape'  => $apellidos,
        ':fnac' => $fecha_nac,
        ':sexo' => $sexo,
        ':tel'  => $telefono,
        ':em'   => $email,
        ':dir'  => !empty($direccion) ? $direccion : null,
        ':id_u' => $id_usuario
    ]);
    $id_paciente = (int)$stmtPac->fetchColumn();

    // 6. Auditoría
    AuditoriaHelper::registrar(
        $id_usuario,
        'INSERT',
        'pacientes',
        "Auto-registro de paciente {$codigo_paciente} ({$nombres} {$apellidos}, CI: {$ci}) con cuenta '{$username}'"
    );

    $pdo->commit();

    // Iniciar sesión automáticamente para el nuevo paciente
    $usuarioSesion = [
        'id_usuario'      => $id_usuario,
        'username'        => $username,
        'email'           => $email,
        'id_rol'          => 4,
        'nombre_rol'      => 'Paciente',
        'estado'          => 'ACTIVO',
        'id_paciente'     => $id_paciente,
        'id_medico'       => null,
        'nombre_completo' => "{$nombres} {$apellidos}"
    ];

    AuthMiddleware::registrarInicioSesion($usuarioSesion);
    $_SESSION['flash_success'] = "¡Cuenta creada con éxito! Bienvenido(a) a la Clínica Vida Salud, {$nombres}.";
    header('Location: /clinicaVidaSalud/views/dashboard/index.php');
    exit();

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = 'Error al procesar el registro: ' . $e->getMessage();
    header('Location: /clinicaVidaSalud/views/auth/registro.php');
    exit();
}
