<header>
  <nav class="navbar">
    <div class="navbar-brand">
      <a href="<?= site_url('/') ?>">MiApp</a>
    </div>
    <ul class="navbar-menu">
      <?php if (session()->get('isLoggedIn')): ?>
        <li><a href="<?= site_url('/user/profile') ?>">Perfil</a></li>
        <li><a href="<?= site_url('/user/settings') ?>">Ajustes</a></li>
        <li><a href="<?= site_url('/auth/signout') ?>">Salir</a></li>
      <?php else: ?>
        <li><a href="<?= site_url('/signin') ?>">Iniciar sesión</a></li>
        <li><a href="<?= site_url('/signup') ?>">Registrarse</a></li>
      <?php endif; ?>
    </ul>
    <button class="navbar-toggle">☰</button>
  </nav>
</header>