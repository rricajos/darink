// /main.js
import EntryController from "./controllers/EntryController.js";
import TabController from "./controllers/TabController.js";

document.addEventListener("DOMContentLoaded", () => {
  TabController.init();
  EntryController.init();
});

// Registrar Service Worker y mostrar snackbar si hay una nueva versión
if ("serviceWorker" in navigator) {
  navigator.serviceWorker
    .register("/darink/service-worker.js", {
      scope: "/darink/",
      updateViaCache: "none",
    })
    .then((reg) => {
      reg.update();
    })
    .catch(console.error);
}

// Mostrar barra visual para actualizar
function showUpdateSnackbar() {
  const snackbar = document.createElement("div");
  snackbar.id = "updateSnackbar";
  snackbar.innerHTML = `
    <span>Hay una nueva versión de Darink App.</span>
    <div style="display: flex; gap: 0.5rem;">
      <button id="btnUpdateNow">Actualizar</button>
      <button id="btnDismissUpdate">Ignorar</button>
    </div>
  `;

  snackbar.querySelector("#btnUpdateNow").onclick = () => {
    window.location.reload();
  };

  snackbar.querySelector("#btnDismissUpdate").onclick = () => {
    snackbar.remove();
  };

  document.body.appendChild(snackbar);
}
