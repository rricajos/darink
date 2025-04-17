<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<h2>🗂️ Agenda de almuerzos</h2>

<?php foreach ($lunches as $lunch): ?>
    <div>
        <strong><?= esc($lunch['lunch_start_at']) ?></strong> — <?= esc($lunch['lunch_location']) ?><br>
        <?= esc($lunch['lunch_tag']) ?: 'Sin etiqueta' ?>
        <hr>
    </div>
<?php endforeach; ?>

<?= $this->endSection() ?>
