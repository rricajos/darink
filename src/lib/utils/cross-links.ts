import type { Entry } from '$lib/db';

export interface MedSymptomCorrelation {
	medication: string;
	symptom: string;
	coOccurrences: number;
	totalMedDays: number;
	totalSymptomDays: number;
	correlation: number;
}

export function computeMedSymptomCorrelations(entries: Entry[]): MedSymptomCorrelation[] {
	// Build sets of dates per medication and per symptom
	const medDates = new Map<string, Set<string>>();
	const symptomDates = new Map<string, Set<string>>();

	for (const e of entries) {
		const date = (e.data.date as string) ?? e.createdAt.slice(0, 10);
		if (!date) continue;

		if (e.type === 'medication') {
			const name = ((e.data.medication as string) ?? '').toLowerCase().trim();
			if (!name) continue;
			if (!medDates.has(name)) medDates.set(name, new Set());
			medDates.get(name)!.add(date);
		} else if (e.type === 'symptom') {
			const name = ((e.data.symptom as string) ?? '').toLowerCase().trim();
			if (!name) continue;
			if (!symptomDates.has(name)) symptomDates.set(name, new Set());
			symptomDates.get(name)!.add(date);
		}
	}

	const results: MedSymptomCorrelation[] = [];

	for (const [med, mDates] of medDates) {
		for (const [sym, sDates] of symptomDates) {
			let coOccurrences = 0;
			for (const d of mDates) {
				if (sDates.has(d)) coOccurrences++;
			}
			if (coOccurrences === 0) continue;

			const totalMedDays = mDates.size;
			const totalSymptomDays = sDates.size;
			const correlation = coOccurrences / Math.max(totalMedDays, totalSymptomDays);

			results.push({
				medication: med,
				symptom: sym,
				coOccurrences,
				totalMedDays,
				totalSymptomDays,
				correlation
			});
		}
	}

	results.sort((a, b) => b.coOccurrences - a.coOccurrences);
	return results;
}

export function getMedicationNamesFromRegimen(regimen: Array<{ name: string }>): string[] {
	return regimen.map((r) => r.name);
}
