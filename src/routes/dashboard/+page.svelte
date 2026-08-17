<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { useEntries } from '$lib/stores/entries.svelte';

	const store = useEntries();

	const stats = $derived.by(() => {
		const all = store.items;
		const today = new Date().toISOString().slice(0, 10);
		return {
			total: all.length,
			today: all.filter((e) => e.createdAt.startsWith(today)).length,
			checkins: all.filter((e) => e.type === 'checkin').length,
			intakes: all.filter((e) => e.type === 'intake').length,
			trainings: all.filter((e) => e.type.startsWith('training.')).length,
			habits: all.filter((e) => e.type === 'habit').length,
			supplements: all.filter((e) => e.type === 'supplement').length,
			experiments: all.filter((e) => e.type === 'experiment').length
		};
	});

	const weeklyActivity = $derived.by(() => {
		const all = store.items;
		const days: { label: string; count: number }[] = [];
		for (let i = 6; i >= 0; i--) {
			const d = new Date();
			d.setDate(d.getDate() - i);
			const key = d.toISOString().slice(0, 10);
			const weekday = d.toLocaleDateString('en', { weekday: 'short' });
			days.push({ label: weekday, count: all.filter((e) => e.createdAt.startsWith(key)).length });
		}
		return days;
	});

	const moodTrend = $derived.by(() => {
		const checkins = store.items
			.filter((e) => e.type === 'checkin')
			.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt))
			.slice(-14);
		return checkins.map((e) => ({
			date: e.createdAt.slice(5, 10),
			mood: (e.data.mood as number) ?? 5,
			energy: (e.data.energy as number) ?? 5
		}));
	});

	const weekSummary = $derived.by(() => {
		const now = new Date();
		const weekAgo = new Date(now.getTime() - 7 * 86400000).toISOString();
		const week = store.items.filter((e) => e.createdAt >= weekAgo);
		const checkins = week.filter((e) => e.type === 'checkin');
		const sleeps = week.filter((e) => e.type === 'signal.sleep');

		const avg = (arr: number[]) => arr.length ? +(arr.reduce((a, b) => a + b, 0) / arr.length).toFixed(1) : null;

		return {
			entries: week.length,
			avgMood: avg(checkins.map((e) => e.data.mood as number)),
			avgEnergy: avg(checkins.map((e) => e.data.energy as number)),
			avgSleep: avg(sleeps.map((e) => e.data.hours as number))
		};
	});
</script>

<PageHeader title="Dashboard" />

