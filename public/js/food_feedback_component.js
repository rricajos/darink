document.addEventListener("DOMContentLoaded", () => {
  const sizeSelect = document.getElementById("size-select");
  const portionSelect = document.getElementById("portion-select");
  const feelingSelect = document.getElementById("feeling-select");
  const ball = document.getElementById("dynamic-ball");

  const segments = [
    ball.querySelector(".segment-1"),
    ball.querySelector(".segment-2"),
    ball.querySelector(".segment-3")
  ];

  function updateBall() {
    // Reset clases de tamaño y color
    ball.className = "ball";
    ball.classList.add(sizeSelect.value);
    ball.classList.add(feelingSelect.value);

    // Mostrar solo los tercios seleccionados
    const activePortions = parseInt(portionSelect.value);
    segments.forEach((segment, index) => {
      segment.style.display = index < activePortions ? "block" : "none";
    });
  }

  sizeSelect.addEventListener("change", updateBall);
  portionSelect.addEventListener("change", updateBall);
  feelingSelect.addEventListener("change", updateBall);

  updateBall(); // Inicial
});
