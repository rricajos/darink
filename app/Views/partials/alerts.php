<?php if (session()->getFlashdata('error')): ?>
  <div class="flash flash-error"><?= esc(session('error')) ?></div>
<?php endif ?>

<?php if (session()->getFlashdata('message')): ?>
  <div class="flash flash-message"><?= esc(session('message')) ?></div>
<?php endif ?>

<?php if ($errors = session()->getFlashdata('errors')): ?>
  <div class="flash flash-error">
    <ul>
      <?php foreach ((array) $errors as $error): ?>
        <li><?= esc($error) ?></li>
      <?php endforeach ?>
    </ul>
  </div>
<?php endif ?>


<style>
  .alert {
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
    font-weight: bold;
  }

  .alert-success {
    background: #d4edda;
    color: #155724;
  }

  .alert-danger {
    background: #f8d7da;
    color: #721c24;
  }

  .alert-warning {
    background: #fff3cd;
    color: #856404;
  }
</style>