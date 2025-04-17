

<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<section class="super-flex-column-nowrap" style="align-items: center; gap: var(--spacing-md); padding: var(--spacing-lg);">

<h2 style="font-size: var(--font-size-xl);">Crear cuenta</h2>

<?php if (session()->getFlashdata('errors')): ?>
    <div class="flash flash-error">
        <ul>
            <?php foreach (session()->getFlashdata('errors') as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach ?>
        </ul>
    </div>
<?php endif; ?>

<form action="<?= site_url('auth/signup') ?>" method="POST" class="form-auth flex-column-nowrap" style="gap: var(--spacing-sm);">

    <?= csrf_field() ?>

    <input type="text" name="user_first_name" placeholder="Nombre" value="<?= old('user_first_name') ?>" required class="input">
    <input type="text" name="user_last_name" placeholder="Apellidos" value="<?= old('user_last_name') ?>" required class="input">
    <input type="text" name="user_nickname" placeholder="Usuario" value="<?= old('user_nickname') ?>" required class="input">
    <input type="email" name="user_email" placeholder="Correo electrónico" value="<?= old('user_email') ?>" required class="input">
    <input type="password" name="user_password" placeholder="Contraseña" required class="input">
    <input type="number" name="user_age" placeholder="Edad" value="<?= old('user_age') ?>" required class="input">

    <select name="user_gender" required class="input">
        <option value="">Selecciona tu género</option>
        <option value="male" <?= old('user_gender') === 'male' ? 'selected' : '' ?>>Hombre</option>
        <option value="female" <?= old('user_gender') === 'female' ? 'selected' : '' ?>>Mujer</option>
        <option value="other" <?= old('user_gender') === 'other' ? 'selected' : '' ?>>Otro</option>
    </select>

    <button type="submit" class="btn-primary">Registrarse</button>
</form>
</section>

<style>
  .flash {
    padding: var(--spacing-sm);
    border-radius: var(--border-radius-sm);
    font-size: var(--font-size-sm);
    width: 100%;
    max-width: 400px;
    text-align: left;
  }

  .flash-error {
    background-color: #ffe5e5;
    color: #d10000;
  }

  .form-auth {
    width: 100%;
    max-width: 400px;
    display: flex;
  }

  .input {
    padding: var(--spacing-sm);
    font-size: var(--font-size-md);
    border: 1px solid var(--color-muted);
    border-radius: var(--border-radius-sm);
    width: 100%;
  }

  .input:focus {
    outline: none;
    border-color: var(--color-primary);
  }

  .btn-primary {
    padding: var(--spacing-sm);
    background-color: var(--color-primary);
    color: white;
    border: none;
    border-radius: var(--border-radius-sm);
    font-size: var(--font-size-md);
    cursor: pointer;
    transition: background-color 0.2s ease-in-out;
  }

  .btn-primary:hover {
    background-color: #005fcc;
  }
</style>


    <?= $this->endSection() ?>
