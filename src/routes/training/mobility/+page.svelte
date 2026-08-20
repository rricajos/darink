<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { useLocale } from '$lib/stores/locale.svelte';
	import type { Entry } from '$lib/db';

	const { t } = useLocale();

	const store = useEntries('training.mobility');

	let date = $state(new Date().toISOString().slice(0, 10));
	let routine = $state('');
	let durationMin = $state(15);
	let notes = $state('');

	function submit() {
		if (!routine.trim()) return;
		entries.add('training.mobility', { date, routine: routine.trim(), durationMin, notes });
		routine = ''; notes = '';
		date = new Date().toISOString().slice(0, 10);
		toast.show(t.training.mobilityLogged);
	}

	function repeatLast() {
		const last = store.items.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt))[0];
		if (!last) return;
		routine = last.data.routine as string;
		durationMin = last.data.durationMin as number;
		notes = (last.data.notes as string) || '';
		toast.show(t.common.prefilled);
	}

	// --- Analytics derived state ---

	const sorted = $derived(
		store.items.toSorted((a, b) => (a.data.date as string).localeCompare(b.data.date as string))
	);

	const totalSessions = $derived(store.items.length);

	const totalTime = $derived(
		store.items.reduce((sum, e) => sum + (e.data.durationMin as number), 0)
	);

	const avgDuration = $derived(
		totalSessions > 0 ? Math.round(totalTime / totalSessions) : 0
	);

	const currentStreak = $derived.by(() => {
		if (sorted.length === 0) return 0;
		const sessionDates = new Set(sorted.map(e => e.data.date as string));
		const today = new Date();
		let streak = 0;
		let d = new Date(today);
		// Check today first, if no session today, start from yesterday
		const todayStr = d.toISOString().slice(0, 10);
		if (!sessionDates.has(todayStr)) {
			d.setDate(d.getDate() - 1);
		}
		while (true) {
			const key = d.toISOString().slice(0, 10);
			if (sessionDates.has(key)) {
				streak++;
				d.setDate(d.getDate() - 1);
			} else {
				break;
			}
		}
		return streak;
	});

	// Routine frequency: top 5
	const routineFreq = $derived.by(() => {
		const counts: Record<string, number> = {};
		for (const e of store.items) {
			const r = e.data.routine as string;
			counts[r] = (counts[r] || 0) + 1;
		}
		return Object.entries(counts)
			.toSorted((a, b) => b[1] - a[1])
			.slice(0, 5);
	});

	const routineMaxCount = $derived(
		routineFreq.length > 0 ? routineFreq[0][1] : 1
	);

	// Duration trend: last 20 sessions by date
	const trendData = $derived.by(() => {
		const byDate = sorted.slice(-20);
		return byDate.map(e => ({
			date: e.data.date as string,
			mins: e.data.durationMin as number
		}));
	});

	const trendMax = $derived(
		trendData.length > 0 ? Math.max(...trendData.map(d => d.mins)) : 1
	);

	// Monthly consistency: last 30 days
	const monthGrid = $derived.by(() => {
		const sessionDates = new Set(store.items.map(e => e.data.date as string));
		const today = new Date();
		const todayStr = today.toISOString().slice(0, 10);
		const days: { date: string; label: string; active: boolean; isToday: boolean }[] = [];
		for (let i = 29; i >= 0; i--) {
			const d = new Date(today);
			d.setDate(d.getDate() - i);
			const key = d.toISOString().slice(0, 10);
			days.push({
				date: key,
				label: String(d.getDate()),
				active: sessionDates.has(key),
				isToday: key === todayStr
			});
		}
		return days;
	});
</script>

<svelte:head>
  <title>{t.training.mobility} | Darink</title>
</svelte:head>

<PageHeader title={t.training.mobility} back="/training" breadcrumbs={[{ href: "/training", label: t.training.title }]} />

