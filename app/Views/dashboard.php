<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <h2>Bienvenido, <?= session()->get('username') ?></h2>
    <a href="<?= site_url('auth/logout') ?>">Cerrar Sesión</a>
</body>
</html>
