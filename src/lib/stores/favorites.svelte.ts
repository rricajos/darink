import { ui } from '$lib/db';

let _items = $state<string[]>([]);
let _inited = false;

function load() {
	if (_inited) return;
	_inited = true;
	const saved = ui.get().favorites;
	if (Array.isArray(saved)) _items = saved as string[];
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
		}
	};
}
