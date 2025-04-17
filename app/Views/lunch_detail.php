<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<h1>🥗 Detalles del almuerzo</h1>

<p><strong>Ubicación:</strong> <?= esc($lunch['lunch_location']) ?></p>
<p><strong>Inicio:</strong> <?= $lunch['lunch_start_at'] ?></p>
<p><strong>Fin:</strong> <?= $lunch['lunch_end_at'] ?? 'No especificado' ?></p>

<h2>🍽️ Comidas registradas</h2>
<ul>
    <?php foreach ($foods as $food): ?>
        <li>
            <?= esc($food['food_title']) ?> - <?= ucfirst($food['food_size']) ?>
        </li>
    <?php endforeach; ?>
</ul>

<?= $this->endSection() ?>
