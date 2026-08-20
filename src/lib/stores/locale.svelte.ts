import { ui } from '$lib/db';
import en from '$lib/i18n/en';
import es from '$lib/i18n/es';

export type Locale = 'en' | 'es';
type Dict = typeof en;

const dicts: Record<Locale, Dict> = { en, es };

let _locale = $state<Locale>('en');

export function initLocale(): void {
	const saved = ui.get().locale as string;
	if (saved === 'es') _locale = 'es';
}

export function setLocale(l: Locale): void {
	_locale = l;
	ui.patch({ locale: l });
}

export function useLocale() {
	const dict = $derived(dicts[_locale]);
	return {
		get t(): Dict { return dict; },
		get locale(): Locale { return _locale; }
	};
}
