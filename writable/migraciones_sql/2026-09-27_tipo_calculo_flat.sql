-- ============================================================================
-- tenants.tipo_calculo default FLAT — equivale a 000014_TenantTipoCalculo.
-- En el server la migracion PHP no corrio (raw query sin prefijo); ahora la
-- migracion usa prefixTable() y este delta aplica el cambio directo.
-- ============================================================================

ALTER TABLE `CT_tenants` MODIFY `tipo_calculo` VARCHAR(12) NOT NULL DEFAULT 'FLAT';
UPDATE `CT_tenants` SET `tipo_calculo` = 'FLAT' WHERE `tipo_calculo` = 'FRANCES';
