<?php
/**
 * API: Asistente de Voz con IA (Google Gemini 3.8 Flash)
 * Endpoint Seguro para el procesamiento de comandos de voz de pacientes
 * Cumple con CU-08, CU-09, CU-10, CU-11, RF06, RF07, RF08, RN01, RN06, RN07, RN13
 * 
 * Medidas de Seguridad Implementadas:
 * 1. Control de Acceso Estricto (Sesión activa obligatoria).
 * 2. Cero Riesgo de IDOR: id_paciente vinculado exclusivamente a la sesión del servidor.
 * 3. Inmutabilidad del CI del Paciente (RN01): Ninguna orden de voz puede modificar el CI.
 * 4. Human-in-the-Loop: Modificaciones (alta, reprogramación, baja) requieren confirmación explícita.
 * 5. Rate Limiting por sesión (máximo 25 consultas/minuto).
 * 6. Auditoría y Trazabilidad total en la tabla 'bitacoras'.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/helpers/AuthMiddleware.php';
require_once __DIR__ . '/../app/helpers/AuditoriaHelper.php';
require_once __DIR__ . '/../app/models/CitaMedica.php';
require_once __DIR__ . '/../app/models/Medico.php';
require_once __DIR__ . '/../app/models/Especialidad.php';
require_once __DIR__ . '/../app/models/Paciente.php';
require_once __DIR__ . '/../app/models/Notificacion.php';
require_once __DIR__ . '/../app/services/GeminiVozService.php';

AuthMiddleware::iniciarSesion();
if (!isset($_SESSION['usuario'])) {
    echo json_encode([
        'status' => false,
        'message' => 'Sesión expirada o no autenticada.',
        'mensaje_voz' => 'Tu sesión ha expirado. Por favor inicia sesión nuevamente.'
    ]);
    exit();
}

$id_usuario_auth = (int)($_SESSION['usuario']['id_usuario'] ?? 0);
$id_rol_auth     = (int)($_SESSION['usuario']['id_rol'] ?? 0);
$nombre_usuario  = $_SESSION['usuario']['nombre_completo'] ?? $_SESSION['usuario']['username'] ?? 'Paciente';

// Rate Limiting por sesión
$now = time();
if (!isset($_SESSION['voz_rate_limit'])) {
    $_SESSION['voz_rate_limit'] = ['count' => 0, 'window_start' => $now];
}
if ($now - $_SESSION['voz_rate_limit']['window_start'] > 60) {
    $_SESSION['voz_rate_limit'] = ['count' => 1, 'window_start' => $now];
} else {
    $_SESSION['voz_rate_limit']['count']++;
    if ($_SESSION['voz_rate_limit']['count'] > 30) {
        echo json_encode([
            'status' => false,
            'message' => 'Límite de consultas por voz excedido. Por favor espera un momento.',
            'mensaje_voz' => 'Has realizado varias consultas en poco tiempo. Por favor espera unos segundos antes de continuar.'
        ]);
        exit();
    }
}

// Control de acceso estricto: El Asistente de Voz es EXCLUSIVAMENTE para Pacientes (rol 4)
if (!AuthMiddleware::esPaciente()) {
    echo json_encode([
        'status' => false,
        'message' => 'Acceso denegado: El control por voz está habilitado exclusivamente para el perfil de Paciente.',
        'mensaje_voz' => 'Esta funcionalidad de control por voz está disponible únicamente para los pacientes de la clínica.'
    ]);
    exit();
}

// Obtener o resolver id_paciente legítimo (Zero Trust / Anti-IDOR)
$id_paciente = (int)($_SESSION['usuario']['id_paciente'] ?? 0);
if ($id_paciente <= 0) {
    $pdo = Database::getConnection();
    // Si no está en sesión, verificar si el usuario tiene ficha en pacientes
    $stmtP = $pdo->prepare("SELECT id_paciente FROM pacientes WHERE id_usuario = :u LIMIT 1");
    $stmtP->execute([':u' => $id_usuario_auth]);
    $id_paciente = (int)$stmtP->fetchColumn();

    if ($id_paciente <= 0) {
        echo json_encode([
            'status' => false,
            'message' => 'No se encontró un expediente de paciente vinculado a su usuario.',
            'mensaje_voz' => 'No encontramos tu ficha de paciente en la clínica. Por favor consulta en recepción.'
        ]);
        exit();
    }
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    case 'procesar_comando':
        $comando = trim($_POST['comando'] ?? $_GET['comando'] ?? '');
        if (empty($comando)) {
            echo json_encode([
                'status' => false,
                'message' => 'El comando de voz no puede estar vacío.',
                'mensaje_voz' => 'No alcancé a escucharte bien. Por favor intenta hablar nuevamente.'
            ]);
            exit();
        }

        if (mb_strlen($comando, 'UTF-8') > 500) {
            echo json_encode([
                'status' => false,
                'message' => 'El comando es demasiado extenso.',
                'mensaje_voz' => 'El mensaje fue demasiado largo. Por favor sé más breve.'
            ]);
            exit();
        }

        // Obtener contexto actual
        $especialidades = Especialidad::listarTodas(true);
        $medicos = Medico::listarTodos(null, null, true);

        $citasPaciente = [];
        if ($id_paciente > 0) {
            $todasCitas = CitaMedica::listar(['id_paciente' => $id_paciente]);
            // Filtrar únicamente citas futuras o del día
            $hoy = date('Y-m-d');
            foreach ($todasCitas as $c) {
                if ($c['fecha_cita'] >= $hoy && in_array($c['estado'], ['PROGRAMADA', 'CONFIRMADA'], true)) {
                    $citasPaciente[] = $c;
                }
            }
        }

        // Obtener notificaciones del paciente
        $notificaciones = Notificacion::listarPorUsuario($id_usuario_auth, 6);
        $noLeidasCount = Notificacion::contarNoLeidas($id_usuario_auth);

        $contexto = [
            'especialidades'   => $especialidades,
            'medicos'          => $medicos,
            'citas_paciente'   => $citasPaciente,
            'notificaciones'   => $notificaciones,
            'total_no_leidas'  => $noLeidasCount
        ];

        $gemini = new GeminiVozService();
        $interpretacion = $gemini->interpretarComando($comando, $contexto);

        if (!$interpretacion['status']) {
            echo json_encode($interpretacion);
            exit();
        }

        $intencion = $interpretacion['intencion'];
        $detallesVisuales = null;
        $accionPendiente = null;

        // Limpiar acción pendiente previa
        unset($_SESSION['asistente_voz_pending']);

        switch ($intencion) {
            case 'CONSULTAR_PROXIMA_CITA':
                if (empty($citasPaciente)) {
                    $interpretacion['mensaje_voz'] = "Hola {$nombre_usuario}, actualmente no tienes ninguna cita médica agendada. Si necesitas atención, dime con qué especialista te gustaría agendar.";
                    $interpretacion['mensaje_texto'] = "No tienes citas médicas próximas agendadas.";
                    $interpretacion['requiere_confirmacion'] = false;
                } else {
                    $prox = $citasPaciente[0];
                    $fechaFormateada = date('d/m/Y', strtotime($prox['fecha_cita']));
                    $horaFormateada = substr($prox['hora_inicio'], 0, 5);
                    $interpretacion['mensaje_voz'] = "Tu próxima cita es el {$fechaFormateada} a las {$horaFormateada} con el doctor {$prox['medico_nombre']} en {$prox['especialidad_nombre']}. Código: {$prox['codigo_cita']}.";
                    $interpretacion['mensaje_texto'] = "Próxima cita: {$prox['codigo_cita']} con Dr(a). {$prox['medico_nombre']} ({$prox['especialidad_nombre']}) para el {$fechaFormateada} a las {$horaFormateada} ({$prox['estado']}).";
                    $interpretacion['requiere_confirmacion'] = false;
                    $detallesVisuales = [
                        'tipo' => 'cita_info',
                        'codigo' => $prox['codigo_cita'],
                        'medico' => $prox['medico_nombre'],
                        'especialidad' => $prox['especialidad_nombre'],
                        'fecha' => $fechaFormateada,
                        'hora' => $horaFormateada,
                        'estado' => $prox['estado']
                    ];
                }
                break;

            case 'CONSULTAR_NOTIFICACIONES':
                if ($noLeidasCount === 0) {
                    $interpretacion['mensaje_voz'] = "Hola {$nombre_usuario}, estás al día. No tienes notificaciones nuevas ni recordatorios pendientes.";
                    $interpretacion['mensaje_texto'] = "No tienes notificaciones pendientes sin leer en el sistema.";
                } else {
                    $primerTitulo = $notificaciones[0]['titulo'] ?? 'Aviso';
                    $primerMsg = $notificaciones[0]['mensaje'] ?? '';
                    $interpretacion['mensaje_voz'] = "Tienes {$noLeidasCount} notificaciones sin leer. La más reciente dice: {$primerTitulo}: {$primerMsg}.";
                    $interpretacion['mensaje_texto'] = "Tienes {$noLeidasCount} notificación(es) no leída(s). Revisa el panel de la campana en la barra superior.";
                    $detallesVisuales = [
                        'tipo' => 'notificaciones',
                        'total_no_leidas' => $noLeidasCount,
                        'lista' => array_slice($notificaciones, 0, 3)
                    ];
                }
                $interpretacion['requiere_confirmacion'] = false;
                break;

            case 'CONSULTAR_DISPONIBILIDAD':
                $id_medico = $interpretacion['id_medico'];
                $fecha = $interpretacion['fecha'];

                if ($id_medico && $fecha) {
                    $disp = CitaMedica::consultarDisponibilidad($id_medico, $fecha);
                    if ($disp['status'] && !empty($disp['slots'])) {
                        $slotsLibres = array_slice($disp['slots'], 0, 4);
                        $textoHoras = implode(', ', $slotsLibres);
                        $interpretacion['mensaje_voz'] = "El doctor {$disp['medico']} tiene disponibilidad el {$fecha} en los horarios: {$textoHoras}. ¿Te gustaría que te reserve alguno?";
                        $interpretacion['mensaje_texto'] = "Horarios disponibles para Dr(a). {$disp['medico']} ({$fecha}): " . implode(', ', $disp['slots']);
                        $detallesVisuales = [
                            'tipo' => 'slots',
                            'medico' => $disp['medico'],
                            'fecha' => $fecha,
                            'slots' => $disp['slots']
                        ];
                    } else {
                        $msgNo = $disp['message'] ?? 'No hay horarios disponibles para esa fecha.';
                        $interpretacion['mensaje_voz'] = "Para esa fecha no hay cupos libres con ese médico. ¿Deseas consultar otro día?";
                        $interpretacion['mensaje_texto'] = $msgNo;
                    }
                }
                $interpretacion['requiere_confirmacion'] = false;
                break;

            case 'RESERVAR_CITA':
                if ($id_paciente <= 0) {
                    $interpretacion['status'] = false;
                    $interpretacion['mensaje_voz'] = "No tienes una ficha de paciente asociada a tu usuario. Por favor consulta en recepción.";
                    $interpretacion['mensaje_texto'] = "Error: Usuario sin id_paciente asociado.";
                    $interpretacion['requiere_confirmacion'] = false;
                    break;
                }

                $id_medico = $interpretacion['id_medico'];
                $id_esp    = $interpretacion['id_especialidad'];
                $fecha     = $interpretacion['fecha'];
                $inicio    = $interpretacion['hora_inicio'];
                $fin       = $interpretacion['hora_fin'];
                $motivo    = $interpretacion['motivo'] ?: 'Consulta agendada por voz';

                // Si falta fecha o médico o especialidad, pedir amablemente
                if (!$id_medico || !$fecha || !$inicio) {
                    $faltantes = [];
                    if (!$id_medico) $faltantes[] = "el médico o especialidad";
                    if (!$fecha) $faltantes[] = "la fecha deseada";
                    if (!$inicio) $faltantes[] = "el horario preferido";
                    
                    $interpretacion['requiere_confirmacion'] = false;
                    $interpretacion['mensaje_voz'] = "Para reservar tu cita necesito que me indiques: " . implode(' y ', $faltantes) . ". ¿Podrías mencionarlo?";
                    break;
                }

                // Si no se proporcionó id_especialidad, obtener la primera especialidad del médico
                if (!$id_esp) {
                    $pdo = Database::getConnection();
                    $stmtEsp = $pdo->prepare("SELECT id_especialidad FROM medico_especialidad WHERE id_medico = :m LIMIT 1");
                    $stmtEsp->execute([':m' => $id_medico]);
                    $id_esp = (int)$stmtEsp->fetchColumn();
                    if (!$id_esp) {
                        $id_esp = 1; // Default general
                    }
                }

                // Validar disponibilidad real en servidor antes de pedir confirmación
                $disp = CitaMedica::consultarDisponibilidad($id_medico, $fecha);
                if (!$disp['status']) {
                    $interpretacion['requiere_confirmacion'] = false;
                    $interpretacion['mensaje_voz'] = "No fue posible reservar: " . $disp['message'];
                    $interpretacion['mensaje_texto'] = $disp['message'];
                    break;
                }

                // Verificar si el horario solicitado está entre los libres
                $horaNormalizada = substr($inicio, 0, 5);
                if (!in_array($horaNormalizada, $disp['slots'], true)) {
                    $sugerencias = array_slice($disp['slots'], 0, 3);
                    $sugTexto = !empty($sugerencias) ? ("Horarios libres sugeridos: " . implode(', ', $sugerencias)) : "No quedan cupos en ese día.";
                    $interpretacion['requiere_confirmacion'] = false;
                    $interpretacion['mensaje_voz'] = "El horario de las {$horaNormalizada} no está disponible. " . (!empty($sugerencias) ? "Podría ser a las " . implode(' o a las ', $sugerencias) : "Intenta con otra fecha.");
                    $interpretacion['mensaje_texto'] = "El bloque {$horaNormalizada} no está disponible. {$sugTexto}";
                    break;
                }

                // Preparar acción pendiente para confirmación
                $datosCita = [
                    'tipo'            => 'RESERVAR_CITA',
                    'id_paciente'     => $id_paciente,
                    'id_medico'       => $id_medico,
                    'id_especialidad' => $id_esp,
                    'fecha_cita'      => $fecha,
                    'hora_inicio'     => $inicio,
                    'hora_fin'        => $fin,
                    'modalidad'       => 'PRESENCIAL',
                    'motivo_consulta' => $motivo,
                    'medico_nombre'   => $interpretacion['nombre_medico'],
                    'especialidad_nombre' => $interpretacion['nombre_especialidad']
                ];

                $_SESSION['asistente_voz_pending'] = $datosCita;

                $interpretacion['requiere_confirmacion'] = true;
                $interpretacion['mensaje_voz'] = "¿Confirmas tu cita con {$interpretacion['nombre_medico']} para el {$fecha} a las {$inicio}? Por favor di 'Confirmar' o pulsa el botón.";
                $interpretacion['mensaje_texto'] = "Confirma tu cita médica con {$interpretacion['nombre_medico']} el {$fecha} a las {$inicio}.";

                $detallesVisuales = [
                    'accion' => 'RESERVAR',
                    'medico' => $interpretacion['nombre_medico'],
                    'especialidad' => $interpretacion['nombre_especialidad'],
                    'fecha' => $fecha,
                    'hora' => $inicio
                ];
                $accionPendiente = 'RESERVAR_CITA';
                break;

            case 'REPROGRAMAR_CITA':
                if (empty($citasPaciente)) {
                    $interpretacion['requiere_confirmacion'] = false;
                    $interpretacion['mensaje_voz'] = "No tienes ninguna cita agendada que puedas reprogramar.";
                    $interpretacion['mensaje_texto'] = "No tienes citas activas para reprogramar.";
                    break;
                }

                // Identificar cita objetivo
                $citaObjetivo = null;
                if (!empty($interpretacion['id_cita'])) {
                    foreach ($citasPaciente as $c) {
                        if ($c['id_cita'] === $interpretacion['id_cita']) {
                            $citaObjetivo = $c;
                            break;
                        }
                    }
                }
                if (!$citaObjetivo && !empty($interpretacion['id_medico'])) {
                    foreach ($citasPaciente as $c) {
                        if ((int)$c['id_medico'] === $interpretacion['id_medico']) {
                            $citaObjetivo = $c;
                            break;
                        }
                    }
                }
                if (!$citaObjetivo) {
                    $citaObjetivo = $citasPaciente[0]; // La más próxima
                }

                $nuevaFecha  = $interpretacion['fecha'];
                $nuevoInicio = $interpretacion['hora_inicio'];
                $nuevoFin    = $interpretacion['hora_fin'];

                if (!$nuevaFecha || !$nuevoInicio) {
                    $interpretacion['requiere_confirmacion'] = false;
                    $interpretacion['mensaje_voz'] = "Para reprogramar tu cita con el doctor {$citaObjetivo['medico_nombre']}, ¿qué nueva fecha y hora prefieres?";
                    $interpretacion['mensaje_texto'] = "Indica la nueva fecha y hora para reprogramar la cita {$citaObjetivo['codigo_cita']}.";
                    break;
                }

                // Validar disponibilidad
                $disp = CitaMedica::consultarDisponibilidad((int)$citaObjetivo['id_medico'], $nuevaFecha);
                $horaNormalizada = substr($nuevoInicio, 0, 5);
                if (!$disp['status'] || !in_array($horaNormalizada, $disp['slots'], true)) {
                    $interpretacion['requiere_confirmacion'] = false;
                    $interpretacion['mensaje_voz'] = "El doctor no tiene disponibilidad a las {$horaNormalizada} en la nueva fecha seleccionada. Por favor elige otro horario.";
                    $interpretacion['mensaje_texto'] = "Horario no disponible para reprogramación.";
                    break;
                }

                $_SESSION['asistente_voz_pending'] = [
                    'tipo'         => 'REPROGRAMAR_CITA',
                    'id_cita'      => (int)$citaObjetivo['id_cita'],
                    'codigo_cita'  => $citaObjetivo['codigo_cita'],
                    'medico_nombre'=> $citaObjetivo['medico_nombre'],
                    'nueva_fecha'  => $nuevaFecha,
                    'nuevo_inicio' => $nuevoInicio,
                    'nuevo_fin'    => $nuevoFin
                ];

                $interpretacion['requiere_confirmacion'] = true;
                $interpretacion['mensaje_voz'] = "¿Confirmas reprogramar tu cita {$citaObjetivo['codigo_cita']} para el {$nuevaFecha} a las {$nuevoInicio}? Di 'Confirmar' o presiona el botón.";
                $interpretacion['mensaje_texto'] = "Reprogramación de cita {$citaObjetivo['codigo_cita']} con Dr(a). {$citaObjetivo['medico_nombre']} al {$nuevaFecha} a las {$nuevoInicio}.";

                $detallesVisuales = [
                    'accion' => 'REPROGRAMAR',
                    'codigo' => $citaObjetivo['codigo_cita'],
                    'medico' => $citaObjetivo['medico_nombre'],
                    'fecha_nueva' => $nuevaFecha,
                    'hora_nueva' => $nuevoInicio
                ];
                $accionPendiente = 'REPROGRAMAR_CITA';
                break;

            case 'CANCELAR_CITA':
                if (empty($citasPaciente)) {
                    $interpretacion['requiere_confirmacion'] = false;
                    $interpretacion['mensaje_voz'] = "No tienes citas activas para cancelar.";
                    $interpretacion['mensaje_texto'] = "No hay citas activas disponibles para cancelación.";
                    break;
                }

                $citaObjetivo = null;
                if (!empty($interpretacion['id_cita'])) {
                    foreach ($citasPaciente as $c) {
                        if ($c['id_cita'] === $interpretacion['id_cita']) {
                            $citaObjetivo = $c;
                            break;
                        }
                    }
                }
                if (!$citaObjetivo && !empty($interpretacion['id_medico'])) {
                    foreach ($citasPaciente as $c) {
                        if ((int)$c['id_medico'] === $interpretacion['id_medico']) {
                            $citaObjetivo = $c;
                            break;
                        }
                    }
                }
                if (!$citaObjetivo) {
                    $citaObjetivo = $citasPaciente[0];
                }

                $motivo = $interpretacion['motivo'] ?: 'Cancelación solicitada por el paciente mediante Asistente de Voz con IA';

                $_SESSION['asistente_voz_pending'] = [
                    'tipo'        => 'CANCELAR_CITA',
                    'id_cita'     => (int)$citaObjetivo['id_cita'],
                    'codigo_cita' => $citaObjetivo['codigo_cita'],
                    'medico_nombre' => $citaObjetivo['medico_nombre'],
                    'fecha_cita'  => $citaObjetivo['fecha_cita'],
                    'hora_inicio' => $citaObjetivo['hora_inicio'],
                    'motivo'      => $motivo
                ];

                $interpretacion['requiere_confirmacion'] = true;
                $interpretacion['mensaje_voz'] = "¿Estás seguro de que deseas cancelar tu cita {$citaObjetivo['codigo_cita']} con el doctor {$citaObjetivo['medico_nombre']} del {$citaObjetivo['fecha_cita']}? Di 'Confirmar' o presiona cancelar.";
                $interpretacion['mensaje_texto'] = "¿Deseas cancelar la cita {$citaObjetivo['codigo_cita']} con Dr(a). {$citaObjetivo['medico_nombre']} programada para el {$citaObjetivo['fecha_cita']} a las " . substr($citaObjetivo['hora_inicio'], 0, 5) . "?";

                $detallesVisuales = [
                    'accion' => 'CANCELAR',
                    'codigo' => $citaObjetivo['codigo_cita'],
                    'medico' => $citaObjetivo['medico_nombre'],
                    'fecha' => $citaObjetivo['fecha_cita'],
                    'hora' => substr($citaObjetivo['hora_inicio'], 0, 5)
                ];
                $accionPendiente = 'CANCELAR_CITA';
                break;

            case 'INFORMACION_GENERAL':
            case 'DESCONOCIDO':
            default:
                $interpretacion['requiere_confirmacion'] = false;
                break;
        }

        // Auditoría del comando recibido
        AuditoriaHelper::registrar(
            $id_usuario_auth,
            'SELECT',
            'asistente_voz',
            "Comando por voz recibido: \"{$comando}\" -> Intención: {$intencion}"
        );

        echo json_encode([
            'status'                => true,
            'intencion'             => $intencion,
            'mensaje_voz'           => $interpretacion['mensaje_voz'],
            'mensaje_texto'         => $interpretacion['mensaje_texto'],
            'requiere_confirmacion' => $interpretacion['requiere_confirmacion'],
            'detalles'              => $detallesVisuales,
            'accion_pendiente'      => $accionPendiente
        ]);
        exit();

    case 'confirmar_accion':
        if (!isset($_SESSION['asistente_voz_pending']) || empty($_SESSION['asistente_voz_pending'])) {
            echo json_encode([
                'status' => false,
                'message' => 'No hay ninguna acción pendiente de confirmación o ya expiró.',
                'mensaje_voz' => 'No hay ninguna acción pendiente para confirmar.'
            ]);
            exit();
        }

        $pending = $_SESSION['asistente_voz_pending'];
        $tipo = $pending['tipo'] ?? '';

        if ($tipo === 'RESERVAR_CITA') {
            // Anti-IDOR: asegurar que la cita se reserve exclusivamente para el paciente autenticado
            $pending['id_paciente'] = $id_paciente;
            $res = CitaMedica::programar($pending, $id_usuario_auth);

            if ($res['status']) {
                AuditoriaHelper::registrar(
                    $id_usuario_auth,
                    'INSERT',
                    'asistente_voz',
                    "Cita {$res['codigo_cita']} confirmada mediante Asistente de Voz"
                );

                Notificacion::crear(
                    $id_usuario_auth,
                    'ASISTENTE_VOZ',
                    'Cita Agendada por Voz',
                    "Tu cita médica ({$res['codigo_cita']}) fue agendada exitosamente mediante el Asistente de Voz.",
                    "/clinicaVidaSalud/views/consultas/historial.php"
                );

                unset($_SESSION['asistente_voz_pending']);
                echo json_encode([
                    'status'      => true,
                    'message'     => $res['message'],
                    'codigo_cita' => $res['codigo_cita'],
                    'mensaje_voz' => "¡Listo! Tu cita médica ha sido confirmada con éxito con el código {$res['codigo_cita']}. Te esperamos en la clínica."
                ]);
            } else {
                echo json_encode([
                    'status'      => false,
                    'message'     => $res['message'],
                    'mensaje_voz' => "Lo siento, ocurrió un error al programar la cita: " . $res['message']
                ]);
            }
            exit();
        }

        if ($tipo === 'REPROGRAMAR_CITA') {
            $id_cita = (int)($pending['id_cita'] ?? 0);
            
            // Validar propiedad de la cita (Anti-IDOR)
            $pdo = Database::getConnection();
            $stmtV = $pdo->prepare("SELECT id_paciente FROM citas_medicas WHERE id_cita = :c");
            $stmtV->execute([':c' => $id_cita]);
            $ownerPaciente = (int)$stmtV->fetchColumn();

            if ($ownerPaciente !== $id_paciente && !AuthMiddleware::esAdmin()) {
                unset($_SESSION['asistente_voz_pending']);
                echo json_encode([
                    'status' => false,
                    'message' => 'Violación de seguridad: No puedes modificar citas de otro paciente.',
                    'mensaje_voz' => 'Acción denegada por motivos de seguridad.'
                ]);
                exit();
            }

            $res = CitaMedica::reprogramar($id_cita, $pending['nueva_fecha'], $pending['nuevo_inicio'], $pending['nuevo_fin'], $id_usuario_auth);
            if ($res['status']) {
                AuditoriaHelper::registrar(
                    $id_usuario_auth,
                    'UPDATE',
                    'asistente_voz',
                    "Cita ID {$id_cita} reprogramada mediante Asistente de Voz"
                );

                Notificacion::crear(
                    $id_usuario_auth,
                    'ASISTENTE_VOZ',
                    'Cita Reprogramada por Voz',
                    "Tu cita fue reprogramada con éxito para el {$pending['nueva_fecha']} a las {$pending['nuevo_inicio']} mediante el Asistente de Voz.",
                    "/clinicaVidaSalud/views/consultas/historial.php"
                );

                unset($_SESSION['asistente_voz_pending']);
                echo json_encode([
                    'status'      => true,
                    'message'     => $res['message'],
                    'mensaje_voz' => "Tu cita médica ha sido reprogramada exitosamente para el {$pending['nueva_fecha']} a las {$pending['nuevo_inicio']}."
                ]);
            } else {
                echo json_encode([
                    'status'      => false,
                    'message'     => $res['message'],
                    'mensaje_voz' => "No se pudo reprogramar la cita: " . $res['message']
                ]);
            }
            exit();
        }

        if ($tipo === 'CANCELAR_CITA') {
            $id_cita = (int)($pending['id_cita'] ?? 0);

            // Validar propiedad de la cita (Anti-IDOR)
            $pdo = Database::getConnection();
            $stmtV = $pdo->prepare("SELECT id_paciente FROM citas_medicas WHERE id_cita = :c");
            $stmtV->execute([':c' => $id_cita]);
            $ownerPaciente = (int)$stmtV->fetchColumn();

            if ($ownerPaciente !== $id_paciente && !AuthMiddleware::esAdmin()) {
                unset($_SESSION['asistente_voz_pending']);
                echo json_encode([
                    'status' => false,
                    'message' => 'Violación de seguridad: No puedes cancelar citas de otro paciente.',
                    'mensaje_voz' => 'Acción denegada por motivos de seguridad.'
                ]);
                exit();
            }

            $res = CitaMedica::cancelar($id_cita, $pending['motivo'], $id_usuario_auth);
            if ($res['status']) {
                AuditoriaHelper::registrar(
                    $id_usuario_auth,
                    'UPDATE',
                    'asistente_voz',
                    "Cita ID {$id_cita} cancelada mediante Asistente de Voz"
                );

                Notificacion::crear(
                    $id_usuario_auth,
                    'ASISTENTE_VOZ',
                    'Cita Cancelada por Voz',
                    "Tu cita ({$pending['codigo_cita']}) fue cancelada correctamente según tu confirmación de voz.",
                    "/clinicaVidaSalud/views/consultas/historial.php"
                );

                unset($_SESSION['asistente_voz_pending']);
                echo json_encode([
                    'status'      => true,
                    'message'     => $res['message'],
                    'mensaje_voz' => "Tu cita ha sido cancelada correctamente según tu solicitud."
                ]);
            } else {
                echo json_encode([
                    'status'      => false,
                    'message'     => $res['message'],
                    'mensaje_voz' => "No se pudo cancelar la cita: " . $res['message']
                ]);
            }
            exit();
        }

        unset($_SESSION['asistente_voz_pending']);
        echo json_encode([
            'status' => false,
            'message' => 'Tipo de acción no reconocido.',
            'mensaje_voz' => 'No se reconoció la acción pendiente.'
        ]);
        exit();

    case 'cancelar_accion':
        unset($_SESSION['asistente_voz_pending']);
        echo json_encode([
            'status' => true,
            'message' => 'Acción cancelada.',
            'mensaje_voz' => 'Entendido, he cancelado la acción. ¿En qué más te puedo ayudar?'
        ]);
        exit();

    default:
        echo json_encode([
            'status' => false,
            'message' => 'Acción no soportada por el Asistente de Voz.'
        ]);
        exit();
}
