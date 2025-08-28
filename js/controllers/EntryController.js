// /js/controllers/EntryController.js
import EntryModel from "../models/EntryModel.js";
import EntryView from "../views/EntryView.js";
import { pad, roundHalfHour } from "../utils.js";
import TabController from "./TabController.js";
import ConfettiController from "./ConfettiController.js";
import ThemeController from "./ThemeController.js";
import { saveUI, loadUI } from "../../storage.js";

let editIndex = null;

const EntryController = {
  bar: null,
  steps: [],
  navBtns: [],
  currentStep: 0,
  totalSteps: 3,
  filter: { from: "", to: "", mood: "", query: "" },

  init() {
    ThemeController.init();
    this.setupFilterToggle();
    this.cacheStepElements();
    this.setupStepNavigation();
    this.setupTimeControls();

    this.restoreUI(); // ← restaura estado
    this.setupFilters();
    this.setupPWAInstall();
    this.setupExportImport();

    this.render();
    EntryView.bindEvents(this);

    window.addEventListener("beforeunload", () => this.persistUI());
  },

  render() {
    const entries = this.applyFilters(EntryModel.getAll());
    EntryView.render(entries, this);
  },

  save(data) {
    let targetIdx;
    if (editIndex === null) {
      EntryModel.add(data);
      targetIdx = EntryModel.getAll().length - 1;
    } else {
      EntryModel.update(editIndex, data);
      targetIdx = editIndex;
    }

    this.resetForm();
    this.render();
    ConfettiController.fire();

    setTimeout(() => {
      TabController.showTab("list");
      setTimeout(() => {
        const li = document.querySelector(`li[data-dbidx='${targetIdx}']`);
        if (li) {
          li.classList.add("highlight");
          li.scrollIntoView({ behavior: "smooth", block: "center" });
          setTimeout(() => li.classList.remove("highlight"), 2000);
        }
      }, 50);
    }, 0);
  },

  clearAll() {
    if (confirm("¿Borrar toda la base de datos?")) {
      EntryModel.clear();
      this.render();
    }
  },

  edit(data, idx) {
    editIndex = idx;
    const form = document.getElementById("entryForm");

    const [ds, ts] = data.whenStart.split(" ");
    const [de, te] = data.whenEnd.split(" ");

    this.dateStart.value = ds;
    this.timeStart.value = ts;
    this.dateEnd.value = de;
    this.timeEnd.value = te;

    Object.entries(data).forEach(([k, v]) => {
      if (form.elements[k]) form.elements[k].value = v;
    });

    this.startFields.hidden = false;
    this.endFields.hidden = false;
    this.timeDash.style.display = "none";
    this.cancelBtn.hidden = false;

    TabController.showTab("add");
    this.showStep(0);
    this.persistUI();
  },

  cancelEdit() {
    const targetIdx = editIndex;
    this.resetForm();
    TabController.showTab("list");
    this.render();

    if (targetIdx !== null) {
      setTimeout(() => {
        const li = document.querySelector(`li[data-dbidx='${targetIdx}']`);
        if (li) {
          li.classList.add("highlight");
          li.scrollIntoView({ behavior: "smooth", block: "center" });
          setTimeout(() => li.classList.remove("highlight"), 2000);
        }
      }, 50);
    }
  },

  resetForm() {
    const form = document.getElementById("entryForm");
    form.reset();
    editIndex = null;
    this.setDefaultTimes();
    this.startFields.hidden = false;
    this.endFields.hidden = false;
    this.timeDash.style.display = "flex";
    this.cancelBtn.hidden = true;
    this.showStep(0);
    this.persistUI();
  },

  setupPWAInstall() {
    this.installBtn = document.getElementById("installBtn");

    window.addEventListener("beforeinstallprompt", (e) => {
      e.preventDefault();
      this.deferredPrompt = e;
      this.installBtn.hidden = false;
    });

    this.installBtn.onclick = async () => {
      if (!this.deferredPrompt) return;
      this.deferredPrompt.prompt();
      await this.deferredPrompt.userChoice;
      this.deferredPrompt = null;
      this.installBtn.hidden = true;
    };

    window.addEventListener("appinstalled", () => {
      this.deferredPrompt = null;
      this.installBtn.hidden = true;
    });
  },

  setupFilterToggle() {
    const toggleBtn = document.getElementById("toggleFilters");
    const toggleIcon = document.getElementById("toggleFiltersIcon");
    const panel = document.getElementById("filterPanel");
    if (!toggleBtn || !panel || !toggleIcon) return;

    toggleBtn.onclick = () => {
      const visible = !panel.hidden;
      panel.hidden = visible;
      toggleIcon.classList.toggle("flip-vertical", !visible);
      saveUI({ filtersOpen: !visible });
    };
  },

  cacheStepElements() {
    this.bar = document.getElementById("progressBar");
    this.steps = [...document.querySelectorAll("form section")];
    this.navBtns = [...document.querySelectorAll("#progressNav button")];

    this.dateStart = document.getElementById("dateStart");
    this.timeStart = document.getElementById("timeStart");
    this.dateEnd = document.getElementById("dateEnd");
    this.timeEnd = document.getElementById("timeEnd");

    this.startFields = document.getElementById("startFields");
    this.endFields = document.getElementById("endFields");
    this.timeDash = document.getElementById("timeDash");
    this.cancelBtn = document.getElementById("cancelEdit");

    // guarda cada cambio de formulario
    const form = document.getElementById("entryForm");
    form.addEventListener("input", () => this.persistUI());
    form.addEventListener("change", () => this.persistUI());
  },

  setupStepNavigation() {
    this.navBtns.forEach((btn) =>
      btn.addEventListener("click", () =>
        this.showStep(Number(btn.dataset.step))
      )
    );

    document.getElementById("next0").onclick = () => this.showStep(1);
    document.getElementById("back1").onclick = () => this.showStep(0);
    document.getElementById("next1").onclick = () => this.showStep(2);
    document.getElementById("back2").onclick = () => this.showStep(1);

    this.cancelBtn.onclick = () => this.cancelEdit();
  },

  setupTimeControls() {
    document.getElementById("btnStartNow").onclick = () => {
      this.setStartNow();
      saveUI({ timeMode: "start" });
    };
    document.getElementById("btnEndNow").onclick = () => {
      this.setEndNow();
      saveUI({ timeMode: "end" });
    };
    document.getElementById("swapDates").onclick = () => {
      this.swapTimes();
      this.persistUI();
    };
  },

  setDefaultTimes() {
    const now = new Date();
    const start = roundHalfHour(new Date(now.getTime() - 30 * 60000), "floor");
    const end = roundHalfHour(new Date(now.getTime() + 30 * 60000), "ceil");
    this.setTimeFields(start, end);
  },

  setTimeFields(start, end) {
    this.dateStart.value = start.toISOString().split("T")[0];
    this.timeStart.value = `${pad(start.getHours())}:${pad(
      start.getMinutes()
    )}`;
    this.dateEnd.value = end.toISOString().split("T")[0];
    this.timeEnd.value = `${pad(end.getHours())}:${pad(end.getMinutes())}`;
  },

  setStartNow() {
    const now = new Date();
    const end = roundHalfHour(new Date(now.getTime() + 30 * 60000), "ceil");
    this.setTimeFields(now, end);
    document.getElementById("btnStartNow").classList.add("active");
    document.getElementById("btnEndNow").classList.remove("active");
    this.startFields.hidden = true;
    this.endFields.hidden = false;
    this.persistUI();
  },

  setEndNow() {
    const now = new Date();
    const start = roundHalfHour(new Date(now.getTime() - 30 * 60000), "floor");
    this.setTimeFields(start, now);
    document.getElementById("btnStartNow").classList.remove("active");
    document.getElementById("btnEndNow").classList.add("active");
    this.startFields.hidden = false;
    this.endFields.hidden = true;
    this.persistUI();
  },

  swapTimes() {
    [this.dateStart.value, this.dateEnd.value] = [
      this.dateEnd.value,
      this.dateStart.value,
    ];
    [this.timeStart.value, this.timeEnd.value] = [
      this.timeEnd.value,
      this.timeStart.value,
    ];
  },

  showStep(i) {
    this.currentStep = i;
    this.steps.forEach((s, idx) => (s.hidden = idx !== i));
    this.navBtns.forEach((b, idx) => {
      b.classList.toggle("active", idx === i);
      b.classList.toggle("done", idx < i);
    });
    this.updateProgressBar(i);
    window.scrollTo({ top: 0 });
    saveUI({ step: i }); // persiste paso actual
  },

  updateProgressBar(i) {
    this.bar.style.width = `${(i / (this.totalSteps - 1)) * 100}%`;
  },

  deleteSelected(indexes) {
    const db = EntryModel.getAll();
    indexes.sort((a, b) => b - a).forEach((i) => db.splice(i, 1));
    localStorage.setItem("darinkDB", JSON.stringify(db));
    this.render();
  },

  setupFilters() {
    const fFrom = document.getElementById("fFrom");
    const fTo = document.getElementById("fTo");
    const fMood = document.getElementById("fMood");
    const searchTxt = document.getElementById("searchTxt");

    fFrom.oninput = () => {
      this.filter.from = fFrom.value;
      this.render();
      this.persistUI();
    };
    fTo.oninput = () => {
      this.filter.to = fTo.value;
      this.render();
      this.persistUI();
    };
    fMood.onchange = () => {
      this.filter.mood = fMood.value;
      this.render();
      this.persistUI();
    };
    searchTxt.oninput = () => {
      this.filter.query = searchTxt.value.toLowerCase();
      this.render();
      this.persistUI();
    };

    document.getElementById("applyFilter").onclick = () => this.render();
    document.getElementById("clearFilter").onclick = () => {
      this.filter = { from: "", to: "", mood: "", query: "" };
      fFrom.value = fTo.value = fMood.value = searchTxt.value = "";
      this.render();
      this.persistUI();
    };
  },

  applyFilters(entries) {
    return entries.filter((e) => {
      const d = e.whenStart.slice(0, 10);
      if (this.filter.from && d < this.filter.from) return false;
      if (this.filter.to && d > this.filter.to) return false;
      if (this.filter.mood && e.mood !== this.filter.mood) return false;
      if (
        this.filter.query &&
        !Object.values(e).some((v) =>
          String(v).toLowerCase().includes(this.filter.query)
        )
      ) {
        return false;
      }
      return true;
    });
  },

  setupExportImport() {
    const exportBtn = document.getElementById("exportBtn");
    const importInput = document.getElementById("importInput");

    exportBtn.onclick = () => {
      const data = EntryModel.getAll();
      const blob = new Blob([JSON.stringify(data, null, 2)], {
        type: "application/json",
      });
      const a = document.createElement("a");
      a.href = URL.createObjectURL(blob);
      a.download = "database.json";
      a.click();
    };

    importInput.onchange = async () => {
      const file = importInput.files[0];
      if (!file) return;

      try {
        const json = JSON.parse(await file.text());
        if (Array.isArray(json)) {
          const ok = confirm(
            "Esto reemplazará tu base de datos actual. ¿Continuar?"
          );
          if (!ok) return;

          EntryModel.clear();
          json.forEach((entry) => EntryModel.add(entry));
          this.render();

          TabController.showTab("list");

          setTimeout(() => {
            document.querySelectorAll("li[data-dbidx]").forEach((li) => {
              li.classList.add("highlight");
              setTimeout(() => li.classList.remove("highlight"), 2000);
            });
          }, 50);
        } else {
          alert("Formato no válido.");
        }
      } catch {
        alert("Error al leer el archivo.");
      }
    };
  },

  // ---- persistencia UI ----
  persistUI() {
    const form = document.getElementById("entryForm");
    const data = Object.fromEntries(new FormData(form));
    saveUI({
      form: {
        ...data,
        dateStart: this.dateStart.value,
        timeStart: this.timeStart.value,
        dateEnd: this.dateEnd.value,
        timeEnd: this.timeEnd.value,
      },
      flags: {
        startHidden: this.startFields.hidden,
        endHidden: this.endFields.hidden,
        timeDash: this.timeDash.style.display,
        cancelVisible: !this.cancelBtn.hidden,
      },
      filters: this.filter,
      step: this.currentStep,
    });
  },

  restoreUI() {
    const ui = loadUI();

    // filtros
    if (ui.filters) {
      this.filter = ui.filters;
      const fFrom = document.getElementById("fFrom");
      const fTo = document.getElementById("fTo");
      const fMood = document.getElementById("fMood");
      const searchTxt = document.getElementById("searchTxt");
      if (fFrom) fFrom.value = this.filter.from || "";
      if (fTo) fTo.value = this.filter.to || "";
      if (fMood) fMood.value = this.filter.mood || "";
      if (searchTxt) searchTxt.value = this.filter.query || "";
    }

    // formulario
    if (ui.form) {
      const form = document.getElementById("entryForm");
      Object.entries(ui.form).forEach(([k, v]) => {
        if (form.elements[k] !== undefined) form.elements[k].value = v;
      });
    } else {
      this.setDefaultTimes();
    }

    // flags de visibilidad
    if (ui.flags) {
      this.startFields.hidden = !!ui.flags.startHidden;
      this.endFields.hidden = !!ui.flags.endHidden;
      this.timeDash.style.display = ui.flags.timeDash || "flex";
      this.cancelBtn.hidden = !ui.flags.cancelVisible;
    }

    // paso actual
    this.showStep(typeof ui.step === "number" ? ui.step : 0);
  },
};

export default EntryController;
