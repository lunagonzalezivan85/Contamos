-- Contamos — solo estructura
-- Generado: 2026-10-10 11:53:17 · BD: cfsi

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- acceso_solicitudes
DROP TABLE IF EXISTS `acceso_solicitudes`;
CREATE TABLE `acceso_solicitudes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `negocio` varchar(160) NOT NULL,
  `telefono` varchar(30) NOT NULL,
  `correo` varchar(160) DEFAULT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `plan_estimado` decimal(10,2) DEFAULT NULL,
  `detalle` text DEFAULT NULL,
  `plan_id` int(10) unsigned DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- arqueos
DROP TABLE IF EXISTS `arqueos`;
CREATE TABLE `arqueos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `empleado_id` bigint(20) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `saldo_inicial` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cobros` decimal(12,2) NOT NULL DEFAULT 0.00,
  `desembolsos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `esperado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `contado` decimal(12,2) NOT NULL,
  `diferencia` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` varchar(20) NOT NULL DEFAULT 'CUADRADO',
  `observacion` varchar(255) DEFAULT NULL,
  `resuelto_por` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_arqueos_dia` (`tenant_id`,`empleado_id`,`fecha`),
  KEY `fk_arqueos_empleados` (`empleado_id`),
  KEY `idx_arqueos_fecha` (`tenant_id`,`fecha`),
  CONSTRAINT `fk_arqueos_empleados` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_arqueos_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- asistencias
DROP TABLE IF EXISTS `asistencias`;
CREATE TABLE `asistencias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `empleado_id` bigint(20) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `entrada` datetime NOT NULL,
  `salida` datetime DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'ABIERTA',
  `editada` tinyint(1) NOT NULL DEFAULT 0,
  `editado_por` bigint(20) unsigned DEFAULT NULL,
  `registrado_por` bigint(20) unsigned DEFAULT NULL,
  `observacion` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_asist_fecha` (`tenant_id`,`fecha`),
  KEY `idx_asist_abierta` (`empleado_id`,`estado`),
  CONSTRAINT `fk_asist_empleados` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_asist_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- audit_logs
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `entidad` varchar(100) DEFAULT NULL,
  `entidad_id` bigint(20) unsigned DEFAULT NULL,
  `datos_anteriores` text DEFAULT NULL,
  `datos_nuevos` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_audit_logs_users` (`user_id`),
  KEY `idx_audit_logs_tenant` (`tenant_id`),
  KEY `idx_audit_logs_entidad` (`entidad`,`entidad_id`),
  CONSTRAINT `fk_audit_logs_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_audit_logs_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- clientes
DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `persona_id` bigint(20) unsigned NOT NULL,
  `codigo` varchar(30) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'ACTIVO',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `limite_credito` decimal(12,2) DEFAULT NULL,
  `monto_max` decimal(12,2) DEFAULT NULL,
  `monto_min` decimal(12,2) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_clientes_persona` (`persona_id`),
  CONSTRAINT `fk_clientes_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- contratos
DROP TABLE IF EXISTS `contratos`;
CREATE TABLE `contratos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `acceso_solicitud_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned DEFAULT NULL,
  `plan_id` int(10) unsigned DEFAULT NULL,
  `monto_mensual` decimal(10,2) NOT NULL,
  `dia_pago` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `gracia_dias` smallint(5) unsigned NOT NULL DEFAULT 4,
  `cuenta_bancaria` varchar(200) DEFAULT NULL,
  `terminos` text DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'ACTIVO',
  `fecha_inicio` date NOT NULL,
  `fecha_corte` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `acceso_solicitud_id` (`acceso_solicitud_id`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- cuotas
DROP TABLE IF EXISTS `cuotas`;
CREATE TABLE `cuotas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `solicitud_id` bigint(20) unsigned NOT NULL,
  `n` smallint(5) unsigned NOT NULL,
  `fecha_vence` date NOT NULL,
  `cuota` decimal(12,2) NOT NULL,
  `interes` decimal(12,2) NOT NULL DEFAULT 0.00,
  `capital` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saldo_proyectado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pagado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `mora_dev` decimal(12,2) DEFAULT 0.00,
  `mora` decimal(12,2) DEFAULT 0.00,
  `descuento` decimal(12,2) DEFAULT 0.00,
  `estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE',
  `fecha_pago` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cuotas_sol` (`solicitud_id`,`n`),
  KEY `idx_cuotas_vence` (`tenant_id`,`fecha_vence`),
  CONSTRAINT `fk_cuotas_solicitudes` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cuotas_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- empleados
