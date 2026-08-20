<script lang="ts">
	import { useEntries } from '$lib/stores/entries.svelte';
	import { ui } from '$lib/db';
	import PageHeader from '$lib/components/PageHeader.svelte';
	import type { Entry } from '$lib/db';
	import { onMount } from 'svelte';
	import { useLocale } from '$lib/stores/locale.svelte';
	import { toast } from '$lib/stores/toast.svelte';

	const { t } = useLocale();
	const store = useEntries();
	const hydrationStore = useEntries('hydration');

	// Week selector: Monday-based weeks
	function toMonday(d: Date): Date {
		const copy = new Date(d);
		const day = copy.getDay();
		const diff = day === 0 ? 6 : day - 1;
		copy.setDate(copy.getDate() - diff);
		copy.setHours(0, 0, 0, 0);
		return copy;
	}

	function toSunday(monday: Date): Date {
		const copy = new Date(monday);
		copy.setDate(copy.getDate() + 6);
		copy.setHours(23, 59, 59, 999);
		return copy;
	}

	function isoDate(d: Date): string {
		return d.toISOString().slice(0, 10);
	}

	function fmtRange(mon: Date, sun: Date): string {
		const opts: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric' };
		const monStr = mon.toLocaleDateString(undefined, opts);
		const sunStr = sun.toLocaleDateString(undefined, { ...opts, year: 'numeric' });
		return `${monStr} - ${sunStr}`;
	}

	function dateOf(e: Entry): string {
		return (e.data.date as string) ?? e.createdAt.slice(0, 10);
	}

	let weekStart = $state(toMonday(new Date()));
	let weekEnd = $derived(toSunday(weekStart));
	let weekLabel = $derived(fmtRange(weekStart, weekEnd));

	// Previous week boundaries
	let prevWeekStart = $derived.by(() => {
		const d = new Date(weekStart);
		d.setDate(d.getDate() - 7);
		return d;
	});
	let prevWeekEnd = $derived(toSunday(prevWeekStart));

	function prevWeek() {
		const d = new Date(weekStart);
		d.setDate(d.getDate() - 7);
		weekStart = d;
	}

	function nextWeek() {
		const d = new Date(weekStart);
		d.setDate(d.getDate() + 7);
		weekStart = d;
	}

	function onDateInput(e: Event) {
		const val = (e.target as HTMLInputElement).value;
		if (val) weekStart = toMonday(new Date(val));
	}

	// Filter entries to a date range
	function entriesInRange(start: Date, end: Date): Entry[] {
		const s = start.toISOString();
		const e = end.toISOString();
		return store.items.filter((entry) => {
			const d = entry.data.date ? new Date(entry.data.date as string).toISOString() : entry.createdAt;
			return d >= s && d <= e;
		});
	}

	// Current week entries
	const weekEntries = $derived(entriesInRange(weekStart, weekEnd));

	// Previous week entries
	const prevEntries = $derived(entriesInRange(prevWeekStart, prevWeekEnd));

	// Entry counts by type
	const typeCounts = $derived.by(() => {
		const counts: Record<string, number> = {};
		for (const e of weekEntries) {
			counts[e.type] = (counts[e.type] || 0) + 1;
		}
		return Object.entries(counts).sort((a, b) => b[1] - a[1]);
	});

	// --- Check-in averages (current + previous) ---
	function checkinAvgs(entries: Entry[]) {
		const cks = entries.filter((e) => e.type === 'checkin');
		if (cks.length === 0) return { mood: null, energy: null, stress: null, sleep: null, count: 0 };
		const avg = (field: string) => {
			const vals = cks.map((e) => Number(e.data[field]) || 0);
			return +(vals.reduce((a, b) => a + b, 0) / vals.length).toFixed(1);
		};
		return { mood: avg('mood'), energy: avg('energy'), stress: avg('stress'), sleep: avg('sleep'), count: cks.length };
	}

	const currentCheckins = $derived(checkinAvgs(weekEntries));
	const prevCheckins = $derived(checkinAvgs(prevEntries));

	// --- Daily mood/energy data for bar chart ---
	const DAY_LABELS = ['M', 'T', 'W', 'T', 'F', 'S', 'S'] as const;

	const dailyMoodEnergy = $derived.by(() => {
		const days: { label: string; date: string; mood: number | null; energy: number | null }[] = [];
		for (let i = 0; i < 7; i++) {
			const d = new Date(weekStart);
			d.setDate(d.getDate() + i);
			const key = isoDate(d);
			const dayCks = weekEntries.filter((e) => e.type === 'checkin' && dateOf(e) === key);
			if (dayCks.length > 0) {
				const latest = dayCks[dayCks.length - 1];
				days.push({
					label: DAY_LABELS[i],
					date: key,
					mood: Number(latest.data.mood) || null,
					energy: Number(latest.data.energy) || null
				});
			} else {
				days.push({ label: DAY_LABELS[i], date: key, mood: null, energy: null });
			}
		}
		return days;
	});

	// --- Training sessions (current + previous) ---
	const TRAINING_TYPES = ['training.strength', 'training.rings', 'training.hiit', 'training.cardio', 'training.mobility'] as const;
	const TYPE_LABELS = $derived.by((): Record<string, string> => ({
		'training.strength': t.report.strength,
		'training.rings': t.report.rings,
		'training.hiit': t.report.hiit,
		'training.cardio': t.report.cardio,
		'training.mobility': t.report.mobility
	}));

	function countTraining(entries: Entry[]) {
		const te = entries.filter((e) => TRAINING_TYPES.includes(e.type as typeof TRAINING_TYPES[number]));
		const byType: Record<string, number> = {};
		for (const e of te) {
			const label = TYPE_LABELS[e.type] ?? e.type;
			byType[label] = (byType[label] || 0) + 1;
		}
		return { total: te.length, byType: Object.entries(byType).sort((a, b) => b[1] - a[1]) };
	}

	const currentTraining = $derived(countTraining(weekEntries));
	const prevTraining = $derived(countTraining(prevEntries));

	// --- Habit completion (current + previous) ---
	function countHabits(entries: Entry[]) {
		const he = entries.filter((e) => e.type === 'habit');
		const map: Record<string, Set<string>> = {};
		for (const e of he) {
			const h = String(e.data.habit || '');
			const d = String(e.data.date || e.createdAt.slice(0, 10));
			if (!h) continue;
			if (!map[h]) map[h] = new Set();
			map[h].add(d);
		}
		return Object.entries(map)
			.map(([habit, days]) => ({ habit, days: days.size }))
			.sort((a, b) => b.days - a.days);
	}

	const currentHabits = $derived(countHabits(weekEntries));
	const prevHabits = $derived(countHabits(prevEntries));

	// Previous habit lookup for comparison
	const prevHabitMap = $derived.by(() => {
		const m = new Map<string, number>();
		for (const h of prevHabits) m.set(h.habit, h.days);
		return m;
	});

	// --- Supplement adherence ---
	const supplementEntries = $derived(weekEntries.filter((e) => e.type === 'supplement'));
	let plannedStack = $state<Array<{ name: string; dose: string; timing: string }>>([]);
	onMount(() => {
		const saved = ui.get();
		if (Array.isArray(saved.supplementStack)) {
			plannedStack = saved.supplementStack as Array<{ name: string; dose: string; timing: string }>;
		}
	});
	const suppAdherence = $derived.by(() => {
		if (plannedStack.length === 0) return null;
		const results: { name: string; daysLogged: number }[] = [];
		for (const planned of plannedStack) {
			const days = new Set<string>();
			for (const e of supplementEntries) {
				if ((e.data.name as string)?.toLowerCase() === planned.name.toLowerCase()) {
					const d = String(e.data.date || e.createdAt.slice(0, 10));
					days.add(d);
				}
			}
			results.push({ name: planned.name, daysLogged: days.size });
		}
		return results;
	});

	// Previous supplement adherence
	const prevSuppAdherence = $derived.by(() => {
		if (plannedStack.length === 0) return null;
		const prevSupps = prevEntries.filter((e) => e.type === 'supplement');
		const results: { name: string; daysLogged: number }[] = [];
		for (const planned of plannedStack) {
			const days = new Set<string>();
			for (const e of prevSupps) {
				if ((e.data.name as string)?.toLowerCase() === planned.name.toLowerCase()) {
					const d = String(e.data.date || e.createdAt.slice(0, 10));
					days.add(d);
				}
			}
			results.push({ name: planned.name, daysLogged: days.size });
		}
		return results;
	});

	// --- Weight change ---
	const weightEntries = $derived(
		weekEntries.filter((e) => e.type === 'weight').sort((a, b) => a.createdAt.localeCompare(b.createdAt))
	);
	const firstWeight = $derived(weightEntries.length > 0 ? Number(weightEntries[0].data.weight) || null : null);
	const lastWeight = $derived(weightEntries.length > 0 ? Number(weightEntries[weightEntries.length - 1].data.weight) || null : null);
	const weightDelta = $derived(
		firstWeight !== null && lastWeight !== null && weightEntries.length > 1
			? (lastWeight - firstWeight).toFixed(1)
			: null
	);

	// --- Top intakes ---
	const intakeEntries = $derived(weekEntries.filter((e) => e.type === 'intake'));
	const topIntakes = $derived.by(() => {
		const counts = new Map<string, number>();
		for (const e of intakeEntries) {
			const w = String(e.data.what || '').trim().toLowerCase();
			if (w) counts.set(w, (counts.get(w) || 0) + 1);
		}
		return [...counts.entries()]
			.sort((a, b) => b[1] - a[1])
			.slice(0, 10);
	});

	// --- Journal entries ---
	const journalEntries = $derived(
		weekEntries.filter((e) => e.type === 'journal').sort((a, b) => a.createdAt.localeCompare(b.createdAt))
	);

	// --- Hydration weekly summary ---
	function hydrationInRange(start: Date, end: Date) {
		const s = isoDate(start);
		const e = isoDate(end);
		return hydrationStore.items.filter((entry) => {
			const d = (entry.data.date as string) ?? entry.createdAt.slice(0, 10);
			return d >= s && d <= e;
		});
	}

	const weekHydration = $derived(hydrationInRange(weekStart, weekEnd));
	const prevWeekHydration = $derived(hydrationInRange(prevWeekStart, prevWeekEnd));

	let hydrationTarget = $state(3000);
	onMount(() => {
		const saved = ui.get();
		if (typeof saved.hydrationTarget === 'number') {
			hydrationTarget = saved.hydrationTarget;
		}
	});

	const hydrationSummary = $derived.by(() => {
		if (weekHydration.length === 0) return null;
		const totalMl = weekHydration.reduce((sum, e) => sum + (Number(e.data.amount) || 0), 0);
		// Count unique days
		const days = new Set(weekHydration.map((e) => (e.data.date as string) ?? e.createdAt.slice(0, 10)));
		const daysCount = days.size;
		// Days meeting target
		const dailyTotals = new Map<string, number>();
		for (const e of weekHydration) {
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			dailyTotals.set(d, (dailyTotals.get(d) || 0) + (Number(e.data.amount) || 0));
		}
		const metTarget = [...dailyTotals.values()].filter((v) => v >= hydrationTarget).length;
		const avgDaily = daysCount > 0 ? Math.round(totalMl / daysCount) : 0;
		return { totalMl, daysCount, metTarget, avgDaily };
	});

	const prevHydrationSummary = $derived.by(() => {
		if (prevWeekHydration.length === 0) return null;
		const totalMl = prevWeekHydration.reduce((sum, e) => sum + (Number(e.data.amount) || 0), 0);
		const days = new Set(prevWeekHydration.map((e) => (e.data.date as string) ?? e.createdAt.slice(0, 10)));
		const daysCount = days.size;
		const avgDaily = daysCount > 0 ? Math.round(totalMl / daysCount) : 0;
		return { totalMl, daysCount, avgDaily };
	});

	// --- Weekly composite score ---
	const weeklyScore = $derived.by(() => {
		type Component = { weight: number; value: number };
		const components: Component[] = [];
		const uiData = ui.get();

		// Mood + Energy average (25%)
		if (currentCheckins.mood !== null && currentCheckins.energy !== null) {
			const avg = ((currentCheckins.mood + currentCheckins.energy) / 2) * 10;
			components.push({ weight: 25, value: avg });
		}

		// Sleep quality (25%)
		if (currentCheckins.sleep !== null) {
			const sleepVal = Math.min(currentCheckins.sleep, 10) * 10;
			components.push({ weight: 25, value: sleepVal });
		}

		// Habit completion rate (20%)
		const customH = Array.isArray(uiData.customHabits) ? (uiData.customHabits as Array<{ id: string }>) : [];
		const totalHabitTypes = 6 + customH.length;
		if (totalHabitTypes > 0 && currentHabits.length > 0) {
			// Average days across habits / 7
			const avgDays = currentHabits.reduce((s, h) => s + h.days, 0) / currentHabits.length;
			const pct = Math.min((avgDays / 7) * 100, 100);
			components.push({ weight: 20, value: pct });
		}

		// Supplement adherence (15%)
		if (suppAdherence !== null && suppAdherence.length > 0) {
			const avgAdh = suppAdherence.reduce((s, su) => s + su.daysLogged, 0) / suppAdherence.length;
			const pct = Math.min((avgAdh / 7) * 100, 100);
			components.push({ weight: 15, value: pct });
		}

		// Training days/7 (15%)
		const trainingDays = new Set(
			weekEntries
				.filter((e) => TRAINING_TYPES.includes(e.type as typeof TRAINING_TYPES[number]))
				.map((e) => dateOf(e))
		).size;
		components.push({ weight: 15, value: Math.min((trainingDays / 7) * 100, 100) });

		if (components.length === 0) return { score: 0, hasData: false };

		const totalWeight = components.reduce((s, c) => s + c.weight, 0);
		const weighted = components.reduce((s, c) => s + (c.value * c.weight / totalWeight), 0);
		return { score: Math.round(weighted), hasData: true };
	});

	// Previous week score for comparison
	const prevWeeklyScore = $derived.by(() => {
		type Component = { weight: number; value: number };
		const components: Component[] = [];
		const uiData = ui.get();

		if (prevCheckins.mood !== null && prevCheckins.energy !== null) {
			const avg = ((prevCheckins.mood + prevCheckins.energy) / 2) * 10;
			components.push({ weight: 25, value: avg });
		}

		if (prevCheckins.sleep !== null) {
			const sleepVal = Math.min(prevCheckins.sleep, 10) * 10;
			components.push({ weight: 25, value: sleepVal });
		}

		const customH = Array.isArray(uiData.customHabits) ? (uiData.customHabits as Array<{ id: string }>) : [];
		const totalHabitTypes = 6 + customH.length;
		if (totalHabitTypes > 0 && prevHabits.length > 0) {
			const avgDays = prevHabits.reduce((s, h) => s + h.days, 0) / prevHabits.length;
			const pct = Math.min((avgDays / 7) * 100, 100);
			components.push({ weight: 20, value: pct });
		}

		if (prevSuppAdherence !== null && prevSuppAdherence.length > 0) {
			const avgAdh = prevSuppAdherence.reduce((s, su) => s + su.daysLogged, 0) / prevSuppAdherence.length;
			const pct = Math.min((avgAdh / 7) * 100, 100);
			components.push({ weight: 15, value: pct });
		}

		const trainingDays = new Set(
			prevEntries
				.filter((e) => TRAINING_TYPES.includes(e.type as typeof TRAINING_TYPES[number]))
				.map((e) => dateOf(e))
		).size;
		components.push({ weight: 15, value: Math.min((trainingDays / 7) * 100, 100) });

		if (components.length === 0) return { score: 0, hasData: false };

		const totalWeight = components.reduce((s, c) => s + c.weight, 0);
		const weighted = components.reduce((s, c) => s + (c.value * c.weight / totalWeight), 0);
		return { score: Math.round(weighted), hasData: true };
	});

	function scoreColor(score: number): string {
		if (score < 40) return '#e53e3e';
		if (score <= 70) return '#e8a735';
		return 'var(--c-done)';
	}

	// --- Delta helpers ---
	function delta(current: number | null, prev: number | null): { value: string; direction: 'up' | 'down' | 'same' } | null {
		if (current === null || prev === null) return null;
		const diff = +(current - prev).toFixed(1);
		if (diff > 0) return { value: `+${diff}`, direction: 'up' };
		if (diff < 0) return { value: `${diff}`, direction: 'down' };
		return { value: '0', direction: 'same' };
	}

	// Stress is inverted: lower is better
	function deltaInverted(current: number | null, prev: number | null): { value: string; direction: 'up' | 'down' | 'same' } | null {
		if (current === null || prev === null) return null;
		const diff = +(current - prev).toFixed(1);
		if (diff < 0) return { value: `${diff}`, direction: 'up' };
		if (diff > 0) return { value: `+${diff}`, direction: 'down' };
		return { value: '0', direction: 'same' };
	}

	// Type display label
	function typeLabel(tp: string): string {
		const labels: Record<string, string> = {
			checkin: t.timeline.checkin,
			intake: t.timeline.intake,
			journal: t.report.journal,
			habit: t.report.habit,
			supplement: t.report.supplement,
			weight: t.report.weight,
			experiment: t.timeline.experiment,
			hydration: t.timeline.hydration,
			'training.strength': t.report.strength,
			'training.rings': t.report.rings,
			'training.hiit': t.report.hiit,
			'training.cardio': t.report.cardio,
			'training.mobility': t.report.mobility,
			'signal.sleep': t.report.sleepSignalLabel,
			'signal.skin': t.report.skinSignalLabel,
			'signal.hair': t.report.hairSignalLabel,
			'signal.genital': t.report.genitalSignalLabel
		};
		return labels[tp] ?? tp;
	}

	function doPrint() {
		window.print();
	}

	function buildReportText(): string {
		const lines: string[] = [`Darink — ${t.report.weeklyReport}`, weekLabel, ''];
		if (weeklyScore.hasData) lines.push(`${t.report.score}: ${weeklyScore.score}/100`);
		if (currentCheckins.count > 0) {
			lines.push('', `${t.report.checkinAverages} (${currentCheckins.count}):`);
			if (currentCheckins.mood !== null) lines.push(`  ${t.common.mood}: ${currentCheckins.mood}`);
			if (currentCheckins.energy !== null) lines.push(`  ${t.common.energy}: ${currentCheckins.energy}`);
			if (currentCheckins.sleep !== null) lines.push(`  ${t.common.sleep}: ${currentCheckins.sleep}h`);
			if (currentCheckins.stress !== null) lines.push(`  ${t.common.stress}: ${currentCheckins.stress}`);
		}
		if (currentTraining.total > 0) {
			lines.push('', `${t.report.training}: ${currentTraining.total} ${t.report.sessions}`);
			for (const [type, count] of currentTraining.byType) lines.push(`  ${type}: ${count}`);
		}
		lines.push('', `${t.report.totalEntries}: ${weekEntries.length}`);
		return lines.join('\n');
	}

	async function copyReport() {
		await navigator.clipboard.writeText(buildReportText());
		toast.show(t.report.copied);
	}

	function downloadReport() {
		const blob = new Blob([JSON.stringify(weekEntries, null, 2)], { type: 'application/json' });
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = `darink-report-${isoDate(weekStart)}.json`;
		a.click();
		URL.revokeObjectURL(url);
		toast.show(t.report.downloaded);
	}

	// --- Medication Adherence ---
	let medicationRegimen = $state<Array<{ name: string; dose: string; frequency: string }>>([]);
	onMount(() => {
		const saved = ui.get();
		if (Array.isArray(saved.medicationRegimen)) {
			medicationRegimen = saved.medicationRegimen as Array<{ name: string; dose: string; frequency: string }>;
		}
	});

	const medicationAdherence = $derived.by(() => {
		if (medicationRegimen.length === 0) return null;
		const results: { name: string; dose: string; daysLogged: number; pct: number }[] = [];
		for (const med of medicationRegimen) {
			const days = new Set<string>();
			for (const e of supplementEntries) {
				if ((e.data.name as string)?.toLowerCase() === med.name.toLowerCase()) {
					const d = String(e.data.date || e.createdAt.slice(0, 10));
					days.add(d);
				}
			}
			const pct = Math.min(Math.round((days.size / 7) * 100), 100);
			results.push({ name: med.name, dose: med.dose, daysLogged: days.size, pct });
		}
		return results;
	});

	// --- Supplement Compliance (horizontal bar version) ---
	const supplementCompliance = $derived.by(() => {
		if (plannedStack.length === 0) return null;
		const results: { name: string; dose: string; timing: string; daysLogged: number; pct: number }[] = [];
		for (const item of plannedStack) {
			const days = new Set<string>();
			for (const e of supplementEntries) {
				if ((e.data.name as string)?.toLowerCase() === item.name.toLowerCase()) {
					const d = String(e.data.date || e.createdAt.slice(0, 10));
					days.add(d);
				}
			}
			const pct = Math.min(Math.round((days.size / 7) * 100), 100);
			results.push({ name: item.name, dose: item.dose, timing: item.timing, daysLogged: days.size, pct });
		}
		return results;
	});

	// --- Training Volume Summary ---
	function trainingVolume(entries: Entry[]) {
		const te = entries.filter((e) => TRAINING_TYPES.includes(e.type as typeof TRAINING_TYPES[number]));
		const totalSessions = te.length;
		const totalMinutes = te.reduce((sum, e) => sum + (Number(e.data.durationMin) || 0), 0);
		const byType: Record<string, { count: number; minutes: number }> = {};
		for (const e of te) {
			const label = TYPE_LABELS[e.type] ?? e.type;
			if (!byType[label]) byType[label] = { count: 0, minutes: 0 };
			byType[label].count += 1;
			byType[label].minutes += Number(e.data.durationMin) || 0;
		}
		return {
			totalSessions,
			totalMinutes,
			byType: Object.entries(byType).sort((a, b) => b[1].count - a[1].count)
		};
	}

	const currentVolume = $derived(trainingVolume(weekEntries));
	const prevVolume = $derived(trainingVolume(prevEntries));

	const TRAINING_COLORS = $derived.by((): Record<string, string> => ({
		[t.report.strength]: '#6366f1',
		[t.report.rings]: '#f59e0b',
		[t.report.hiit]: '#ef4444',
		[t.report.cardio]: '#10b981',
		[t.report.mobility]: '#8b5cf6'
	}));

	// --- Signal Summary ---
	const signalSummary = $derived.by(() => {
		const result: {
			sleep: { avgHours: number; avgQuality: number; count: number } | null;
			skin: { avgElasticity: number; count: number } | null;
			hair: { latestDensity: number | null; count: number } | null;
			genital: { avgLibido: number; count: number } | null;
		} = { sleep: null, skin: null, hair: null, genital: null };

		// Sleep signals
		const sleepEntries = weekEntries.filter((e) => e.type === 'signal.sleep');
		if (sleepEntries.length > 0) {
			const hours = sleepEntries.map((e) => Number(e.data.hours) || 0);
			const quality = sleepEntries.map((e) => Number(e.data.quality) || 0);
			result.sleep = {
				avgHours: +(hours.reduce((a, b) => a + b, 0) / hours.length).toFixed(1),
				avgQuality: +(quality.reduce((a, b) => a + b, 0) / quality.length).toFixed(1),
				count: sleepEntries.length
			};
		}

		// Skin signals
		const skinEntries = weekEntries.filter((e) => e.type === 'signal.skin');
		if (skinEntries.length > 0) {
			const elasticity = skinEntries.map((e) => Number(e.data.elasticity) || 0);
			result.skin = {
				avgElasticity: +(elasticity.reduce((a, b) => a + b, 0) / elasticity.length).toFixed(1),
				count: skinEntries.length
			};
		}

		// Hair signals
		const hairEntries = weekEntries.filter((e) => e.type === 'signal.hair');
		if (hairEntries.length > 0) {
			const sorted = [...hairEntries].sort((a, b) => a.createdAt.localeCompare(b.createdAt));
			const latest = sorted[sorted.length - 1];
			result.hair = {
				latestDensity: Number(latest.data.density) || null,
				count: hairEntries.length
			};
		}

		// Genital signals
		const genitalEntries = weekEntries.filter((e) => e.type === 'signal.genital');
		if (genitalEntries.length > 0) {
			const libido = genitalEntries.map((e) => Number(e.data.libido) || 0);
			result.genital = {
				avgLibido: +(libido.reduce((a, b) => a + b, 0) / libido.length).toFixed(1),
				count: genitalEntries.length
			};
		}

		const hasAny = result.sleep || result.skin || result.hair || result.genital;
		return hasAny ? result : null;
	});

	// --- Overall Weekly Grade ---
	function letterGrade(score: number): string {
		if (score >= 80) return 'A';
		if (score >= 65) return 'B';
		if (score >= 50) return 'C';
		if (score >= 35) return 'D';
		return 'F';
	}

	function gradeColor(grade: string): string {
		switch (grade) {
			case 'A': return '#16a34a';
			case 'B': return '#22c55e';
			case 'C': return '#e8a735';
			case 'D': return '#f97316';
			case 'F': return '#e53e3e';
			default: return 'var(--c-text-muted)';
		}
	}

	const currentGrade = $derived(weeklyScore.hasData ? letterGrade(weeklyScore.score) : null);
	const prevGrade = $derived(prevWeeklyScore.hasData ? letterGrade(prevWeeklyScore.score) : null);
