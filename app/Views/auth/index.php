<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<main class="super-main grid-auto-fit-256px;"
    style="align-items: center; justify-content: space-between; gap: var(--spacing-xl); padding: var(--spacing-md);">


    <!-- Imagen izquierda -->
    <div class="image-wrapper" style="max-width: 340px; width: 100%;">
        <img src="<?= base_url('images/florr.png') ?>" alt="Ilustración alimentación consciente"
            style="width: 100%; border-radius: var(--border-radius-md);" class="floating-image-a">
    </div>

    <!-- Columna derecha -->
    <div class="flex-column-nowrap" style="gap: var(--spacing-lg); max-width: 400px;">

        <h1 style="font-size: var(--font-size-xxl); color: var(--color-primary);">
            Sé compasiva y paciente...
        </h1>

        <p style="font-size: var(--font-size-lg);">
            Paso a paso en el proceso de sanar y nutrir tu cuerpo y mente con empatía y
            conocimiento.
        </p>

        <!-- Beneficios -->
        <ul
            style="display: flex; flex-direction: column; gap: var(--spacing-sm); font-size: var(--font-size-md); list-style: disc;">
            <li>🍎 Hora de reconectar con tu cuerpo.</li>
            <li>🧘 Tu bitacora emocional-nutricional.</li>
            <li>🤍 Ideal para compartir tus pasos.</li>
        </ul>

    </div>



</main>

<section style="margin: var(--spacing-sm);">

    <div class="super-grid-auto-fit-320px;" style="
    padding: var(--spacing-xl); 
    gap: var(--spacing-md); 
    text-align: left; 
    background-color: #f9f9f9; 
    border-radius: var(--border-radius-lg);">




        <div class="image-wrapper" style="max-width: 400px; width: 100%;">
            <img src="<?= base_url('images/tca.png') ?>" alt="Ilustración alimentación consciente"
                style="width: 100%; border-radius: var(--border-radius-md);" class="floating-image-b">
        </div>

        <div class="flex-column-nowrap" style="max-width: 400px; gap: var(--spacing-md);">
            <h2 style="font-size: var(--font-size-xl); color: var(--color-text);">
                Te quiero Dari 💙
            </h2>


            <p style="max-width: 700px; font-size: var(--font-size-md); color: var(--color-muted); line-height: 1.6;">
                Esta aplicación tiene un nombre y un motivo real: Darinka, mi pareja, mi alma... Ella ha vivido con un
                Trastorno de la Conducta Alimentaria desde que era niña, en silencio, con culpa, miedo y dolor.
                <br><br>
                Verla luchar cada día me cambió. Y en lugar de quedarme quieto, decidí crear algo. Algo que pudiera
                acompañarla a ella —y a otras personas como ella— con cuidado, escucha y sin juicios.
                <br>

            <h3
                style="font-size: var(--font-size-md); font-weight: normal; font-style: italic; color: var(--color-muted);">
                Tu amolsito,
            </h3>

            </p>
        </div>

    </div>




</section>

<section style="margin: var(--spacing-sm);">

    <div class="super-grid-auto-fit-320px;" style="
    
    padding: var(--spacing-xl); 
    gap: var(--spacing-md); 
    text-align: left; 
    background-color: #f9f9f9; 
    border-radius: var(--border-radius-lg);">

        <div class="flex-column-nowrap" style=" max-width: 400px; gap: var(--spacing-md);">
            <h2 style="font-size: var(--font-size-xl); color: var(--color-text);">
                Aprendamos sobre el TCA
            </h2>
            <h3
                style="font-size: var(--font-size-md); font-weight: normal; font-style: italic; color: var(--color-muted);">
                Escuchamos pero no juzgamos
            </h3>

            <p style="max-width: 700px; font-size: var(--font-size-md); color: var(--color-muted); line-height: 1.6;">
                Los <strong>Trastornos de la Conducta Alimentaria (TCA)</strong> no son una cuestión de “comer bien” o
                “tener fuerza de voluntad”.
                Son <strong>trastornos mentales complejos</strong> que afectan la relación con la comida, el cuerpo y la
                identidad.

                <br><br>

                <strong>Darink.App</strong> tiene en cuenta este factor y ofrece una solución pensada con empatía, para
                que
                puedas:
                <strong>registrar tus comidas</strong>, <strong>identificar patrones</strong> y <strong>aprender a
                    construir
                    una relación más sana con la alimentación</strong>.

            </p>
        </div>

        <div class="image-wrapper" style="max-width: 400px; width: 100%;">
            <img src="<?= base_url('images/tca_shadow.png') ?>" alt="Ilustración alimentación consciente"
                style="width: 100%; border-radius: var(--border-radius-md);" class="floating-image-b">
        </div>

    </div>