<section class="form">
	<label>{t.common.date} <input type="date" bind:value={date} /></label>
	<label>{t.training.routine} <input type="text" bind:value={routine} placeholder="Hip opener, Shoulder..." /></label>
	<label>{t.training.durationMin} <input type="number" min="1" bind:value={durationMin} /></label>
	<label>{t.common.notes} <textarea bind:value={notes} rows="2"></textarea></label>
	<div class="form-actions">
		<button class="primary" onclick={submit}>{t.training.logSession}</button>
		{#if store.items.length > 0}
			<button onclick={repeatLast}>{t.common.repeatLast}</button>
		{/if}
	</div>
</section>

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			date: fd.get('date') as string,
			routine: (fd.get('routine') as string).trim(),
			durationMin: Number(fd.get('durationMin')),
			notes: (fd.get('notes') as string).trim()
		});
		toast.show(t.common.updated);
		done();
	}}>
		<label>{t.common.date} <input type="date" name="date" value={data.date || ''} /></label>
		<label>{t.training.routine} <input type="text" name="routine" value={data.routine} /></label>
		<label>{t.training.durationMin} <input type="number" name="durationMin" min="1" value={data.durationMin} /></label>
		<label>{t.common.notes} <textarea name="notes" rows="2">{data.notes}</textarea></label>
		<div class="edit-actions">
			<button type="submit">{t.common.save}</button>
			<button type="button" onclick={done}>{t.common.cancel}</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div><strong>{item.data.routine}</strong> <span class="meta">{item.data.durationMin}min</span></div>
	{/snippet}
</EntryList>

