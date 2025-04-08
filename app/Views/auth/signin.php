<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<section class="super-flex-column-nowrap" style="align-items: center; gap: var(--spacing-md); padding: var(--spacing-lg);">

  <h2 style="font-size: var(--font-size-xl);">Iniciar sesión</h2>

  <?php if (session()->getFlashdata('error')): ?>
      <div class="flash flash-error">
          <?= esc(session()->getFlashdata('error')) ?>
      </div>
  <?php endif; ?>

  <?php if (session()->getFlashdata('message')): ?>
      <div class="flash flash-message">
          <?= esc(session()->getFlashdata('message')) ?>
      </div>
  <?php endif; ?>

  <form action="<?= site_url('signin') ?>" method="post" class="form-auth flex-column-nowrap" style="gap: var(--spacing-sm);">

      <?= csrf_field() ?>

      <input
          type="email"
          name="user_email"
          placeholder="Correo electrónico"
          value="<?= old('user_email') ?>"
          required
          class="input"
      >

      <input
          type="password"
          name="user_password"
          placeholder="Contraseña"
          required
          class="input"
      >

      <button type="submit" class="btn-primary">Entrar</button>

  </form>
</section>
<style>
  .flash {
    padding: var(--spacing-sm);
    border-radius: var(--border-radius-sm);
    font-size: var(--font-size-sm);
    width: 100%;
    max-width: 400px;
    text-align: center;
  }

  .flash-error {
    background-color: #ffe5e5;
    color: #d10000;
  }

  .flash-message {
    background-color: #e6ffe6;
    color: #007a00;
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