DROP TABLE IF EXISTS `empleados`;
CREATE TABLE `empleados` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `persona_id` bigint(20) unsigned NOT NULL,
  `carnet` varchar(20) DEFAULT NULL,
  `pin` varchar(10) DEFAULT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `ruta` varchar(80) DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'ACTIVO',
  `puede_desembolsar` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_empleados_persona` (`persona_id`),
  CONSTRAINT `fk_empleados_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- error_log
DROP TABLE IF EXISTS `error_log`;
CREATE TABLE `error_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `origen` varchar(120) NOT NULL,
  `mensaje` varchar(255) NOT NULL,
  `traza` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_error_log_tenant` (`tenant_id`),
  KEY `idx_error_log_fecha` (`created_at`),
  CONSTRAINT `fk_error_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- gasto_categorias
DROP TABLE IF EXISTS `gasto_categorias`;
CREATE TABLE `gasto_categorias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'ACTIVO',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gasto_cat_nombre` (`tenant_id`,`nombre`),
  CONSTRAINT `fk_gasto_cat_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- gastos
DROP TABLE IF EXISTS `gastos`;
CREATE TABLE `gastos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `categoria_id` bigint(20) unsigned NOT NULL,
  `empleado_id` bigint(20) unsigned DEFAULT NULL,
  `registrado_por` bigint(20) unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `concepto` varchar(150) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL,
  `metodo` varchar(20) NOT NULL DEFAULT 'EFECTIVO',
  `estado` varchar(20) NOT NULL DEFAULT 'ACTIVO',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_gastos_cat` (`categoria_id`),
  KEY `fk_gastos_empleados` (`empleado_id`),
  KEY `idx_gastos_fecha` (`tenant_id`,`fecha`),
  KEY `idx_gastos_cat` (`tenant_id`,`categoria_id`),
  CONSTRAINT `fk_gastos_cat` FOREIGN KEY (`categoria_id`) REFERENCES `gasto_categorias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gastos_empleados` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_gastos_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ingresos
DROP TABLE IF EXISTS `ingresos`;
CREATE TABLE `ingresos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'VENTA',
  `empleado_id` bigint(20) unsigned DEFAULT NULL,
  `registrado_por` bigint(20) unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `concepto` varchar(150) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL,
  `iva_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `metodo` varchar(20) NOT NULL DEFAULT 'EFECTIVO',
  `estado` varchar(20) NOT NULL DEFAULT 'ACTIVO',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_ingresos_empleados` (`empleado_id`),
  KEY `idx_ingresos_fecha` (`tenant_id`,`fecha`),
  KEY `idx_ingresos_tipo` (`tenant_id`,`tipo`),
  CONSTRAINT `fk_ingresos_empleados` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_ingresos_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- login_attempts
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `exito` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_login_attempts_users` (`user_id`),
  KEY `idx_login_attempts_tenant` (`tenant_id`),
  KEY `idx_login_attempts_username` (`username`),
  CONSTRAINT `fk_login_attempts_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_login_attempts_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=142 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- menus
DROP TABLE IF EXISTS `menus`;
CREATE TABLE `menus` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `icono` varchar(100) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `estado` varchar(20) NOT NULL DEFAULT 'ACTIVO',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_menus_tenant` (`tenant_id`),
  KEY `idx_menus_parent` (`parent_id`),
  CONSTRAINT `fk_menus_parent` FOREIGN KEY (`parent_id`) REFERENCES `menus` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_menus_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- meta_metricas
