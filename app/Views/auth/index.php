<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<main class="super-main grid-auto-fit-256px"
    style="align-items: center; justify-content: space-between; gap: var(--spacing-xl); padding: var(--spacing-xl);">

    <!-- Columna izquierda -->
    <div class="flex-column-nowrap" style="gap: var(--spacing-lg); max-width: 400px;">

        <h1 style="font-size: var(--font-size-xxl); color: var(--color-primary);">
            Sé compasivo y paciente...
        </h1>

        <p style="font-size: var(--font-size-md);">
            Tu espacio seguro para construir una relación más sana con la comida.
            En Darink.App, te acompañamos paso a paso en el proceso de sanar y nutrir tu cuerpo y mente con empatía y
            conocimiento.
        </p>

        <!-- Beneficios -->
        <ul
            style="display: flex; flex-direction: column; gap: var(--spacing-sm); font-size: var(--font-size-md); list-style: disc;">
            <li>🍎 Hora de reconectar con tu cuerpo.</li>
            <li>🧘 Tu bitacora emocional-nutricional.</li>
            <li>🤝 Ideal para el acompañamiento profesional.</li>
        </ul>

    </div>

    <!-- Imagen derecha -->
    <div class="image-wrapper" style="max-width: 300px; width: 100%;">
        <img src="<?= base_url('images/dualphoneeee.png') ?>" alt="Ilustración alimentación consciente"
            style="width: 100%; border-radius: var(--border-radius-md); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">
    </div>

</main>
<style>

</style>

<?= $this->endSection() ?>