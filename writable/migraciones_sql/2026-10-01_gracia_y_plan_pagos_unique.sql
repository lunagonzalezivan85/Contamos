-- 2026-10-01 · Fase 4 — cobranza SaaS y suspensión automática
-- 1) tenants.gracia_dias: días de gracia tras dia_pago (viene del contrato).
-- 2) plan_pagos: un cargo por tenant por período (cobros:generar idempotente).

ALTER TABLE `CT_tenants`
    ADD COLUMN `gracia_dias` SMALLINT UNSIGNED NOT NULL DEFAULT 4 AFTER `dia_pago`;

-- Dedupe por si quedó doble (misma pareja tenant/periodo):
DELETE pp FROM `CT_plan_pagos` pp
JOIN (
    SELECT tenant_id, periodo, MIN(id) AS keep_id
    FROM `CT_plan_pagos` GROUP BY tenant_id, periodo HAVING COUNT(*) > 1
) dup ON pp.tenant_id = dup.tenant_id AND pp.periodo = dup.periodo AND pp.id <> dup.keep_id;

ALTER TABLE `CT_plan_pagos`
    ADD UNIQUE KEY `uq_tenant_periodo` (`tenant_id`, `periodo`);
