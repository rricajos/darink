

<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

    <h2>Crear cuenta</h2>

    <?php if (session()->getFlashdata('errors')): ?>
        <div class="error">
            <ul>
                <?php foreach (session()->getFlashdata('errors') as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="<?= site_url('signup') ?>" method="post">
        <?= csrf_field() ?>

        <input type="text" name="user_first_name" placeholder="Nombre" value="<?= old('user_first_name') ?>" required>
        <input type="text" name="user_last_name" placeholder="Apellidos" value="<?= old('user_last_name') ?>" required>
        <input type="text" name="user_nickname" placeholder="Usuario" value="<?= old('user_nickname') ?>" required>
        <input type="email" name="user_email" placeholder="Correo electrónico" value="<?= old('user_email') ?>" required>
        <input type="password" name="user_password" placeholder="Contraseña" required>
        <input type="number" name="user_age" placeholder="Edad" value="<?= old('user_age') ?>" required>
        <select name="user_gender" required>
            <option value="">Selecciona tu género</option>
            <option value="male" <?= old('user_gender') === 'male' ? 'selected' : '' ?>>Hombre</option>
            <option value="female" <?= old('user_gender') === 'female' ? 'selected' : '' ?>>Mujer</option>
            <option value="other" <?= old('user_gender') === 'other' ? 'selected' : '' ?>>Otro</option>
        </select>

        <button type="submit">Registrarse</button>
    </form>

    <?= $this->endSection() ?>
