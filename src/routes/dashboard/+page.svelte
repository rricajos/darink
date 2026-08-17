<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { useEntries } from '$lib/stores/entries.svelte';

	const store = useEntries();

	let period = $state<'week' | 'month' | '3month'>('week');

	const periodDays = $derived(period === 'week' ? 7 : period === 'month' ? 30 : 90);

	const periodComparison = $derived.by(() => {
		const all = store.items;
		const now = Date.now();
		const msPerDay = 86400000;
		const currentStart = new Date(now - periodDays * msPerDay).toISOString();
		const prevStart = new Date(now - periodDays * 2 * msPerDay).toISOString();
		const prevEnd = currentStart;

		const current = all.filter((e) => e.createdAt >= currentStart);
		const previous = all.filter((e) => e.createdAt >= prevStart && e.createdAt < prevEnd);

		const avg = (arr: number[]) => arr.length ? +(arr.reduce((a, b) => a + b, 0) / arr.length).toFixed(1) : null;

		const currentCheckins = current.filter((e) => e.type === 'checkin');
		const previousCheckins = previous.filter((e) => e.type === 'checkin');

		const currentTrainings = current.filter((e) => e.type.startsWith('training.')).length;
		const previousTrainings = previous.filter((e) => e.type.startsWith('training.')).length;

		// Habit completion: count unique days with at least one habit entry
		const habitDays = (entries: typeof all) => {
			const days = new Set(
				entries
					.filter((e) => e.type === 'habit')
					.map((e) => (e.data.date as string) ?? e.createdAt.slice(0, 10))
			);
			return days.size;
		};

		return {
			entries: { current: current.length, previous: previous.length },
			mood: {
				current: avg(currentCheckins.map((e) => e.data.mood as number)),
				previous: avg(previousCheckins.map((e) => e.data.mood as number))
			},
			energy: {
				current: avg(currentCheckins.map((e) => e.data.energy as number)),
				previous: avg(previousCheckins.map((e) => e.data.energy as number))
			},
			training: { current: currentTrainings, previous: previousTrainings },
			habitDays: { current: habitDays(current), previous: habitDays(previous) }
		};
	});

	const periodLabels = $derived({
		current: period === 'week' ? 'This week' : period === 'month' ? 'This month' : 'This quarter',
		previous: period === 'week' ? 'Last week' : period === 'month' ? 'Last month' : 'Last quarter'
	});

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

	/* --- Today's Log Overview --- */
	const todayLog = $derived.by(() => {
		const today = new Date().toISOString().slice(0, 10);
		const todayEntries = store.items.filter((e) => e.createdAt.startsWith(today));
		const groups: { key: string; label: string; items: string[] }[] = [];

		const checkins = todayEntries.filter((e) => e.type === 'checkin');
		if (checkins.length > 0) {
			groups.push({
				key: 'checkins',
				label: 'Check-ins',
				items: checkins.map((e) => `Mood ${e.data.mood} · Energy ${e.data.energy}`)
			});
		}

		const intakes = todayEntries.filter((e) => e.type === 'intake');
		if (intakes.length > 0) {
			groups.push({
				key: 'intakes',
				label: 'Intakes',
				items: intakes.map((e) => e.data.what as string)
			});
		}

		const trainings = todayEntries.filter((e) => e.type.startsWith('training.'));
		if (trainings.length > 0) {
			groups.push({
				key: 'training',
				label: 'Training',
				items: trainings.map((e) => {
					const sub = e.type.replace('training.', '');
					const name = (e.data.exercise ?? e.data.activity ?? e.data.routine ?? e.data.progression ?? e.data.name ?? sub) as string;
					return `${sub}: ${name}`;
				})
			});
		}

		const habits = todayEntries.filter((e) => e.type === 'habit');
		if (habits.length > 0) {
			const habitLabels: Record<string, string> = {
				cold: 'Cold', sun: 'Sun', fasting: 'Fasting',
				meditation: 'Meditation', wimhof: 'Wim Hof', ejaculation: 'Ejac. control'
			};
			groups.push({
				key: 'habits',
				label: 'Habits',
				items: habits.map((e) => habitLabels[e.data.habit as string] ?? (e.data.habit as string))
			});
		}

		const supps = todayEntries.filter((e) => e.type === 'supplement');
		if (supps.length > 0) {
			groups.push({
				key: 'supplements',
				label: 'Supplements',
				items: supps.map((e) => `${e.data.name}${e.data.dose ? ' ' + e.data.dose : ''}`)
			});
		}

		const signals = todayEntries.filter((e) => e.type.startsWith('signal.'));
		if (signals.length > 0) {
			groups.push({
				key: 'signals',
				label: 'Signals',
				items: signals.map((e) => e.type.replace('signal.', ''))
			});
		}

		return groups;
	});

	/* --- Habit Streaks Summary --- */
	const habitStreaks = $derived.by(() => {
		const habitEntries = store.items.filter((e) => e.type === 'habit');
		if (habitEntries.length === 0) return [];

		const habitTypes: { id: string; label: string }[] = [
			{ id: 'cold', label: 'Cold' },
			{ id: 'sun', label: 'Sun' },
			{ id: 'fasting', label: 'Fasting' },
			{ id: 'meditation', label: 'Meditation' },
			{ id: 'wimhof', label: 'Wim Hof' },
			{ id: 'ejaculation', label: 'Ejac. control' }
		];

		const result: { label: string; streak: number }[] = [];
		const today = new Date();

		for (const h of habitTypes) {
			const dates = new Set(
				habitEntries
					.filter((e) => e.data.habit === h.id)
					.map((e) => (e.data.date as string) ?? e.createdAt.slice(0, 10))
			);
			if (dates.size === 0) continue;

			let current = 0;
			for (let i = 0; i < 365; i++) {
				const d = new Date(today);
				d.setDate(d.getDate() - i);
				const key = d.toISOString().slice(0, 10);
				if (dates.has(key)) {
					current++;
				} else {
					break;
				}
			}
			if (current > 0) result.push({ label: h.label, streak: current });
		}

		return result;
	});

	/* --- Sleep Quality Card --- */
	const sleepCard = $derived.by(() => {
		const weekAgo = new Date(Date.now() - 7 * 86400000).toISOString();
		const sleeps = store.items
			.filter((e) => e.type === 'signal.sleep' && e.createdAt >= weekAgo)
			.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt));
		if (sleeps.length === 0) return null;

		const hours = sleeps.map((e) => Number(e.data.hours));
		const quals = sleeps.map((e) => Number(e.data.quality));
		const avg = (arr: number[]) => +(arr.reduce((a, b) => a + b, 0) / arr.length).toFixed(1);

		return {
			avgHours: avg(hours),
			avgQuality: avg(quals),
			lastHours: hours[0],
			lastQuality: quals[0]
		};
	});

	/* --- Correlation Insight --- */
	const correlation = $derived.by(() => {
		const checkins = store.items.filter((e) => e.type === 'checkin');
		const sleeps = store.items.filter((e) => e.type === 'signal.sleep');
		if (checkins.length === 0 || sleeps.length === 0) return null;

		// Build maps by date
		const sleepByDate = new Map<string, number>();
		for (const s of sleeps) {
			const date = (s.data.date as string) ?? s.createdAt.slice(0, 10);
			sleepByDate.set(date, Number(s.data.hours));
		}

		const moodByDate = new Map<string, number>();
		for (const c of checkins) {
			const date = (c.data.date as string) ?? c.createdAt.slice(0, 10);
			moodByDate.set(date, Number(c.data.mood));
		}

		// Find matching dates
		const pairs: { x: number; y: number }[] = [];
		for (const [date, sleepH] of sleepByDate) {
			const m = moodByDate.get(date);
			if (m !== undefined) pairs.push({ x: sleepH, y: m });
		}

		if (pairs.length < 5) return { r: null, label: 'Not enough data', color: 'muted' };

		// Pearson correlation
		const n = pairs.length;
		let sumX = 0, sumY = 0, sumXY = 0, sumX2 = 0, sumY2 = 0;
		for (const p of pairs) {
			sumX += p.x; sumY += p.y;
			sumXY += p.x * p.y;
			sumX2 += p.x * p.x;
			sumY2 += p.y * p.y;
		}
		const denom = Math.sqrt((n * sumX2 - sumX * sumX) * (n * sumY2 - sumY * sumY));
		if (denom === 0) return { r: 0, label: 'No variance', color: 'muted' };

		const r = +((n * sumXY - sumX * sumY) / denom).toFixed(2);
		let label: string;
		let color: string;

		if (r > 0.3) { label = 'positive correlation'; color = 'green'; }
		else if (r < -0.3) { label = 'negative correlation'; color = 'red'; }
		else { label = 'weak correlation'; color = 'amber'; }

		return { r, label, color };
	});
