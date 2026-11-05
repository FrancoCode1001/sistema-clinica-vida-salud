-- Limpieza previa para ejecución idempotente
DROP TRIGGER IF EXISTS trg_bitacora_citas ON citas_medicas;
DROP TRIGGER IF EXISTS trg_bitacora_medicos ON medicos;
DROP TRIGGER IF EXISTS trg_bitacora_pacientes ON pacientes;
DROP TRIGGER IF EXISTS trg_validar_solapamiento_turno_medico ON horarios_atencion;
DROP TRIGGER IF EXISTS trg_crear_perfil_medico_defecto ON pacientes;
DROP TRIGGER IF EXISTS trg_normalizar_especialidad ON especialidades;
DROP TRIGGER IF EXISTS trg_impedir_borrado_fisico_cita ON citas_medicas;
DROP TRIGGER IF EXISTS trg_proteger_historial_medico ON medicos;
DROP TRIGGER IF EXISTS trg_proteger_historial_paciente ON pacientes;
DROP TRIGGER IF EXISTS trg_auditar_cambio_estado_cita ON citas_medicas;
DROP TRIGGER IF EXISTS trg_validar_transicion_estado_cita ON citas_medicas;
DROP TRIGGER IF EXISTS trg_evitar_citas_superpuestas ON citas_medicas;
DROP TRIGGER IF EXISTS trg_validar_disponibilidad_cita ON citas_medicas;

DROP TABLE IF EXISTS bitacoras CASCADE;
DROP TABLE IF EXISTS historial_estados_cita CASCADE;
DROP TABLE IF EXISTS citas_medicas CASCADE;
DROP TABLE IF EXISTS horarios_atencion CASCADE;
DROP TABLE IF EXISTS medico_especialidad CASCADE;
DROP TABLE IF EXISTS medicos CASCADE;
DROP TABLE IF EXISTS especialidades CASCADE;
DROP TABLE IF EXISTS perfiles_medicos CASCADE;
DROP TABLE IF EXISTS pacientes CASCADE;
DROP TABLE IF EXISTS usuarios CASCADE;
DROP TABLE IF EXISTS roles CASCADE;

-- ============================================================================
-- 1. CREACIÓN DE LAS 11 TABLAS RELACIONALES (Sección 6.2.2)
-- ============================================================================

-- 1.1 Tabla: roles
CREATE TABLE roles (
    id_rol SERIAL PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(150),
    estado VARCHAR(15) NOT NULL DEFAULT 'ACTIVO'
);

-- 1.2 Tabla: usuarios
CREATE TABLE usuarios (
    id_usuario SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    id_rol INT NOT NULL,
    estado VARCHAR(15) NOT NULL DEFAULT 'ACTIVO',
    notificaciones_activas BOOLEAN NOT NULL DEFAULT TRUE,
    privacidad_contacto_emergencia BOOLEAN NOT NULL DEFAULT TRUE,
    privacidad_recordatorios BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
);

