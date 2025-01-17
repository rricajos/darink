/darink
│
├── /assets              # Archivos estáticos como imágenes, CSS y JS
│   ├── /css
│   ├── /images
│   └── /js
│
├── /controllers         # Archivos PHP que manejan las operaciones con los modelos
│   ├── UserController.php
│   ├── LunchController.php
│   └── LightController.php
│
├── /models              # Modelos para interactuar con la base de datos
│   ├── MySQL.php        # Conexión a la base de datos (Singleton)
│   ├── User.php         # Modelo de usuario
│   ├── Lunch.php        # Modelo de comida (almuerzo)
│   ├── Food.php         # Modelo de comida individual
│   └── Light.php        # Modelo de evaluación (semáforo)
│
├── /views               # Vistas del front-end
│   ├── index.php        # Página principal de la SPA
│   ├── perfil.php       # Página de perfil del usuario
│   ├── lunches.php      # Página que muestra las comidas registradas
│   ├── addLunch.php     # Formulario para añadir una nueva comida
│   ├── lightForm.php    # Formulario para evaluar las comidas
│   └── login.php        # Página de inicio de sesión
│
├── /config              # Configuración del proyecto
│   └── config.php       # Archivo con la configuración de la base de datos y otras configuraciones
│
└── .htaccess            # Archivo de configuración de Apache (si estás usando Apache)
