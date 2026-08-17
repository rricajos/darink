<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { useEntries } from '$lib/stores/entries.svelte';
	import type { Entry } from '$lib/db';

	const types = [
		{ href: '/training/strength', label: 'Strength', desc: 'Weights, sets, reps, RIR' },
		{ href: '/training/rings', label: 'Rings', desc: 'Progressions, holds, levels' },
		{ href: '/training/hiit', label: 'HIIT', desc: 'Intervals, work/rest, HR' },
		{ href: '/training/cardio', label: 'Cardio', desc: 'Distance, time, zone' },
		{ href: '/training/mobility', label: 'Mobility', desc: 'Routine, duration, notes' }
	];

	const TRAINING_TYPES = ['training.strength', 'training.rings', 'training.hiit', 'training.cardio', 'training.mobility'] as const;
	const TYPE_COLORS: Record<string, string> = {
		'training.strength': '#4aa3ff',
		'training.rings': '#e8a735',
		'training.hiit': '#e53e3e',
		'training.cardio': '#2e8b57',
		'training.mobility': '#9b59b6'
	};
	const TYPE_LABELS: Record<string, string> = {
		'training.strength': 'Strength',
		'training.rings': 'Rings',
		'training.hiit': 'HIIT',
		'training.cardio': 'Cardio',
		'training.mobility': 'Mobility'
	};

	const allStore = useEntries();

	const trainingEntries = $derived(
		allStore.items.filter((e: Entry) => TRAINING_TYPES.includes(e.type as typeof TRAINING_TYPES[number]))
	);

	// --- helpers ---
	function dateKey(iso: string): string {
		return iso.slice(0, 10);
	}

	function daysAgo(n: number): Date {
		const d = new Date();
		d.setHours(0, 0, 0, 0);
		d.setDate(d.getDate() - n);
		return d;
	}

	function isoToDate(iso: string): Date {
		return new Date(iso);
	}

	// --- 1. Weekly volume summary (last 7 days) ---
	const weekStart = $derived(daysAgo(6));
	const weekEntries = $derived(
		trainingEntries.filter((e: Entry) => isoToDate(e.createdAt) >= weekStart)
	);
	const volumeByType = $derived.by(() => {
		const counts: Record<string, number> = {};
		for (const t of TRAINING_TYPES) counts[t] = 0;
		for (const e of weekEntries) counts[e.type] = (counts[e.type] || 0) + 1;
		return counts;
	});

	// --- 2. Training frequency chart (last 14 days) ---
	const freq14Start = $derived(daysAgo(13));
	const freq14Entries = $derived(
		trainingEntries.filter((e: Entry) => isoToDate(e.createdAt) >= freq14Start)
	);
	const freqData = $derived.by(() => {
		const days: { key: string; byType: Record<string, number> }[] = [];
		for (let i = 13; i >= 0; i--) {
			const d = daysAgo(i);
			const key = d.toISOString().slice(0, 10);
			const byType: Record<string, number> = {};
			for (const t of TRAINING_TYPES) byType[t] = 0;
			days.push({ key, byType });
		}
		for (const e of freq14Entries) {
			const key = dateKey(e.createdAt);
			const day = days.find((d) => d.key === key);
			if (day) day.byType[e.type] = (day.byType[e.type] || 0) + 1;
		}
		return days;
	});
	const freqMaxTotal = $derived(
		Math.max(1, ...freqData.map((d) => TRAINING_TYPES.reduce((s, t) => s + d.byType[t], 0)))
	);

	// --- 3. Strength progression (top 3 exercises, 1RM via Epley) ---
	const strengthEntries = $derived(
		trainingEntries.filter((e: Entry) => e.type === 'training.strength')
	);
	const top3Exercises = $derived.by(() => {
		const counts: Record<string, number> = {};
		for (const e of strengthEntries) {
			const ex = String(e.data.exercise || '').toLowerCase();
			if (ex) counts[ex] = (counts[ex] || 0) + 1;
		}
		return Object.entries(counts)
			.sort((a, b) => b[1] - a[1])
			.slice(0, 3)
			.map(([name]) => name);
	});
	const strengthProgressData = $derived.by(() => {
		const result: { exercise: string; points: { date: string; e1rm: number }[] }[] = [];
		for (const exName of top3Exercises) {
			const matching = strengthEntries
				.filter((e: Entry) => String(e.data.exercise || '').toLowerCase() === exName)
				.sort((a: Entry, b: Entry) => a.createdAt.localeCompare(b.createdAt));
			// most recent set per day
			const byDay: Record<string, Entry> = {};
			for (const e of matching) {
				byDay[dateKey(e.createdAt)] = e;
			}
			const points = Object.entries(byDay)
				.sort(([a], [b]) => a.localeCompare(b))
				.map(([date, e]) => {
					const w = Number(e.data.weight) || 0;
					const r = Number(e.data.reps) || 1;
					const e1rm = w * (1 + r / 30);
					return { date, e1rm: Math.round(e1rm * 10) / 10 };
				});
			if (points.length > 1) {
				result.push({ exercise: exName, points });
			}
		}
		return result;
	});

	// --- 4. Cardio progress (pace min/km, top activity, last 30) ---
	const cardioEntries = $derived(
		trainingEntries.filter((e: Entry) => e.type === 'training.cardio')
	);
	const topCardioActivity = $derived.by(() => {
		const counts: Record<string, number> = {};
		for (const e of cardioEntries) {
			const a = String(e.data.activity || '').toLowerCase();
			if (a) counts[a] = (counts[a] || 0) + 1;
		}
		const sorted = Object.entries(counts).sort((a, b) => b[1] - a[1]);
		return sorted.length > 0 ? sorted[0][0] : '';
	});
	const cardioProgressData = $derived.by(() => {
		if (!topCardioActivity) return [];
		return cardioEntries
			.filter((e: Entry) => String(e.data.activity || '').toLowerCase() === topCardioActivity)
			.sort((a: Entry, b: Entry) => a.createdAt.localeCompare(b.createdAt))
			.slice(-30)
			.map((e: Entry) => {
				const dist = Number(e.data.distanceKm) || 0;
				const dur = Number(e.data.durationMin) || 0;
				const pace = dist > 0 ? dur / dist : 0;
				return { date: dateKey(e.createdAt), pace: Math.round(pace * 100) / 100 };
			})
			.filter((p) => p.pace > 0);
	});

	// --- 5. Total training stats ---
	const totalSessions = $derived(trainingEntries.length);
	const thisWeekStart = $derived(daysAgo(6));
	const lastWeekStart = $derived(daysAgo(13));
	const thisWeekCount = $derived(
		trainingEntries.filter((e: Entry) => isoToDate(e.createdAt) >= thisWeekStart).length
	);
	const lastWeekCount = $derived(
		trainingEntries.filter((e: Entry) => {
			const d = isoToDate(e.createdAt);
			return d >= lastWeekStart && d < thisWeekStart;
		}).length
	);
	const weekDiff = $derived(thisWeekCount - lastWeekCount);

	// --- SVG chart helpers ---
	function polylinePoints(
		data: { x: number; y: number }[],
		width: number,
		height: number,
		padX: number,
		padY: number
	): string {
		if (data.length === 0) return '';
		const xs = data.map((d) => d.x);
		const ys = data.map((d) => d.y);
		const minX = Math.min(...xs);
		const maxX = Math.max(...xs);
		const minY = Math.min(...ys);
		const maxY = Math.max(...ys);
		const rangeX = maxX - minX || 1;
		const rangeY = maxY - minY || 1;
		return data
			.map((d) => {
				const px = padX + ((d.x - minX) / rangeX) * (width - 2 * padX);
				const py = padY + (1 - (d.y - minY) / rangeY) * (height - 2 * padY);
				return `${px},${py}`;
			})
			.join(' ');
	}