{#if store.items.length > 0}
	<!-- Quick Stats -->
	<section class="analytics">
		<h3>{t.training.quickStats}</h3>
		<div class="stat-grid">
			<div class="card">
				<div class="stat-value">{totalSessions}</div>
				<div class="stat-label">{t.training.sessions}</div>
			</div>
			<div class="card">
				<div class="stat-value">{totalTime}<span class="stat-unit">min</span></div>
				<div class="stat-label">{t.training.totalTime}</div>
			</div>
			<div class="card">
				<div class="stat-value">{avgDuration}<span class="stat-unit">min</span></div>
				<div class="stat-label">{t.training.avgDuration}</div>
			</div>
			<div class="card">
				<div class="stat-value">{currentStreak}<span class="stat-unit">d</span></div>
				<div class="stat-label">{t.common.streak}</div>
			</div>
		</div>
	</section>

	<!-- Routine Frequency Chart -->
	{#if routineFreq.length > 0}
		<section class="analytics">
			<h3>{t.training.routineFrequency}</h3>
			<div class="chart-wrap">
				<svg viewBox="0 0 300 {routineFreq.length * 36 + 8}" preserveAspectRatio="xMidYMid meet" width="100%">
					{#each routineFreq as [name, count], i}
						{@const barWidth = (count / routineMaxCount) * 180}
						<text
							x="108"
							y={i * 36 + 22}
							text-anchor="end"
							fill="var(--c-text)"
							font-size="12"
						>{name.length > 14 ? name.slice(0, 13) + '…' : name}</text>
						<rect
							x="114"
							y={i * 36 + 10}
							width={barWidth}
							height="20"
							rx="4"
							fill="var(--c-accent)"
							opacity="0.85"
						/>
						<text
							x={114 + barWidth + 6}
							y={i * 36 + 24}
							fill="var(--c-text-muted)"
							font-size="11"
						>{count}</text>
					{/each}
				</svg>
			</div>
		</section>
	{/if}

	<!-- Duration Trend -->
	{#if trendData.length >= 2}
		{@const chartW = 300}
		{@const chartH = 160}
		{@const padL = 36}
		{@const padR = 10}
		{@const padT = 14}
		{@const padB = 28}
		{@const plotW = chartW - padL - padR}
		{@const plotH = chartH - padT - padB}
		{@const yMax = Math.max(trendMax, 1)}
		{@const points = trendData.map((d, i) => ({
			x: padL + (trendData.length > 1 ? (i / (trendData.length - 1)) * plotW : plotW / 2),
			y: padT + plotH - (d.mins / yMax) * plotH,
			date: d.date,
			mins: d.mins
		}))}
		{@const polyline = points.map(p => `${p.x},${p.y}`).join(' ')}
		<section class="analytics">
			<h3>{t.training.durationTrend}</h3>
			<div class="chart-wrap">
				<svg viewBox="0 0 {chartW} {chartH}" preserveAspectRatio="xMidYMid meet" width="100%">
					<text x={padL - 4} y={padT + 4} text-anchor="end" fill="var(--c-text-muted)" font-size="9">{yMax}</text>
					<text x={padL - 4} y={padT + plotH + 4} text-anchor="end" fill="var(--c-text-muted)" font-size="9">0</text>
					<line x1={padL} y1={padT} x2={padL + plotW} y2={padT} stroke="var(--c-border)" stroke-width="0.5" />
					<line x1={padL} y1={padT + plotH} x2={padL + plotW} y2={padT + plotH} stroke="var(--c-border)" stroke-width="0.5" />
					<polyline
						points={polyline}
						fill="none"
						stroke="var(--c-accent)"
						stroke-width="2"
						stroke-linejoin="round"
						stroke-linecap="round"
					/>
					{#each points as p}
						<circle cx={p.x} cy={p.y} r="3" fill="var(--c-accent)" />
					{/each}
					<!-- X axis date labels (first, mid, last) -->
					{#each [0, Math.floor(points.length / 2), points.length - 1] as idx}
						{#if points[idx]}
							<text
								x={points[idx].x}
								y={chartH - 4}
								text-anchor="middle"
								fill="var(--c-text-muted)"
								font-size="9"
							>{points[idx].date.slice(5).replace('-', '/')}</text>
						{/if}
					{/each}
				</svg>
			</div>
		</section>
	{/if}

	<!-- Monthly Consistency -->
	<section class="analytics">
		<h3>{t.training.monthlyConsistency}</h3>
		<div class="consistency-grid">
			{#each monthGrid as day}
				<div
					class="day-cell"
					class:active={day.active}
					class:today={day.isToday}
					title={day.date}
				>
					<span class="day-num">{day.label}</span>
				</div>
			{/each}
		</div>
	</section>
{/if}

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }
	.form-actions { display: flex; gap: 0.5rem; }
	.form-actions button { flex: 1; }

	/* Analytics */
	.analytics { margin-top: 1.5rem; }
	.analytics h3 {
		padding: 0 1rem;
		margin: 0 0 0.75rem;
		font-size: 1rem;
		color: var(--c-text);
	}

	/* Quick Stats grid */
	.stat-grid {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 0.75rem;
		padding: 0 1rem;
	}
	.card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 1rem;
		text-align: center;
	}
	.stat-value {
		font-size: 1.6rem;
		font-weight: 700;
		color: var(--c-text);
		line-height: 1.2;
	}
	.stat-unit {
		font-size: 0.75rem;
		font-weight: 400;
		color: var(--c-text-muted);
		margin-left: 2px;
	}
	.stat-label {
		font-size: 0.8rem;
		color: var(--c-text-muted);
		margin-top: 0.25rem;
	}

	/* Chart wrapper */
	.chart-wrap {
		padding: 0 1rem;
	}
	.chart-wrap svg {
		display: block;
		width: 100%;
		height: auto;
	}

	/* Monthly Consistency grid */
	.consistency-grid {
		display: grid;
		grid-template-columns: repeat(6, 1fr);
		gap: 4px;
		padding: 0 1rem;
	}
	.day-cell {
		aspect-ratio: 1;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: 6px;
		background: var(--c-border);
		position: relative;
	}
	.day-cell.active {
		background: var(--c-accent);
	}
	.day-cell.today {
		outline: 2px solid var(--c-text);
		outline-offset: -2px;
	}
	.day-num {
		font-size: 0.7rem;
		color: var(--c-text-muted);
		font-weight: 500;
	}
	.day-cell.active .day-num {
		color: #fff;
	}
</style>
