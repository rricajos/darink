<script lang="ts">
	import { useEntries } from '$lib/stores/entries.svelte';
	import { ui } from '$lib/db';
	import PageHeader from '$lib/components/PageHeader.svelte';
	import type { Entry } from '$lib/db';
	import { onMount } from 'svelte';

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
		const monStr = mon.toLocaleDateString('en-US', opts);
		const sunStr = sun.toLocaleDateString('en-US', { ...opts, year: 'numeric' });
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
	const TYPE_LABELS: Record<string, string> = {
		'training.strength': 'Strength',
		'training.rings': 'Rings',
		'training.hiit': 'HIIT',
		'training.cardio': 'Cardio',
		'training.mobility': 'Mobility'
	};

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
	function typeLabel(t: string): string {
		const labels: Record<string, string> = {
			checkin: 'Check-in',
			intake: 'Intake',
			journal: 'Journal',
			habit: 'Habit',
			supplement: 'Supplement',
			weight: 'Weight',
			experiment: 'Experiment',
			hydration: 'Hydration',
			'training.strength': 'Strength',
			'training.rings': 'Rings',
			'training.hiit': 'HIIT',
			'training.cardio': 'Cardio',
			'training.mobility': 'Mobility',
			'signal.sleep': 'Sleep signal',
			'signal.skin': 'Skin signal',
			'signal.hair': 'Hair signal',
			'signal.genital': 'Genital signal'
		};
		return labels[t] ?? t;
	}

	function doPrint() {
		window.print();
	}
</script>

<svelte:head>
	<title>Weekly Report | Darink</title>
</svelte:head>

