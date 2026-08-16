// /storage.js
export const dbKey = "darinkDB";
export const loadDB = () => JSON.parse(localStorage.getItem(dbKey) || "[]");
export const saveDB = (db) => localStorage.setItem(dbKey, JSON.stringify(db));
export const clearDB = () => localStorage.removeItem(dbKey);
export const addEntry = (e) => {
  const db = loadDB();
  db.push(e);
  saveDB(db);
};
export const deleteEntry = (i) => {
  const db = loadDB();
  db.splice(i, 1);
  saveDB(db);
};

// tema
export const themeKey = "darinkTheme";
export const saveTheme = (v) => localStorage.setItem(themeKey, v);
export const loadTheme = () => localStorage.getItem(themeKey);

// estado UI
export const tabKey = "darinkTab";
export const saveTab = (t) => localStorage.setItem(tabKey, t);
export const loadTab = () => localStorage.getItem(tabKey) || "add";

export const uiKey = "darinkUI";
export const loadUI = () => JSON.parse(localStorage.getItem(uiKey) || "{}");
export const saveUI = (patch) => {
  const cur = loadUI();
  localStorage.setItem(uiKey, JSON.stringify({ ...cur, ...patch }));
};
