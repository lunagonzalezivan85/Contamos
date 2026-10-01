-- ============================================================================
-- SYNC esquema local → server  ·  27/sep/2026
-- Ejecutar en phpMyAdmin de db_aa03a4_actas (prefijo CT_).
-- Diferencias reales encontradas comparando columna por columna:
--   · CT_tenants: faltan ruc y conami_registro (migraciones 000011 y 000012)
-- Todo lo demás coincide (los bigint(20)/bigint son solo el ancho de display
-- de MySQL 5.7 — no hay diferencia real).
-- ============================================================================

ALTER TABLE `CT_tenants`
    ADD COLUMN `ruc` VARCHAR(30) NULL AFTER `razon_social`,
    ADD COLUMN `conami_registro` VARCHAR(40) NULL AFTER `ruc`;
