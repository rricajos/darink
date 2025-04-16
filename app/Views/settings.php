<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

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

        <input type="text" name="user_first_name" placeholder="Nombre" value="<?= esc($user['user_first_name']) ?>"
            required>
        <input type="text" name="user_last_name" placeholder="Apellidos" value="<?= esc($user['user_last_name']) ?>"
            required>
        <input type="text" name="user_nickname" placeholder="Usuario" value="<?= esc($user['user_nickname']) ?>"
            required>
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


<?= $this->endSection() ?>