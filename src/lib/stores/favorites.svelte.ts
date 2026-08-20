import { ui } from '$lib/db';

const DEFAULTS = ['/intake', '/training', '/dashboard'];

let _items = $state<string[]>([]);
let _inited = false;

function load() {
	if (_inited) return;
	_inited = true;
	const data = ui.get();
	if (data.favoritesV2) {
		_items = Array.isArray(data.favorites) ? data.favorites as string[] : [...DEFAULTS];
	} else {
		const old = Array.isArray(data.favorites) ? data.favorites as string[] : [];
		const merged = [...DEFAULTS.filter(d => !old.includes(d)), ...old];
		_items = merged;
		ui.patch({ favorites: merged, favoritesV2: true });
	}
}

export function useFavorites() {
	load();
	return {
		get items() { return _items; },
		has(href: string) { return _items.includes(href); },
		toggle(href: string) {
			if (_items.includes(href)) {
				_items = _items.filter(f => f !== href);
			} else {
				_items = [..._items, href];
			}
			ui.patch({ favorites: _items });
		},
		move(href: string, dir: -1 | 1) {
			const idx = _items.indexOf(href);
			if (idx < 0) return;
			const target = idx + dir;
			if (target < 0 || target >= _items.length) return;
			const copy = [..._items];
			[copy[idx], copy[target]] = [copy[target], copy[idx]];
			_items = copy;
			ui.patch({ favorites: _items });
		}
	};
}
