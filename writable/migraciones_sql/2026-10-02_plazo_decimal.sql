-- 2026-10-02 · plazo decimal en solicitudes (2.5 meses → 10 cuotas semanales)
-- Correr una sola vez en el server (tablas con prefijo CT_).

ALTER TABLE CT_solicitudes
    MODIFY COLUMN plazo_meses    DECIMAL(5,1) NULL DEFAULT NULL,
    MODIFY COLUMN plazo_aprobado DECIMAL(5,1) NULL DEFAULT NULL;
