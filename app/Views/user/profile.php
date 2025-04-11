<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<section class="profile-container super-padding">
    <header class="super-flex super-justify-between super-align-center">
        <h2 class="super-title">Mi Perfil</h2>
        <nav class="profile-actions super-flex super-gap">
            <a href="<?= site_url('user/settings') ?>" class="super-button">Editar perfil</a>
            <a href="<?= site_url('auth/signout') ?>" class="super-button danger">Cerrar sesión</a>
        </nav>
    </header>

    <div class="super-profile-info super-grid grid-auto-fit-min200 super-gap super-padding">
        <div class="profile-item">
            <span class="label">Nombre</span>
            <p><?= esc($user['user_first_name']) ?> <?= esc($user['user_last_name']) ?></p>
        </div>
        <div class="profile-item">
            <span class="label">Usuario</span>
            <p><?= esc($user['user_nickname']) ?></p>
        </div>
        <div class="profile-item">
            <span class="label">Correo</span>
            <p><?= esc($user['user_email']) ?></p>
        </div>
        <div class="profile-item">
            <span class="label">Edad</span>
            <p><?= esc($user['user_age']) ?> años</p>
        </div>
        <div class="profile-item">
            <span class="label">Género</span>
            <p><?= esc(ucfirst($user['user_gender'])) ?></p>
        </div>
        <div class="profile-item">
            <span class="label">Miembro desde</span>
            <p><?= date('d/m/Y', strtotime($user['user_created_at'])) ?></p>
        </div>
    </div>
</section>
<style>
    .profile-container {
    max-width: 800px;
    margin: 0 auto;
}

.profile-info {
    background-color: var(--background-alt);
    border-radius: var(--radius);
    padding: var(--spacing-md);
}

.profile-item .label {
    font-weight: bold;
    color: var(--primary);
    display: block;
    margin-bottom: 0.25rem;
}

.super-button {
    background-color: var(--primary);
    color: #fff;
    padding: 0.5rem 1rem;
    border-radius: var(--radius);
    text-decoration: none;
    transition: background 0.3s ease;
}

.super-button:hover {
    background-color: var(--primary-dark);
}

.super-button.danger {
    background-color: var(--danger);
}

.super-button.danger:hover {
    background-color: var(--danger-dark);
}

</style>
<?= $this->endSection() ?>
