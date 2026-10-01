-- 2026-09-28 — empleados.puede_desembolsar
-- Permiso por gestor para entregar desembolsos (portal + app).
-- Default 1: los gestores actuales conservan la capacidad.
ALTER TABLE `CT_empleados`
    ADD COLUMN `puede_desembolsar` TINYINT(1) NOT NULL DEFAULT 1 AFTER `estado`;