</script>

<PageHeader title="Training" />

<section class="grid">
	{#each types as t}
		<a href={t.href} class="card">
			<strong>{t.label}</strong>
			<span>{t.desc}</span>
		</a>
	{/each}
</section>

{#if trainingEntries.length > 0}
	<!-- 5. Total training stats -->
	<section class="analytics">
		<h2>Overview</h2>
		<div class="metrics-row">
			<div class="metric-card">
				<span class="metric-value">{totalSessions}</span>
				<span class="metric-label">Total sessions</span>
			</div>
			<div class="metric-card">
				<span class="metric-value">{thisWeekCount}</span>
				<span class="metric-label">This week</span>
			</div>
			<div class="metric-card">
				<span class="metric-value">
					{lastWeekCount}
					{#if weekDiff > 0}
						<span class="trend up">+{weekDiff}</span>
					{:else if weekDiff < 0}
						<span class="trend down">{weekDiff}</span>
					{/if}
				</span>
				<span class="metric-label">Last week</span>
			</div>
		</div>
	</section>

	<!-- 1. Weekly volume summary -->
	{#if weekEntries.length > 0}
		<section class="analytics">
			<h2>This week</h2>
			<div class="volume-row">
				{#each TRAINING_TYPES as tt}
					{@const count = volumeByType[tt]}
					{#if count > 0}
						<div class="volume-card" style="border-left: 3px solid {TYPE_COLORS[tt]}">
							<span class="metric-value">{count}</span>
							<span class="metric-label">{TYPE_LABELS[tt]}</span>
						</div>
					{/if}
				{/each}
			</div>
		</section>
	{/if}

	<!-- 2. Training frequency chart (14 days) -->
	{#if freq14Entries.length > 0}
		<section class="analytics">
			<h2>Frequency (14 days)</h2>
			<div class="chart-wrap">
				<svg viewBox="0 0 280 100" class="bar-chart">
					{#each freqData as day, i}
						{@const total = TRAINING_TYPES.reduce((s, t) => s + day.byType[t], 0)}
						{@const barW = 280 / 14 - 2}
						{@const x = i * (280 / 14) + 1}
						{#if total > 0}
							{#each TRAINING_TYPES as tt, ti}
								{@const segH = (day.byType[tt] / freqMaxTotal) * 85}
								{@const prevH = TRAINING_TYPES.slice(0, ti).reduce((s, pt) => s + (day.byType[pt] / freqMaxTotal) * 85, 0)}
								{#if day.byType[tt] > 0}
									<rect
										x={x}
										y={95 - prevH - segH}
										width={barW}
										height={segH}
										rx="2"
										fill={TYPE_COLORS[tt]}
										opacity="0.85"
									/>
								{/if}
							{/each}
						{/if}
					{/each}
				</svg>
				<div class="chart-range">
					<span>{freqData[0]?.key.slice(5)}</span>
					<span>{freqData[freqData.length - 1]?.key.slice(5)}</span>
				</div>
				<div class="legend">
					{#each TRAINING_TYPES as tt}
						<span class="legend-item">
							<span class="dot" style="background:{TYPE_COLORS[tt]}"></span>
							{TYPE_LABELS[tt]}
						</span>
					{/each}
				</div>
			</div>
		</section>
	{/if}

	<!-- 3. Strength progression (top 3 exercises) -->
	{#if strengthProgressData.length > 0}
		<section class="analytics">
			<h2>Strength progression (est. 1RM)</h2>
			{#each strengthProgressData as ex}
				{@const pts = ex.points.map((p, i) => ({ x: i, y: p.e1rm }))}
				{@const line = polylinePoints(pts, 280, 80, 10, 8)}
				{@const lastVal = ex.points[ex.points.length - 1].e1rm}
				<div class="mini-chart-block">
					<div class="mini-chart-header">
						<strong>{ex.exercise}</strong>
						<span class="metric-sub">{lastVal} kg</span>
					</div>
					<svg viewBox="0 0 280 80" class="line-chart">
						<polyline points={line} fill="none" stroke="var(--c-accent)" stroke-width="2" stroke-linejoin="round" />
					</svg>
				</div>
			{/each}
		</section>
	{/if}

	<!-- 4. Cardio progress (pace) -->
	{#if cardioProgressData.length > 1}
		{@const cPts = cardioProgressData.map((p, i) => ({ x: i, y: p.pace }))}
		{@const cLine = polylinePoints(cPts, 280, 80, 10, 8)}
		{@const lastPace = cardioProgressData[cardioProgressData.length - 1].pace}
		<section class="analytics">
			<h2>Cardio pace ({topCardioActivity})</h2>
			<div class="mini-chart-block">
				<div class="mini-chart-header">
					<strong>{lastPace} min/km</strong>
					<span class="metric-sub">last {cardioProgressData.length} sessions</span>
				</div>
				<svg viewBox="0 0 280 80" class="line-chart">
					<polyline points={cLine} fill="none" stroke="var(--c-done)" stroke-width="2" stroke-linejoin="round" />
				</svg>
			</div>
		</section>
	{/if}
{/if}

<style>
	.grid {
		display: grid;
		grid-template-columns: 1fr;
		gap: 0.5rem;
		padding: 0 1rem;
	}

	@media (min-width: 600px) {
		.grid { grid-template-columns: repeat(2, 1fr); }
	}

	@media (min-width: 900px) {
		.grid { grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
	}

	.card {
		display: flex;
		flex-direction: column;
		padding: 1rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		text-decoration: none;
		color: var(--c-text);
		transition: border-color 0.15s;
	}

	.card:hover {
		border-color: var(--c-accent);
	}

	.card span {
		font-size: 0.85rem;
		color: var(--c-text-muted);
	}

	/* Analytics sections */
	.analytics {
		padding: 1.5rem 1rem 0;
	}

	.analytics h2 {
		font-size: 0.75rem;
		font-weight: 600;
		text-transform: uppercase;
		color: var(--c-text-muted);
		margin-bottom: 0.5rem;
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
		font-size: 0.75rem;
		font-weight: 600;
		color: var(--c-text-muted);
		text-transform: uppercase;
	}

	.metric-sub {
		font-size: 0.7rem;
		color: var(--c-text-muted);
	}

	.trend {
		font-size: 0.75rem;
		font-weight: 600;
	}

	.trend.up { color: var(--c-done); }
	.trend.down { color: var(--c-cancel, #e53e3e); }

	/* Volume cards */
	.volume-row {
		display: flex;
		gap: 0.5rem;
		flex-wrap: wrap;
	}

	.volume-card {
		flex: 1;
		min-width: 70px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.6rem 0.75rem;
		text-align: center;
		display: flex;
		flex-direction: column;
		gap: 0.1rem;
	}

	/* Charts */
	.chart-wrap {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.5rem;
	}

	.bar-chart {
		width: 100%;
		height: auto;
		display: block;
	}

	.chart-range {
		display: flex;
		justify-content: space-between;
		font-size: 0.7rem;
		color: var(--c-text-muted);
		margin-top: 0.2rem;
		padding: 0 0.25rem;
	}

	.legend {
		display: flex;
		gap: 0.75rem;
		flex-wrap: wrap;
		font-size: 0.7rem;
		color: var(--c-text-muted);
		margin-top: 0.4rem;
	}

	.legend-item {
		display: flex;
		align-items: center;
		gap: 0.25rem;
	}

	.dot {
		display: inline-block;
		width: 8px;
		height: 8px;
		border-radius: 50%;
	}

	.mini-chart-block {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.5rem;
		margin-bottom: 0.5rem;
	}

	.mini-chart-header {
		display: flex;
		justify-content: space-between;
		align-items: baseline;
		margin-bottom: 0.25rem;
		font-size: 0.85rem;
	}

	.mini-chart-header strong {
		text-transform: capitalize;
	}

	.line-chart {
		width: 100%;
		height: auto;
		display: block;
	}

	@media (max-width: 359px) {
		.metrics-row, .volume-row { flex-direction: column; }
		.metric-card, .volume-card { min-width: auto; }
	}
</style>