DROP TABLE IF EXISTS `meta_metricas`;
CREATE TABLE `meta_metricas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `codigo` varchar(40) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `unidad` varchar(12) NOT NULL DEFAULT 'MONTO',
  `modo` varchar(8) NOT NULL DEFAULT 'MINIMO',
  `calculo` varchar(8) NOT NULL DEFAULT 'AUTO',
  `formula` varchar(255) DEFAULT NULL,
  `estado` varchar(10) NOT NULL DEFAULT 'ACTIVO',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mmetricas_tenant` (`tenant_id`,`codigo`),
  CONSTRAINT `fk_mmetricas_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- metas
DROP TABLE IF EXISTS `metas`;
CREATE TABLE `metas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `empleado_id` bigint(20) unsigned NOT NULL,
  `metrica_id` bigint(20) unsigned NOT NULL,
  `periodo` char(7) NOT NULL,
  `meta` decimal(14,2) NOT NULL DEFAULT 0.00,
  `avance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `notas` varchar(255) DEFAULT NULL,
  `estado` varchar(10) NOT NULL DEFAULT 'ACTIVO',
  `registrado_por` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_metas_metricas` (`metrica_id`),
  KEY `fk_metas_empleados` (`empleado_id`),
  KEY `idx_metas_lookup` (`tenant_id`,`empleado_id`,`metrica_id`,`periodo`),
  CONSTRAINT `fk_metas_empleados` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_metas_metricas` FOREIGN KEY (`metrica_id`) REFERENCES `meta_metricas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_metas_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- migrations
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `group` varchar(255) NOT NULL,
  `namespace` varchar(255) NOT NULL,
  `time` int(11) NOT NULL,
  `batch` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- notifications
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `mensaje` varchar(500) NOT NULL,
  `tipo` enum('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `url` varchar(255) DEFAULT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_notifications_users` (`user_id`),
  KEY `idx_notifications_user` (`tenant_id`,`user_id`,`leida`),
  CONSTRAINT `fk_notifications_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notifications_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- pago_aplicaciones
DROP TABLE IF EXISTS `pago_aplicaciones`;
CREATE TABLE `pago_aplicaciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `pago_id` int(10) unsigned NOT NULL,
  `cuota_id` int(10) unsigned NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `tipo` varchar(10) NOT NULL DEFAULT 'CUOTA',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id_pago_id` (`tenant_id`,`pago_id`),
  KEY `cuota_id` (`cuota_id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- pagos
DROP TABLE IF EXISTS `pagos`;
CREATE TABLE `pagos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `solicitud_id` bigint(20) unsigned NOT NULL,
  `cuota_id` bigint(20) unsigned DEFAULT NULL,
  `revierte_id` bigint(20) unsigned DEFAULT NULL,
  `empleado_id` bigint(20) unsigned DEFAULT NULL,
  `registrado_por` bigint(20) unsigned DEFAULT NULL,
  `aprobado_por` bigint(20) unsigned DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL,
  `metodo` varchar(20) NOT NULL DEFAULT 'EFECTIVO',
  `tipo` varchar(10) NOT NULL DEFAULT 'PAGO',
  `fecha_hora` datetime NOT NULL,
  `observacion` varchar(255) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'REVISION',
  `resuelto_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pagos_cuotas` (`cuota_id`),
  KEY `idx_pagos_estado` (`tenant_id`,`estado`),
  KEY `idx_pagos_sol` (`solicitud_id`),
  KEY `idx_pagos_gestor` (`empleado_id`,`fecha_hora`),
  KEY `fk_pagos_revierte` (`revierte_id`),
  CONSTRAINT `fk_pagos_cuotas` FOREIGN KEY (`cuota_id`) REFERENCES `cuotas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pagos_empleados` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pagos_revierte` FOREIGN KEY (`revierte_id`) REFERENCES `pagos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pagos_solicitudes` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pagos_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- password_reset_tokens
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_prt_users` (`user_id`),
  KEY `idx_prt_tenant` (`tenant_id`),
  KEY `idx_prt_token` (`token`),
  CONSTRAINT `fk_prt_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_prt_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- permissions
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(100) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_codigo` (`codigo`),
  KEY `idx_permissions_modulo` (`modulo`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- persona_activos
DROP TABLE IF EXISTS `persona_activos`;
CREATE TABLE `persona_activos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `persona_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `valor` decimal(14,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_persona_activos` (`persona_id`),
  CONSTRAINT `fk_persona_activos` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- persona_contactos
DROP TABLE IF EXISTS `persona_contactos`;
CREATE TABLE `persona_contactos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `persona_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `tipo` varchar(30) DEFAULT NULL,
  `valor` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_persona_contactos` (`persona_id`),
  CONSTRAINT `fk_persona_contactos` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- persona_direcciones
DROP TABLE IF EXISTS `persona_direcciones`;
CREATE TABLE `persona_direcciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `persona_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `tipo` varchar(30) DEFAULT NULL,
  `departamento` varchar(100) DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `barrio` varchar(100) DEFAULT NULL,
  `detalle` varchar(255) DEFAULT NULL,
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_persona_direcciones` (`persona_id`),
  CONSTRAINT `fk_persona_direcciones` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- persona_documentos
DROP TABLE IF EXISTS `persona_documentos`;
CREATE TABLE `persona_documentos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `persona_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `tipo` varchar(60) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `archivo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_persona_documentos` (`persona_id`),
  CONSTRAINT `fk_persona_documentos` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- persona_egresos
DROP TABLE IF EXISTS `persona_egresos`;
CREATE TABLE `persona_egresos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `persona_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `monto` decimal(14,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_persona_egresos` (`persona_id`),
  CONSTRAINT `fk_persona_egresos` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- persona_ingresos
DROP TABLE IF EXISTS `persona_ingresos`;
CREATE TABLE `persona_ingresos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `persona_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `fuente` varchar(150) DEFAULT NULL,
  `monto` decimal(14,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_persona_ingresos` (`persona_id`),
  CONSTRAINT `fk_persona_ingresos` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- persona_negocios
DROP TABLE IF EXISTS `persona_negocios`;
CREATE TABLE `persona_negocios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `persona_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `nombre` varchar(150) DEFAULT NULL,
  `actividad` varchar(150) DEFAULT NULL,
  `sector_economico` varchar(100) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `tiempo` varchar(60) DEFAULT NULL,
  `dias_venta` varchar(40) DEFAULT NULL,
  `promedio_venta_dia` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_persona_negocios` (`persona_id`),
  CONSTRAINT `fk_persona_negocios` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- persona_pasivos
DROP TABLE IF EXISTS `persona_pasivos`;
CREATE TABLE `persona_pasivos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `persona_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `acreedor` varchar(150) DEFAULT NULL,
  `monto` decimal(14,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_persona_pasivos` (`persona_id`),
  CONSTRAINT `fk_persona_pasivos` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- persona_referencias
DROP TABLE IF EXISTS `persona_referencias`;
CREATE TABLE `persona_referencias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `persona_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `nombre` varchar(150) DEFAULT NULL,
  `parentesco` varchar(60) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_persona_referencias` (`persona_id`),
  CONSTRAINT `fk_persona_referencias` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- personas
DROP TABLE IF EXISTS `personas`;
CREATE TABLE `personas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `tipo` varchar(20) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `genero` varchar(10) DEFAULT NULL,
  `cedula` varchar(30) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `fecha_nac` date DEFAULT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'ACTIVO',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_personas_tipo` (`tenant_id`,`tipo`),
  CONSTRAINT `fk_personas_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- plan_pagos
DROP TABLE IF EXISTS `plan_pagos`;
CREATE TABLE `plan_pagos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `plan_id` int(10) unsigned DEFAULT NULL,
  `periodo` char(7) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `moneda` varchar(3) NOT NULL DEFAULT 'USD',
  `metodo` varchar(30) DEFAULT NULL,
  `referencia` varchar(60) DEFAULT NULL,
  `fecha_pago` date DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE',
  `registrado_por` bigint(20) unsigned DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plan_pago_periodo` (`tenant_id`,`periodo`),
  UNIQUE KEY `uq_tenant_periodo` (`tenant_id`,`periodo`),
  KEY `fk_plan_pago_plan` (`plan_id`),
  CONSTRAINT `fk_plan_pago_plan` FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_plan_pago_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- plan_solicitudes
DROP TABLE IF EXISTS `plan_solicitudes`;
CREATE TABLE `plan_solicitudes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `plan_id` int(10) unsigned NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE',
  `nota` text DEFAULT NULL,
  `solicitado_por` bigint(20) unsigned DEFAULT NULL,
  `resuelto_por` bigint(20) unsigned DEFAULT NULL,
  `fecha_resolucion` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id_estado` (`tenant_id`,`estado`),
  KEY `fk_plan_sol_plan` (`plan_id`),
  CONSTRAINT `fk_plan_sol_plan` FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_plan_sol_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- planes
DROP TABLE IF EXISTS `planes`;
CREATE TABLE `planes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `slug` varchar(40) NOT NULL,
  `precio_mensual` decimal(10,2) NOT NULL DEFAULT 0.00,
  `moneda` varchar(3) NOT NULL DEFAULT 'USD',
  `max_creditos_activos` int(11) NOT NULL DEFAULT -1,
  `max_empleados` int(11) NOT NULL DEFAULT -1,
  `max_usuarios` int(11) NOT NULL DEFAULT -1,
  `features` text DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `estado` varchar(20) NOT NULL DEFAULT 'ACTIVO',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- plantillas
DROP TABLE IF EXISTS `plantillas`;
CREATE TABLE `plantillas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `contenido` text NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'ACTIVO',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plantillas_tenant_slug` (`tenant_id`,`slug`),
  CONSTRAINT `fk_plantillas_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- reporte_asignaciones
DROP TABLE IF EXISTS `reporte_asignaciones`;
CREATE TABLE `reporte_asignaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `reporte_key` varchar(60) NOT NULL,
  `categoria_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id_reporte_key` (`tenant_id`,`reporte_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- reporte_categorias
DROP TABLE IF EXISTS `reporte_categorias`;
CREATE TABLE `reporte_categorias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- role_menus
DROP TABLE IF EXISTS `role_menus`;
CREATE TABLE `role_menus` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `menu_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_menus` (`tenant_id`,`role_id`,`menu_id`),
  KEY `fk_role_menus_menus` (`menu_id`),
  KEY `idx_role_menus_tenant` (`tenant_id`),
  KEY `idx_rm_role` (`role_id`),
  CONSTRAINT `fk_role_menus_menus` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_role_menus_roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_role_menus_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=190 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- role_permissions
DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_permissions` (`tenant_id`,`role_id`,`permission_id`),
  KEY `fk_role_permissions_permissions` (`permission_id`),
  KEY `idx_role_permissions_tenant` (`tenant_id`),
  KEY `idx_rp_role` (`role_id`),
  CONSTRAINT `fk_role_permissions_permissions` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_role_permissions_roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_role_permissions_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=362 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- roles
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `es_sistema` tinyint(1) NOT NULL DEFAULT 0,
  `estado` varchar(20) NOT NULL DEFAULT 'ACTIVO',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_tenant_slug` (`tenant_id`,`slug`),
  KEY `idx_roles_tenant` (`tenant_id`),
  CONSTRAINT `fk_roles_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- solicitud_analisis
DROP TABLE IF EXISTS `solicitud_analisis`;
CREATE TABLE `solicitud_analisis` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `solicitud_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `ingresos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cuota` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cuota_mes` decimal(12,2) NOT NULL DEFAULT 0.00,
  `activos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pasivos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `patrimonio` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ratio` decimal(6,3) DEFAULT NULL,
  `nivel` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id_solicitud_id` (`tenant_id`,`solicitud_id`),
  KEY `cliente_id` (`cliente_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- solicitud_historial
DROP TABLE IF EXISTS `solicitud_historial`;
CREATE TABLE `solicitud_historial` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `solicitud_id` bigint(20) unsigned NOT NULL,
  `accion` varchar(20) NOT NULL DEFAULT 'ESTADO',
  `estado` varchar(20) DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `empleado_id` bigint(20) unsigned DEFAULT NULL,
  `actor` varchar(150) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sol_hist_sol` (`solicitud_id`),
  KEY `idx_sol_hist_tenant` (`tenant_id`),
  CONSTRAINT `fk_sol_hist_sol` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sol_hist_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- solicitudes
DROP TABLE IF EXISTS `solicitudes`;
CREATE TABLE `solicitudes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `empleado_id` bigint(20) unsigned DEFAULT NULL,
  `asignado_a` bigint(20) unsigned DEFAULT NULL,
  `ruta` varchar(80) DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL,
  `plazo_meses` decimal(5,1) DEFAULT NULL,
  `frecuencia` varchar(10) DEFAULT NULL,
  `destino` varchar(200) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE',
  `origen` varchar(20) DEFAULT 'INTERNO',
  `monto_aprobado` decimal(12,2) DEFAULT NULL,
  `tasa_aprobada` decimal(5,2) DEFAULT NULL,
  `plazo_aprobado` decimal(5,1) DEFAULT NULL,
  `frecuencia_aprobada` varchar(5) DEFAULT NULL,
  `fecha_primer_pago` date DEFAULT NULL,
  `fecha_desembolso` date DEFAULT NULL,
  `fecha_entrega` datetime DEFAULT NULL,
  `codigo_credito` varchar(25) DEFAULT NULL,
  `saldo_favor` decimal(12,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `tasa_mensual` decimal(6,3) DEFAULT NULL,
  `dias_semana` int(1) DEFAULT NULL,
  `tipo_calculo` varchar(12) NOT NULL DEFAULT 'FRANCES',
  `gracia_meses` int(3) NOT NULL DEFAULT 0,
  `gracia_tipo` varchar(10) DEFAULT NULL,
  `comision` decimal(12,2) NOT NULL DEFAULT 0.00,
  `seguro` decimal(12,2) NOT NULL DEFAULT 0.00,
  `refinancia_id` int(10) unsigned DEFAULT NULL,
  `paso_dias` int(3) DEFAULT NULL,
  `nota_revision` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id_estado` (`tenant_id`,`estado`),
  KEY `cliente_id` (`cliente_id`),
  KEY `solicitudes_asignado_a_foreign` (`asignado_a`),
  CONSTRAINT `solicitudes_asignado_a_foreign` FOREIGN KEY (`asignado_a`) REFERENCES `empleados` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- tenants
DROP TABLE IF EXISTS `tenants`;
CREATE TABLE `tenants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `razon_social` varchar(200) DEFAULT NULL,
  `ruc` varchar(30) DEFAULT NULL,
  `conami_registro` varchar(40) DEFAULT NULL,
  `slug` varchar(100) NOT NULL,
  `app_codigo` varchar(12) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `lema` varchar(200) DEFAULT NULL,
  `quienes_somos` text DEFAULT NULL,
  `voucher_footer` varchar(255) DEFAULT NULL,
  `horario` varchar(200) DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `moneda` varchar(10) DEFAULT 'C$',
  `tasa_interes` decimal(5,2) DEFAULT 3.00,
  `mora_diaria_pct` decimal(6,4) DEFAULT 0.0000,
  `pronto_pago_pct` decimal(5,2) DEFAULT 0.00,
  `plazo_meses_max` int(3) unsigned DEFAULT 24,
  `contacto_nombre` varchar(150) DEFAULT NULL,
  `contacto_cargo` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'ACTIVO',
  `plan_id` int(10) unsigned DEFAULT NULL,
  `dia_pago` tinyint(3) unsigned DEFAULT 10,
  `gracia_dias` smallint(5) unsigned DEFAULT 4,
  `suscripcion_estado` varchar(20) DEFAULT 'ACTIVA',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `mision` text DEFAULT NULL,
  `vision` text DEFAULT NULL,
  `valores` text DEFAULT NULL,
  `tipo_calculo` varchar(12) NOT NULL DEFAULT 'FLAT',
  `comision_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `seguro_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenants_slug` (`slug`),
  KEY `fk_tenants_plan` (`plan_id`),
  CONSTRAINT `fk_tenants_plan` FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- users
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `persona_id` bigint(20) unsigned DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'ACTIVO',
  `debe_cambiar_password` tinyint(1) NOT NULL DEFAULT 0,
  `ultimo_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_tenant_username` (`tenant_id`,`username`),
  UNIQUE KEY `uq_users_tenant_email` (`tenant_id`,`email`),
  KEY `idx_users_tenant` (`tenant_id`),
  KEY `idx_users_role` (`role_id`),
  CONSTRAINT `fk_users_roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_users_tenants` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- valoraciones
DROP TABLE IF EXISTS `valoraciones`;
CREATE TABLE `valoraciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `estrellas` tinyint(3) unsigned NOT NULL,
  `resena` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_val_user` (`user_id`),
  KEY `idx_val_usuario` (`tenant_id`,`user_id`),
  CONSTRAINT `fk_val_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_val_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
