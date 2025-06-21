/* Darink App – lógica principal */

const DB_KEY = 'json.json';        // nuestra “base de datos”
let entries = [];                  // cache en memoria

// ------- UI elements -------
const $form        = document.getElementById('entry-form');
const $view        = document.getElementById('entries-view');
const $exportBtn   = document.getElementById('export-btn');
const $importBtn   = document.getElementById('import-btn');
const $importInput = document.getElementById('import-input');

// ------- Carga inicial -------
loadDB();
render();

// ------- Eventos -------

// Alta de una nueva entrada
$form.addEventListener('submit', evt => {
  evt.preventDefault();

  const fd = new FormData($form);
  const entry = Object.fromEntries(fd.entries());

  // si no se estableció hora de fin ➜ ahora
  if (!entry.end) {
    entry.end = new Date().toISOString().slice(0,16);
  }

  entries.push(entry);
  saveDB();
  render();
  $form.reset();
});

// Exportar json.json
$exportBtn.addEventListener('click', () => {
  const blob = new Blob([JSON.stringify(entries, null, 2)],
                        { type: 'application/json' });
  const url = URL.createObjectURL(blob);

  const a = document.createElement('a');
  a.href = url;
  a.download = 'json.json';
  a.click();

  URL.revokeObjectURL(url);
});

// Importar json.json
$importBtn.addEventListener('click', () => $importInput.click());

$importInput.addEventListener('change', e => {
  const file = e.target.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = () => {
    try {
      const imported = JSON.parse(reader.result);
      if (!Array.isArray(imported)) throw TypeError();
      entries = imported;
      saveDB();
      render();
      alert('Base de datos importada correctamente.');
    } catch {
      alert('json.json no válido 🥲');
    }
  };
  reader.readAsText(file, 'utf-8');
});

// ------- Persistencia (localStorage) -------

function loadDB() {
  const raw = localStorage.getItem(DB_KEY);
  if (raw) {
    try { entries = JSON.parse(raw) || []; }
    catch { entries = []; }
  }
}

function saveDB() {
  localStorage.setItem(DB_KEY, JSON.stringify(entries));
}

// ------- Render -------
function render() {
  $view.textContent = entries.length
    ? JSON.stringify(entries, null, 2)
    : '(sin registros)';
}

// ------- Instalación PWA (opcional) -------
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('service-worker.js');
}
