<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Perfil</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; background-color: #f4f4f4; }
        .profile-container { max-width: 700px; margin: auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        h2 { text-align: center; color: #333; }
        .info-list { list-style: none; padding: 0; }
        .info-list li { margin-bottom: 12px; font-size: 1.1em; }
        .label { font-weight: bold; color: #555; }
        .actions { margin-top: 30px; text-align: center; }
        .actions a { margin: 0 10px; text-decoration: none; color: white; background: #007BFF; padding: 10px 20px; border-radius: 6px; display: inline-block; }
        .actions a.logout { background: #dc3545; }
    </style>
</head>
<body>

    <div class="profile-container">
        <h2>Mi Perfil</h2>

        <ul class="info-list">
            <li><span class="label">Nombre:</span> <?= esc($user['user_first_name']) ?> <?= esc($user['user_last_name']) ?></li>
            <li><span class="label">Usuario:</span> <?= esc($user['user_nickname']) ?></li>
            <li><span class="label">Correo:</span> <?= esc($user['user_email']) ?></li>
            <li><span class="label">Edad:</span> <?= esc($user['user_age']) ?> años</li>
            <li><span class="label">Género:</span> <?= esc(ucfirst($user['user_gender'])) ?></li>
            <li><span class="label">Miembro desde:</span> <?= date('d/m/Y', strtotime($user['user_created_at'])) ?></li>
        </ul>

        <div class="actions">
            <a href="<?= site_url('user/settings') ?>">Editar perfil</a>
            <a href="<?= site_url('auth/signout') ?>" class="logout">Cerrar sesión</a>
        </div>
    </div>

</body>
</html>
