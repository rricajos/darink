// /js/controllers/TabController.js
import { saveTab, loadTab } from "../../storage.js";

const TabController = {
  tabs: [],
  sections: [],

  init() {
    this.tabs = [...document.querySelectorAll("#mainTabs button")];
    this.sections = [...document.querySelectorAll("[data-tab]")];

    this.tabs.forEach((b) => {
      b.onclick = () => this.showTab(b.dataset.go);
    });

    this.showTab(loadTab()); // restaura última pestaña
  },

  showTab(name) {
    this.sections.forEach(
      (s) => (s.style.display = s.dataset.tab === name ? "block" : "none")
    );
    this.tabs.forEach((b) =>
      b.classList.toggle("active", b.dataset.go === name)
    );
    saveTab(name); // persiste pestaña activa
  },
};

export default TabController;
