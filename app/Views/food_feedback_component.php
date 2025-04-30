<div>
  <div class="feedback-component">
    <div class="selectors">
      <label for="size-select">1. ¿De qué tamaño?</label>
      <select id="size-select">
        <option value="small">Pequeño</option>
        <option value="medium" selected>Normal</option>
        <option value="large">Grande</option>
      </select>

      <label for="portion-select">2. ¿Cuántos tercios comiste?</label>
      <select id="portion-select">
        <option value="1">Un tercio</option>
        <option value="2" selected>Dos tercios</option>
        <option value="3">Tres tercios</option>
      </select>

      <label for="feeling-select">3. ¿Cómo te hizo sentir?</label>
      <select id="feeling-select">
        <option value="green" selected>Semáforo verde</option>
        <option value="yellow">Semáforo amarillo</option>
        <option value="red">Semáforo rojo</option>
      </select>
    </div>

    <div class="ball-container">
      <div id="dynamic-ball" class="ball medium green" data-portions="2"></div>
    </div>
  </div>
</div>