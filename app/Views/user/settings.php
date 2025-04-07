<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ajustes de Perfil</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; background-color: #f0f0f0; }
        .settings-container { max-width: 700px; margin: auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        h2 { text-align: center; color: #333; }
        form { display: flex; flex-direction: column; gap: 15px; }
        input, select { padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 1em; }
        button { padding: 10px; background-color: #28a745; color: white; font-size: 1em; border: none; border-radius: 5px; cursor: pointer; }
        .error, .message { margin-bottom: 10px; font-size: 0.9em; }
        .error { color: red; }
        .message { color: green; }
    </style>
</head>
<body>

    <div class="settings-container">
        <h2>Ajustes de Perfil</h2>

        <?php if (session()->getFlashdata('errors')): ?>
            <div class="error">
                <ul>
                    <?php foreach (session()->getFlashdata('errors') as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('message')): ?>
            <div class="message"><?= esc(session()->getFlashdata('message')) ?></div>
        <?php endif; ?>

        <form action="<?= site_url('user/settings') ?>" method="post">
            <?= csrf_field() ?>

            <input type="text" name="user_first_name" placeholder="Nombre" value="<?= esc($user['user_first_name']) ?>" required>
            <input type="text" name="user_last_name" placeholder="Apellidos" value="<?= esc($user['user_last_name']) ?>" required>
            <input type="text" name="user_nickname" placeholder="Usuario" value="<?= esc($user['user_nickname']) ?>" required>
            <input type="number" name="user_age" placeholder="Edad" value="<?= esc($user['user_age']) ?>" required>

            <select name="user_gender" required>
                <option value="">Selecciona tu género</option>
                <option value="male" <?= $user['user_gender'] === 'male' ? 'selected' : '' ?>>Hombre</option>
                <option value="female" <?= $user['user_gender'] === 'female' ? 'selected' : '' ?>>Mujer</option>
                <option value="other" <?= $user['user_gender'] === 'other' ? 'selected' : '' ?>>Otro</option>
            </select>

            <hr>

            <label for="new_password">Cambiar contraseña (opcional)</label>
            <input type="password" name="new_password" placeholder="Nueva contraseña">
            <input type="password" name="confirm_password" placeholder="Confirmar nueva contraseña">

            <button type="submit">Guardar cambios</button>
        </form>
    </div>

</body>
</html>