-- 1.3 Tabla: pacientes (RF02 / RN01)
CREATE TABLE pacientes (
    id_paciente SERIAL PRIMARY KEY,
    codigo_paciente VARCHAR(20) NOT NULL UNIQUE,
    ci VARCHAR(20) NOT NULL UNIQUE,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    sexo CHAR(1) NOT NULL CHECK (sexo IN ('M', 'F')),
    telefono VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    direccion VARCHAR(200),
    estado VARCHAR(15) NOT NULL DEFAULT 'ACTIVO',
    id_usuario INT UNIQUE,
    CONSTRAINT fk_paciente_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

-- 1.4 Tabla: perfiles_medicos (Sección 3.4)
CREATE TABLE perfiles_medicos (
    id_perfil SERIAL PRIMARY KEY,
    id_paciente INT NOT NULL UNIQUE,
    tipo_sangre VARCHAR(5),
    alergias TEXT DEFAULT 'Ninguna',
    enfermedades_cronicas TEXT DEFAULT 'Ninguna',
    medicamentos TEXT DEFAULT 'Ninguno',
    antecedentes TEXT DEFAULT 'Sin antecedentes relevantes',
    emergencia_nombre VARCHAR(120),
    emergencia_parentesco VARCHAR(50),
    emergencia_telefono VARCHAR(20),
    CONSTRAINT fk_perfil_paciente FOREIGN KEY (id_paciente) REFERENCES pacientes(id_paciente)
);

-- 1.5 Tabla: especialidades (RF04)
CREATE TABLE especialidades (
    id_especialidad SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    estado VARCHAR(15) NOT NULL DEFAULT 'ACTIVO'
);

-- 1.6 Tabla: medicos (RF03 / RN02)
CREATE TABLE medicos (
    id_medico SERIAL PRIMARY KEY,
    codigo_medico VARCHAR(20) NOT NULL UNIQUE,
    nro_registro_profesional VARCHAR(50) NOT NULL UNIQUE,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    estado VARCHAR(15) NOT NULL DEFAULT 'ACTIVO',
    id_usuario INT UNIQUE,
    CONSTRAINT fk_medico_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

-- 1.7 Tabla: medico_especialidad (Intermedia N:M)
CREATE TABLE medico_especialidad (
    id_medico INT NOT NULL,
    id_especialidad INT NOT NULL,
    fecha_asignacion DATE NOT NULL DEFAULT CURRENT_DATE,
    PRIMARY KEY (id_medico, id_especialidad),
    CONSTRAINT fk_me_medico FOREIGN KEY (id_medico) REFERENCES medicos(id_medico),
    CONSTRAINT fk_me_especialidad FOREIGN KEY (id_especialidad) REFERENCES especialidades(id_especialidad)
);

-- 1.8 Tabla: horarios_atencion (RF05 / RN08)
CREATE TABLE horarios_atencion (
    id_horario SERIAL PRIMARY KEY,
    id_medico INT NOT NULL,
    dia_semana SMALLINT NOT NULL CHECK (dia_semana BETWEEN 1 AND 6), -- 1=Lunes .. 6=Sábado
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    duracion_minutos INT NOT NULL DEFAULT 30 CHECK (duracion_minutos > 0),
    estado VARCHAR(15) NOT NULL DEFAULT 'ACTIVO',
    CONSTRAINT chk_rango_horario CHECK (hora_fin > hora_inicio),
    CONSTRAINT fk_horario_medico FOREIGN KEY (id_medico) REFERENCES medicos(id_medico)
);

-- 1.9 Tabla: citas_medicas (RF06, RF07, RF08, RF09, RF10)
CREATE TABLE citas_medicas (
    id_cita SERIAL PRIMARY KEY,
    codigo_cita VARCHAR(20) NOT NULL UNIQUE,
    id_paciente INT NOT NULL,
    id_medico INT NOT NULL,
    id_especialidad INT NOT NULL,
    fecha_cita DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    modalidad VARCHAR(20) NOT NULL DEFAULT 'PRESENCIAL',
    motivo_consulta TEXT,
    motivo_cancelacion TEXT,
    estado VARCHAR(20) NOT NULL DEFAULT 'PROGRAMADA'
        CHECK (estado IN ('PROGRAMADA', 'CONFIRMADA', 'ATENDIDA', 'CANCELADA', 'INASISTENCIA')),
    fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_horas_cita CHECK (hora_fin > hora_inicio),
    CONSTRAINT fk_cita_paciente FOREIGN KEY (id_paciente) REFERENCES pacientes(id_paciente),
    CONSTRAINT fk_cita_medico FOREIGN KEY (id_medico) REFERENCES medicos(id_medico),
    CONSTRAINT fk_cita_especialidad FOREIGN KEY (id_especialidad) REFERENCES especialidades(id_especialidad)
);

-- Índice Único Parcial para cumplir RF07 y RN09:
-- Impide que un médico tenga dos citas en la misma fecha y hora_inicio,
-- EXCEPTO cuando la cita está 'CANCELADA' (liberando el horario).
CREATE UNIQUE INDEX idx_unico_cita_medico_horario
ON citas_medicas (id_medico, fecha_cita, hora_inicio)
WHERE estado <> 'CANCELADA';

-- 1.10 Tabla: historial_estados_cita (Trazabilidad RF10 / RF12)
CREATE TABLE historial_estados_cita (
    id_historial SERIAL PRIMARY KEY,
    id_cita INT NOT NULL,
    estado_anterior VARCHAR(20),
    estado_nuevo VARCHAR(20) NOT NULL,
    observacion TEXT,
    fecha_cambio TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_usuario INT,
    CONSTRAINT fk_hist_cita FOREIGN KEY (id_cita) REFERENCES citas_medicas(id_cita),
    CONSTRAINT fk_hist_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

-- 1.11 Tabla: bitacoras (Auditoría General del Sistema CU-15)
CREATE TABLE bitacoras (
    id_bitacora SERIAL PRIMARY KEY,
    id_usuario INT NULL,
    accion VARCHAR(30) NOT NULL, -- INSERT, UPDATE, DELETE, LOGIN, LOGOUT
    modulo VARCHAR(50) NOT NULL, -- pacientes, medicos, citas_medicas, usuarios, etc.
    descripcion TEXT NOT NULL,
    ip_acceso VARCHAR(45) DEFAULT '127.0.0.1',
    fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bitacora_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

-- 1.12 Tabla: notificaciones (Sistema de Notificaciones Internas)
CREATE TABLE notificaciones (
    id_notificacion SERIAL PRIMARY KEY,
    id_usuario INT NOT NULL,
    tipo VARCHAR(50) NOT NULL, -- CITA_NUEVA, CITA_CONFIRMADA, CITA_REPROGRAMADA, CITA_CANCELADA, CITA_RECORDATORIO_MEDICO, SISTEMA
    titulo VARCHAR(150) NOT NULL,
    mensaje TEXT NOT NULL,
    enlace VARCHAR(255) NULL,
    leida BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
);

CREATE INDEX idx_notif_usuario_leida ON notificaciones (id_usuario, leida);
CREATE INDEX idx_notif_fecha ON notificaciones (fecha_creacion DESC);

-- ============================================================================
-- 2. POBLACIÓN DE DATOS SEMILLA (INSERT - Páginas 94-98)
-- ============================================================================

-- 2.1 roles
INSERT INTO roles (id_rol, nombre_rol, descripcion, estado) VALUES
(1, 'Administrador', 'Control total del sistema y configuración clínica', 'ACTIVO'),
(2, 'Recepcionista', 'Admisión de pacientes y programación de citas', 'ACTIVO'),
(3, 'Medico', 'Atención de consultas y visualización de agenda', 'ACTIVO'),
(4, 'Paciente', 'Autogestión de perfil y visualización de citas', 'ACTIVO'),
(5, 'Auditor', 'Rol de supervisión y lectura de reportes gerenciales', 'INACTIVO');

-- 2.2 usuarios (password default: 'password' / hash bcrypt estándar compatible)
INSERT INTO usuarios (id_usuario, username, email, password_hash, id_rol, estado) VALUES
(1, 'admin', 'admin@vidasalud.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'ACTIVO'),
(2, 'recepcion1', 'recepcion@vidasalud.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'ACTIVO'),
(3, 'dr.perez', 'juan.perez@vidasalud.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'ACTIVO'),
(4, 'dra.gomez', 'carla.gomez@vidasalud.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'ACTIVO'),
(5, 'maria.lopez', 'maria.lopez@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'ACTIVO');

-- 2.3 pacientes
INSERT INTO pacientes (id_paciente, codigo_paciente, ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, email, direccion, estado, id_usuario) VALUES
(1, 'PAC-0001', '8451230', 'María', 'López Ríos', '1992-05-14', 'F', '70011999', 'maria.lopez@gmail.com', 'Av. Beni #250 Cond. Norte', 'ACTIVO', 5),
(2, 'PAC-0002', '6320145', 'Roberto', 'Méndez Roca', '1985-11-20', 'M', '71022334', 'rmendez.actualizado@gmail.com', 'Calle Libertad #112', 'ACTIVO', NULL),
(3, 'PAC-0003', '9541023', 'Lucía', 'Vargas Soliz', '2018-03-08', 'F', '72188888', 'contacto.vargas@gmail.com', 'Barrio Equipetrol Calle 4', 'ACTIVO', NULL),
(4, 'PAC-0004', '5214789', 'Fernando', 'Suárez Justiniano', '1978-08-30', 'M', '75044556', 'fsuarez@yahoo.com', 'Av. Banzer Km 5', 'ACTIVO', NULL),
(5, 'PAC-0005', '7412580', 'Patricia', 'Cuéllar Paz', '1996-01-25', 'F', '76055667', 'patricia.cuellar@gmail.com', 'Av. Roca y Coronado #55', 'INACTIVO', NULL);

-- 2.4 perfiles_medicos
INSERT INTO perfiles_medicos (id_perfil, id_paciente, tipo_sangre, alergias, enfermedades_cronicas, medicamentos, antecedentes, emergencia_nombre, emergencia_parentesco, emergencia_telefono) VALUES
(1, 1, 'O+', 'Penicilina y Sulfas', 'Ninguna', 'Ninguno', 'Apendicectomía en 2019', 'Pedro López', 'Padre', '70099881'),
(2, 2, 'A+', 'Ninguna', 'Hipertensión arterial', 'Losartán 50mg cada 12h', 'Control cardiológico anual', 'Elena Roca', 'Esposa', '71099882'),
(3, 3, 'O-', 'Polen y polvo', 'Asma leve', 'Salbutamol inhalador', 'Paciente pediátrico', 'Claudia Soliz', 'Madre', '72100000'),
(4, 4, 'B+', 'Ibuprofeno (AINEs)', 'Diabetes Tipo 2', 'Metformina 850mg', 'Control endocrinológico semestral', 'Rosa Justiniano', 'Hermana', '75099884'),
(5, 5, 'O+', 'Ninguna', 'Ninguna', 'Ácido fólico', 'Control ginecológico', 'Marco Paz', 'Esposo', '76099885');

-- 2.5 especialidades
INSERT INTO especialidades (id_especialidad, nombre, descripcion, estado) VALUES
(1, 'Medicina General', 'Medicina general e interna de primer nivel', 'ACTIVO'),
(2, 'Pediatría', 'Especialidad pediátrica y neonatología básica', 'ACTIVO'),
(3, 'Ginecología', 'Ginecología y obstetricia integral', 'ACTIVO'),
(4, 'Odontología', 'Odontología general y rehabilitación oral', 'ACTIVO'),
(5, 'Cardiología', 'Diagnóstico y tratamiento de enfermedades cardiovasculares', 'ACTIVO');

-- 2.6 medicos
INSERT INTO medicos (id_medico, codigo_medico, nro_registro_profesional, nombres, apellidos, telefono, email, estado, id_usuario) VALUES
(1, 'MED-001', 'MP-10452', 'Juan', 'Pérez Salvatierra', '70910001', 'juan.perez@vidasalud.com', 'ACTIVO', 3),
(2, 'MED-002', 'MP-20891', 'Carla', 'Gómez Arancibia', '70920002', 'carla.gomez@vidasalud.com', 'ACTIVO', 4),
(3, 'MED-003', 'MP-30547', 'Andrés', 'Villarroel Gutiérrez', '70933333', 'andres.villarroel@vidasalud.com', 'ACTIVO', NULL),
(4, 'MED-004', 'MP-40129', 'Valeria', 'Ortiz Monasterio', '70944444', 'dra.ortiz@vidasalud.com', 'ACTIVO', NULL),
(5, 'MED-005', 'MP-50983', 'Ricardo', 'Banegas Dorado', '70955555', 'ricardo.banegas@vidasalud.com', 'INACTIVO', NULL);

-- 2.7 medico_especialidad
INSERT INTO medico_especialidad (id_medico, id_especialidad, fecha_asignacion) VALUES
(1, 1, '2026-01-12'), -- Dr. Juan Pérez -> Medicina General
(2, 2, '2026-01-14'), -- Dra. Carla Gómez -> Pediatría
(3, 3, '2026-01-18'), -- Dr. Andrés Villarroel -> Ginecología
(4, 4, '2026-02-02'), -- Dra. Valeria Ortiz -> Odontología
(5, 5, '2026-02-10'); -- Dr. Ricardo Banegas -> Cardiología

-- 2.8 horarios_atencion
INSERT INTO horarios_atencion (id_horario, id_medico, dia_semana, hora_inicio, hora_fin, duracion_minutos, estado) VALUES
(1, 1, 1, '08:00', '12:30', 30, 'ACTIVO'), -- Médico 1: Lunes 08:00 a 12:30
(2, 1, 3, '14:30', '18:30', 30, 'ACTIVO'), -- Médico 1: Miércoles 14:30 a 18:30
(3, 2, 2, '09:00', '13:00', 30, 'ACTIVO'), -- Médico 2: Martes 09:00 a 13:00
(4, 3, 4, '08:30', '13:00', 30, 'ACTIVO'), -- Médico 3: Jueves 08:30 a 13:00
(5, 4, 5, '15:00', '19:00', 30, 'ACTIVO'); -- Médico 4: Viernes 15:00 a 19:00

-- 2.9 citas_medicas
INSERT INTO citas_medicas (id_cita, codigo_cita, id_paciente, id_medico, id_especialidad, fecha_cita, hora_inicio, hora_fin, modalidad, motivo_consulta, motivo_cancelacion, estado) VALUES
(1, 'CM-0001', 1, 1, 1, '2026-11-16', '09:00', '09:30', 'PRESENCIAL', 'Consulta general y control anual', NULL, 'CONFIRMADA'),
(2, 'CM-0002', 3, 2, 2, '2026-11-24', '10:00', '10:30', 'PRESENCIAL', 'Control pediátrico de rutina', NULL, 'CONFIRMADA'),
(3, 'CM-0003', 5, 3, 3, '2026-11-12', '10:00', '10:30', 'PRESENCIAL', 'Control prenatal y revisión de ecografía', NULL, 'ATENDIDA'),
(4, 'CM-0004', 2, 4, 4, '2026-11-13', '15:30', '16:00', 'PRESENCIAL', 'Limpieza dental y revisión', 'Reprogramará la próxima semana por viaje', 'CANCELADA'),
(5, 'CM-0005', 4, 1, 1, '2026-11-09', '08:30', '09:00', 'PRESENCIAL', 'Renovación de receta médica', NULL, 'INASISTENCIA');

-- 2.10 historial_estados_cita
INSERT INTO historial_estados_cita (id_historial, id_cita, estado_anterior, estado_nuevo, observacion, id_usuario) VALUES
(1, 1, NULL, 'PROGRAMADA', 'Reserva inicial validada sin conflictos', 2),
(2, 2, 'PROGRAMADA', 'CONFIRMADA', 'Confirmación telefónica por recepción', 2),
(3, 3, 'CONFIRMADA', 'ATENDIDA', 'Paciente atendida puntualmente', 3),
(4, 4, 'PROGRAMADA', 'CANCELADA', 'Horario liberado automáticamente', 2),
(5, 5, 'CONFIRMADA', 'INASISTENCIA', 'Inasistencia registrada al cierre del turno', 2);

-- 2.11 bitacoras
INSERT INTO bitacoras (id_bitacora, id_usuario, accion, modulo, descripcion, ip_acceso) VALUES
(1, 1, 'LOGIN', 'Autenticación', 'Inicio de sesión exitoso del usuario admin', '10.0.0.1'),
(2, 1, 'INSERT', 'medicos', 'Alta de médico titular MED-001', '192.168.1.10'),
(3, 2, 'INSERT', 'pacientes', 'Alta de paciente PAC-0001 con perfil médico', '192.168.1.15'),
(4, 2, 'INSERT', 'Programación de Citas', 'Programación de cita médica CM-0001 para fecha 2026-11-16 09:00', '192.168.1.15'),
(5, 2, 'UPDATE', 'Cancelación de Citas', 'Cancelación lógica de cita CM-0004. Motivo: Viaje imprevisto', '192.168.1.15');

-- Actualizar secuencias de las llaves primarias
SELECT setval('roles_id_rol_seq', COALESCE((SELECT MAX(id_rol) FROM roles), 1));
SELECT setval('usuarios_id_usuario_seq', COALESCE((SELECT MAX(id_usuario) FROM usuarios), 1));
SELECT setval('pacientes_id_paciente_seq', COALESCE((SELECT MAX(id_paciente) FROM pacientes), 1));
SELECT setval('perfiles_medicos_id_perfil_seq', COALESCE((SELECT MAX(id_perfil) FROM perfiles_medicos), 1));
SELECT setval('especialidades_id_especialidad_seq', COALESCE((SELECT MAX(id_especialidad) FROM especialidades), 1));
SELECT setval('medicos_id_medico_seq', COALESCE((SELECT MAX(id_medico) FROM medicos), 1));
SELECT setval('horarios_atencion_id_horario_seq', COALESCE((SELECT MAX(id_horario) FROM horarios_atencion), 1));
SELECT setval('citas_medicas_id_cita_seq', COALESCE((SELECT MAX(id_cita) FROM citas_medicas), 1));
SELECT setval('historial_estados_cita_id_historial_seq', COALESCE((SELECT MAX(id_historial) FROM historial_estados_cita), 1));
SELECT setval('bitacoras_id_bitacora_seq', COALESCE((SELECT MAX(id_bitacora) FROM bitacoras), 1));

-- ============================================================================
-- 3. PROCEDIMIENTOS ALMACENADOS (Páginas 112-118)
-- ============================================================================

-- 3.1 sp_registrar_paciente (RF02 / RN01)
CREATE OR REPLACE PROCEDURE sp_registrar_paciente(
    p_codigo VARCHAR, p_ci VARCHAR, p_nombres VARCHAR, p_apellidos VARCHAR,
    p_fecha_nac DATE, p_sexo CHAR, p_telefono VARCHAR, p_email VARCHAR, p_direccion VARCHAR
)
LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM pacientes WHERE ci = p_ci) THEN
        RAISE EXCEPTION 'RN01: Ya existe un paciente registrado con el CI %', p_ci;
    END IF;
    INSERT INTO pacientes (codigo_paciente, ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, email, direccion, estado)
    VALUES (p_codigo, p_ci, p_nombres, p_apellidos, p_fecha_nac, p_sexo, p_telefono, p_email, p_direccion, 'ACTIVO');
    RAISE NOTICE 'Paciente % % registrado correctamente.', p_nombres, p_apellidos;
END;
$$;

-- 3.2 sp_registrar_especialidad (RF04)
CREATE OR REPLACE PROCEDURE sp_registrar_especialidad(p_nombre VARCHAR, p_descripcion TEXT)
LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM especialidades WHERE UPPER(TRIM(nombre)) = UPPER(TRIM(p_nombre))) THEN
        RAISE EXCEPTION 'RF04: Ya existe la especialidad con el nombre normalizado %', p_nombre;
    END IF;
    INSERT INTO especialidades (nombre, descripcion, estado)
    VALUES (TRIM(p_nombre), p_descripcion, 'ACTIVO');
    RAISE NOTICE 'Especialidad % creada exitosamente.', p_nombre;
END;
$$;

-- 3.3 sp_registrar_medico (RF03 / RN02)
CREATE OR REPLACE PROCEDURE sp_registrar_medico(
    p_codigo VARCHAR, p_matricula VARCHAR, p_nombres VARCHAR, p_apellidos VARCHAR,
    p_telefono VARCHAR, p_email VARCHAR, p_id_especialidad INT
)
LANGUAGE plpgsql AS $$
DECLARE
    v_id_medico INT;
BEGIN
    INSERT INTO medicos (codigo_medico, nro_registro_profesional, nombres, apellidos, telefono, email, estado)
    VALUES (p_codigo, p_matricula, p_nombres, p_apellidos, p_telefono, p_email, 'ACTIVO')
    RETURNING id_medico INTO v_id_medico;

    INSERT INTO medico_especialidad (id_medico, id_especialidad, fecha_asignacion)
    VALUES (v_id_medico, p_id_especialidad, CURRENT_DATE);

    RAISE NOTICE 'Médico % registrado con ID %', p_codigo, v_id_medico;
END;
$$;

-- 3.4 sp_registrar_horario_medico (RF05 / RN08)
CREATE OR REPLACE PROCEDURE sp_registrar_horario_medico(
    p_id_medico INT, p_dia SMALLINT, p_inicio TIME, p_fin TIME, p_duracion INT
)
LANGUAGE plpgsql AS $$
BEGIN
    IF p_fin <= p_inicio THEN
        RAISE EXCEPTION 'RF05: La hora final (%) debe ser posterior a la hora inicial (%).', p_fin, p_inicio;
    END IF;
    INSERT INTO horarios_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_minutos, estado)
    VALUES (p_id_medico, p_dia, p_inicio, p_fin, p_duracion, 'ACTIVO');
    RAISE NOTICE 'Horario registrado correctamente para el médico %.', p_id_medico;
END;
$$;

-- 3.5 sp_programar_cita (RF06 / RF07 / RN04 / RN05 / RN06 / RN07)
CREATE OR REPLACE PROCEDURE sp_programar_cita(
    p_codigo VARCHAR, p_id_paciente INT, p_id_medico INT, p_id_especialidad INT,
    p_fecha DATE, p_inicio TIME, p_fin TIME, p_motivo TEXT, p_id_usuario INT
)
LANGUAGE plpgsql AS $$
DECLARE
    v_id_cita INT;
BEGIN
    -- Validar fecha no pasada (RN06)
    IF p_fecha < CURRENT_DATE THEN
        RAISE EXCEPTION 'RN06: No se permiten registrar citas en fechas pasadas (%).', p_fecha;
    END IF;

    -- Validar médico activo (RN04)
    IF NOT EXISTS (SELECT 1 FROM medicos WHERE id_medico = p_id_medico AND estado = 'ACTIVO') THEN
        RAISE EXCEPTION 'RN04: El médico seleccionado no se encuentra activo.';
    END IF;

    -- Validar superposición de horarios (RF07 / RN07 / RN09)
    IF EXISTS (
        SELECT 1 FROM citas_medicas
        WHERE id_medico = p_id_medico
          AND fecha_cita = p_fecha
          AND estado <> 'CANCELADA'
          AND (p_inicio < hora_fin AND p_fin > hora_inicio)
    ) THEN
        RAISE EXCEPTION 'RF07: El médico ya tiene una cita asignada en ese bloque horario.';
    END IF;

    INSERT INTO citas_medicas (codigo_cita, id_paciente, id_medico, id_especialidad, fecha_cita, hora_inicio, hora_fin, motivo_consulta, estado)
    VALUES (p_codigo, p_id_paciente, p_id_medico, p_id_especialidad, p_fecha, p_inicio, p_fin, p_motivo, 'PROGRAMADA')
    RETURNING id_cita INTO v_id_cita;

    INSERT INTO historial_estados_cita (id_cita, estado_anterior, estado_nuevo, observacion, id_usuario)
    VALUES (v_id_cita, NULL, 'PROGRAMADA', 'Cita registrada exitosamente', p_id_usuario);

    RAISE NOTICE 'Cita % programada con éxito.', p_codigo;
END;
$$;

-- 3.6 sp_reprogramar_cita (RF08)
CREATE OR REPLACE PROCEDURE sp_reprogramar_cita(
    p_id_cita INT, p_nueva_fecha DATE, p_nuevo_inicio TIME, p_nuevo_fin TIME, p_id_usuario INT
)
LANGUAGE plpgsql AS $$
DECLARE
    v_estado_actual VARCHAR;
    v_id_medico INT;
BEGIN
    SELECT estado, id_medico INTO v_estado_actual, v_id_medico
    FROM citas_medicas WHERE id_cita = p_id_cita;

    IF v_estado_actual IN ('ATENDIDA', 'CANCELADA') THEN
        RAISE EXCEPTION 'RF08: No se puede reprogramar una cita en estado %.', v_estado_actual;
    END IF;

    IF EXISTS (
        SELECT 1 FROM citas_medicas
        WHERE id_medico = v_id_medico
          AND fecha_cita = p_nueva_fecha
          AND id_cita <> p_id_cita
          AND estado <> 'CANCELADA'
          AND (p_nuevo_inicio < hora_fin AND p_nuevo_fin > hora_inicio)
    ) THEN
        RAISE EXCEPTION 'RF08: Conflicto detectado en el nuevo horario seleccionado.';
    END IF;

    UPDATE citas_medicas
    SET fecha_cita = p_nueva_fecha, hora_inicio = p_nuevo_inicio, hora_fin = p_nuevo_fin
    WHERE id_cita = p_id_cita;

    INSERT INTO historial_estados_cita (id_cita, estado_anterior, estado_nuevo, observacion, id_usuario)
    VALUES (p_id_cita, v_estado_actual, v_estado_actual, 'Cita reprogramada a nueva fecha/hora', p_id_usuario);

    RAISE NOTICE 'Cita ID % reprogramada exitosamente.', p_id_cita;
END;
$$;

-- 3.7 sp_cancelar_cita (RF09 / RN09)
CREATE OR REPLACE PROCEDURE sp_cancelar_cita(
    p_id_cita INT, p_motivo TEXT, p_id_usuario INT
)
LANGUAGE plpgsql AS $$
DECLARE
    v_estado_actual VARCHAR;
BEGIN
    SELECT estado INTO v_estado_actual FROM citas_medicas WHERE id_cita = p_id_cita;

    IF v_estado_actual = 'ATENDIDA' THEN
        RAISE EXCEPTION 'RF09: Una cita atendida no puede cancelarse como si nunca hubiera ocurrido.';
    END IF;

    UPDATE citas_medicas
    SET estado = 'CANCELADA', motivo_cancelacion = p_motivo
    WHERE id_cita = p_id_cita;

    INSERT INTO historial_estados_cita (id_cita, estado_anterior, estado_nuevo, observacion, id_usuario)
    VALUES (p_id_cita, v_estado_actual, 'CANCELADA', p_motivo, p_id_usuario);

    RAISE NOTICE 'Cita ID % cancelada lógicamente; horario liberado.', p_id_cita;
END;
$$;

-- 3.8 sp_cambiar_estado_cita (RF10)
CREATE OR REPLACE PROCEDURE sp_cambiar_estado_cita(
    p_id_cita INT, p_nuevo_estado VARCHAR, p_observacion TEXT, p_id_usuario INT
)
LANGUAGE plpgsql AS $$
DECLARE
    v_estado_actual VARCHAR;
BEGIN
    SELECT estado INTO v_estado_actual FROM citas_medicas WHERE id_cita = p_id_cita;

    IF v_estado_actual = 'CANCELADA' AND p_nuevo_estado = 'ATENDIDA' THEN
        RAISE EXCEPTION 'RF10: Una cita cancelada no puede marcarse como atendida.';
    END IF;

    UPDATE citas_medicas SET estado = p_nuevo_estado WHERE id_cita = p_id_cita;

    INSERT INTO historial_estados_cita (id_cita, estado_anterior, estado_nuevo, observacion, id_usuario)
    VALUES (p_id_cita, v_estado_actual, p_nuevo_estado, p_observacion, p_id_usuario);

    RAISE NOTICE 'Estado de cita % actualizado de % a %.', p_id_cita, v_estado_actual, p_nuevo_estado;
END;
$$;

-- 3.9 sp_desactivar_paciente (RN11)
CREATE OR REPLACE PROCEDURE sp_desactivar_paciente(p_id_paciente INT)
LANGUAGE plpgsql AS $$
BEGIN
    UPDATE pacientes SET estado = 'INACTIVO' WHERE id_paciente = p_id_paciente;
    RAISE NOTICE 'RN11: Paciente ID % desactivado conservando su historial de citas.', p_id_paciente;
END;
$$;

-- 3.10 sp_resumen_citas_periodo (RF13)
CREATE OR REPLACE PROCEDURE sp_resumen_citas_periodo(p_inicio DATE, p_fin DATE)
LANGUAGE plpgsql AS $$
DECLARE
    v_atendidas INT;
    v_canceladas INT;
    v_inasistencias INT;
BEGIN
    SELECT COUNT(*) INTO v_atendidas FROM citas_medicas WHERE fecha_cita BETWEEN p_inicio AND p_fin AND estado = 'ATENDIDA';
    SELECT COUNT(*) INTO v_canceladas FROM citas_medicas WHERE fecha_cita BETWEEN p_inicio AND p_fin AND estado = 'CANCELADA';
    SELECT COUNT(*) INTO v_inasistencias FROM citas_medicas WHERE fecha_cita BETWEEN p_inicio AND p_fin AND estado = 'INASISTENCIA';
    RAISE NOTICE 'Período % a % -> Atendidas: %, Canceladas: %, Inasistencias: %',
        p_inicio, p_fin, v_atendidas, v_canceladas, v_inasistencias;
END;
$$;

-- ============================================================================
-- 4. TRIGGERS DE VALIDACIÓN, AUDITORÍA Y REGLAS DE NEGOCIO (Páginas 119-124)
-- ============================================================================

-- 4.1 Validar que el médico esté ACTIVO y dentro de su horario (RN04 / RN05 / RF07)
CREATE OR REPLACE FUNCTION fn_validar_disponibilidad_cita()
RETURNS TRIGGER AS $$
DECLARE
    v_estado_medico VARCHAR;
    v_dia_semana SMALLINT;
BEGIN
    SELECT estado INTO v_estado_medico FROM medicos WHERE id_medico = NEW.id_medico;
    IF v_estado_medico <> 'ACTIVO' THEN
        RAISE EXCEPTION 'RN04: No se puede asignar cita a un médico inactivo.';
    END IF;

    -- Obtener día de la semana ISO (1=Lunes .. 7=Domingo)
    v_dia_semana := EXTRACT(ISODOW FROM NEW.fecha_cita);

    IF NOT EXISTS (
        SELECT 1 FROM horarios_atencion
        WHERE id_medico = NEW.id_medico
          AND dia_semana = v_dia_semana
          AND estado = 'ACTIVO'
          AND NEW.hora_inicio >= hora_inicio
          AND NEW.hora_fin <= hora_fin
    ) THEN
        RAISE EXCEPTION 'RN05: El horario seleccionado (% a %) está fuera del horario de atención del médico.',
            NEW.hora_inicio, NEW.hora_fin;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_validar_disponibilidad_cita
BEFORE INSERT OR UPDATE OF fecha_cita, hora_inicio, hora_fin, id_medico ON citas_medicas
FOR EACH ROW EXECUTE FUNCTION fn_validar_disponibilidad_cita();

-- 4.2 Impedir superposición de citas para el mismo médico (RF07 / RN07 / RN09)
CREATE OR REPLACE FUNCTION fn_evitar_citas_superpuestas()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.estado <> 'CANCELADA' THEN
        IF EXISTS (
            SELECT 1 FROM citas_medicas
            WHERE id_medico = NEW.id_medico
              AND fecha_cita = NEW.fecha_cita
              AND id_cita <> COALESCE(NEW.id_cita, -1)
              AND estado <> 'CANCELADA'
              AND (NEW.hora_inicio < hora_fin AND NEW.hora_fin > hora_inicio)
        ) THEN
            RAISE EXCEPTION 'RF07 / RN07: El médico ya posee otra cita activa que se superpone con el horario % - %.',
                NEW.hora_inicio, NEW.hora_fin;
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_evitar_citas_superpuestas
BEFORE INSERT OR UPDATE ON citas_medicas
FOR EACH ROW EXECUTE FUNCTION fn_evitar_citas_superpuestas();

-- 4.3 Validar transiciones legales de estado en citas médicas (RF08 / RF09 / RF10 / RN10)
CREATE OR REPLACE FUNCTION fn_validar_transicion_estado_cita()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.estado = 'CANCELADA' AND NEW.estado = 'ATENDIDA' THEN
        RAISE EXCEPTION 'RF10: Una cita CANCELADA no puede marcarse directamente como ATENDIDA.';
    END IF;

    IF OLD.estado = 'ATENDIDA' AND NEW.estado = 'CANCELADA' THEN
        RAISE EXCEPTION 'RF09 / RN10: Una cita ATENDIDA conserva su registro histórico y no puede cancelarse.';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_validar_transicion_estado_cita
BEFORE UPDATE OF estado ON citas_medicas
FOR EACH ROW EXECUTE FUNCTION fn_validar_transicion_estado_cita();

-- 4.4 Registrar automáticamente en historial_estados_cita cada cambio de estado (RF10 / RF12)
CREATE OR REPLACE FUNCTION fn_auditar_cambio_estado_cita()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.estado IS DISTINCT FROM NEW.estado THEN
        INSERT INTO historial_estados_cita (id_cita, estado_anterior, estado_nuevo, observacion, fecha_cambio)
        VALUES (
            NEW.id_cita,
            OLD.estado,
            NEW.estado,
            COALESCE(NEW.motivo_cancelacion, 'Cambio automático de estado'),
            NOW()
        );
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_auditar_cambio_estado_cita
AFTER UPDATE OF estado ON citas_medicas
FOR EACH ROW EXECUTE FUNCTION fn_auditar_cambio_estado_cita();

-- 4.5 Impedir eliminación física de pacientes con citas históricas (RN11)
CREATE OR REPLACE FUNCTION fn_proteger_historial_paciente()
RETURNS TRIGGER AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM citas_medicas WHERE id_paciente = OLD.id_paciente) THEN
        RAISE EXCEPTION 'RN11: No se permite eliminar físicamente un paciente con citas históricas asociadas. Debe desactivarlo.';
    END IF;
    RETURN OLD;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_proteger_historial_paciente
BEFORE DELETE ON pacientes
FOR EACH ROW EXECUTE FUNCTION fn_proteger_historial_paciente();

-- 4.6 Impedir eliminación física de médicos con citas históricas (RN11)
CREATE OR REPLACE FUNCTION fn_proteger_historial_medico()
RETURNS TRIGGER AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM citas_medicas WHERE id_medico = OLD.id_medico) THEN
        RAISE EXCEPTION 'RN11: No se permite eliminar físicamente un médico con citas históricas asociadas. Debe desactivarlo.';
    END IF;
    RETURN OLD;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_proteger_historial_medico
BEFORE DELETE ON medicos
FOR EACH ROW EXECUTE FUNCTION fn_proteger_historial_medico();

-- 4.7 Impedir eliminación física de citas médicas (RF09)
CREATE OR REPLACE FUNCTION fn_impedir_borrado_fisico_cita()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.estado = 'ATENDIDA' THEN
        RAISE EXCEPTION 'RF09 / RN10: Prohibido eliminar físicamente citas médicas atendidas.';
    END IF;
    RETURN OLD;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_impedir_borrado_fisico_cita
BEFORE DELETE ON citas_medicas
FOR EACH ROW EXECUTE FUNCTION fn_impedir_borrado_fisico_cita();

-- 4.8 Normalizar nombre de especialidad antes de insertar o actualizar (RF04)
CREATE OR REPLACE FUNCTION fn_normalizar_especialidad()
RETURNS TRIGGER AS $$
BEGIN
    NEW.nombre := INITCAP(TRIM(REGEXP_REPLACE(NEW.nombre, '\s+', ' ', 'g')));
    IF EXISTS (
        SELECT 1 FROM especialidades
        WHERE UPPER(nombre) = UPPER(NEW.nombre)
          AND id_especialidad <> COALESCE(NEW.id_especialidad, -1)
    ) THEN
        RAISE EXCEPTION 'RF04: Ya existe una especialidad registrada con el nombre %.', NEW.nombre;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_normalizar_especialidad
BEFORE INSERT OR UPDATE ON especialidades
FOR EACH ROW EXECUTE FUNCTION fn_normalizar_especialidad();

-- 4.9 Crear perfil médico base automáticamente al registrar un nuevo paciente (Sección 3.4)
CREATE OR REPLACE FUNCTION fn_crear_perfil_medico_defecto()
RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO perfiles_medicos (id_paciente) VALUES (NEW.id_paciente)
    ON CONFLICT (id_paciente) DO NOTHING;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_crear_perfil_medico_defecto
AFTER INSERT ON pacientes
FOR EACH ROW EXECUTE FUNCTION fn_crear_perfil_medico_defecto();

-- 4.10 Validar solapamiento de turnos al configurar horarios de un médico (RF05)
CREATE OR REPLACE FUNCTION fn_validar_solapamiento_turno_medico()
RETURNS TRIGGER AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM horarios_atencion
        WHERE id_medico = NEW.id_medico
          AND dia_semana = NEW.dia_semana
          AND id_horario <> COALESCE(NEW.id_horario, -1)
          AND estado = 'ACTIVO'
          AND (NEW.hora_inicio < hora_fin AND NEW.hora_fin > hora_inicio)
    ) THEN
        RAISE EXCEPTION 'RF05: El turno ingresado se superpone con otro horario ya configurado para el médico en ese día.';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_validar_solapamiento_turno_medico
BEFORE INSERT OR UPDATE ON horarios_atencion
FOR EACH ROW EXECUTE FUNCTION fn_validar_solapamiento_turno_medico();

-- 4.11 Triggers de Bitácora Automática para Pacientes, Médicos y Citas Médicas
CREATE OR REPLACE FUNCTION fn_registrar_bitacora_general()
RETURNS TRIGGER AS $$
DECLARE
    v_desc TEXT;
BEGIN
    IF TG_TABLE_NAME = 'pacientes' THEN
        v_desc := CONCAT('Operación ', TG_OP, ' en paciente CI: ', COALESCE(NEW.ci, OLD.ci), ' - ', COALESCE(NEW.nombres, OLD.nombres));
    ELSIF TG_TABLE_NAME = 'medicos' THEN
        v_desc := CONCAT('Operación ', TG_OP, ' en médico Código: ', COALESCE(NEW.codigo_medico, OLD.codigo_medico));
    ELSIF TG_TABLE_NAME = 'citas_medicas' THEN
        v_desc := CONCAT('Operación ', TG_OP, ' en cita: ', COALESCE(NEW.codigo_cita, OLD.codigo_cita), ' (Estado: ', COALESCE(NEW.estado, OLD.estado), ')');
    ELSE
        v_desc := CONCAT('Operación ', TG_OP, ' ejecutada en tabla ', TG_TABLE_NAME);
    END IF;

    INSERT INTO bitacoras (id_usuario, accion, modulo, descripcion, fecha_hora)
    VALUES (NULL, TG_OP, TG_TABLE_NAME, v_desc, NOW());

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_bitacora_pacientes
AFTER INSERT OR UPDATE ON pacientes
FOR EACH ROW EXECUTE FUNCTION fn_registrar_bitacora_general();

CREATE TRIGGER trg_bitacora_medicos
AFTER INSERT OR UPDATE ON medicos
FOR EACH ROW EXECUTE FUNCTION fn_registrar_bitacora_general();

CREATE TRIGGER trg_bitacora_citas
AFTER INSERT OR UPDATE ON citas_medicas
FOR EACH ROW EXECUTE FUNCTION fn_registrar_bitacora_general();

-- ============================================================================
-- FIN DEL SCRIPT DATABASE.SQL
-- ============================================================================
