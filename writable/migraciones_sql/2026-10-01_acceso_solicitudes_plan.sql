-- 2026-10-01 · /alta — calculadora de plan + código partner
-- Columnas nuevas en acceso_solicitudes (server con prefijo CT_)
-- Idempotente: correr solo si las columnas no existen.

ALTER TABLE `CT_acceso_solicitudes`
    ADD COLUMN `codigo`        VARCHAR(20)    NULL AFTER `correo`,
    ADD COLUMN `plan_estimado` DECIMAL(10,2)  NULL AFTER `codigo`,
    ADD COLUMN `detalle`       TEXT           NULL AFTER `plan_estimado`;
