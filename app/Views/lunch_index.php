<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>


<h2>Todas tus Comidas </h2>

<?php if (session()->getFlashdata('success')): ?>
    <p style="color: green;"><?= session()->getFlashdata('success') ?></p>
<?php endif; ?>



<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <!-- <th>ID</th> -->
            <th>Que</th>
            <th>Donde</th>
            <th>Cuando</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($lunches as $lunch): ?>
            <tr>
                <!-- <td><?= $lunch['lunch_id'] ?></td> -->
                <td><?= $lunch['lunch_tag'] ?></td>

                <td><?= $lunch['lunch_location'] ?></td>
                <td>
                    <?php
                    $startTimestamp = strtotime($lunch['lunch_start_at']);
                    $endTimestamp = strtotime($lunch['lunch_end_at']);

                    $startDate = date('Y-m-d', $startTimestamp);
                    $endDate = date('Y-m-d', $endTimestamp);

                    $startTime = date('H:i', $startTimestamp);
                    $endTime = date('H:i', $endTimestamp);

                    $today = date('Y-m-d');
                    $textStart = '';

                    // Determinar etiqueta para la fecha de inicio
                    if ($startDate == $today) {
                        $textStart = 'Hoy';
                    } elseif ($startDate == date('Y-m-d', strtotime('-1 day'))) {
                        $textStart = 'Ayer';
                    } elseif ($startDate == date('Y-m-d', strtotime('+1 day'))) {
                        $textStart = 'Mañana';
                    } else {
                        $textStart = date('d/m/Y', $startTimestamp);
                    }

                    // Mostrar dependiendo si es el mismo día o no
                    if ($startDate == $endDate) {
                        echo "$textStart, de $startTime a $endTime";
                    } else {
                        echo "Del " . date('d/m/Y H:i', $startTimestamp) . " al " . date('d/m/Y H:i', $endTimestamp);
                    }
                    ?>
                </td>


                <td>
                    <a class="btn" href="<?= site_url('lunch/' . $lunch['lunch_uuid']) ?>">Editar</a>
                    <form action="<?= site_url('lunch/delete/' . $lunch['lunch_uuid']) ?>" method="POST"
                        style="display:inline;">
                        <button type="submit" class="btn btn-danger"
                            onclick="return confirm('¿Estás seguro de eliminar este lunch?')">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<a class="btn btn-success" href="<?= site_url('lunch/new') ?>">Crear nuevo Lunch</a>



<?= $this->endSection() ?>