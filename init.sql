CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nickname VARCHAR(64) UNIQUE NOT NULL,
    firstname VARCHAR(128) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    phone_prefix VARCHAR(4),  -- Permite códigos como "+1"
    phone_number VARCHAR(16), -- Soporta formatos telefónicos internacionales
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',

    -- Índices adicionales para optimizar consultas
    INDEX (nickname),
    INDEX (email),
    INDEX (phone_number)
);

CREATE TABLE lunchs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    coordinates POINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    -- Índice espacial para mejorar búsquedas por coordenadas
    SPATIAL INDEX (coordinates)
);

CREATE TABLE foods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lunch_id INT NOT NULL, 
    title VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lunch_id) REFERENCES lunchs(id) ON DELETE CASCADE
);
