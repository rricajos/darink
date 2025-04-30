<footer id="main-footer" class="super-flex-column-nowrap"
  style="gap: var(--spacing-xl); padding: var(--spacing-xl) var(--spacing-md); border-top: 1px solid var(--color-muted); background-color: var(--color-bg);">

  <div class="super-flex-row-wrap" style="justify-content: space-between; gap: var(--spacing-xl);">

    <!-- Branding -->
    <div style="flex: 1; min-width: 220px;">
      <a href="<?= site_url('/') ?>"
        style="text-decoration: none; font-size: var(--font-size-xl); font-weight: bold; color: var(--color-primary);">Darink.App</a>
      <p style="margin-top: var(--spacing-xs); font-size: var(--font-size-sm); color: var(--color-text);">
        Conectando personas, sin algoritmos 💜
      </p>
    </div>

    <!-- Enlaces rápidos -->
    <div style="flex: 1; min-width: 220px;">
      <h4
        style="font-size: var(--font-size-md); font-weight: bold; margin-bottom: var(--spacing-sm); color: var(--color-primary);">
        Enlaces</h4>
      <ul class="super-flex-column-nowrap" style="gap: var(--spacing-xs);">
        <li><a href="<?= site_url('/about') ?>"
            style="text-decoration: none; color: var(--color-text); font-size: var(--font-size-sm);">Acerca de</a></li>
        <li><a href="<?= site_url('/faq') ?>"
            style="text-decoration: none; color: var(--color-text); font-size: var(--font-size-sm);">FAQ</a></li>
        <li><a href="<?= site_url('/contact') ?>"
            style="text-decoration: none; color: var(--color-text); font-size: var(--font-size-sm);">Contacto</a></li>
        <li><a href="<?= site_url('/terms') ?>"
            style="text-decoration: none; color: var(--color-text); font-size: var(--font-size-sm);">Términos</a></li>
      </ul>
    </div>

    <!-- Redes sociales -->
    <div style="flex: 1; min-width: 220px;">
      <h4
        style="font-size: var(--font-size-md); font-weight: bold; margin-bottom: var(--spacing-sm); color: var(--color-primary);">
        Redes</h4>
      <ul class="super-flex-row-nowrap" style="gap: var(--spacing-md); align-items: center;">
        <li>
          <a href="https://twitter.com" target="_blank" rel="noopener" aria-label="Twitter"
            style="color: var(--color-text);">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path
                d="M22.46 6c-.77.35-1.6.58-2.46.69a4.25 4.25 0 001.88-2.34 8.36 8.36 0 01-2.69 1.03 4.2 4.2 0 00-7.17 3.83A11.95 11.95 0 013 4.89a4.2 4.2 0 001.3 5.61 4.14 4.14 0 01-1.9-.52v.05a4.2 4.2 0 003.36 4.12 4.23 4.23 0 01-1.89.07 4.2 4.2 0 003.92 2.91A8.44 8.44 0 012 19.54a11.87 11.87 0 006.29 1.84c7.55 0 11.68-6.26 11.68-11.69 0-.18-.01-.36-.02-.53A8.3 8.3 0 0024 5.56a8.4 8.4 0 01-2.54.7z" />
            </svg>
          </a>
        </li>
        <li>
          <a href="https://github.com" target="_blank" rel="noopener" aria-label="GitHub"
            style="color: var(--color-text);">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path
                d="M12 0C5.37 0 0 5.38 0 12a12 12 0 008.21 11.44c.6.11.82-.26.82-.58v-2.01c-3.34.73-4.04-1.61-4.04-1.61-.55-1.41-1.34-1.79-1.34-1.79-1.09-.75.08-.74.08-.74 1.21.09 1.85 1.25 1.85 1.25 1.07 1.84 2.81 1.31 3.5 1 .11-.78.42-1.31.76-1.61-2.66-.3-5.47-1.34-5.47-5.95 0-1.31.47-2.38 1.24-3.22-.13-.3-.54-1.51.12-3.15 0 0 1-.32 3.3 1.23a11.45 11.45 0 016 0c2.3-1.55 3.3-1.23 3.3-1.23.66 1.64.25 2.85.12 3.15.77.84 1.24 1.91 1.24 3.22 0 4.62-2.81 5.65-5.49 5.95.43.37.81 1.1.81 2.22v3.29c0 .32.22.7.83.58A12 12 0 0024 12c0-6.62-5.38-12-12-12z" />
            </svg>
          </a>
        </li>
      </ul>
    </div>

  </div>

  <!-- Copyright -->
  <div
    style="text-align: center; font-size: var(--font-size-xs); color: var(--color-muted); margin-top: var(--spacing-lg);">
    <p>&copy; <?= date('Y') ?> Darink.App. Todos los derechos reservados.</p>
  </div>

</footer>
<pre>
<?php
// print_r(get_defined_vars()); ?>
</pre>
<?php
// echo '<pre>';
// print_r(session()->get());
// echo '</pre>';
?>