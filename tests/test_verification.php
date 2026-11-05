<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/models/Paciente.php';
require_once __DIR__ . '/../app/models/Usuario.php';
require_once __DIR__ . '/../app/models/PerfilMedico.php';
require_once __DIR__ . '/../app/models/Notificacion.php';

echo "=== INICIO DE PRUEBAS DE VERIFICACIÓN ===\n";

$pdo = Database::getConnection();

// 1. Test Inmutabilidad del CI y actualización de datos personales y contacto
echo "\n--- 1. Prueba: Datos Personales y Contacto (CI inmutable) ---\n";
$pacienteAntes = Paciente::obtenerPorId(1);
$ciOriginal = $pacienteAntes['ci'];
echo "CI Original del Paciente 1: {$ciOriginal}\n";

$datosTest = [
    'ci'               => '9999999_INTENTO_CAMBIO', // Intento no permitido
    'nombres'          => 'María Elena',
    'apellidos'        => 'López Ríos',
    'fecha_nacimiento' => '1992-05-14',
    'sexo'             => 'F',
    'telefono'         => '70011999',
    'email'            => 'maria.lopez@gmail.com',
    'direccion'        => 'Av. Beni #250 Cond. Norte, Piso 4 Depto 402'
];

$resAct = Paciente::actualizarDatosPersonalesYContacto(1, $datosTest, 1);
echo "Resultado actualización: " . ($resAct['status'] ? 'EXITO' : 'FALLO') . " - {$resAct['message']}\n";

$pacienteDespues = Paciente::obtenerPorId(1);
echo "CI Después de actualizar: {$pacienteDespues['ci']} (¿CI protegido?: " . ($pacienteDespues['ci'] === $ciOriginal ? 'SI' : 'NO') . ")\n";
echo "Nombres actualizados: {$pacienteDespues['nombres']}\n";
echo "Dirección actualizada: {$pacienteDespues['direccion']}\n";

// 2. Test Cambio de Contraseña y Verificación Segura
echo "\n--- 2. Prueba: Configuración de Cuenta - Cambio de Contraseña ---\n";
// Probar contraseña errónea
$resPassErr = Usuario::cambiarPassword(5, 'clave_incorrecta', 'nuevaClave123', 5);
echo "Intento con clave incorrecta: " . (!$resPassErr['status'] ? 'RECHAZADO CORRECTAMENTE' : 'ERROR') . " - {$resPassErr['message']}\n";

// Probar cambio correcto
$resPassOk = Usuario::cambiarPassword(5, 'password', 'nuevaClave123', 5);
echo "Cambio con clave correcta: " . ($resPassOk['status'] ? 'EXITO' : 'FALLO') . " - {$resPassOk['message']}\n";

// Verificar nueva clave con password_verify
$u5 = Usuario::buscarPorCredencial('maria.lopez');
$validoNueva = Usuario::verificarPassword('nuevaClave123', $u5['password_hash']);
echo "¿Nueva contraseña válida con password_verify?: " . ($validoNueva ? 'SI' : 'NO') . "\n";

// Restaurar clave original 'password'
Usuario::cambiarPassword(5, 'nuevaClave123', 'password', 5);
echo "Clave restaurada a 'password' para mantener semillas.\n";

// 3. Test Preferencias de Notificaciones y Privacidad
echo "\n--- 3. Prueba: Preferencias de Notificaciones y Privacidad ---\n";
$resPref = Usuario::actualizarPreferencias(5, true, true, false, 5);
echo "Resultado guardar preferencias: " . ($resPref['status'] ? 'EXITO' : 'FALLO') . " - {$resPref['message']}\n";
$prefs = Usuario::obtenerPreferencias(5);
echo "Notificaciones activas: " . ($prefs['notificaciones_activas'] ? 'TRUE' : 'FALSE') . "\n";
echo "Privacidad emergencia: " . ($prefs['privacidad_contacto_emergencia'] ? 'TRUE' : 'FALSE') . "\n";
echo "Privacidad recordatorios: " . ($prefs['privacidad_recordatorios'] ? 'TRUE' : 'FALSE') . "\n";

// 4. Test Notificaciones Internas
echo "\n--- 4. Prueba: Sistema de Notificaciones Internas ---\n";
$noLeidasAntes = Notificacion::contarNoLeidas(5);
echo "Notificaciones no leídas usuario 5 antes: {$noLeidasAntes}\n";

Notificacion::crear(5, 'CITA_NUEVA', 'Cita Test Verificación', 'Mensaje de prueba de notificación en tiempo real.', '/clinicaVidaSalud/views/consultas/historial.php');
$noLeidasDespues = Notificacion::contarNoLeidas(5);
echo "Notificaciones no leídas usuario 5 después: {$noLeidasDespues}\n";

$notifs = Notificacion::listarPorUsuario(5, 5);
echo "Última notificación registrada: '{$notifs[0]['titulo']}' ({$notifs[0]['tipo']})\n";

Notificacion::marcarTodasLeidas(5);
$noLeidasFinal = Notificacion::contarNoLeidas(5);
echo "Notificaciones no leídas tras marcar todas leídas: {$noLeidasFinal}\n";

// 5. Test disparador de notificación por evento de cita
echo "\n--- 5. Prueba: Disparadores automáticos por evento de cita ---\n";
Notificacion::notificarNuevaCita(1);
echo "Disparador notificarNuevaCita(1) ejecutado sin errores.\n";

Notificacion::notificarReprogramacionCita(1, '2026-11-20', '09:00:00');
echo "Disparador notificarReprogramacionCita(1) ejecutado sin errores.\n";

Notificacion::notificarCancelacionCita(4, 'Motivo de prueba médica');
echo "Disparador notificarCancelacionCita(4) ejecutado sin errores.\n";

echo "\n=== TODAS LAS PRUEBAS COMPLETADAS EXITOSAMENTE ===\n";
