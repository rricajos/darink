<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="super-felx">
<h1>🍽️ Comidas registradas</h1>

<?php if (session()->getFlashdata('message')): ?>
    <div class="alert alert-success">
        <?= session()->getFlashdata('message') ?>
    </div>
<?php endif; ?>

<table class="food-table">
    <thead>
        <tr>
            <th>📅 Fecha</th>
            <th>⏰ Hora</th>
            <th>🍔 ¿Qué?</th>
            <th>📍 Lugar</th>
            <th>⚖️ Cantidad</th>
            <th>🚦 Semáforo</th>
            <th>📝 Nota</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($foods as $food): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($food['food_start'])) ?></td>
                <td><?= date('H:i', strtotime($food['food_start'])) ?></td>
                <td><?= esc($food['item']) ?></td>
                <td><?= ucfirst($food['location']) ?></td>
                <td><?= ucfirst($food['quantity']) ?></td>
                <td><?= ucfirst($food['traffic_light']) ?></td>
                <td><?= esc($food['comment']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</section>


<?= $this->endSection() ?>
<style>
  .food-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 2rem;
}

.food-table th, .food-table td {
    border: 1px solid #ccc;
    padding: 0.75rem;
    text-align: left;
}

.food-table th {
    background-color: #f8f8f8;
    font-weight: bold;
}

.food-table tr:nth-child(even) {
    background-color: #f0f4f8;
}

</style>