</section>

<section class="super-flex-column-nowrap"
    style="padding: var(--spacing-xl); align-items: center; gap: var(--spacing-md); text-align: center;">

    <h3 style="font-size: var(--font-size-lg); color: var(--color-primary);">
        ¿Te gustaría empezar este camino con nosotros?
    </h3>

    <?php if (!session()->get('isLoggedIn')): ?>
        <div class="flex-row-wrap" style="gap: var(--spacing-md);">
            <a href="<?= site_url('/signup') ?>" class="btn-primary">Crear cuenta</a>
            <a href="<?= site_url('/signin') ?>" class="btn-secondary">Ya tengo cuenta</a>
        </div>
    <?php endif; ?>
</section>


<!-- Sección funcional: cómo ayuda Darink.App -->
<section class="super-flex-column-nowrap"
    style="padding: var(--spacing-xl); align-items: center; gap: var(--spacing-md); text-align: center;">

    <h2 style="font-size: var(--font-size-xl); color: var(--color-text);">
        ¿Cómo te ayuda Darink.App?
    </h2>

    <p style="max-width: 700px; font-size: var(--font-size-md); color: var(--color-muted); line-height: 1.6;">
        Sabemos que la relación con la comida puede ser compleja, delicada y muy personal.
        Por eso, en Darink.App te ofrecemos una herramienta que **te escucha**, **no te juzga**, y **te acompaña con
        respeto**.
    </p>

    <!-- Funcionalidades clave -->
    <div class="flex-row-wrap"
        style="justify-content: center; gap: var(--spacing-lg); flex-wrap: wrap; margin-top: var(--spacing-lg); max-width: 1000px;">

        <!-- 1. Registro de alimentos -->
        <div style="max-width: 300px; text-align: left;">
            <h3 style="font-size: var(--font-size-lg); color: var(--color-primary); margin-bottom: var(--spacing-sm);">
                📋 Registro rápido y fácil</h3>
            <p style="font-size: var(--font-size-sm); color: var(--color-text); line-height: 1.5;">
                Puedes registrar todo lo que consumes, tanto alimentos sólidos como líquidos, sin contar calorías ni
                macro-nutrientes.
                <br><br>
                Lo importante aquí no es cuánto comes, sino **cómo te sientes al hacerlo**.
            </p>
        </div>

        <!-- 2. Agrupación por comidas -->
        <div style="max-width: 300px; text-align: left;">
            <h3 style="font-size: var(--font-size-lg); color: var(--color-primary); margin-bottom: var(--spacing-sm);">
                🍽️ Detecta tus patrones.</h3>
            <p style="font-size: var(--font-size-sm); color: var(--color-text); line-height: 1.5;">
                Los alimentos se agrupan en **"comidas"** como desayuno, almuerzo, cena o meriendas.
                <br><br>
                Esto permite ver tus hábitos de forma compasiva y detectar patrones sin ansiedad ni presión.
            </p>
        </div>

        <!-- 3. Semáforo emocional -->
        <div style="max-width: 300px; text-align: left;">
            <h3 style="font-size: var(--font-size-lg); color: var(--color-primary); margin-bottom: var(--spacing-sm);">
                🚦 ¿Cómo te sentiste?</h3>
            <p style="font-size: var(--font-size-sm); color: var(--color-text); line-height: 1.5;">
                Después de cada comida, la app te preguntará cómo te sentiste:
                <br><br>
                <strong style="color: green;">🟢 Verde:</strong> tranquilo/a, en paz.
                <br>
                <strong style="color: orange;">🟡 Amarillo:</strong> con dudas, algo incómodo/a.
                <br>
                <strong style="color: red;">🔴 Rojo:</strong> ansiedad, culpa, angustia.
                <br><br>
                Esta autoevaluación no es para medirte, sino para **escucharte** y ayudarte a identificar emociones
                relacionadas con la comida.
            </p>
        </div>

    </div>
</section>

<style>

</style>

<?= $this->endSection() ?>