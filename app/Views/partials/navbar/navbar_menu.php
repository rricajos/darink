<ul class="navbar-menu" id="main-menu">

  <?php if (session()->get('isLoggedIn')): ?>

    <li style="--i:0"><a href="<?= site_url('/dashboard') ?>">DashBoard</a></li>
    <li style="--i:0"><a href="<?= site_url('/profile') ?>">Perfil</a></li>
    <li style="--i:1"><a href="<?= site_url('/settings') ?>">Ajustes</a></li>
    <li style="--i:2"><a href="<?= site_url('/auth/signout') ?>">Salir</a></li>

  <?php else: ?>

    <li style="--i:0"><a href="<?= site_url('/auth/signin') ?>">Iniciar sesión</a></li>
    <li style="--i:1"><a href="<?= site_url('/auth/signup') ?>">Registrarse</a></li>
    <li style="--i:2"><a href="<?= site_url('/auth') ?>">Probar Demo</a></li>

  <?php endif; ?>

</ul>