{#if weekSummary.entries > 0}
<section class="summary">
	<h2>This week</h2>
	<div class="summary-row">
		<div class="chip">{weekSummary.entries} entries</div>
		{#if weekSummary.avgMood}<div class="chip">Mood {weekSummary.avgMood}</div>{/if}
		{#if weekSummary.avgEnergy}<div class="chip">Energy {weekSummary.avgEnergy}</div>{/if}
		{#if weekSummary.avgSleep}<div class="chip">Sleep {weekSummary.avgSleep}h</div>{/if}
	</div>
</section>
{/if}

<section class="chart-section">
	<h2>Activity (7 days)</h2>
	<div class="bar-chart">
		{#each weeklyActivity as day}
			{@const maxAct = Math.max(...weeklyActivity.map((d) => d.count), 1)}
			<div class="bar-col">
				<div class="bar" style="height: {(day.count / maxAct) * 100}%">
					{#if day.count > 0}<span class="bar-val">{day.count}</span>{/if}
				</div>
				<span class="bar-label">{day.label}</span>
			</div>
		{/each}
	</div>
</section>

{#if moodTrend.length > 1}
{@const chartW = 280 / Math.max(moodTrend.length - 1, 1)}
<section class="chart-section">
	<h2>Mood & Energy (last 14)</h2>
	<svg class="line-chart" viewBox="0 0 280 100" preserveAspectRatio="none">
		<polyline
			fill="none" stroke="var(--c-accent)" stroke-width="2" stroke-linejoin="round"
			points={moodTrend.map((p, i) => `${i * chartW},${100 - p.mood * 10}`).join(' ')}
		/>
		<polyline
			fill="none" stroke="var(--c-done)" stroke-width="2" stroke-linejoin="round" stroke-dasharray="4 2"
			points={moodTrend.map((p, i) => `${i * chartW},${100 - p.energy * 10}`).join(' ')}
		/>
	</svg>
	<div class="legend">
		<span class="dot mood"></span> Mood
		<span class="dot energy"></span> Energy
	</div>
</section>
{/if}

<section class="stats">
	<div class="stat highlight">
		<span class="value">{stats.today}</span>
		<span class="label">Today</span>
	</div>
	<div class="stat"><span class="value">{stats.checkins}</span><span class="label">Check-ins</span></div>
	<div class="stat"><span class="value">{stats.intakes}</span><span class="label">Intakes</span></div>
	<div class="stat"><span class="value">{stats.trainings}</span><span class="label">Training</span></div>
	<div class="stat"><span class="value">{stats.habits}</span><span class="label">Habits</span></div>
	<div class="stat"><span class="value">{stats.supplements}</span><span class="label">Supplements</span></div>
	<div class="stat"><span class="value">{stats.experiments}</span><span class="label">Experiments</span></div>
	<div class="stat total"><span class="value">{stats.total}</span><span class="label">Total entries</span></div>
</section>

<style>
	h2 {
		font-size: 0.9rem;
		font-weight: 600;
		margin-bottom: 0.5rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}

	.summary {
		padding: 0 1rem 1rem;
	}
	.summary-row {
		display: flex;
		gap: 0.5rem;
		flex-wrap: wrap;
	}
	.chip {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: 20px;
		padding: 0.35rem 0.75rem;
		font-size: 0.85rem;
		font-weight: 500;
	}

	.chart-section {
		padding: 0 1rem 1.5rem;
	}

	.bar-chart {
		display: flex;
		align-items: flex-end;
		gap: 0.25rem;
		height: 120px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem 0.5rem 0;
	}
	.bar-col {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		height: 100%;
		justify-content: flex-end;
	}
	.bar {
		width: 100%;
		max-width: 32px;
		background: var(--c-accent);
		border-radius: 3px 3px 0 0;
		min-height: 2px;
		display: flex;
		align-items: flex-start;
		justify-content: center;
		transition: height 0.3s;
	}
	.bar-val {
		font-size: 0.65rem;
		font-weight: 600;
		color: #fff;
		margin-top: 2px;
	}
	.bar-label {
		font-size: 0.7rem;
		color: var(--c-text-muted);
		margin-top: 0.25rem;
	}

	.line-chart {
		width: 100%;
		height: 100px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.5rem;
	}
	.legend {
		display: flex;
		gap: 1rem;
		font-size: 0.75rem;
		color: var(--c-text-muted);
		margin-top: 0.25rem;
		align-items: center;
	}
	.dot {
		display: inline-block;
		width: 8px;
		height: 8px;
		border-radius: 50%;
	}
	.dot.mood { background: var(--c-accent); }
	.dot.energy { background: var(--c-done); }

	.stats {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 0.5rem;
		padding: 0 1rem;
	}
	.stat {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 1rem;
		text-align: center;
	}
	.stat.highlight {
		grid-column: 1 / -1;
		background: var(--c-accent-bg);
		border-color: var(--c-accent);
	}
	.stat.total { grid-column: 1 / -1; }
	.value { display: block; font-size: 1.5rem; font-weight: 700; }
	.label { font-size: 0.8rem; color: var(--c-text-muted); }

	@media (min-width: 600px) {
		.stats { grid-template-columns: repeat(3, 1fr); }
		.stat.highlight, .stat.total { grid-column: 1 / -1; }
		.value { font-size: 2rem; }
	}
	@media (min-width: 900px) {
		.stats { grid-template-columns: repeat(4, 1fr); gap: 0.75rem; }
		.stat { padding: 1.25rem; }
		.value { font-size: 2.25rem; }
		.bar-chart { height: 160px; }
	}
	@media (max-width: 359px) {
		.stats { grid-template-columns: 1fr; }
		.stat.highlight, .stat.total { grid-column: auto; }
	}
</style>
