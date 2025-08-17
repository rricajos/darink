/* File: js/app-flow.js
   Minimal map of Darink App flow (PWA, offline-first).
*/

const APP_FLOW = {
  boot: [
    "index.html loads CSS + /js/version.js, /js/utils.js, /storage.js, /js/main.js",
    "service-worker.js registered",
    "manifest.json provides PWA meta",
  ],
  controllers: {
    ThemeController: [
      "reads/saves theme in storage",
      "applies data-theme on <html>",
    ],
    InstallController: [
      "listens to beforeinstallprompt",
      "shows install UI and triggers prompt",
    ],
    TabController: [
      "handles step navigation (Dónde → Cuándo → Qué/Tamaño/Porciones → Semáforo/Comentario/Tags)",
      "validates step before moving next/prev",
    ],
    EntryController: [
      "binds form inputs + buttons",
      "hydrates view from model/storage on load",
      "collects step data → builds EntryModel",
      "persists entry via storage.js to database.json (export/import manual)",
      "triggers ConfettiController on successful save",
      "updates list/history view",
    ],
    ConfettiController: ["plays feedback animation after save"],
  },
  model_view: {
    EntryModel: [
      "defines entry shape (where, when, what/size/portions, trafficLight/comment/tags)",
      "provides serialize/validate helpers",
    ],
    EntryView: [
      "renders inputs per step",
      "renders entries list and empty states",
    ],
  },
  storage: {
    file: "database.json",
    module: "storage.js (local-first, manual export/import)",
    versioning: [
      "/js/version.js provides APP_VERSION",
      "/js/sync-version.js compares/caches assets",
    ],
  },
  lifecycle: [
    "DOMContentLoaded → main.js boot()",
    "register SW → cache static assets",
    "init ThemeController/InstallController/TabController/EntryController",
    "user fills steps → EntryController validates",
    "save → storage.write(entry) → update view → confetti",
    "optional export/import database.json",
  ],
  offline: [
    "service-worker caches shell + assets",
    "reads/writes from storage without network",
  ],
};

/* Tiny helper to inspect flow in the console */
export function printAppFlow() {
  console.group("Darink App Flow");
  Object.entries(APP_FLOW).forEach(([k, v]) => {
    console.group(k);
    Array.isArray(v)
      ? v.forEach((i) => console.log("•", i))
      : Object.entries(v).forEach(([sk, sv]) => {
          console.group(sk);
          sv.forEach((i) => console.log("•", i));
          console.groupEnd();
        });
    console.groupEnd();
  });
  console.groupEnd();
}

/* Usage:
   import { printAppFlow } from './js/app-flow.js';
   printAppFlow();
*/
