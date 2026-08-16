import { db, type Entry } from '$lib/db';

let _version = $state(0);

function bump() {
	_version++;
}

export function useEntries(type?: string): { readonly items: Entry[] } {
	const result = $derived.by(() => {
		const _ = _version;
		return type ? db.getByType(type) : db.getAll();
	});

	return {
		get items() {
			return result;
		}
	};
}

export const entries = {
	add(type: string, data: Record<string, unknown>) {
		db.add(type, data);
		bump();
	},
	update(id: string, data: Record<string, unknown>) {
		db.update(id, data);
		bump();
	},
	remove(id: string) {
		db.remove(id);
		bump();
	},
	removeMany(ids: string[]) {
		db.removeMany(ids);
		bump();
	}
};
