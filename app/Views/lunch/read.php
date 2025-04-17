<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<h2>🍽️ Detalle del almuerzo</h2>

<p><strong>Lugar:</strong> <?= esc($lunch['lunch_location']) ?></p>
<p><strong>Inicio:</strong> <?= esc($lunch['lunch_start_at']) ?></p>
<p><strong>Fin:</strong> <?= esc($lunch['lunch_end_at']) ?: 'No especificado' ?></p>
<p><strong>Etiqueta:</strong> <?= esc($lunch['lunch_tag']) ?: 'Sin etiqueta' ?></p>

<a href="<?= base_url("/user/lunch/update/{$lunch['lunch_id']}") ?>">✏️ Editar</a> |
<a href="<?= base_url("/user/lunch/delete/{$lunch['lunch_id']}") ?>">🗑️ Eliminar</a>


<?= view('lunch/add_food') ?>

<?= $this->endSection() ?>
