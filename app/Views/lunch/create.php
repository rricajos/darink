<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<h2>🍽️ Registrar nuevo almuerzo</h2>

<form action="<?= base_url('/lunch/create') ?>" method="POST" class="lunch-container">

    <label>🏷️ Tipo de comida:</label>
    <select name="lunch_tag" required>
        <option value="breakfast">Breakfast</option>
        <option value="brunch">Brunch</option>
        <option value="lunch" selected>Lunch</option>
        <option value="snack">Snack</option>
        <option value="dinner">Dinner</option>
        <option value="other">Other</option>
    </select>

    <label>📍 Lugar:</label>
    <select name="lunch_location" required>
        <option value="house">Casa</option>
        <option value="work">Trabajo</option>
        <option value="school">Escuela</option>
        <option value="restaurant">Restaurante</option>
        <option value="other">Otro</option>
    </select>

    <label>🕒 Inicio:</label>
    <input type="datetime-local" name="lunch_start_at" value="<?= $default_start ?>" required>

    <label>🕓 Fin:</label>
    <input type="datetime-local" name="lunch_end_at" value="<?= $default_end ?>" required>

    <button type="submit">Guardar almuerzo</button>
</form>

<?= $this->endSection() ?>