<PageHeader title="Weekly Report" back="/more" />

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
		Print
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
				<span class="weekly-score-label">Score</span>
				{#if scoreDelta}
					<span class="delta delta-{scoreDelta.direction}">{scoreDelta.value}</span>
				{/if}
			</div>
		{/if}
	</div>

	{#if weekEntries.length === 0}
		<div class="empty-state">
			<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
			<p>No entries for this week</p>
			<p class="empty-hint">Select a different week or log some data first.</p>
		</div>
	{:else}
		<!-- Entry count by type -->
		<section class="report-section">
			<h2>Activity Overview</h2>
			<div class="metrics-row">
				<div class="metric-card">
					<span class="metric-value">{weekEntries.length}</span>
					<span class="metric-label">Total entries</span>
				</div>
				{#each typeCounts.slice(0, 5) as [t, count]}
					<div class="metric-card">
						<span class="metric-value">{count}</span>
						<span class="metric-label">{typeLabel(t)}</span>
					</div>
				{/each}
			</div>
			{#if typeCounts.length > 5}
				<table class="data-table">
					<thead><tr><th>Type</th><th>Count</th></tr></thead>
					<tbody>
						{#each typeCounts as [t, count]}
							<tr><td>{typeLabel(t)}</td><td>{count}</td></tr>
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
				<h2>Check-in Averages</h2>
				<div class="metrics-row">
					{#if currentCheckins.mood !== null}
						<div class="metric-card">
							<span class="metric-value">{currentCheckins.mood}</span>
							<span class="metric-label">Mood</span>
							{#if moodDelta}
								<span class="delta delta-{moodDelta.direction}">{moodDelta.value}</span>
							{/if}
						</div>
					{/if}
					{#if currentCheckins.energy !== null}
						<div class="metric-card">
							<span class="metric-value">{currentCheckins.energy}</span>
							<span class="metric-label">Energy</span>
							{#if energyDelta}
								<span class="delta delta-{energyDelta.direction}">{energyDelta.value}</span>
							{/if}
						</div>
					{/if}
					{#if currentCheckins.stress !== null}
						<div class="metric-card">
							<span class="metric-value">{currentCheckins.stress}</span>
							<span class="metric-label">Stress</span>
							{#if stressDelta}
								<span class="delta delta-{stressDelta.direction}">{stressDelta.value}</span>
							{/if}
						</div>
					{/if}
					{#if currentCheckins.sleep !== null}
						<div class="metric-card">
							<span class="metric-value">{currentCheckins.sleep}</span>
							<span class="metric-label">Sleep (h)</span>
							{#if sleepDelta}
								<span class="delta delta-{sleepDelta.direction}">{sleepDelta.value}</span>
							{/if}
						</div>
					{/if}
				</div>
				<p class="note">Based on {currentCheckins.count} check-in{currentCheckins.count !== 1 ? 's' : ''}{prevCheckins.count > 0 ? ` (prev week: ${prevCheckins.count})` : ''}</p>
			</section>

			<!-- Daily mood/energy mini-chart -->
			<section class="report-section">
				<h2>Daily Mood / Energy</h2>
				<div class="mini-charts">
					<!-- Mood chart -->
					<div class="mini-chart-block">
						<span class="mini-chart-title">Mood</span>
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
						<span class="mini-chart-title">Energy</span>
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
				<h2>Training</h2>
				<div class="metrics-row">
					<div class="metric-card">
						<span class="metric-value">{currentTraining.total}</span>
						<span class="metric-label">Sessions</span>
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
				<h2>Habits</h2>
				<table class="data-table">
					<thead><tr><th>Habit</th><th>Days done</th><th>vs prev</th></tr></thead>
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
				<h2>Supplement Adherence</h2>
				<table class="data-table">
					<thead><tr><th>Supplement</th><th>Days taken</th><th>vs prev</th></tr></thead>
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
				<h2>Hydration</h2>
				<div class="metrics-row">
					<div class="metric-card">
						<span class="metric-value">{(hydrationSummary.totalMl / 1000).toFixed(1)}L</span>
						<span class="metric-label">Total</span>
					</div>
					<div class="metric-card">
						<span class="metric-value">{hydrationSummary.metTarget}/{hydrationSummary.daysCount}</span>
						<span class="metric-label">Days on target</span>
					</div>
					<div class="metric-card">
						<span class="metric-value">{(hydrationSummary.avgDaily / 1000).toFixed(1)}L</span>
						<span class="metric-label">Daily avg</span>
						{#if hydDelta}
							<span class="delta delta-{hydDelta.direction}">{hydDelta.value}ml</span>
						{/if}
					</div>
				</div>
				{#if prevHydrationSummary}
					<p class="note">Prev week: {(prevHydrationSummary.totalMl / 1000).toFixed(1)}L total, {(prevHydrationSummary.avgDaily / 1000).toFixed(1)}L daily avg</p>
				{/if}
			</section>
		{/if}

		<!-- Weight change -->
		{#if weightEntries.length > 0}
			<section class="report-section">
				<h2>Weight</h2>
				<div class="metrics-row">
					{#if lastWeight !== null}
						<div class="metric-card">
							<span class="metric-value">{lastWeight} kg</span>
							<span class="metric-label">Latest</span>
						</div>
					{/if}
					{#if weightDelta !== null}
						<div class="metric-card">
							<span class="metric-value" class:positive={Number(weightDelta) > 0} class:negative={Number(weightDelta) < 0}>
								{Number(weightDelta) > 0 ? '+' : ''}{weightDelta} kg
							</span>
							<span class="metric-label">Change</span>
						</div>
					{/if}
				</div>
			</section>
		{/if}

		<!-- Top intakes -->
		{#if topIntakes.length > 0}
			<section class="report-section">
				<h2>Top Intakes</h2>
				<table class="data-table">
					<thead><tr><th>#</th><th>Food / Drink</th><th>Count</th></tr></thead>
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
				<h2>Journal</h2>
				{#each journalEntries as entry}
					<div class="journal-card">
						<div class="journal-meta">
							<span>{new Date(entry.createdAt).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })}</span>
							{#if entry.data.mood}
								<span class="mood-badge">Mood: {entry.data.mood}/10</span>
							{/if}
						</div>
						<p class="journal-text">{entry.data.text}</p>
					</div>
				{/each}
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

	@media (max-width: 359px) {
		.metrics-row { flex-direction: column; }
		.metric-card { min-width: auto; }
		.mini-charts { flex-direction: column; }
		.mini-chart-block { min-width: auto; }
	}
</style>
