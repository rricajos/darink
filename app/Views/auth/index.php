


<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php echo base_url() ?>

<h1>Bienvenido 👋</h1>
<p>Elige entre iniciar sesión, registrarte o conocer los planes:</p>
<h1>Bienvenido 👋</h1>

<div class="auth-links">
    <a href="<?= site_url('signin') ?>">Iniciar sesión</a> |
    <a href="<?= site_url('signup') ?>">Registrarse</a>
</div>

<h2>Elige tu plan</h2>
<div class="plans">
    <div class="plan">
        <h3>Gratis</h3>
        <p class="plan-price">0€</p>
        <ul>
            <li>Acceso limitado</li>
            <li>Funcionalidades básicas</li>
            <li>Soporte comunitario</li>
        </ul>
    </div>
    <div class="plan">
        <h3>Personal</h3>
        <p class="plan-price">4,99€/mes</p>
        <ul>
            <li>Acceso completo</li>
            <li>Personalización de cuenta</li>
            <li>Soporte por email</li>
        </ul>
    </div>
    <div class="plan">
        <h3>Profesional</h3>
        <p class="plan-price">49,99€/mes</p>
        <ul>
            <li>Todo incluido</li>
            <li>Acceso a herramientas premium</li>
            <li>Soporte prioritario 24/7</li>
        </ul>
    </div>
</div>

<div class="support-form">
    <h2>¿Necesitas ayuda?</h2>
    <form action="<?= site_url('support/request') ?>" method="post">
        <input type="text" name="name" placeholder="Tu nombre" required>
        <input type="email" name="email" placeholder="Tu email" required>
        <textarea name="message" rows="4" placeholder="Cuéntanos tu problema o duda..." required></textarea>
        <button type="submit">Enviar solicitud</button>
    </form>
</div>

<?= $this->endSection() ?>