// service-worker.js  (NECESARIO: corrige ruta CSS y evita quedar “pegado”)
// No afecta a la DB (localStorage: "darinkDB")
const CACHE = "darink-cache-v9";

const ASSETS = [
  "./",
  "./index.html",
  "./manifest.json",
  "./css/style.css", // ← antes: styles.css (mal)
  "./css/font-awesome.min.css",
  "./js/main.js",
  "./js/utils.js",
  "./js/controllers/EntryController.js",
  "./js/controllers/TabController.js",
  "./js/controllers/ThemeController.js",
  "./js/controllers/InstallController.js",
  "./js/controllers/ConfettiController.js",
  "./storage.js",
  "./icons/icon-192.png",
  "./icons/icon-512.png",
];

self.addEventListener("install", (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)));
  self.skipWaiting();
});
self.addEventListener("activate", (e) => {
  e.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))
        )
      )
  );
  self.clients.claim();
});

self.addEventListener("fetch", (e) => {
  const req = e.request;
  const url = new URL(req.url);
  const isJS = url.pathname.endsWith(".js");
  const isCSS = url.pathname.endsWith(".css");

  if (req.mode === "navigate") {
    e.respondWith(caches.match("./index.html").then((r) => r || fetch(req)));
    return;
  }

  if (isJS || isCSS) {
    // network-first para ver cambios sin Ctrl+Shift+R
    e.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy));
          return res;
        })
        .catch(() => caches.match(req))
    );
    return;
  }

  e.respondWith(caches.match(req).then((r) => r || fetch(req)));
});
