-- 2026-10-01 · /admin/leads — modal Contactar con selector de plan
-- Columna nueva en acceso_solicitudes (server con prefijo CT_)
-- Idempotente: correr solo si la columna no existe.

ALTER TABLE `CT_acceso_solicitudes`
    ADD COLUMN `plan_id` INT UNSIGNED NULL AFTER `detalle`;
