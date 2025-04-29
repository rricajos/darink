<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>


<h2>Crear Lunch</h2>

<?php if (session()->getFlashdata('success')): ?>
    <div style="padding: 10px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; margin-bottom: 15px;">
        <?= session()->getFlashdata('success') ?>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div style="padding: 10px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; margin-bottom: 15px;">
        <?= session()->getFlashdata('error') ?>
    </div>
<?php endif; ?>

<form action="<?= site_url('lunch/create/') ?>" method="POST">
    <?= csrf_field() ?>

    <label>Tag:
        <select name="lunch_tag">
            <option value="breakfast">Breakfast</option>
            <option value="brunch">Brunch</option>
            <option value="lunch" selected>Lunch</option>
            <option value="snack">Snack</option>
            <option value="dinner">Dinner</option>
            <option value="other">Other</option>
        </select>
    </label><br><br>

    <label>Location:
        <select name="lunch_location">
            <option value="house" selected>House</option>
            <option value="work">Work</option>
            <option value="school">School</option>
            <option value="restaurant">Restaurant</option>
            <option value="other">Other</option>
        </select>
    </label><br><br>

    <label>Start Time:
        <input type="datetime-local" name="lunch_start_at">
    </label><br><br>

    <label>End Time:
        <input type="datetime-local" name="lunch_end_at">
    </label><br><br>

    <!-- <input type="hidden" name="user_id" value="1"> -->

    <button type="submit">Guardar</button>
</form>


<?= $this->endSection() ?>