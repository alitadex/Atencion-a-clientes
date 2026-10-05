CREATE DATABASE IF NOT EXISTS atencion_clientes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE atencion_clientes;

CREATE TABLE IF NOT EXISTS usuarios_sistema (
 id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(150) NOT NULL,
 correo VARCHAR(150) NOT NULL UNIQUE,
 username VARCHAR(50) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL,
 telefono VARCHAR(20) NULL,
 rol ENUM('administrador','usuario','empleado') NOT NULL DEFAULT 'usuario',
 activo TINYINT(1) NOT NULL DEFAULT 1,
 fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
 cuenta VARCHAR(50) PRIMARY KEY,
 nombre VARCHAR(150) NOT NULL,
 correo VARCHAR(150) NULL,
 telefono VARCHAR(30) NULL,
 direccion VARCHAR(255) NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cat_reportes (
 id_reporte INT AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(100) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cat_medios_reporte (
 id_medio INT AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(100) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cat_estatus (
 id_estatus INT AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(100) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS reportes (
 id_reporte INT AUTO_INCREMENT PRIMARY KEY,
 folio_reporte INT NOT NULL,
 cuenta VARCHAR(50) NOT NULL,
 tipo_reporte_id INT NULL,
 medio_id INT NULL,
 estatus_final_id INT NULL,
 fecha_reporte DATE NULL,
 asunto VARCHAR(255) NULL,
 descripcion TEXT NULL,
 personal VARCHAR(150) NULL,
 fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX(cuenta), INDEX(tipo_reporte_id), INDEX(estatus_final_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS seguimiento_reportes (
 id_seguimiento INT AUTO_INCREMENT PRIMARY KEY,
 id_reporte INT NOT NULL,
 estatus_id INT NULL,
 comentario TEXT NULL,
 personal VARCHAR(150) NULL,
 fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(id_reporte)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS convenios (
 id_convenio INT AUTO_INCREMENT PRIMARY KEY,
 cuenta VARCHAR(50) NOT NULL,
 fecha_convenio DATE NULL,
 descripcion TEXT NULL,
 monto DECIMAL(12,2) NULL,
 estatus_id INT NULL,
 INDEX(cuenta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS ordenes_trabajo (
 id_orden INT AUTO_INCREMENT PRIMARY KEY,
 cuenta VARCHAR(50) NOT NULL,
 descripcion TEXT NULL,
 estatus_id INT NULL,
 fecha_inicio DATE NULL,
 fecha_fin DATE NULL,
 personal VARCHAR(150) NULL,
 INDEX(cuenta), INDEX(estatus_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS presupuestos (
 id_presupuesto INT AUTO_INCREMENT PRIMARY KEY,
 cuenta VARCHAR(50) NOT NULL,
 descripcion TEXT NULL,
 monto DECIMAL(12,2) NULL,
 estatus_id INT NULL,
 fecha DATE NULL,
 INDEX(cuenta), INDEX(estatus_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cat_estatus (nombre,activo) SELECT 'Pendiente',1 WHERE NOT EXISTS (SELECT 1 FROM cat_estatus WHERE nombre='Pendiente');
INSERT INTO cat_estatus (nombre,activo) SELECT 'En proceso',1 WHERE NOT EXISTS (SELECT 1 FROM cat_estatus WHERE nombre='En proceso');
INSERT INTO cat_estatus (nombre,activo) SELECT 'Resuelto',1 WHERE NOT EXISTS (SELECT 1 FROM cat_estatus WHERE nombre='Resuelto');
INSERT INTO cat_reportes (nombre,activo) SELECT 'Queja',1 WHERE NOT EXISTS (SELECT 1 FROM cat_reportes WHERE nombre='Queja');
INSERT INTO cat_reportes (nombre,activo) SELECT 'Solicitud',1 WHERE NOT EXISTS (SELECT 1 FROM cat_reportes WHERE nombre='Solicitud');
INSERT INTO cat_medios_reporte (nombre,activo) SELECT 'Presencial',1 WHERE NOT EXISTS (SELECT 1 FROM cat_medios_reporte WHERE nombre='Presencial');
INSERT INTO cat_medios_reporte (nombre,activo) SELECT 'Teléfono',1 WHERE NOT EXISTS (SELECT 1 FROM cat_medios_reporte WHERE nombre='Teléfono');
INSERT INTO cat_medios_reporte (nombre,activo) SELECT 'Correo',1 WHERE NOT EXISTS (SELECT 1 FROM cat_medios_reporte WHERE nombre='Correo');
