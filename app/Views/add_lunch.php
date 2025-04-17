<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<h1>🍽️ ¿Qué tipo de alimento querés registrar?</h1>

<form method="post" action="<?= base_url('/user/add/lunch/select-type') ?>">
    <input type="hidden" name="lunch_start_at" value="<?= date('Y-m-d H:i') ?>">
    <input type="hidden" name="lunch_location" value="unknown">

    <button type="submit" name="type" value="solid">🍞 Sólido</button>
    <button type="submit" name="type" value="liquid" disabled>🥤 Líquido (próximamente)</button>
</form>

<?= $this->endSection() ?>
