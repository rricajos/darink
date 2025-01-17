<?php

// Definir configuraciones globales de la aplicación

// Configuración de la base de datos
define('DB_HOST', 'localhost');         // Dirección del servidor de base de datos
define('DB_USER', 'root');              // Usuario para conectar a la base de datos
define('DB_PASS', '');                  // Contraseña del usuario
define('DB_NAME', 'darink');            // Nombre de la base de datos

// Configuración de la aplicación
define('APP_URL', 'http://localhost/darinka'); // URL base de la aplicación
define('APP_NAME', 'Mi App de Comidas');     // Nombre de la aplicación

// Configuración de correo electrónico (si es necesario para la app)
define('MAIL_HOST', 'smtp.mailtrap.io');
define('MAIL_PORT', 2525);
define('MAIL_USER', 'tu_usuario');
define('MAIL_PASS', 'tu_contraseña');

// Configuración de la zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires'); // Ajusta según tu zona horaria

// Configuración de sesión
ini_set('session.cookie_lifetime', 86400);  // Duración de la sesión en segundos (24 horas)
ini_set('session.gc_maxlifetime', 86400);    // Duración máxima del garbage collector de sesiones

// Configuración de errores y depuración
ini_set('display_errors', 1); // Mostrar errores (solo en desarrollo)
error_reporting(E_ALL); // Reportar todos los errores

// Configuración de seguridad
define('SECRET_KEY', 'mi_clave_secreta'); // Usada para firmar tokens o sesiones seguras

?>
