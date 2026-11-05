-- ============================================================================
-- MIGRACIÓN SEGURA: Sistema de Notificaciones Internas y Configuración de Cuenta
-- Clínica Vida Salud - PostgreSQL
-- ============================================================================

-- 1. Agregar campos de configuración de cuenta y privacidad a la tabla usuarios
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'usuarios' AND column_name = 'notificaciones_activas'
    ) THEN
        ALTER TABLE usuarios ADD COLUMN notificaciones_activas BOOLEAN NOT NULL DEFAULT TRUE;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'usuarios' AND column_name = 'privacidad_contacto_emergencia'
    ) THEN
        ALTER TABLE usuarios ADD COLUMN privacidad_contacto_emergencia BOOLEAN NOT NULL DEFAULT TRUE;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'usuarios' AND column_name = 'privacidad_recordatorios'
    ) THEN
        ALTER TABLE usuarios ADD COLUMN privacidad_recordatorios BOOLEAN NOT NULL DEFAULT TRUE;
    END IF;
END $$;

-- 2. Crear tabla de notificaciones internas del sistema
CREATE TABLE IF NOT EXISTS notificaciones (
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

-- 3. Índices para rendimiento de consultas en tiempo real y polling AJAX
CREATE INDEX IF NOT EXISTS idx_notif_usuario_leida ON notificaciones (id_usuario, leida);
CREATE INDEX IF NOT EXISTS idx_notif_fecha ON notificaciones (fecha_creacion DESC);

-- 4. Notificaciones iniciales de bienvenida / sistema para los usuarios de prueba existentes
INSERT INTO notificaciones (id_usuario, tipo, titulo, mensaje, enlace, leida)
SELECT u.id_usuario, 'SISTEMA', 'Bienvenido/a al Sistema', 'Su cuenta está activa en la plataforma de la Clínica Vida Salud.', '/clinicaVidaSalud/views/dashboard/index.php', FALSE
FROM usuarios u
WHERE NOT EXISTS (
    SELECT 1 FROM notificaciones n WHERE n.id_usuario = u.id_usuario
);

-- 5. Eliminar rol obsoleto Auditor si existe
DELETE FROM roles WHERE nombre_rol = 'Auditor';

