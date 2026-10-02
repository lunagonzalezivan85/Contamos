-- 2026-10-01 · Plan Básico → $19/mes, 1 usuario incluido
-- Esquema sobreconsumo: usuario $3.00 · cliente $0.20 · crédito $0.15 · empleado $1.00
-- (empleados y créditos ya quedan en 5 y 50)

UPDATE `CT_planes`
SET `precio_mensual` = 19.00,
    `max_usuarios`   = 1,
    `updated_at`     = NOW()
WHERE `slug` = 'basico';
