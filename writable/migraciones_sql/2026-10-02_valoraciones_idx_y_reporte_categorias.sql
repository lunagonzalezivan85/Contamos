-- 2026-10-02 · fix valoraciones + tablas de reportes faltantes en el server
-- 1) ct_valoraciones tenia UNIQUE(tenant_id,user_id) → la 2da valoración del
--    mismo usuario lanzaba "Duplicate entry" (HTTP 500 /valoracion).
--    El diseño guarda una fila por calificación (historial cada 5 días).
-- 2) ct_reporte_categorias / ct_reporte_asignaciones nunca se crearon en el
--    server → /reportes tiraba 500 (tabla inexistente).

ALTER TABLE CT_valoraciones
    ADD INDEX idx_val_usuario (tenant_id, user_id),
    DROP INDEX tenant_id_user_id;

CREATE TABLE IF NOT EXISTS CT_reporte_categorias (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id  BIGINT UNSIGNED NOT NULL,
    nombre     VARCHAR(80) NOT NULL,
    orden      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo     TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_rcat_tenant (tenant_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS CT_reporte_asignaciones (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id    BIGINT UNSIGNED NOT NULL,
    reporte_key  VARCHAR(60) NOT NULL,
    categoria_id BIGINT UNSIGNED NULL,
    created_at   DATETIME NULL,
    updated_at   DATETIME NULL,
    UNIQUE KEY uk_rasig_tenant_key (tenant_id, reporte_key)
) ENGINE=InnoDB;
