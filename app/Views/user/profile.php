<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="profile-container">
    <h2>Mi Perfil</h2>

    <ul class="info-list">
        <li><span class="label">Nombre:</span> <?= esc($user['user_first_name']) ?> <?= esc($user['user_last_name']) ?>
        </li>
        <li><span class="label">Usuario:</span> <?= esc($user['user_nickname']) ?></li>
        <li><span class="label">Correo:</span> <?= esc($user['user_email']) ?></li>
        <li><span class="label">Edad:</span> <?= esc($user['user_age']) ?> años</li>
        <li><span class="label">Género:</span> <?= esc(ucfirst($user['user_gender'])) ?></li>
        <li><span class="label">Miembro desde:</span> <?= date('d/m/Y', strtotime($user['user_created_at'])) ?></li>
    </ul>

    <div class="actions">
        <a href="<?= site_url('user/settings') ?>">Editar perfil</a>
        <a href="<?= site_url('auth/signout') ?>" class="logout">Cerrar sesión</a>
    </div>
</div>


<?= $this->endSection() ?>