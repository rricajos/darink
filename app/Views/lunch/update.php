<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<h2>✏️ Editar almuerzo</h2>

<form action="<?= base_url("/user/lunch/update/{$lunch['lunch_id']}") ?>" method="post">
    <label>Lugar:</label>
    <input type="text" name="lunch_location" value="<?= esc($lunch['lunch_location']) ?>" required>

    <label>Inicio:</label>
    <input type="datetime-local" name="lunch_start_at" value="<?= date('Y-m-d\TH:i', strtotime($lunch['lunch_start_at'])) ?>" required>

    <label>Fin:</label>
    <input type="datetime-local" name="lunch_end_at" value="<?= $lunch['lunch_end_at'] ? date('Y-m-d\TH:i', strtotime($lunch['lunch_end_at'])) : '' ?>">

    <label>Etiqueta:</label>
    <input type="text" name="lunch_tag" value="<?= esc($lunch['lunch_tag']) ?>">

    <button type="submit">Actualizar almuerzo</button>
</form>

<?= $this->endSection() ?>
