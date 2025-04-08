<header id="main-header" class="super-flex-row-nowrap">
  <nav id="main-navbar" class="flex-row-nowrap"
    style="justify-content: space-between; align-items: center; width: 100%;">

    <div class="navbar-brand">
      <a href="<?= site_url('/') ?>">Darink.App</a>
    </div>

    <ul class="navbar-menu flex-row-nowrap" id="main-menu" style="gap: var(--spacing-md); align-items: center;">

      <?php if (session()->get('isLoggedIn')): ?>
        <li><a href="<?= site_url('/user/profile') ?>">Perfil</a></li>
        <li><a href="<?= site_url('/user/settings') ?>">Ajustes</a></li>
        <li><a href="<?= site_url('/auth/signout') ?>">Salir</a></li>
      <?php else: ?>
        <li><a href="<?= site_url('/signin') ?>">Iniciar sesión</a></li>
        <li><a href="<?= site_url('/signup') ?>">Registrarse</a></li>
      <?php endif; ?>
    </ul>

    <button class="navbar-toggle" id="menu-toggle" aria-label="Abrir menú">☰</button>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const toggleButton = document.getElementById('menu-toggle');
        const menu = document.getElementById('main-menu');

        toggleButton.addEventListener('click', function () {
          menu.classList.toggle('menu-open');
        });
      });
    </script>
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
    display: none;
    /* puedes mostrar esto con media queries */
    background: none;
    border: none;
    font-size: var(--font-size-xxl);
    cursor: pointer;
  }

  @media (max-width: 768px) {

    .navbar-menu {
      display: none;
      flex-direction: column;
      width: 100%;
      background-color: var(--color-bg);
      padding: var(--spacing-sm);
      position: absolute;
      bottom: 0;
      right: 0;
      z-index: 1;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
      background-color: var(--color-bg);
    }

    .navbar-menu.menu-open {
      display: flex;
    }

    .navbar-toggle {
      padding: var(--spacing-sm);
      display: block;
      position: fixed;
      bottom: 0;
      right: 0;
      z-index: 2;
    }
    #main-header {
      width: 100%;
      padding: var(--spacing-sm);
      display: block;
      position: fixed;
      bottom: 0;
      right: 0;
      z-index: 2;
      margin-bottom: 0;
    }
  }
</style>