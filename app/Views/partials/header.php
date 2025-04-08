<header id="main-header" class="super-flex-row-nowrap">
  <nav id="main-navbar" class="flex-row-nowrap" role="navigation" aria-label="Menú principal"
    style="justify-content: space-between; align-items: center; width: 100%;">

    <div class="navbar-brand">
      <a href="<?= site_url('/') ?>">Darink.App</a>
    </div>

    <button class="navbar-toggle" id="menu-toggle" aria-label="Abrir menú" aria-expanded="false"
      aria-controls="main-menu">
      <span class="bar"></span>
      <span class="bar"></span>
      <span class="bar"></span>
      <span class="sr-only">Abrir menú</span>
    </button>

    <div class="overlay" id="menu-overlay"></div>

    <ul class="navbar-menu" id="main-menu">
      <?php if (session()->get('isLoggedIn')): ?>
        <li style="--i:0"><a href="<?= site_url('/user/profile') ?>">Perfil</a></li>
        <li style="--i:1"><a href="<?= site_url('/user/settings') ?>">Ajustes</a></li>
        <li style="--i:2"><a href="<?= site_url('/auth/signout') ?>">Salir</a></li>
      <?php else: ?>
        <li style="--i:0"><a href="<?= site_url('/signin') ?>">Iniciar sesión</a></li>
        <li style="--i:1"><a href="<?= site_url('/signup') ?>">Registrarse</a></li>
      <?php endif; ?>
    </ul>

  </nav>
</header>

<style>
  :root {
    --header-height: 70px;
  }

  #main-header {
    position: sticky;
    top: 0;
    z-index: 30;
    padding: var(--spacing-md);
    font-size: var(--font-size-lg);
    background-color: var(--color-bg);
    border-bottom: 1px solid var(--color-muted);
    height: var(--header-height);
    transition: background-color 0.3s ease, backdrop-filter 0.3s ease, box-shadow 0.3s ease;
    border-bottom: 2px solid var(--color-muted);
;
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
    flex-direction: row;
    gap: var(--spacing-md);
  }

  .navbar-menu li a {
    text-decoration: none;
    color: var(--color-text);
    font-size: var(--font-size-md);
  }

  .navbar-toggle {
    display: none;
    background: none;
    border: none;
    cursor: pointer;
    width: 30px;
    height: 24px;
    position: relative;
    z-index: 31;
    margin-right: var(--spacing-xs);
  }

  .navbar-toggle .bar {
    display: block;
    position: absolute;
    width: 100%;
    height: 4px;
    background-color: var(--color-bg);
    border-radius: 4px;
    transition: all 0.2s ease;
  }

  .navbar-toggle .bar:nth-child(1) {
    top: 0;
  }

  .navbar-toggle .bar:nth-child(2) {
    top: 50%;
    transform: translateY(-50%);
  }

  .navbar-toggle .bar:nth-child(3) {
    bottom: 0;
  }

  .navbar-toggle.open .bar:nth-child(1) {
    transform: rotate(45deg);
    top: 50%;
  }

  .navbar-toggle.open .bar:nth-child(2) {
    opacity: 0;
  }

  .navbar-toggle.open .bar:nth-child(3) {
    transform: rotate(-45deg);
    bottom: auto;
    top: 50%;
  }

  .sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    border: 0;
  }

  .overlay {
    position: fixed;
    top: var(--header-height);
    left: 0;
    right: 0;
    bottom: 0;
    backdrop-filter: blur(6px);
    background-color: rgba(0, 0, 0, 0.1);
    z-index: 15;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
  }

  .overlay.active {
    opacity: 1;
    pointer-events: auto;
  }

  @media (max-width: 768px) {
    #main-header{
      background-color: var(--color-primary);
      
    }
    .navbar-menu {
      flex-direction: column;
      gap: var(--spacing-sm);
      position: fixed;
      top: var(--header-height);
      left: -100%;
      height: calc(100vh - var(--header-height));
      width: 70%;
      max-width: 300px;
      background-color: var(--color-bg);
      padding: var(--spacing-md);
      
      transition: left 0.3s ease;
      z-index: 20;
     
    }

    .navbar-menu.menu-open {
      left: 0;
    }

    .navbar-menu li {
      opacity: 0;
      transform: translateX(-30px);
      animation: slideInLeft 0.3s forwards;
      animation-delay: calc(0.1s * var(--i));
    }

    .navbar-menu.menu-open li {
      animation: slideInLeft 0.3s forwards;
    }

    @keyframes slideInLeft {
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    .navbar-toggle {
      display: block;
    }

    .navbar-brand a {
      color: var(--color-bg);
    }
  }


</style>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const toggleButton = document.getElementById('menu-toggle');
    const menu = document.getElementById('main-menu');
    const overlay = document.getElementById('menu-overlay');

    function toggleMenu() {
      const isOpen = menu.classList.toggle('menu-open');
      toggleButton.setAttribute('aria-expanded', isOpen);
      overlay.classList.toggle('active', isOpen);
      toggleButton.classList.toggle('open');

      if (isOpen) {
        menu.querySelectorAll('li').forEach((li, index) => {
          li.style.animation = 'none';
          void li.offsetWidth;
          li.style.animation = `slideInLeft 0.3s forwards`;
          li.style.animationDelay = `${0.1 * index}s`;
        });
      }
    }

    toggleButton.addEventListener('click', toggleMenu);
    overlay.addEventListener('click', toggleMenu);
  });
</script>