</script>

<PageHeader title="Dashboard" />

<section class="period-selector">
	<button class="period-chip" class:active={period === 'week'} onclick={() => period = 'week'}>Week</button>
	<button class="period-chip" class:active={period === 'month'} onclick={() => period = 'month'}>Month</button>
	<button class="period-chip" class:active={period === '3month'} onclick={() => period = '3month'}>3 Months</button>
</section>

{#if stats.total === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
	<p>Welcome to Darink!</p>
	<p class="empty-hint">Start logging to see your dashboard come alive.</p>
</div>
{:else}

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

{#if periodComparison.entries.current > 0 || periodComparison.entries.previous > 0}
<section class="comparison-section">
	<h2>Period comparison</h2>
	<div class="comparison-grid">
		<div class="cmp-header">Metric</div>
		<div class="cmp-header">{periodLabels.current}</div>
		<div class="cmp-header">{periodLabels.previous}</div>
		<div class="cmp-header">Delta</div>

		{#if true}
			{@const eDelta = periodComparison.entries.current - periodComparison.entries.previous}
			<div class="cmp-metric">Entries</div>
			<div class="cmp-val">{periodComparison.entries.current}</div>
			<div class="cmp-val">{periodComparison.entries.previous}</div>
			<div class="cmp-delta {eDelta > 0 ? 'positive' : eDelta < 0 ? 'negative' : ''}">
				{#if eDelta !== 0}
					{eDelta > 0 ? '+' : ''}{eDelta}
					{#if eDelta > 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 2L10 7H2Z" fill="currentColor"/></svg>{:else}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 10L2 5H10Z" fill="currentColor"/></svg>{/if}
				{:else}
					--
				{/if}
			</div>
		{/if}

		{#if periodComparison.mood.current != null || periodComparison.mood.previous != null}
			{@const mC = periodComparison.mood.current ?? 0}
			{@const mP = periodComparison.mood.previous ?? 0}
			{@const mDelta = +(mC - mP).toFixed(1)}
			<div class="cmp-metric">Mood</div>
			<div class="cmp-val">{periodComparison.mood.current ?? '--'}</div>
			<div class="cmp-val">{periodComparison.mood.previous ?? '--'}</div>
			<div class="cmp-delta {mDelta > 0 ? 'positive' : mDelta < 0 ? 'negative' : ''}">
				{#if periodComparison.mood.current != null && periodComparison.mood.previous != null}
					{mDelta > 0 ? '+' : ''}{mDelta}
					{#if mDelta > 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 2L10 7H2Z" fill="currentColor"/></svg>{:else if mDelta < 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 10L2 5H10Z" fill="currentColor"/></svg>{/if}
				{:else}
					--
				{/if}
			</div>
		{/if}

		{#if periodComparison.energy.current != null || periodComparison.energy.previous != null}
			{@const eC = periodComparison.energy.current ?? 0}
			{@const eP = periodComparison.energy.previous ?? 0}
			{@const enDelta = +(eC - eP).toFixed(1)}
			<div class="cmp-metric">Energy</div>
			<div class="cmp-val">{periodComparison.energy.current ?? '--'}</div>
			<div class="cmp-val">{periodComparison.energy.previous ?? '--'}</div>
			<div class="cmp-delta {enDelta > 0 ? 'positive' : enDelta < 0 ? 'negative' : ''}">
				{#if periodComparison.energy.current != null && periodComparison.energy.previous != null}
					{enDelta > 0 ? '+' : ''}{enDelta}
					{#if enDelta > 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 2L10 7H2Z" fill="currentColor"/></svg>{:else if enDelta < 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 10L2 5H10Z" fill="currentColor"/></svg>{/if}
				{:else}
					--
				{/if}
			</div>
		{/if}

		{#if true}
			{@const tDelta = periodComparison.training.current - periodComparison.training.previous}
			<div class="cmp-metric">Training</div>
			<div class="cmp-val">{periodComparison.training.current}</div>
			<div class="cmp-val">{periodComparison.training.previous}</div>
			<div class="cmp-delta {tDelta > 0 ? 'positive' : tDelta < 0 ? 'negative' : ''}">
				{#if tDelta !== 0}
					{tDelta > 0 ? '+' : ''}{tDelta}
					{#if tDelta > 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 2L10 7H2Z" fill="currentColor"/></svg>{:else}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 10L2 5H10Z" fill="currentColor"/></svg>{/if}
				{:else}
					--
				{/if}
			</div>
		{/if}

		{#if true}
			{@const hDelta = periodComparison.habitDays.current - periodComparison.habitDays.previous}
			<div class="cmp-metric">Habit days</div>
			<div class="cmp-val">{periodComparison.habitDays.current}</div>
			<div class="cmp-val">{periodComparison.habitDays.previous}</div>
			<div class="cmp-delta {hDelta > 0 ? 'positive' : hDelta < 0 ? 'negative' : ''}">
				{#if hDelta !== 0}
					{hDelta > 0 ? '+' : ''}{hDelta}
					{#if hDelta > 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 2L10 7H2Z" fill="currentColor"/></svg>{:else}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 10L2 5H10Z" fill="currentColor"/></svg>{/if}
				{:else}
					--
				{/if}
			</div>
		{/if}
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

{#if todayLog.length > 0}
<section class="today-log">
	<h2>Today's log</h2>
	{#each todayLog as group}
	<div class="log-group">
		<h3>{group.label}</h3>
		<div class="log-chips">
			{#each group.items as item}
			<span class="log-chip">{item}</span>
			{/each}
		</div>
	</div>
	{/each}
</section>
{/if}

{#if habitStreaks.length > 0}
<section class="streaks-summary">
	<h2>Habit streaks</h2>
	<div class="streaks-row">
		{#each habitStreaks as hs}
		<span class="streak-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; display: inline-block; margin-right: 2px;"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg> {hs.label} {hs.streak}d</span>
		{/each}
	</div>
</section>
{/if}

{#if sleepCard}
<section class="sleep-card-section">
	<h2>Sleep (7 days)</h2>
	<div class="sleep-card">
		<div class="sleep-stat">
			<span class="sleep-val">{sleepCard.avgHours}h</span>
			<span class="sleep-lbl">Avg hours</span>
		</div>
		<div class="sleep-stat">
			<span class="sleep-val">{sleepCard.avgQuality}/10</span>
			<span class="sleep-lbl">Avg quality</span>
		</div>
		<div class="sleep-stat">
			<span class="sleep-val">{sleepCard.lastHours}h</span>
			<span class="sleep-lbl">Last night</span>
		</div>
		<div class="sleep-stat">
			<span class="sleep-val">{sleepCard.lastQuality}/10</span>
			<span class="sleep-lbl">Last quality</span>
		</div>
	</div>
</section>
{/if}

{#if correlation}
<section class="correlation-section">
	<h2>Correlation insight</h2>
	{#if correlation.r !== null}
	<div class="corr-card corr-{correlation.color}">
		<span class="corr-indicator"></span>
		<span>Sleep ↔ Mood: {correlation.r > 0 ? '+' : ''}{correlation.r} ({correlation.label})</span>
	</div>
	{:else}
	<div class="corr-card corr-muted">
		<span class="corr-indicator"></span>
		<span>{correlation.label}</span>
	</div>
	{/if}
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

{/if}

<style>
	h2 {
		font-size: 0.9rem;
		font-weight: 600;
		margin-bottom: 0.5rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}

	/* Period Selector */
	.period-selector {
		display: flex;
		gap: 0.5rem;
		padding: 0 1rem 1rem;
	}
	.period-chip {
		flex: 1;
		padding: 0.4rem 0.75rem;
		border: 1px solid var(--c-border);
		border-radius: 20px;
		background: var(--c-bg-card);
		color: var(--c-text-muted);
		font-size: 0.85rem;
		font-weight: 500;
		cursor: pointer;
		text-align: center;
		transition: all 0.15s;
	}
	.period-chip.active {
		background: var(--c-accent);
		color: #fff;
		border-color: var(--c-accent);
	}

	/* Comparison Grid */
	.comparison-section {
		padding: 0 1rem 1.5rem;
	}
	.comparison-grid {
		display: grid;
		grid-template-columns: 1.2fr 1fr 1fr 1fr;
		gap: 0;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		overflow: hidden;
	}
	.comparison-grid > div {
		padding: 0.5rem 0.6rem;
		font-size: 0.8rem;
		border-bottom: 1px solid var(--c-border);
	}
	.comparison-grid > div:nth-last-child(-n+4) {
		border-bottom: none;
	}
	.cmp-header {
		font-weight: 600;
		font-size: 0.7rem;
		text-transform: uppercase;
		color: var(--c-text-muted);
		letter-spacing: 0.03em;
		background: var(--c-bg);
	}
	.cmp-metric {
		font-weight: 500;
	}
	.cmp-val {
		text-align: center;
		font-variant-numeric: tabular-nums;
	}
	.cmp-delta {
		text-align: center;
		font-weight: 600;
		font-variant-numeric: tabular-nums;
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 0.2rem;
	}
	.cmp-delta.positive {
		color: #22c55e;
	}
	.cmp-delta.negative {
		color: #ef4444;
	}
	.arrow-icon {
		width: 10px;
		height: 10px;
		flex-shrink: 0;
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

	/* Today's Log */
	.today-log {
		padding: 0 1rem 1rem;
	}
	.log-group {
		margin-bottom: 0.75rem;
	}
	.log-group h3 {
		font-size: 0.8rem;
		color: var(--c-text-muted);
		margin-bottom: 0.25rem;
		font-weight: 500;
	}
	.log-chips {
		display: flex;
		flex-wrap: wrap;
		gap: 0.25rem;
	}
	.log-chip {
		display: inline-block;
		padding: 0.25rem 0.6rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: 16px;
		font-size: 0.8rem;
	}

	/* Habit Streaks */
	.streaks-summary {
		padding: 0 1rem 1.5rem;
	}
	.streaks-row {
		display: flex;
		flex-wrap: wrap;
		gap: 0.5rem;
		align-items: center;
	}
	.streak-item {
		font-size: 0.85rem;
		font-weight: 500;
		white-space: nowrap;
	}

	/* Sleep Card */
	.sleep-card-section {
		padding: 0 1rem 1.5rem;
	}
	.sleep-card {
		display: grid;
		grid-template-columns: repeat(4, 1fr);
		gap: 0.5rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem;
	}
	.sleep-stat {
		text-align: center;
	}
	.sleep-val {
		display: block;
		font-size: 1.15rem;
		font-weight: 700;
	}
	.sleep-lbl {
		font-size: 0.7rem;
		color: var(--c-text-muted);
	}

	/* Correlation */
	.correlation-section {
		padding: 0 1rem 1.5rem;
	}
	.corr-card {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem 1rem;
		font-size: 0.85rem;
	}
	.corr-indicator {
		display: inline-block;
		width: 10px;
		height: 10px;
		border-radius: 50%;
		flex-shrink: 0;
	}
	.corr-green .corr-indicator { background: #22c55e; }
	.corr-amber .corr-indicator { background: #f59e0b; }
	.corr-red .corr-indicator { background: #ef4444; }
	.corr-muted .corr-indicator { background: var(--c-text-muted); }

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
