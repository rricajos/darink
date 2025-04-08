<header id="main-header" class="super-flex-row-nowrap">
  <nav id="main-navbar" class="flex-row-nowrap" style="justify-content: space-between; align-items: center; width: 100%;">
    
    <div class="navbar-brand">
      <a href="<?= site_url('/') ?>">Darink.App</a>
    </div>

    <ul class="navbar-menu flex-row-nowrap" style="gap: var(--spacing-md); align-items: center;">
      <?php if (session()->get('isLoggedIn')): ?>
        <li><a href="<?= site_url('/user/profile') ?>">Perfil</a></li>
        <li><a href="<?= site_url('/user/settings') ?>">Ajustes</a></li>
        <li><a href="<?= site_url('/auth/signout') ?>">Salir</a></li>
      <?php else: ?>
        <li><a href="<?= site_url('/signin') ?>">Iniciar sesión</a></li>
        <li><a href="<?= site_url('/signup') ?>">Registrarse</a></li>
      <?php endif; ?>
    </ul>

    <button class="navbar-toggle" aria-label="Abrir menú">☰</button>
  </nav>
</header>
<style>
  #main-header {
    padding: var(--spacing-md);
    font-size: var(--font-size-lg);
    background-color: var(--color-bg);
    border-bottom: 1px solid var(--color-muted);
  }

  .navbar-brand a {
    text-decoration: none;
    font-weight: bold;
    font-size: var(--font-size-xl);
    color: var(--color-primary);
  }

  .navbar-menu {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    gap: var(--spacing-md);
  }

  .navbar-menu li a {
    text-decoration: none;
    color: var(--color-text);
    font-size: var(--font-size-md);
  }

  .navbar-toggle {
    display: none; /* puedes mostrar esto con media queries */
    background: none;
    border: none;
    font-size: var(--font-size-xl);
    cursor: pointer;
  }

  @media (max-width: 768px) {
    .navbar-menu {
      display: none; /* podrías controlar esto con JS */
      flex-direction: column;
      width: 100%;
    }

    .navbar-toggle {
      display: block;
    }
  }
</style>
