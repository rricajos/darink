<ul class="navbar-menu" id="main-menu">

  <?php if (session()->get('isLoggedIn')): ?>

    <li style="--i:0"><a href="<?= site_url('/user/dashboard') ?>">DashBoard</a></li>
    <li style="--i:0"><a href="<?= site_url('/user/profile') ?>">Perfil</a></li>
    <li style="--i:1"><a href="<?= site_url('/user/settings') ?>">Ajustes</a></li>
    <li style="--i:2"><a href="<?= site_url('/auth/signout') ?>">Salir</a></li>

  <?php else: ?>

    <li style="--i:0"><a href="<?= site_url('/signin') ?>">Iniciar sesión</a></li>
    <li style="--i:1"><a href="<?= site_url('/signup') ?>">Registrarse</a></li>
    <li style="--i:2"><a href="<?= site_url('/demo') ?>">Probar Demo</a></li>

  <?php endif; ?>

</ul>