</script>

<svelte:head>
	<title>{t.report.weeklyReport} | Darink</title>
</svelte:head>

<PageHeader title={t.report.weeklyReport} back="/more" />

<!-- Week selector -->
<section class="week-nav no-print">
	<button onclick={prevWeek} aria-label="Previous week">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
	</button>
	<input type="date" value={isoDate(weekStart)} oninput={onDateInput} />
	<button onclick={nextWeek} aria-label="Next week">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
	</button>
	<button class="print-btn" onclick={doPrint}>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
		{t.report.print}
	</button>
	<button class="share-btn" onclick={copyReport}>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
		{t.report.copy}
	</button>
	<button class="share-btn" onclick={downloadReport}>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
		{t.report.download}
	</button>
</section>

<!-- Report content -->
<article class="report">
	<!-- Header row: week range + weekly score -->
	<div class="report-header">
		<h2 class="report-range">{weekLabel}</h2>
		{#if weeklyScore.hasData}
			{@const sc = weeklyScore.score}
			{@const scoreDelta = weeklyScore.hasData && prevWeeklyScore.hasData ? delta(weeklyScore.score, prevWeeklyScore.score) : null}
			<div class="weekly-score" style="--score-color: {scoreColor(sc)}">
				<span class="weekly-score-num">{sc}</span>
				<span class="weekly-score-label">{t.report.score}</span>
				{#if scoreDelta}
					<span class="delta delta-{scoreDelta.direction}">{scoreDelta.value}</span>
				{/if}
			</div>
		{/if}
	</div>

	{#if weekEntries.length === 0}
		<div class="empty-state">
			<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
			<p>{t.report.noEntriesThisWeek}</p>
			<p class="empty-hint">{t.report.noEntriesHint}</p>
		</div>
	{:else}
		<!-- Entry count by type -->
		<section class="report-section">
			<h2>{t.report.activityOverview}</h2>
			<div class="metrics-row">
				<div class="metric-card">
					<span class="metric-value">{weekEntries.length}</span>
					<span class="metric-label">{t.report.totalEntries}</span>
				</div>
				{#each typeCounts.slice(0, 5) as [tp, count]}
					<div class="metric-card">
						<span class="metric-value">{count}</span>
						<span class="metric-label">{typeLabel(tp)}</span>
					</div>
				{/each}
			</div>
			{#if typeCounts.length > 5}
				<table class="data-table">
					<thead><tr><th>{t.report.type}</th><th>{t.report.count}</th></tr></thead>
					<tbody>
						{#each typeCounts as [tp, count]}
							<tr><td>{typeLabel(tp)}</td><td>{count}</td></tr>
						{/each}
					</tbody>
				</table>
			{/if}
		</section>

		<!-- Check-in averages with week-over-week comparison -->
		{#if currentCheckins.count > 0}
			{@const moodDelta = delta(currentCheckins.mood, prevCheckins.mood)}
			{@const energyDelta = delta(currentCheckins.energy, prevCheckins.energy)}
			{@const stressDelta = deltaInverted(currentCheckins.stress, prevCheckins.stress)}
			{@const sleepDelta = delta(currentCheckins.sleep, prevCheckins.sleep)}
			<section class="report-section">
				<h2>{t.report.checkinAverages}</h2>
				<div class="metrics-row">
					{#if currentCheckins.mood !== null}
						<div class="metric-card">
							<span class="metric-value">{currentCheckins.mood}</span>
							<span class="metric-label">{t.common.mood}</span>
							{#if moodDelta}
								<span class="delta delta-{moodDelta.direction}">{moodDelta.value}</span>
							{/if}
						</div>
					{/if}
					{#if currentCheckins.energy !== null}
						<div class="metric-card">
							<span class="metric-value">{currentCheckins.energy}</span>
							<span class="metric-label">{t.common.energy}</span>
							{#if energyDelta}
								<span class="delta delta-{energyDelta.direction}">{energyDelta.value}</span>
							{/if}
						</div>
					{/if}
					{#if currentCheckins.stress !== null}
						<div class="metric-card">
							<span class="metric-value">{currentCheckins.stress}</span>
							<span class="metric-label">{t.common.stress}</span>
							{#if stressDelta}
								<span class="delta delta-{stressDelta.direction}">{stressDelta.value}</span>
							{/if}
						</div>
					{/if}
					{#if currentCheckins.sleep !== null}
						<div class="metric-card">
							<span class="metric-value">{currentCheckins.sleep}</span>
							<span class="metric-label">{t.common.sleep} (h)</span>
							{#if sleepDelta}
								<span class="delta delta-{sleepDelta.direction}">{sleepDelta.value}</span>
							{/if}
						</div>
					{/if}
				</div>
				<p class="note">{t.report.basedOnCheckins.replace('{n}', String(currentCheckins.count))}{prevCheckins.count > 0 ? ` (${t.report.prevWeekLabel} ${prevCheckins.count})` : ''}</p>
			</section>

			<!-- Daily mood/energy mini-chart -->
			<section class="report-section">
				<h2>{t.report.dailyMoodEnergy}</h2>
				<div class="mini-charts">
					<!-- Mood chart -->
					<div class="mini-chart-block">
						<span class="mini-chart-title">{t.common.mood}</span>
						<svg viewBox="0 0 154 52" class="mini-chart-svg" role="img" aria-label="Daily mood chart">
							{#each dailyMoodEnergy as day, i}
								{@const barH = day.mood !== null ? (day.mood / 10) * 36 : 0}
								<rect
									x={i * 22 + 1}
									y={40 - barH}
									width="16"
									height={barH}
									rx="2"
									fill={day.mood !== null ? 'var(--c-accent)' : 'var(--c-border)'}
									opacity={day.mood !== null ? 1 : 0.3}
								/>
								<text
									x={i * 22 + 9}
									y="50"
									text-anchor="middle"
									class="bar-label"
								>{day.label}</text>
								{#if day.mood !== null}
									<text
										x={i * 22 + 9}
										y={40 - barH - 2}
										text-anchor="middle"
										class="bar-value"
									>{day.mood}</text>
								{/if}
							{/each}
						</svg>
					</div>
					<!-- Energy chart -->
					<div class="mini-chart-block">
						<span class="mini-chart-title">{t.common.energy}</span>
						<svg viewBox="0 0 154 52" class="mini-chart-svg" role="img" aria-label="Daily energy chart">
							{#each dailyMoodEnergy as day, i}
								{@const barH = day.energy !== null ? (day.energy / 10) * 36 : 0}
								<rect
									x={i * 22 + 1}
									y={40 - barH}
									width="16"
									height={barH}
									rx="2"
									fill={day.energy !== null ? 'var(--c-done)' : 'var(--c-border)'}
									opacity={day.energy !== null ? 1 : 0.3}
								/>
								<text
									x={i * 22 + 9}
									y="50"
									text-anchor="middle"
									class="bar-label"
								>{day.label}</text>
								{#if day.energy !== null}
									<text
										x={i * 22 + 9}
										y={40 - barH - 2}
										text-anchor="middle"
										class="bar-value"
									>{day.energy}</text>
								{/if}
							{/each}
						</svg>
					</div>
				</div>
			</section>
		{/if}

		<!-- Training sessions -->
		{#if currentTraining.total > 0}
			{@const trainDelta = delta(currentTraining.total, prevTraining.total)}
			<section class="report-section">
				<h2>{t.report.training}</h2>
				<div class="metrics-row">
					<div class="metric-card">
						<span class="metric-value">{currentTraining.total}</span>
						<span class="metric-label">{t.report.sessions}</span>
						{#if trainDelta}
							<span class="delta delta-{trainDelta.direction}">{trainDelta.value}</span>
						{/if}
					</div>
					{#each currentTraining.byType as [label, count]}
						<div class="metric-card">
							<span class="metric-value">{count}</span>
							<span class="metric-label">{label}</span>
						</div>
					{/each}
				</div>
			</section>
		{/if}

		<!-- Habit completion -->
		{#if currentHabits.length > 0}
			<section class="report-section">
				<h2>{t.report.habits}</h2>
				<table class="data-table">
					<thead><tr><th>{t.report.habit}</th><th>{t.report.daysDone}</th><th>{t.report.vsPrev}</th></tr></thead>
					<tbody>
						{#each currentHabits as h}
							{@const prevDays = prevHabitMap.get(h.habit) ?? null}
							{@const hDelta = delta(h.days, prevDays)}
							<tr>
								<td>{h.habit}</td>
								<td>{h.days}/7</td>
								<td>
									{#if hDelta}
										<span class="delta delta-{hDelta.direction}">{hDelta.value}</span>
									{:else}
										<span class="delta delta-same">--</span>
									{/if}
								</td>
							</tr>
						{/each}
					</tbody>
				</table>
			</section>
		{/if}

		<!-- Supplement adherence -->
		{#if suppAdherence !== null && suppAdherence.length > 0}
			<section class="report-section">
				<h2>{t.report.supplementAdherence}</h2>
				<table class="data-table">
					<thead><tr><th>{t.report.supplement}</th><th>{t.report.daysTaken}</th><th>{t.report.vsPrev}</th></tr></thead>
					<tbody>
						{#each suppAdherence as s, idx}
							{@const prevDays = prevSuppAdherence !== null ? prevSuppAdherence[idx]?.daysLogged ?? null : null}
							{@const sDelta = delta(s.daysLogged, prevDays)}
							<tr>
								<td>{s.name}</td>
								<td>{s.daysLogged}/7</td>
								<td>
									{#if sDelta}
										<span class="delta delta-{sDelta.direction}">{sDelta.value}</span>
									{:else}
										<span class="delta delta-same">--</span>
									{/if}
								</td>
							</tr>
						{/each}
					</tbody>
				</table>
			</section>
		{/if}

		<!-- Hydration weekly summary -->
		{#if hydrationSummary}
			{@const hydDelta = delta(hydrationSummary.avgDaily, prevHydrationSummary?.avgDaily ?? null)}
			<section class="report-section">
				<h2>{t.report.hydration}</h2>
				<div class="metrics-row">
					<div class="metric-card">
						<span class="metric-value">{(hydrationSummary.totalMl / 1000).toFixed(1)}L</span>
						<span class="metric-label">{t.common.total}</span>
					</div>
					<div class="metric-card">
						<span class="metric-value">{hydrationSummary.metTarget}/{hydrationSummary.daysCount}</span>
						<span class="metric-label">{t.report.daysOnTarget}</span>
					</div>
					<div class="metric-card">
						<span class="metric-value">{(hydrationSummary.avgDaily / 1000).toFixed(1)}L</span>
						<span class="metric-label">{t.report.dailyAvg}</span>
						{#if hydDelta}
							<span class="delta delta-{hydDelta.direction}">{hydDelta.value}ml</span>
						{/if}
					</div>
				</div>
				{#if prevHydrationSummary}
					<p class="note">{t.report.prevWeekLabel} {(prevHydrationSummary.totalMl / 1000).toFixed(1)}L {t.common.total.toLowerCase()}, {(prevHydrationSummary.avgDaily / 1000).toFixed(1)}L {t.report.dailyAvg.toLowerCase()}</p>
				{/if}
			</section>
		{/if}

		<!-- Weight change -->
		{#if weightEntries.length > 0}
			<section class="report-section">
				<h2>{t.report.weight}</h2>
				<div class="metrics-row">
					{#if lastWeight !== null}
						<div class="metric-card">
							<span class="metric-value">{lastWeight} kg</span>
							<span class="metric-label">{t.report.latest}</span>
						</div>
					{/if}
					{#if weightDelta !== null}
						<div class="metric-card">
							<span class="metric-value" class:positive={Number(weightDelta) > 0} class:negative={Number(weightDelta) < 0}>
								{Number(weightDelta) > 0 ? '+' : ''}{weightDelta} kg
							</span>
							<span class="metric-label">{t.report.change}</span>
						</div>
					{/if}
				</div>
			</section>
		{/if}

		<!-- Top intakes -->
		{#if topIntakes.length > 0}
			<section class="report-section">
				<h2>{t.report.topIntakes}</h2>
				<table class="data-table">
					<thead><tr><th>#</th><th>{t.report.foodDrink}</th><th>{t.report.count}</th></tr></thead>
					<tbody>
						{#each topIntakes as [name, count], i}
							<tr>
								<td>{i + 1}</td>
								<td class="capitalize">{name}</td>
								<td>{count}</td>
							</tr>
						{/each}
					</tbody>
				</table>
			</section>
		{/if}

		<!-- Journal entries -->
		{#if journalEntries.length > 0}
			<section class="report-section">
				<h2>{t.report.journal}</h2>
				{#each journalEntries as entry}
					<div class="journal-card">
						<div class="journal-meta">
							<span>{new Date(entry.createdAt).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })}</span>
							{#if entry.data.mood}
								<span class="mood-badge">{t.common.mood}: {entry.data.mood}/10</span>
							{/if}
						</div>
						<p class="journal-text">{entry.data.text}</p>
					</div>
				{/each}
			</section>
		{/if}

		<!-- Medication Adherence -->
		{#if medicationAdherence !== null && medicationAdherence.length > 0}
			<section class="report-section">
				<h2>{t.report.medicationAdherence}</h2>
				<div class="adherence-list">
					{#each medicationAdherence as med}
						<div class="adherence-row">
							<div class="adherence-info">
								<span class="adherence-name">{med.name}</span>
								<span class="adherence-dose">{med.dose}</span>
							</div>
							<div class="adherence-bar-wrap">
								<div
									class="adherence-bar"
									style="width: {med.pct}%; background: {med.pct >= 80 ? 'var(--c-done)' : med.pct >= 50 ? '#e8a735' : '#e53e3e'}"
								></div>
							</div>
							<span class="adherence-pct">{med.pct}%</span>
						</div>
					{/each}
				</div>
			</section>
		{/if}

		<!-- Supplement Compliance -->
		{#if supplementCompliance !== null && supplementCompliance.length > 0}
			<section class="report-section">
				<h2>{t.report.supplementCompliance}</h2>
				<div class="adherence-list">
					{#each supplementCompliance as sup}
						<div class="adherence-row">
							<div class="adherence-info">
								<span class="adherence-name">{sup.name}</span>
								<span class="adherence-dose">{sup.dose} &middot; {sup.timing}</span>
							</div>
							<div class="adherence-bar-wrap">
								<div
									class="adherence-bar"
									style="width: {sup.pct}%; background: {sup.pct >= 80 ? 'var(--c-done)' : sup.pct >= 50 ? '#e8a735' : '#e53e3e'}"
								></div>
							</div>
							<span class="adherence-pct">{sup.daysLogged}/7</span>
						</div>
					{/each}
				</div>
			</section>
		{/if}

		<!-- Training Volume Summary -->
		{#if currentVolume.totalSessions > 0}
			{@const sessionsDelta = delta(currentVolume.totalSessions, prevVolume.totalSessions)}
			{@const minutesDelta = delta(currentVolume.totalMinutes, prevVolume.totalMinutes)}
			<section class="report-section">
				<h2>{t.report.trainingVolume}</h2>
				<div class="metrics-row">
					<div class="metric-card">
						<span class="metric-value">{currentVolume.totalSessions}</span>
						<span class="metric-label">{t.report.sessions}</span>
						{#if sessionsDelta}
							<span class="delta delta-{sessionsDelta.direction}">{sessionsDelta.value}</span>
						{/if}
					</div>
					<div class="metric-card">
						<span class="metric-value">{currentVolume.totalMinutes}</span>
						<span class="metric-label">{t.report.minutes}</span>
						{#if minutesDelta}
							<span class="delta delta-{minutesDelta.direction}">{minutesDelta.value}</span>
						{/if}
					</div>
				</div>
				{#if currentVolume.byType.length > 0}
					<div class="training-breakdown">
						{#each currentVolume.byType as [label, data]}
							{@const color = TRAINING_COLORS[label] ?? 'var(--c-accent)'}
							<div class="training-type-row">
								<span class="training-type-dot" style="background: {color}"></span>
								<span class="training-type-label">{label}</span>
								<span class="training-type-count">{data.count}x</span>
								{#if data.minutes > 0}
									<span class="training-type-min">{data.minutes} min</span>
								{/if}
							</div>
						{/each}
					</div>
				{/if}
			</section>
		{/if}

		<!-- Signal Summary -->
		{#if signalSummary}
			<section class="report-section">
				<h2>{t.report.signalSummary}</h2>
				<div class="signal-grid">
					{#if signalSummary.sleep}
						<div class="signal-card">
							<div class="signal-icon">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
							</div>
							<div class="signal-data">
								<span class="signal-title">{t.report.sleepSignal}</span>
								<span class="signal-value">{signalSummary.sleep.avgHours}h avg</span>
								<span class="signal-sub">Quality: {signalSummary.sleep.avgQuality}/10</span>
							</div>
						</div>
					{/if}
					{#if signalSummary.skin}
						<div class="signal-card">
							<div class="signal-icon">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>
							</div>
							<div class="signal-data">
								<span class="signal-title">{t.report.skinSignal}</span>
								<span class="signal-value">Elasticity: {signalSummary.skin.avgElasticity}/10</span>
								<span class="signal-sub">{signalSummary.skin.count} {signalSummary.skin.count !== 1 ? t.report.readings : t.report.reading}</span>
							</div>
						</div>
					{/if}
					{#if signalSummary.hair}
						<div class="signal-card">
							<div class="signal-icon">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7c0-3.5-3.5-5-7-1.5C9.5 2 6 3.5 6 7c0 4 6.5 10 7 10.5C13.5 17 20 11 20 7Z"/></svg>
							</div>
							<div class="signal-data">
								<span class="signal-title">{t.report.hairSignal}</span>
								{#if signalSummary.hair.latestDensity !== null}
									<span class="signal-value">Density: {signalSummary.hair.latestDensity}/10</span>
								{:else}
									<span class="signal-value">Logged</span>
								{/if}
								<span class="signal-sub">{signalSummary.hair.count} {signalSummary.hair.count !== 1 ? t.report.readings : t.report.reading}</span>
							</div>
						</div>
					{/if}
					{#if signalSummary.genital}
						<div class="signal-card">
							<div class="signal-icon">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
							</div>
							<div class="signal-data">
								<span class="signal-title">{t.report.genitalSignal}</span>
								<span class="signal-value">Libido: {signalSummary.genital.avgLibido}/10</span>
								<span class="signal-sub">{signalSummary.genital.count} {signalSummary.genital.count !== 1 ? t.report.readings : t.report.reading}</span>
							</div>
						</div>
					{/if}
				</div>
			</section>
		{/if}

		<!-- Overall Weekly Grade -->
		{#if currentGrade}
			<section class="report-section grade-section">
				<h2>{t.report.overallWeeklyGrade}</h2>
				<div class="grade-container">
					<div class="grade-badge" style="--grade-color: {gradeColor(currentGrade)}">
						<span class="grade-letter">{currentGrade}</span>
						<span class="grade-score">{weeklyScore.score}/100</span>
					</div>
					{#if prevGrade}
						<div class="grade-comparison">
							{#if currentGrade === prevGrade}
								<span class="grade-same">{t.report.sameAsLastWeek} ({prevGrade})</span>
							{:else if weeklyScore.score > prevWeeklyScore.score}
								<span class="grade-improved">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
									{t.report.upFrom} {prevGrade} ({prevWeeklyScore.score})
								</span>
							{:else}
								<span class="grade-declined">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
									{t.report.downFrom} {prevGrade} ({prevWeeklyScore.score})
								</span>
							{/if}
						</div>
					{/if}
				</div>
			</section>
		{/if}
	{/if}
</article>

<style>
	/* Week navigation */
	.week-nav {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0 1rem 1rem;
	}

	.week-nav input[type="date"] {
		flex: 1;
		max-width: 180px;
	}

	.print-btn {
		display: inline-flex;
		align-items: center;
		gap: 0.35rem;
		margin-left: auto;
		background: var(--c-accent);
		color: #fff;
		border-color: var(--c-accent);
		font-weight: 600;
	}
	.share-btn {
		display: inline-flex;
		align-items: center;
		gap: 0.35rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		color: var(--c-text);
		font-weight: 600;
		font-size: 0.8rem;
	}
	.share-btn:hover { border-color: var(--c-accent); color: var(--c-accent); background: var(--c-bg-card); }

	/* Report */
	.report {
		padding: 0 1rem 2rem;
	}

	.report-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 0.75rem;
		margin-bottom: 1.5rem;
	}

	.report-range {
		font-size: 1.1rem;
		font-weight: 700;
		color: var(--c-text);
		text-transform: none;
		letter-spacing: 0;
		margin: 0;
	}

	/* Weekly score badge */
	.weekly-score {
		display: flex;
		align-items: center;
		gap: 0.3rem;
		background: var(--c-bg-card);
		border: 2px solid var(--score-color, var(--c-border));
		border-radius: var(--radius);
		padding: 0.35rem 0.65rem;
		flex-shrink: 0;
	}

	.weekly-score-num {
		font-size: 1.5rem;
		font-weight: 800;
		color: var(--score-color);
		line-height: 1;
	}

	.weekly-score-label {
		font-size: 0.6rem;
		font-weight: 600;
		text-transform: uppercase;
		color: var(--c-text-muted);
		line-height: 1;
	}

	.report-section {
		margin-bottom: 1.5rem;
		break-inside: avoid;
	}

	.report-section h2 {
		font-size: 0.85rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.05em;
		color: var(--c-text-muted);
		margin-bottom: 0.5rem;
		padding-bottom: 0.25rem;
		border-bottom: 1px solid var(--c-border);
	}

	.metrics-row {
		display: flex;
		gap: 0.5rem;
		flex-wrap: wrap;
	}

	.metric-card {
		flex: 1;
		min-width: 80px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem;
		text-align: center;
		display: flex;
		flex-direction: column;
		gap: 0.15rem;
	}

	.metric-value {
		font-size: 1.4rem;
		font-weight: 700;
	}

	.metric-label {
		font-size: 0.7rem;
		font-weight: 600;
		color: var(--c-text-muted);
		text-transform: uppercase;
	}

	.positive { color: var(--c-done); }
	.negative { color: var(--c-cancel); }

	.note {
		font-size: 0.75rem;
		color: var(--c-text-muted);
		margin-top: 0.35rem;
	}

	/* Delta indicators */
	.delta {
		font-size: 0.7rem;
		font-weight: 600;
		line-height: 1;
	}

	.delta-up {
		color: var(--c-done);
	}

	.delta-up::before {
		content: '';
		display: inline-block;
		width: 0;
		height: 0;
		border-left: 3.5px solid transparent;
		border-right: 3.5px solid transparent;
		border-bottom: 5px solid var(--c-done);
		margin-right: 2px;
		vertical-align: middle;
	}

	.delta-down {
		color: var(--c-cancel);
	}

	.delta-down::before {
		content: '';
		display: inline-block;
		width: 0;
		height: 0;
		border-left: 3.5px solid transparent;
		border-right: 3.5px solid transparent;
		border-top: 5px solid var(--c-cancel);
		margin-right: 2px;
		vertical-align: middle;
	}

	.delta-same {
		color: var(--c-text-muted);
	}

	.delta-same::before {
		content: '';
		display: inline-block;
		width: 8px;
		height: 2px;
		background: var(--c-text-muted);
		margin-right: 2px;
		vertical-align: middle;
	}

	/* Mini bar charts */
	.mini-charts {
		display: flex;
		gap: 1rem;
		flex-wrap: wrap;
	}

	.mini-chart-block {
		flex: 1;
		min-width: 160px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.5rem 0.75rem 0.35rem;
	}

	.mini-chart-title {
		font-size: 0.7rem;
		font-weight: 600;
		text-transform: uppercase;
		color: var(--c-text-muted);
	}

	.mini-chart-svg {
		width: 100%;
		height: auto;
		display: block;
		margin-top: 0.25rem;
	}

	.bar-label {
		font-size: 7px;
		fill: var(--c-text-muted);
		font-weight: 600;
	}

	.bar-value {
		font-size: 6px;
		fill: var(--c-text);
		font-weight: 700;
	}

	/* Data tables */
	.data-table {
		width: 100%;
		border-collapse: collapse;
		font-size: 0.85rem;
	}

	.data-table th,
	.data-table td {
		padding: 0.4rem 0.6rem;
		text-align: left;
		border-bottom: 1px solid var(--c-border);
	}

	.data-table th {
		font-size: 0.7rem;
		font-weight: 600;
		text-transform: uppercase;
		color: var(--c-text-muted);
		background: var(--c-bg);
	}

	.data-table td:last-child,
	.data-table th:last-child {
		text-align: right;
	}

	.capitalize {
		text-transform: capitalize;
	}

	/* Journal cards */
	.journal-card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem;
		margin-bottom: 0.5rem;
		break-inside: avoid;
	}

	.journal-meta {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		font-size: 0.75rem;
		color: var(--c-text-muted);
		margin-bottom: 0.35rem;
	}

	.mood-badge {
		padding: 0.1rem 0.4rem;
		border-radius: 8px;
		background: var(--c-accent-bg);
		color: var(--c-accent);
		font-size: 0.7rem;
	}

	.journal-text {
		font-size: 0.9rem;
		line-height: 1.5;
		white-space: pre-wrap;
		word-break: break-word;
	}

	/* Empty state */
	.empty-state {
		text-align: center;
		padding: 3rem 1rem;
		color: var(--c-text-muted);
	}

	.empty-state svg {
		margin-bottom: 1rem;
		opacity: 0.4;
	}

	.empty-hint {
		font-size: 0.8rem;
		margin-top: 0.25rem;
	}

	/* Print-specific */
	@media print {
		.no-print { display: none !important; }
		.report { padding: 0; }
		.report-range { font-size: 1.2rem; }
		.report-header { margin-bottom: 1rem; }
		.metric-card { border: 1px solid #ccc; }
		.mini-chart-block { border: 1px solid #ccc; }
		.journal-card { border: 1px solid #ccc; }
		.weekly-score { border: 2px solid #333; }
		.weekly-score-num { color: #333 !important; }

		/* Ensure delta arrows print with solid colors */
		.delta-up { color: #16a34a; }
		.delta-up::before { border-bottom-color: #16a34a; }
		.delta-down { color: #dc2626; }
		.delta-down::before { border-top-color: #dc2626; }
		.delta-same { color: #666; }
		.delta-same::before { background: #666; }

		/* Bar chart colors for print */
		.bar-label { fill: #666; }
		.bar-value { fill: #333; }

		/* Score colors for print */
		.weekly-score {
			-webkit-print-color-adjust: exact;
			print-color-adjust: exact;
		}
	}

	/* Adherence horizontal bars (medication + supplement compliance) */
	.adherence-list {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}

	.adherence-row {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.5rem 0.75rem;
	}

	.adherence-info {
		min-width: 90px;
		display: flex;
		flex-direction: column;
		gap: 0.1rem;
	}

	.adherence-name {
		font-size: 0.8rem;
		font-weight: 600;
	}

	.adherence-dose {
		font-size: 0.65rem;
		color: var(--c-text-muted);
	}

	.adherence-bar-wrap {
		flex: 1;
		height: 8px;
		background: var(--c-border);
		border-radius: 4px;
		overflow: hidden;
	}

	.adherence-bar {
		height: 100%;
		border-radius: 4px;
		transition: width 0.3s ease;
	}

	.adherence-pct {
		font-size: 0.75rem;
		font-weight: 700;
		min-width: 32px;
		text-align: right;
	}

	/* Training breakdown */
	.training-breakdown {
		margin-top: 0.5rem;
		display: flex;
		flex-direction: column;
		gap: 0.35rem;
	}

	.training-type-row {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		font-size: 0.8rem;
		padding: 0.25rem 0;
	}

	.training-type-dot {
		width: 10px;
		height: 10px;
		border-radius: 50%;
		flex-shrink: 0;
	}

	.training-type-label {
		flex: 1;
		font-weight: 500;
	}

	.training-type-count {
		font-weight: 700;
		color: var(--c-accent);
	}

	.training-type-min {
		font-size: 0.7rem;
		color: var(--c-text-muted);
		min-width: 48px;
		text-align: right;
	}

	/* Signal summary grid */
	.signal-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
		gap: 0.5rem;
	}

	.signal-card {
		display: flex;
		align-items: flex-start;
		gap: 0.5rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.6rem 0.75rem;
	}

	.signal-icon {
		color: var(--c-accent);
		flex-shrink: 0;
		margin-top: 0.1rem;
	}

	.signal-data {
		display: flex;
		flex-direction: column;
		gap: 0.1rem;
	}

	.signal-title {
		font-size: 0.7rem;
		font-weight: 600;
		text-transform: uppercase;
		color: var(--c-text-muted);
	}

	.signal-value {
		font-size: 0.85rem;
		font-weight: 700;
	}

	.signal-sub {
		font-size: 0.65rem;
		color: var(--c-text-muted);
	}

	/* Overall weekly grade */
	.grade-section {
		margin-top: 0.5rem;
	}

	.grade-container {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 0.75rem;
		padding: 1.25rem 1rem;
	}

	.grade-badge {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		width: 96px;
		height: 96px;
		border-radius: 50%;
		border: 4px solid var(--grade-color);
		background: var(--c-bg-card);
	}

	.grade-letter {
		font-size: 2.5rem;
		font-weight: 900;
		line-height: 1;
		color: var(--grade-color);
	}

	.grade-score {
		font-size: 0.7rem;
		font-weight: 600;
		color: var(--c-text-muted);
		line-height: 1;
	}

	.grade-comparison {
		text-align: center;
	}

	.grade-same {
		font-size: 0.8rem;
		color: var(--c-text-muted);
		font-weight: 500;
	}

	.grade-improved {
		display: inline-flex;
		align-items: center;
		gap: 0.25rem;
		font-size: 0.8rem;
		font-weight: 600;
		color: var(--c-done);
	}

	.grade-declined {
		display: inline-flex;
		align-items: center;
		gap: 0.25rem;
		font-size: 0.8rem;
		font-weight: 600;
		color: var(--c-cancel, #e53e3e);
	}

	@media (max-width: 359px) {
		.metrics-row { flex-direction: column; }
		.metric-card { min-width: auto; }
		.mini-charts { flex-direction: column; }
		.mini-chart-block { min-width: auto; }
		.signal-grid { grid-template-columns: 1fr; }
	}

	@media print {
		.adherence-bar-wrap { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
		.adherence-bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
		.training-type-dot { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
		.grade-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
		.grade-letter { color: #333 !important; }
		.grade-badge { border-color: #333 !important; }
		.signal-card { border: 1px solid #ccc; }
		.adherence-row { border: 1px solid #ccc; }
	}
</style>
