<h1>Editar Food</h1>

<form action="<?= site_url('food/update/' . $food['food_id']) ?>" method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="lunch_id" value="<?= $food['lunch_id'] ?>">

    <label>Título:
        <input type="text" name="food_title" value="<?= esc($food['food_title']) ?>" required>
    </label><br><br>

    <label>Tamaño:
        <select name="food_size">
            <option value="small" <?= $food['food_size'] == 'small' ? 'selected' : '' ?>>Pequeño</option>
            <option value="medium" <?= $food['food_size'] == 'medium' ? 'selected' : '' ?>>Mediano</option>
            <option value="large" <?= $food['food_size'] == 'large' ? 'selected' : '' ?>>Grande</option>
        </select>
    </label><br><br>

    <label>¿Cuánto comiste del plato?
        <select name="food_amount">
            <option value="1/3" <?= $food['food_amount'] == '1/3' ? 'selected' : '' ?>>1/3</option>
            <option value="2/3" <?= $food['food_amount'] == '2/3' ? 'selected' : '' ?>>2/3</option>
            <option value="3/3" <?= $food['food_amount'] == '3/3' ? 'selected' : '' ?>>3/3</option>
        </select>
    </label><br><br>

    <hr>

    <h2>Semáforo</h2>

    <label>Color:
        <select name="light_color">
            <option value="green">Verde</option>
            <option value="yellow">Amarillo</option>
            <option value="red">Rojo</option>
        </select>
    </label><br><br>

    <label>Mensaje:
        <textarea name="light_message" rows="3"></textarea>
    </label><br><br>

    <button type="submit">Actualizar Food</button>
</form>