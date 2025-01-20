CREATE TABLE users (

    user_id INT AUTO_INCREMENT PRIMARY KEY, 
    user_first_name VARCHAR(63) NOT NULL DEFAULT 'unspecified',
    user_last_name VARCHAR(127) NOT NULL DEFAULT 'unspecified',
    user_nickname VARCHAR(31) UNIQUE NOT NULL,
    user_email VARCHAR(127) UNIQUE NOT NULL,
    user_password VARCHAR(63) NOT NULL,
    user_age INT NOT NULL,
    user_gender ENUM('male', 'female', 'unspecified') NOT NULL DEFAULT 'unspecified',
    user_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

);



CREATE TABLE lunches (

    lunch_id INT AUTO_INCREMENT PRIMARY KEY,
    lunch_tag ENUM('breakfast', 'brunch', 'lunch', 'snack', 'dinner') NOT NULL DEFAULT 'lunch',
    lunch_location ENUM('house', 'work', 'school', 'restaurant', 'other') NOT NULL DEFAULT 'house',
    lunch_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    lunch_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    lunch_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);



CREATE TABLE foods (
    food_id INT AUTO_INCREMENT PRIMARY KEY,
    food_title VARCHAR(255) NOT NULL,
    food_photo VARCHAR(255),
    food_size ENUM('huge', 'big', 'medium', 'small', 'tiny') NOT NULL DEFAULT 'medium',
    food_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    food_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    lunch_id INT NOT NULL,
    FOREIGN KEY (lunch_id) REFERENCES lunches(lunch_id) ON DELETE CASCADE
);


CREATE TABLE emojis (

    emoji_id INT AUTO_INCREMENT PRIMARY KEY,
    emoji_unicode VARCHAR(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci

);

CREATE TABLE lights (

    light_id INT AUTO_INCREMENT PRIMARY KEY,
    light_color ENUM('red', 'yellow', 'green') NOT NULL DEFAULT 'green',
    light_message VARCHAR(511),
    light_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    light_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    food_id INT NOT NULL,
    FOREIGN KEY (food_id) REFERENCES foods(food_id) ON DELETE CASCADE,

    emoji_id INT,
    FOREIGN KEY (emoji_id) REFERENCES emojis(emoji_id) ON DELETE SET NULL
);

