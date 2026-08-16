const DB_KEY = 'darinkDB';
const THEME_KEY = 'darinkTheme';
const UI_KEY = 'darinkUI';

export interface Entry {
	id: string;
	type: string;
	data: Record<string, unknown>;
	createdAt: string;
	updatedAt: string;
}

function generateId(): string {
	return Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
}

function isLegacy(arr: unknown[]): boolean {
	if (arr.length === 0) return false;
	const first = arr[0] as Record<string, unknown>;
	return !('type' in first) && ('whenStart' in first || 'what' in first);
}

function migrateLegacy(arr: Record<string, unknown>[]): Entry[] {
	return arr.map((old) => {
		const now = new Date().toISOString();
		return {
			id: generateId(),
			type: 'intake',
			data: {
				what: old.what ?? '',
				amount: old.amount ?? 'normal',
				mood: old.mood ?? 'verde',
				whenStart: old.whenStart ?? '',
				whenEnd: old.whenEnd ?? ''
			},
			createdAt: typeof old.whenStart === 'string' && old.whenStart ? new Date(old.whenStart.replace(' ', 'T')).toISOString() : now,
			updatedAt: now
		};
	});
}

function loadAll(): Entry[] {
	if (typeof localStorage === 'undefined') return [];
	const raw = JSON.parse(localStorage.getItem(DB_KEY) || '[]');
	if (Array.isArray(raw) && isLegacy(raw)) {
		const migrated = migrateLegacy(raw);
		saveAll(migrated);
		return migrated;
	}
	return raw;
}

function saveAll(entries: Entry[]): void {
	localStorage.setItem(DB_KEY, JSON.stringify(entries));
}

export const db = {
	getAll(): Entry[] {
		return loadAll();
	},

	getByType(type: string): Entry[] {
		return loadAll().filter((e) => e.type === type);
	},

	add(type: string, data: Record<string, unknown>): Entry {
		const entries = loadAll();
		const now = new Date().toISOString();
		const entry: Entry = { id: generateId(), type, data, createdAt: now, updatedAt: now };
		entries.push(entry);
		saveAll(entries);
		return entry;
	},

	update(id: string, data: Record<string, unknown>): void {
		const entries = loadAll();
		const idx = entries.findIndex((e) => e.id === id);
		if (idx === -1) return;
		entries[idx] = { ...entries[idx], data, updatedAt: new Date().toISOString() };
		saveAll(entries);
	},

	remove(id: string): void {
		saveAll(loadAll().filter((e) => e.id !== id));
	},

	removeMany(ids: string[]): void {
		const set = new Set(ids);
		saveAll(loadAll().filter((e) => !set.has(e.id)));
	},

	clear(): void {
		localStorage.removeItem(DB_KEY);
	},

	exportJSON(): string {
		return JSON.stringify(loadAll(), null, 2);
	},

	importJSON(json: string): number {
		const imported: Entry[] = JSON.parse(json);
		const existing = loadAll();
		const existingIds = new Set(existing.map((e) => e.id));
		const newEntries = imported.filter((e) => !existingIds.has(e.id));
		saveAll([...existing, ...newEntries]);
		return newEntries.length;
	}
};

export const theme = {
	get(): string | null {
		if (typeof localStorage === 'undefined') return null;
		return localStorage.getItem(THEME_KEY);
	},
	set(value: string): void {
		localStorage.setItem(THEME_KEY, value);
	}
};

export const ui = {
	get(): Record<string, unknown> {
		if (typeof localStorage === 'undefined') return {};
		return JSON.parse(localStorage.getItem(UI_KEY) || '{}');
	},
	patch(updates: Record<string, unknown>): void {
		const current = this.get();
		localStorage.setItem(UI_KEY, JSON.stringify({ ...current, ...updates }));
	}
};
