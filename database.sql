-- ================================================================================
-- DATABASE SCHEMA: SGI Pro - Gestión de Incidentes ISO 27001 (Multi-Tenant SaaS)
-- ================================================================================

DROP DATABASE IF EXISTS gestion_incidentes;
CREATE DATABASE gestion_incidentes;
USE gestion_incidentes;

-- 1. Tabla de Empresas (Tenants)
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabla de Usuarios (Con soporte de Roles Múltiples y vinculación de Admin)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(255) DEFAULT 'analyst',
    company_id INT DEFAULT NULL,
    codigo_admin VARCHAR(20) DEFAULT NULL UNIQUE,
    vinculado_a_admin_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (vinculado_a_admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 3. Tabla de Incidentes (ISO/IEC 27001:2022 A.5.24 - A.5.28)
CREATE TABLE IF NOT EXISTS incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    probability VARCHAR(50) NOT NULL,
    impact VARCHAR(50) NOT NULL,
    risk VARCHAR(50) NOT NULL,
    classification VARCHAR(100) NOT NULL,
    affected_assets TEXT DEFAULT NULL,                -- ISO 27001 A.5.24: Activos Afectados
    evidence_hash VARCHAR(64) DEFAULT NULL,            -- ISO 27001 A.5.28: Evidencia Digital (Hash)
    lessons_learned TEXT DEFAULT NULL,                 -- ISO 27001 A.5.27: Lecciones Aprendidas
    company_id INT DEFAULT NULL,                       -- SaaS Aislamiento (Tenant)
    admin_id INT DEFAULT NULL,
    reporter VARCHAR(100) NOT NULL,
    assignee VARCHAR(100) DEFAULT 'Sin Asignar',
    status VARCHAR(50) DEFAULT 'Abierto',
    mitigation_plan TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 4. Historial de Auditoría de cada Incidente (ISO 27001 Trazabilidad)
CREATE TABLE IF NOT EXISTS incident_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user VARCHAR(100) NOT NULL,
    action TEXT NOT NULL,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE
);

-- 5. Progreso de Capacitación Checklist (ISO 27001 A.7.2)
CREATE TABLE IF NOT EXISTS training_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    check_video TINYINT(1) DEFAULT 0,
    check_policy TINYINT(1) DEFAULT 0,
    check_assets TINYINT(1) DEFAULT 0,
    check_incidents TINYINT(1) DEFAULT 0,
    check_access TINYINT(1) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. Sesiones de Capacitación Registradas (ISO 27001 Concientización)
CREATE TABLE IF NOT EXISTS training_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    instructor VARCHAR(255) NOT NULL,
    attendees INT DEFAULT 0,
    topics TEXT NOT NULL,
    admin_id INT DEFAULT NULL,
    company_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- 7. Reuniones de Implementación (ISO 27001 Despliegue del SGSI)
CREATE TABLE IF NOT EXISTS impl_meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    responsible VARCHAR(255) NOT NULL,
    controls VARCHAR(255) NOT NULL,
    status VARCHAR(100) NOT NULL,
    notes TEXT NOT NULL,
    admin_id INT DEFAULT NULL,
    company_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- 8. Checklist de Controles ISO 27001 (ISO 27001 Estado de Controles)
CREATE TABLE IF NOT EXISTS impl_controls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    control_id VARCHAR(100) NOT NULL,
    status VARCHAR(100) NOT NULL,
    last_revised TIMESTAMP NULL DEFAULT NULL,
    admin_id INT DEFAULT NULL,
    company_id INT DEFAULT NULL,
    UNIQUE(control_id, company_id),
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- 9. Reuniones y Actas de Auditoría (ISO 27001 A.5.35 & Mejora Continua)
CREATE TABLE IF NOT EXISTS audit_meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    type VARCHAR(100) NOT NULL,
    responsible VARCHAR(255) NOT NULL,
    topics TEXT NOT NULL,
    findings TEXT NOT NULL,
    status VARCHAR(100) NOT NULL,
    admin_id INT DEFAULT NULL,
    company_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- ================================================================================
-- SEMILLA DE DATOS (Super Administrador y Empresas de Prueba)
-- ================================================================================

-- Insertar empresas iniciales de prueba (ISO 27001 Tenants)
INSERT INTO companies (id, name) VALUES (1, 'SGI Global');
INSERT INTO companies (id, name) VALUES (2, 'Secure Web Corp');
INSERT INTO companies (id, name) VALUES (3, 'Innova Tech');

-- Insertar Super-Usuario Santiago (Rol Super Admin)
INSERT INTO users (username, email, password_hash, role, company_id, codigo_admin) 
VALUES (
    'santiago',
    'santiago@sgi.com',
    '$2y$10$K9vG8z6yUQzjD3jEw5m6tOqvGzB6v2ZsRz5aKpN9V9aL7cU8eK1bG',
    '["super_admin"]',
    1,
    'SGI-SUPER'
);

INSERT INTO users (username, email, password_hash, role, company_id, codigo_admin)
VALUES (
    'antonio',
    'antonio@sgi.com',
    '$2y$10$YH7YfM9FEnVuN5mZxkK3UeGnPxEzzsKYa2V6U6ZKUeEdTYlW5W2xO',
    '["super_admin"]',
    1,
    'SGI-SUPER-ANTONIO'
);