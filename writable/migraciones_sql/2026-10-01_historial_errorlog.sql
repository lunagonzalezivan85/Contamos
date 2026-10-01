-- 2026-10-01 — solicitud_historial + error_log + solicitudes.nota_revision
-- Timeline de estados de la solicitud (quién creó/aprobó/movió, con nota),
-- bitácora de errores del sistema (visor + purga en /admin/auditoria/errores)
-- y nota que oficina deja al gestor al mandar la solicitud a REVISION.

CREATE TABLE `CT_solicitud_historial` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `solicitud_id` BIGINT UNSIGNED NOT NULL,
    `accion` VARCHAR(20) NOT NULL DEFAULT 'ESTADO',   -- CREADO | ESTADO | EDITADO
    `estado` VARCHAR(20) NULL,                        -- estado destino (moves)
    `nota` VARCHAR(255) NULL,                         -- observación (ej. por qué a revisión)
    `user_id` BIGINT UNSIGNED NULL,                   -- usuario de oficina
    `empleado_id` BIGINT UNSIGNED NULL,               -- gestor en campo
    `actor` VARCHAR(150) NULL,                        -- nombre legible desnormalizado
    `created_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sol_hist_sol` (`solicitud_id`),
    KEY `idx_sol_hist_tenant` (`tenant_id`),
    CONSTRAINT `fk_sol_hist_tenant` FOREIGN KEY (`tenant_id`)
        REFERENCES `CT_tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sol_hist_sol` FOREIGN KEY (`solicitud_id`)
        REFERENCES `CT_solicitudes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Observación que oficina deja al gestor al pasar a REVISION (se limpia al salir)
ALTER TABLE `CT_solicitudes`
    ADD COLUMN `nota_revision` VARCHAR(255) NULL DEFAULT NULL AFTER `paso_dias`;

CREATE TABLE `CT_error_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `origen` VARCHAR(120) NOT NULL,                   -- Clase::método o ruta
    `mensaje` VARCHAR(255) NOT NULL,
    `traza` TEXT NULL,
    `created_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_error_log_tenant` (`tenant_id`),
    KEY `idx_error_log_fecha` (`created_at`),
    CONSTRAINT `fk_error_log_tenant` FOREIGN KEY (`tenant_id`)
        REFERENCES `CT_tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
