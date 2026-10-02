-- 2026-10-01 · /admin/leads — contratos de servicio SaaS (Fase 3)
-- Tabla nueva (server con prefijo CT_).

CREATE TABLE IF NOT EXISTS `CT_contratos` (
    `id`                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `acceso_solicitud_id` INT UNSIGNED     NOT NULL,
    `tenant_id`           INT UNSIGNED     NULL,
    `plan_id`             INT UNSIGNED     NULL,
    `monto_mensual`       DECIMAL(10,2)    NOT NULL,
    `dia_pago`            TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `gracia_dias`         SMALLINT UNSIGNED NOT NULL DEFAULT 4,
    `cuenta_bancaria`     VARCHAR(200)     NULL,
    `terminos`            TEXT             NULL,
    `estado`              VARCHAR(20)      NOT NULL DEFAULT 'ACTIVO',
    `fecha_inicio`        DATE             NOT NULL,
    `fecha_corte`         DATE             NULL,
    `created_at`          DATETIME         NULL,
    `updated_at`          DATETIME         NULL,
    PRIMARY KEY (`id`),
    KEY `acceso_solicitud_id` (`acceso_solicitud_id`),
    KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
