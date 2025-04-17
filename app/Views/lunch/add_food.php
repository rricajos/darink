

<h1>🍽️ Registrar comida</h1>

<form action="<?= base_url('/food/create') ?>" method="POST" class="form-add-food">


  <?php
  // Obtener fecha y hora actual en formato compatible con inputs
  $now = new \DateTime();
  $currentDate = $now->format('Y-m-d');
  $currentTime = $now->format('H:i');
  ?>

  <!-- 🔹 Etapa 1: Cuándo y Dónde -->
  <fieldset>
    <legend>1. Cuándo y Dónde</legend>

    <p>🕒 Inicio:</p>
    <label for="food_start_date">📅 Fecha de inicio:</label>
    <input type="date" id="food_start_date" name="food_start_date" value="<?= $currentDate ?>" required>

    <label for="food_start_time">⏰ Hora de inicio:</label>
    <input type="time" id="food_start_time" name="food_start_time" value="<?= $currentTime ?>" required>

    <p>🕛 Fin:</p>
    <label for="food_end_date">📅 Fecha de fin:</label>
    <input type="date" id="food_end_date" name="food_end_date">

    <label for="food_end_time">⏰ Hora de fin:</label>
    <input type="time" id="food_end_time" name="food_end_time">

    <p>📍 Lugar:</p>
    <label><input type="radio" name="lunch_location" value="house" required <?= ($lunch_location ?? '') === 'house' ? 'checked' : '' ?>> En casa</label>
    <label><input type="radio" name="lunch_location" value="work" <?= ($lunch_location ?? '') === 'work' ? 'work' : '' ?>> En el trabajo</label>
    <label><input type="radio" name="lunch_location" value="school" <?= ($lunch_location ?? '') === 'school' ? 'school' : '' ?>> Escuela</label>
    <label><input type="radio" name="lunch_location" value="restaurant"<?= ($lunch_location ?? '') === 'restaurant' ? 'restaurant' : '' ?>> Bar/Restaurante</label>
    <label><input type="radio" name="lunch_location" value="unknown" <?= ($lunch_location ?? '') === 'unknown' ? 'checked' : '' ?>> Indeterminado</label>
  </fieldset>


  <!-- 🔹 Etapa 2: Qué y Cantidad -->
  <fieldset>
    <legend>2. Qué y Qué Cantidad</legend>

    <label for="food_item">🍕 ¿Qué comiste?</label>
    <input type="text" id="food_item" name="food_item" placeholder="Ej. Pizza con jamón" required>

    <p>🍽️ Cantidad:</p>
    <label><input type="radio" name="food_quantity" value="big" required> Big</label>
    <label><input type="radio" name="food_quantity" value="regular"> Regular</label>
    <label><input type="radio" name="food_quantity" value="small"> Small</label>
    <label><input type="radio" name="food_quantity" value="tiny"> Tiny</label>
    <label><input type="radio" name="food_quantity" value="unkown" checked> Tiny</label>
  </fieldset>

  <!-- 🔹 Etapa 3: Semáforo -->
  <fieldset>
    <legend>3. Método del semáforo (opcional)</legend>

    <p>🚦 Elige un color:</p>
    <label><input type="radio" name="food_traffic_light" value="green"> Verde</label>
    <label><input type="radio" name="food_traffic_light" value="yellow"> Amarillo</label>
    <label><input type="radio" name="food_traffic_light" value="red"> Rojo</label>

    <label for="food_note">📝 Comentarios (opcional):</label>
    <textarea id="food_note" name="food_note" rows="3" placeholder="¿Por qué elegiste ese color?"></textarea>
  </fieldset>

  <button type="submit">Guardar comida</button>
</form>
<style>
  form.form-add-food {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    max-width: 600px;
    margin: 0 auto;
  }

  fieldset {
    border: 1px solid #ccc;
    padding: 1rem 1.5rem;
    border-radius: 8px;
  }

  legend {
    font-weight: bold;
    color: #007bff;
    margin-bottom: 1rem;
  }

  label {
    display: block;
    margin: 0.5rem 0;
  }

  input[type="text"],
  input[type="date"],
  input[type="time"],
  textarea {
    width: 100%;
    padding: 0.5rem;
    margin-top: 0.25rem;
    border-radius: 4px;
    border: 1px solid #ccc;
  }

  button[type="submit"] {
    padding: 0.75rem 1.5rem;
    font-weight: bold;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
  }

  button[type="submit"]:hover {
    background-color: #0056b3;
  }
</style>

