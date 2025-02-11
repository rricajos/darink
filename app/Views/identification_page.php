<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro e Inicio de Sesión - Darink</title>

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css?family=Rubik:300,400,500" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="css/bootstrap.min.css">

    <!-- Themify Icons -->
    <link rel="stylesheet" href="css/themify-icons.css">

    <!-- Custom Styles -->
    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            background: linear-gradient(to right, #6a11cb, #2575fc);
            font-family: 'Rubik', sans-serif;
        }

        .auth-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .auth-container h2 {
            font-weight: 500;
            margin-bottom: 20px;
        }

        .form-group {
            text-align: left;
        }

        .form-control {
            border-radius: 30px;
            padding: 10px 15px;
        }

        .btn-auth {
            width: 100%;
            border-radius: 30px;
            padding: 10px;
            font-weight: bold;
            background: linear-gradient(to right, #6a11cb, #2575fc);
            border: none;
            color: white;
        }

        .btn-auth:hover {
            background: linear-gradient(to right, #5d0eb7, #1e5ed6);
        }

        .switch-auth {
            margin-top: 15px;
        }

        .switch-auth a {
            color: #2575fc;
            font-weight: bold;
            text-decoration: none;
        }

        .social-login {
            margin-top: 20px;
        }

        .social-login a {
            display: inline-block;
            margin: 5px;
            padding: 10px 15px;
            border-radius: 50%;
            color: white;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .social-login .facebook {
            background: #3b5998;
        }

        .social-login .google {
            background: #db4437;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="auth-container">
            <h2>Iniciar Sesión</h2>
            <form>
                <div class="form-group">
                    <label for="email">Correo Electrónico</label>
                    <input type="email" id="email" class="form-control" placeholder="Ingresa tu correo" required>
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" class="form-control" placeholder="Ingresa tu contraseña"
                        required>
                </div>
                <button type="submit" class="btn btn-auth">Iniciar Sesión</button>
            </form>

            <div class="switch-auth">
                ¿No tienes cuenta? <a href="registro.html">Regístrate aquí</a>
            </div>

            <div class="social-login">
                <p>O inicia sesión con</p>
                <a href="#" class="facebook"><span class="ti-facebook"></span></a>
                <a href="#" class="google"><span class="ti-google"></span></a>
            </div>
        </div>
    </div>

</body>

</html>
