<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<h2>📅 Almuerzos en calendario</h2>

<p>(Acá podrías insertar un calendario como FullCalendar con los datos de `$lunches` en JSON)</p>

<pre><?= print_r($lunches, true) ?></pre> <!-- Placeholder -->

<?= $this->endSection() ?>
