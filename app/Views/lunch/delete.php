<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<h2>🗑️ Eliminar almuerzo</h2>

<p>¿Estás seguro de que querés eliminar el almuerzo del <strong><?= esc($lunch['lunch_start_at']) ?></strong> en <strong><?= esc($lunch['lunch_location']) ?></strong>?</p>

<form action="<?= base_url("/user/lunch/delete/{$lunch['lunch_id']}") ?>" method="post">
    <button type="submit">Sí, eliminar</button>
    <a href="<?= base_url("/user/lunch/read/{$lunch['lunch_id']}") ?>">Cancelar</a>
</form>

<?= $this->endSection() ?>
