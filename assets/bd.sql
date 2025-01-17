CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,           -- ID del usuario (auto incremento)
    firstName VARCHAR(255) NOT NULL,             -- Nombre del usuario
    lastName VARCHAR(255) NOT NULL,              -- Apellido del usuario
    nickName VARCHAR(255) UNIQUE NOT NULL,       -- Nombre de usuario (único)
    password VARCHAR(255) NOT NULL,              -- Contraseña (almacenada de manera segura)
    age INT NOT NULL,                            -- Edad del usuario
    gender ENUM('male', 'female', 'other') NOT NULL, -- Género del usuario (opciones predefinidas)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha de creación (automáticamente generado)
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP -- Fecha de última actualización
);


CREATE TABLE lunches (
    id INT AUTO_INCREMENT PRIMARY KEY,                              -- ID único para cada registro de lunch
    user_id INT NOT NULL,                                           -- ID del usuario que registró el lunch (relación con users)
    title VARCHAR(255) NOT NULL,                                    -- Título o nombre del lunch (opcional)
    date DATE NOT NULL,                                             -- Fecha del lunch
    time TIME NOT NULL,                                             -- Hora del lunch
    location LOCATION,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,                 -- Fecha y hora de registro del lunch
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE    -- Relación con la tabla `users`
);

CREATE TABLE foods (
    id INT AUTO_INCREMENT PRIMARY KEY,                              -- ID único para cada registro de lunch
    lunch_id INT NOT NULL,
    size ENUM('Huge', 'Big', 'Medium', 'Small', 'Tiny') NOT NULL,   -- Tamaño del food 




















































































































































    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,                 -- Fecha y hora de registro del lunch
    FOREIGN KEY (lunch_id) REFERENCES lunches(id) ON DELETE CASCADE    -- Relación con la tabla `users`
)

CREATE TABLE lights (
    id INT AUTO_INCREMENT PRIMARY KEY,                              -- ID único para cada registro de lunch
    user_id INT NOT NULL,                                           -- ID del usuario que registró el lunch (relación con users)
                     
    traffic_light ENUM('Red', 'Yellow', 'Green') NOT NULL,          -- Semáforo de tráfico (puedes usar valores de texto como 'Red', 'Yellow', 'Green')
    traffic_light_note TEXT,                                        -- Nota sobre el semáforo de tráfico (opcional)
    traffic_light_emoticon VARCHAR(10),                             -- Emoticón asociado al semáforo (opcional)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,                 -- Fecha y hora de registro del lunch
    FOREIGN KEY (food_id) REFERENCES foods(id) ON DELETE CASCADE    -- Relación con la tabla `users`
);
