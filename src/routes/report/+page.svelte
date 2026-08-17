<script lang="ts">
	import { useEntries } from '$lib/stores/entries.svelte';
	import { ui } from '$lib/db';
	import PageHeader from '$lib/components/PageHeader.svelte';
	import type { Entry } from '$lib/db';
	import { onMount } from 'svelte';

	const store = useEntries();

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

	let weekStart = $state(toMonday(new Date()));
	let weekEnd = $derived(toSunday(weekStart));
	let weekLabel = $derived(fmtRange(weekStart, weekEnd));

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

	// Filter entries to selected week
	const weekEntries = $derived.by(() => {
		const start = weekStart.toISOString();
		const end = weekEnd.toISOString();
		return store.items.filter((e) => {
			const d = e.data.date ? new Date(e.data.date as string).toISOString() : e.createdAt;
			return d >= start && d <= end;
		});
	});

	// Entry counts by type
	const typeCounts = $derived.by(() => {
		const counts: Record<string, number> = {};
		for (const e of weekEntries) {
			counts[e.type] = (counts[e.type] || 0) + 1;
		}
		return Object.entries(counts).sort((a, b) => b[1] - a[1]);
	});

	// Checkin averages
	const checkins = $derived(weekEntries.filter((e) => e.type === 'checkin'));
	const avgMood = $derived(checkins.length > 0
		? (checkins.reduce((s, e) => s + (Number(e.data.mood) || 0), 0) / checkins.length).toFixed(1)
		: null);
	const avgEnergy = $derived(checkins.length > 0
		? (checkins.reduce((s, e) => s + (Number(e.data.energy) || 0), 0) / checkins.length).toFixed(1)
		: null);
	const avgStress = $derived(checkins.length > 0
		? (checkins.reduce((s, e) => s + (Number(e.data.stress) || 0), 0) / checkins.length).toFixed(1)
		: null);
	const avgSleep = $derived(checkins.length > 0
		? (checkins.reduce((s, e) => s + (Number(e.data.sleep) || 0), 0) / checkins.length).toFixed(1)
		: null);

	// Training sessions
	const TRAINING_TYPES = ['training.strength', 'training.rings', 'training.hiit', 'training.cardio', 'training.mobility'] as const;
	const TYPE_LABELS: Record<string, string> = {
		'training.strength': 'Strength',
		'training.rings': 'Rings',
		'training.hiit': 'HIIT',
		'training.cardio': 'Cardio',
		'training.mobility': 'Mobility'
	};
	const trainingEntries = $derived(
		weekEntries.filter((e) => TRAINING_TYPES.includes(e.type as typeof TRAINING_TYPES[number]))
	);
	const trainingByType = $derived.by(() => {
		const counts: Record<string, number> = {};
		for (const e of trainingEntries) {
			const label = TYPE_LABELS[e.type] ?? e.type;
			counts[label] = (counts[label] || 0) + 1;
		}
		return Object.entries(counts).sort((a, b) => b[1] - a[1]);
	});

	// Habit completion
	const habitEntries = $derived(weekEntries.filter((e) => e.type === 'habit'));
	const habitSummary = $derived.by(() => {
		const map: Record<string, Set<string>> = {};
		for (const e of habitEntries) {
			const h = String(e.data.habit || '');
			const d = String(e.data.date || e.createdAt.slice(0, 10));
			if (!h) continue;
			if (!map[h]) map[h] = new Set();
			map[h].add(d);
		}
		return Object.entries(map)
			.map(([habit, days]) => ({ habit, days: days.size }))
			.sort((a, b) => b.days - a.days);
	});

	// Supplement adherence
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
		// Count how many days each planned supplement was taken
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

	// Weight change
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

	// Top intakes
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

	// Journal entries
	const journalEntries = $derived(
		weekEntries.filter((e) => e.type === 'journal').sort((a, b) => a.createdAt.localeCompare(b.createdAt))
	);

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
	<h2 class="report-range">{weekLabel}</h2>

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

		<!-- Check-in averages -->
		{#if checkins.length > 0}
			<section class="report-section">
				<h2>Check-in Averages</h2>
				<div class="metrics-row">
					{#if avgMood !== null}
						<div class="metric-card">
							<span class="metric-value">{avgMood}</span>
							<span class="metric-label">Mood</span>
						</div>
					{/if}
					{#if avgEnergy !== null}
						<div class="metric-card">
							<span class="metric-value">{avgEnergy}</span>
							<span class="metric-label">Energy</span>
						</div>
					{/if}
					{#if avgStress !== null}
						<div class="metric-card">
							<span class="metric-value">{avgStress}</span>
							<span class="metric-label">Stress</span>
						</div>
					{/if}
					{#if avgSleep !== null}
						<div class="metric-card">
							<span class="metric-value">{avgSleep}</span>
							<span class="metric-label">Sleep (h)</span>
						</div>
					{/if}
				</div>
				<p class="note">Based on {checkins.length} check-in{checkins.length !== 1 ? 's' : ''}</p>
			</section>
		{/if}

		<!-- Training sessions -->
		{#if trainingEntries.length > 0}
			<section class="report-section">
				<h2>Training</h2>
				<div class="metrics-row">
					<div class="metric-card">
						<span class="metric-value">{trainingEntries.length}</span>
						<span class="metric-label">Sessions</span>
					</div>
					{#each trainingByType as [label, count]}
						<div class="metric-card">
							<span class="metric-value">{count}</span>
							<span class="metric-label">{label}</span>
						</div>
					{/each}
				</div>
			</section>
		{/if}

		<!-- Habit completion -->
		{#if habitSummary.length > 0}
			<section class="report-section">
				<h2>Habits</h2>
				<table class="data-table">
					<thead><tr><th>Habit</th><th>Days done</th></tr></thead>
					<tbody>
						{#each habitSummary as h}
							<tr>
								<td>{h.habit}</td>
								<td>{h.days}/7</td>
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
					<thead><tr><th>Supplement</th><th>Days taken</th></tr></thead>
					<tbody>
						{#each suppAdherence as s}
							<tr>
								<td>{s.name}</td>
								<td>{s.daysLogged}/7</td>
							</tr>
						{/each}
					</tbody>
				</table>
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

	.report-range {
		font-size: 1.1rem;
		font-weight: 700;
		color: var(--c-text);
		margin-bottom: 1.5rem;
		text-transform: none;
		letter-spacing: 0;
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

	/* Print-specific */
	@media print {
		.no-print { display: none !important; }
		.report { padding: 0; }
		.report-range { font-size: 1.2rem; margin-bottom: 1rem; }
		.metric-card { border: 1px solid #ccc; }
	}

	@media (max-width: 359px) {
		.metrics-row { flex-direction: column; }
		.metric-card { min-width: auto; }
	}
</style>
