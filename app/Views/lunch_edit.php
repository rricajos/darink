<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php if (session()->getFlashdata('success') === 'changes_saved'): ?>
  <div id="modalPreguntar"
    style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:1000;">
    <div style="background:white;padding:20px;border-radius:8px;text-align:center;max-width:400px;width:90%;">
      <h3>¡Cambios guardados!</h3>
      <p>¿Qué quieres hacer ahora?</p>
      <button onclick="seguirEditando()" style="margin-right:10px;">Seguir editando</button>
      <button onclick="verLista()">Ver lista de lunches</button>
    </div>
  </div>

  <script>
    function seguirEditando() {
      document.getElementById('modalPreguntar').style.display = 'none';
    }

    function verLista() {
      window.location.href = "<?= site_url('lunch') ?>"; // Asegúrate que la URL sea correcta
    }
  </script>
<?php endif; ?>


<h2>Editar Lunch</h2>

<!-- FORMULARIO DE EDITAR LUNCH -->
<form id="formLunch" action="<?= site_url('lunch/update/' . $lunch['lunch_id']) ?>" method="POST">
  <?= csrf_field() ?>

  <label>🏷️ Tag:
    <select name="lunch_tag">
      <option value="breakfast" <?= $lunch['lunch_tag'] == 'breakfast' ? 'selected' : '' ?>>Breakfast</option>
      <option value="brunch" <?= $lunch['lunch_tag'] == 'brunch' ? 'selected' : '' ?>>Brunch</option>
      <option value="lunch" <?= $lunch['lunch_tag'] == 'lunch' ? 'selected' : '' ?>>Lunch</option>
      <option value="snack" <?= $lunch['lunch_tag'] == 'snack' ? 'selected' : '' ?>>Snack</option>
      <option value="dinner" <?= $lunch['lunch_tag'] == 'dinner' ? 'selected' : '' ?>>Dinner</option>
      <option value="other" <?= $lunch['lunch_tag'] == 'other' ? 'selected' : '' ?>>Other</option>
    </select>
  </label><br><br>

  <label>📍 Location:
    <select name="lunch_location">
      <option value="house" <?= $lunch['lunch_location'] == 'house' ? 'selected' : '' ?>>House</option>
      <option value="work" <?= $lunch['lunch_location'] == 'work' ? 'selected' : '' ?>>Work</option>
      <option value="school" <?= $lunch['lunch_location'] == 'school' ? 'selected' : '' ?>>School</option>
      <option value="restaurant" <?= $lunch['lunch_location'] == 'restaurant' ? 'selected' : '' ?>>Restaurant</option>
      <option value="other" <?= $lunch['lunch_location'] == 'other' ? 'selected' : '' ?>>Other</option>
    </select>
  </label><br><br>

  <label>⏳ Start Time:
    <input type="datetime-local" name="lunch_start_at"
      value="<?= date('Y-m-d\TH:i', strtotime($lunch['lunch_start_at'])) ?>">
  </label><br><br>

  <label>⌛ End Time:
    <input type="datetime-local" name="lunch_end_at"
      value="<?= date('Y-m-d\TH:i', strtotime($lunch['lunch_end_at'])) ?>">
  </label><br><br>

</form>

<hr>

<!-- LISTA DE FOODS -->

<?php if (!empty($foods)): ?>
  <table border="1" cellpadding="5" cellspacing="0">
    <thead>
      <tr>
        <th>Semáforo</th>
        <th>Título</th>
        <th>Tamaño</th>
        <th>Cantidad Comida</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($foods as $food): ?>
        <tr>
          <td>
            <?php if ($food['light_color']): ?>
              <div
                style="width:20px; height:20px; border-radius:50%; background-color:
                        <?= $food['light_color'] == 'green' ? 'green' : ($food['light_color'] == 'yellow' ? 'yellow' : 'red') ?>;">
              </div>
            <?php else: ?>
              No definido
            <?php endif; ?>
          </td>

          <td><?= esc($food['food_title']) ?></td>
          <td><?= esc($food['food_size']) ?></td>
          <td><?= esc($food['food_amount']) ?></td>
          <td>
            <a class="btn" href="<?= site_url('food/edit/' . $food['food_id']) ?>">Editar</a>
            <form action="<?= site_url('food/delete/' . $food['food_id']) ?>" method="post" style="display:inline;">
              <?= csrf_field() ?>
              <button class="btn btn-danger" type="submit" onclick="return confirm('¿Seguro de eliminar este food?')">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <p>No hay foods todavía para este lunch.</p>
<?php endif; ?>

<hr>

<!-- FORMULARIO PARA CREAR NUEVO FOOD -->
<h2>🥝 Agregar nuevo Food</h2>

<form id="formFood" action="<?= site_url('food/create') ?>" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="lunch_id" value="<?= esc($lunch['lunch_id']) ?>">

  <label>Título:
    <input type="text" name="food_title" required>
  </label><br><br>

  <label>¿Cuánto comiste del plato?
    <select name="food_amount">
      <option value="1/3">1/3</option>
      <option value="2/3">2/3</option>
      <option value="3/3" selected>3/3</option>
    </select>
  </label><br><br>

  <label>Tamaño:
    <select name="food_size">
      <option value="small">Pequeño</option>
      <option value="medium">Mediano</option>
      <option value="large">Grande</option>
    </select>
  </label><br><br>
 
  <label>🚦 Como te has sentido al hacerlo?
    <select name="light_color">
      <option value="green">Verde</option>
      <option value="yellow">Amarillo</option>
      <option value="red">Rojo</option>
    </select>
  </label><br><br>

  <label>Por si quieres dar detalles:
    <textarea name="light_message" rows="3"></textarea>
  </label><br><br>

</form>

<!-- Botón único de guardar -->
<button id="enviarCambios">Guardar cambios</button>

<script>
  // Flags para detectar cambios
  let lunchChanged = false;
  let foodChanged = false;

  // Función para marcar cuando haya cambios
  function monitorChanges(formId, flagSetter) {
    const form = document.getElementById(formId);
    form.querySelectorAll('input, select, textarea').forEach(element => {
      element.addEventListener('change', () => {
        flagSetter(true);
      });
      element.addEventListener('input', () => {
        flagSetter(true);
      });
    });
  }

  // Configurar monitores de cambios
  monitorChanges('formLunch', val => lunchChanged = val);
  monitorChanges('formFood', val => foodChanged = val);

  // Acción del botón
  document.getElementById('enviarCambios').addEventListener('click', function (e) {
    e.preventDefault();

    if (lunchChanged) {
      document.getElementById('formLunch').submit();
    }

    if (foodChanged) {
      // Si lunch también cambió, esperar un poco para evitar colisiones
      if (lunchChanged) {
        setTimeout(() => document.getElementById('formFood').submit(), 500);
      } else {
        document.getElementById('formFood').submit();
      }
    }

    if (!lunchChanged && !foodChanged) {
      alert('No se han realizado cambios.');
    }
  });
</script>

<?= $this->endSection() ?>