-- ============================================================================
-- tenants.app_codigo — código corto (CONT-####) para vincular la app IONIC
-- sin escribir la URL. Equivale a la migracion 000015_TenantAppCodigo.
-- ============================================================================

ALTER TABLE `CT_tenants`
    ADD COLUMN `app_codigo` VARCHAR(12) NULL AFTER `slug`,
    ADD UNIQUE KEY `uq_tenants_app_codigo` (`app_codigo`);

-- Código para el tenant existente
UPDATE `CT_tenants` SET `app_codigo` = 'CONT-5814' WHERE `slug` = 'tu-impulso';
