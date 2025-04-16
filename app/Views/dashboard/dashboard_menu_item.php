<li class="dashboard-menu-item <?= ($active ?? false) ? 'active' : '' ?>">
    <a href="<?= esc($url) ?>">
        <i class="<?= esc($icon) ?>"></i>
        <span><?= esc($label) ?></span>
    </a>
</li>
