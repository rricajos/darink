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