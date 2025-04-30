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





<!-- FORMULARIO DE EDITAR LUNCH -->
<form id="formLunch" action="<?= site_url('lunch/update/' . $lunch['lunch_uuid']) ?>" method="POST">
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
              <button class="btn btn-danger" type="submit"
                onclick="return confirm('¿Seguro de eliminar este food?')">Eliminar</button>
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

<form id="formFood" action="<?= site_url('food/create') ?>" method="POST">
  <?= csrf_field() ?>
  
  <!-- Enviamos el UUID del Lunch -->
  <input type="hidden" name="lunch_uuid" value="<?= esc($lunch['lunch_uuid']) ?>">

  <!-- Título del alimento -->
  <label>Título:
    <input type="text" name="food_title" required>
  </label><br><br>

  <!-- Componente visual y selectores -->
  <div class="feedback-component super-flex-column-wrap">

    <div class="flex-column-wrap">
      <div class="selectors">

        <!-- Tamaño del plato -->
        <label for="size-select">1. ¿De qué tamaño?</label>
        <select id="size-select" name="food_size" required>
          <option value="small">Pequeño</option>
          <option value="medium" selected>Normal</option>
          <option value="large">Grande</option>
        </select>

        <!-- Cantidad de tercios -->
        <label for="portion-select">2. ¿Cuántos tercios comiste?</label>
        <select id="portion-select" name="food_amount" required>
          <option value="1/3">Un tercio</option>  
          <option value="2/3" selected>Dos tercios</option>
          <option value="3/3">Tres tercios</option>
        </select>

        <!-- Semáforo -->
        <label for="feeling-select">3. ¿Cómo te hizo sentir?</label>
        <select id="feeling-select" name="light_color" required>
          <option value="green" selected>Semáforo verde</option>
          <option value="yellow">Semáforo amarillo</option>
          <option value="red">Semáforo rojo</option>
        </select>

      </div>
    </div>

    <!-- Pelota visual -->
    <div class="ball-container">
      <div id="dynamic-ball" class="ball medium green">
        <div class="segment segment-1"></div>
        <div class="segment segment-2"></div>
        <div class="segment segment-3"></div>
      </div>
    </div>

  </div>

  <!-- Comentario opcional -->
  <label>Por si quieres dar detalles:
    <textarea name="light_message" rows="3"></textarea>
  </label><br><br>

</form>


<!-- Botón único de guardar -->
<button id="enviarCambios">Guardar cambios</button>

<!--
<style>
  /* Evita que el cambio de tamaño altere el layout */
  .options {
    display: flex;
    gap: 1rem;
    margin: 0.5rem 0;
    align-items: center;
    min-height: 70px;
    /* reserva espacio para el elemento más grande */
  }

  .options label {
    line-height: 0;
  }


  input[type="radio"] {
    display: none;
  }

  /* Pelotas (tamaño) */
  .ball {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: gray;
    cursor: pointer;
    opacity: 0.5;
    transition: transform 0.2s ease;
  }

  .ball-small {
    width: 30px;
    height: 30px;
  }

  .ball-medium {
    width: 45px;
    height: 45px;
  }

  .ball-large {
    width: 60px;
    height: 60px;
  }

  input[type="radio"]:checked+.ball {
    opacity: 1;
    transform: scale(1.1);
    background: #4caf50;

    border: 2px solid #333;

  }

  /* Porciones (quesitos simulados con fondo) */
  .portion {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    opacity: 0.5;
    background: lightgray;
    /* fallback */
    position: relative;
    overflow: hidden;
  }

  /* Añade un semicírculo interior con pseudo-elemento */
  .portion::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50%;

    z-index: 1;
    background: var(--portion-fill, green);
    clip-path: polygon(50% 50%, 0% 0%, 0% 100%);
  }

  [data-parts="1"]::before {
    clip-path: polygon(50% 50%,
        /* centro */
        0% 0%,
        /* borde superior izquierdo */
        0% 100%
        /* borde inferior izquierdo */
      );
  }

  [data-parts="2"]::before {
    clip-path: polygon(50% 50%,
        /* centro */
        0% 0%,
        /* esquina superior izquierda */
        0% 100%,
        /* esquina inferior izquierda */
        100% 100%
        /* esquina inferior derecha */
      );
  }

  [data-parts="3"]::before {
    clip-path: polygon(0% 0%, 100% 0%, 100% 100%, 0% 100%);
  }

  input[type="radio"]:checked+.portion {
    opacity: 1;
    border: 2px solid #333;
    box-shadow: 0 0 4px rgba(0, 0, 0, 0.3);


  }

  /* Semáforo */
  .traffic {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: gray;
    opacity: 0.5;
    cursor: pointer;
  }

  .light-green {
    background: green;
  }

  .light-yellow {
    background: gold;
  }

  .light-red {
    background: red;
  }

  input[type="radio"]:checked+.traffic {
    opacity: 1;

    border: 2px solid #333;

  }

  /* Tamaños dinámicos */
  [data-dynamic] {
    transition: all 0.2s ease;
  }

  .dynamic-small {
    transform: scale(0.75);
  }

  .dynamic-medium {
    transform: scale(1);
  }

  .dynamic-large {
    transform: scale(1.3);
  }

  /* Colores dinámicos */
  .dynamic-color-green {
    background-color: green !important;
  }

  .dynamic-color-yellow {
    background-color: gold !important;
  }

  .dynamic-color-red {
    background-color: red !important;
  }
</style>

<script>
  // logica de las 9 pelotas

  document.addEventListener('DOMContentLoaded', function () {
    const portionLabels = document.querySelectorAll('.options-portion label');
    const sizeLabels = document.querySelectorAll('.options-size label');
    const trafficLabels = document.querySelectorAll('.options-traffic label');

    // 🔄 Color dinámico: aplicar SOLO a portion + size, excluir semáforo
    function applyColor(color) {
      const colors = ['green', 'yellow', 'red'];

      [...portionLabels, ...sizeLabels].forEach(el => {
        colors.forEach(c => el.classList.remove(`dynamic-color-${c}`));
        el.classList.add(`dynamic-color-${color}`);
        el.setAttribute('data-dynamic', 'true');

        // Para porciones: usa variable CSS
        if (el.classList.contains('portion')) {
          let fillColor = 'green';
          if (color === 'yellow') fillColor = 'gold';
          if (color === 'red') fillColor = 'red';
          el.style.setProperty('--portion-fill', fillColor);
        }
      });
    }


    // 🔄 Tamaño dinámico: aplicar a portions y semáforos, NO a bolas de tamaño
    function applySize(size) {
      const sizes = ['small', 'medium', 'large'];
      portionLabels.forEach(el => {
        sizes.forEach(s => el.classList.remove(`dynamic-${s}`));
        el.classList.add(`dynamic-${size}`);
        el.setAttribute('data-dynamic', 'true');
      });
      trafficLabels.forEach(el => {
        sizes.forEach(s => el.classList.remove(`dynamic-${s}`));
        el.classList.add(`dynamic-${size}`);
        el.setAttribute('data-dynamic', 'true');
      });
    }

    // 🎯 Escuchar selección de semáforo
    trafficLabels.forEach(label => {
      label.addEventListener('click', () => {
        const color = label.getAttribute('data-color');
        applyColor(color);
      });
    });

    // 🎯 Escuchar selección de tamaño
    sizeLabels.forEach(label => {
      label.addEventListener('click', () => {
        const size = label.getAttribute('data-size');
        applySize(size);
      });
    });
  });
</script>


-->



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