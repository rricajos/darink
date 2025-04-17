da<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<h2>📋 Almuerzos en lista</h2>

<ul>
    <?php foreach ($lunches as $lunch): ?>
        <li>
            <a href="<?= base_url("/lunch/{$lunch['lunch_id']}") ?>">
                <?= esc($lunch['lunch_start_at']) ?> — <?= esc($lunch['lunch_location']) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?= $this->endSection() ?>
