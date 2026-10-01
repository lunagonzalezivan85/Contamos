-- 2026-09-29 — persona_egresos: gastos mensuales del cliente
-- Nueva pestaña "Egresos" del expediente (portal gestor + app IONIC) y
-- alimenta el análisis financiero (cuota vs ingreso neto).
CREATE TABLE `CT_persona_egresos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NULL DEFAULT NULL,
    `descripcion` VARCHAR(150) NULL,
    `monto` DECIMAL(14,2) NULL,
    PRIMARY KEY (`id`),
    KEY `idx_persona_egresos` (`persona_id`),
    CONSTRAINT `fk_persona_egresos` FOREIGN KEY (`persona_id`)
        REFERENCES `CT_personas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
