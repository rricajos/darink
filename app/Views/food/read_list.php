da<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<h2>📋 Comidas en lista</h2>

<ul>
    <?php foreach ($foods as $food): ?>
        <li>
            <a href="<?= base_url("/food/{$food['food_id']}") ?>">
                <?= esc($food['food_start_at']) ?> — <?= esc($food['lunch_location']) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?= $this->endSection() ?>
