CREATE DATABASE IF NOT EXISTS gestion_incidentes;
USE gestion_incidentes;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(255) DEFAULT 'analyst',
    empresa VARCHAR(100) DEFAULT NULL,
    codigo_admin VARCHAR(20) DEFAULT NULL UNIQUE,
    vinculado_a_admin_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vinculado_a_admin_id) REFERENCES users(id) ON DELETE SET NULL
);
INSERT INTO users (username, email, password_hash, role, empresa, codigo_admin) 
VALUES (
    'santiago', 
    'santiago@sgi.com', 
    '$2y$10$fN1aC9kI3J5r9v3L.wO6mO/G2C5B5m3E6F7x9X8Y2D1A3B4C5D6E', 
    '["super_admin"]', 
    'SGI Global', 
    'SGI-SUPER'
);