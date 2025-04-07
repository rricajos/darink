<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<h2>Iniciar sesión</h2>

<?php if (session()->getFlashdata('error')): ?>
    <div class="error"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<?php if (session()->getFlashdata('message')): ?>
    <div class="message" style="color: green;"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif; ?>

<form action="<?= site_url('signin') ?>" method="post">
    <?= csrf_field() ?>

    <input type="email" name="user_email" placeholder="Correo electrónico" value="<?= old('user_email') ?>" required>
    <input type="password" name="user_password" placeholder="Contraseña" required>

    <button type="submit">Entrar</button>
</form>


<?= $this->endSection() ?>