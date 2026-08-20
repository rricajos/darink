<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { useLocale } from '$lib/stores/locale.svelte';
	import type { Entry } from '$lib/db';

	const { t } = useLocale();

	const store = useEntries('training.rings');

	let date = $state(new Date().toISOString().slice(0, 10));
	let progression = $state('');
	let holdTime = $state(0);
	let reps = $state(1);
	let assistance = $state('none');
	let level = $state(1);
	let notes = $state('');

	function submit() {
		if (!progression.trim()) return;
		entries.add('training.rings', { date, progression: progression.trim(), holdTime, reps, assistance, level, notes });
		progression = ''; notes = '';
		date = new Date().toISOString().slice(0, 10);
		toast.show(t.training.progressionLogged);
	}

	function repeatLast() {
		const last = store.items.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt))[0];
		if (!last) return;
		progression = last.data.progression as string;
		holdTime = last.data.holdTime as number;
		reps = last.data.reps as number;
		assistance = (last.data.assistance as string) || 'none';
		level = last.data.level as number;
		notes = (last.data.notes as string) || '';
		toast.show(t.common.prefilled);
	}

	// --- Analytics ---

	const totalSessions = $derived(store.items.length);
	const highestLevel = $derived(store.items.length ? Math.max(...store.items.map(e => Number(e.data.level) || 0)) : 0);
	const bestHoldTime = $derived(store.items.length ? Math.max(...store.items.map(e => Number(e.data.holdTime) || 0)) : 0);

	// Top 3 progressions by frequency
	const progressionFreq = $derived.by(() => {
		const freq = new Map<string, number>();
		for (const e of store.items) {
			const p = (e.data.progression as string) || '';
			if (p) freq.set(p, (freq.get(p) || 0) + 1);
		}
		return [...freq.entries()].sort((a, b) => b[1] - a[1]).slice(0, 3).map(([name]) => name);
	});

	const levelChartColors = ['#4aa3ff', '#e8a735', '#9b59b6'];

	// Level data per top progression, sorted by date
	const levelSeriesData = $derived.by(() => {
		return progressionFreq.map(name => {
			const pts = store.items
				.filter(e => e.data.progression === name)
				.sort((a, b) => ((a.data.date as string) || '').localeCompare((b.data.date as string) || ''))
				.map(e => ({ date: e.data.date as string, level: Number(e.data.level) || 0 }));
			const currentLevel = pts.length ? pts[pts.length - 1].level : 0;
			return { name, pts, currentLevel };
		});
	});

	// Hold time trend (last 20 entries)
	const holdTimeTrend = $derived.by(() => {
		return store.items
			.toSorted((a, b) => ((a.data.date as string) || '').localeCompare((b.data.date as string) || ''))
			.slice(-20)
			.map(e => Number(e.data.holdTime) || 0);
	});

	const avgHoldTime = $derived(holdTimeTrend.length ? Math.round(holdTimeTrend.reduce((s, v) => s + v, 0) / holdTimeTrend.length * 10) / 10 : 0);

	// Assistance progression per movement
	const assistanceLabel = $derived.by((): Record<string, string> => ({ band: t.training.bandAssist, partial: t.training.partialAssist, negative: t.training.negativeAssist, none: t.training.noneAssist }));

	const assistanceProgress = $derived.by(() => {
		const map = new Map<string, { first: string; latest: string }>();
		const sorted = store.items.toSorted((a, b) => ((a.data.date as string) || '').localeCompare((b.data.date as string) || ''));
		for (const e of sorted) {
			const p = (e.data.progression as string) || '';
			const a = (e.data.assistance as string) || 'none';
			if (!p) continue;
			const existing = map.get(p);
			if (!existing) {
				map.set(p, { first: a, latest: a });
			} else {
				existing.latest = a;
			}
		}
		return [...map.entries()].map(([name, v]) => ({ name, ...v }));
	});

	// Movement mastery grid
	const masteryGrid = $derived.by(() => {
		const map = new Map<string, { currentLevel: number; bestHold: number; currentAssistance: string; sessions: number }>();
		const sorted = store.items.toSorted((a, b) => ((a.data.date as string) || '').localeCompare((b.data.date as string) || ''));
		for (const e of sorted) {
			const p = (e.data.progression as string) || '';
			if (!p) continue;
			const existing = map.get(p);
			const ht = Number(e.data.holdTime) || 0;
			const lv = Number(e.data.level) || 0;
			const assist = (e.data.assistance as string) || 'none';
			if (!existing) {
				map.set(p, { currentLevel: lv, bestHold: ht, currentAssistance: assist, sessions: 1 });
			} else {
				existing.currentLevel = lv;
				existing.bestHold = Math.max(existing.bestHold, ht);
				existing.currentAssistance = assist;
				existing.sessions++;
			}
		}
		return [...map.entries()].map(([name, v]) => ({ name, ...v }));
	});

	function polylinePoints(values: number[], maxVal: number, width = 280, height = 80, padY = 8): string {
		if (values.length === 0) return '';
		const safe = maxVal || 1;
		return values.map((v, i) => {
			const x = values.length === 1 ? width / 2 : (i / (values.length - 1)) * width;
			const y = padY + (height - 2 * padY) * (1 - v / safe);
			return `${x.toFixed(1)},${y.toFixed(1)}`;
		}).join(' ');
	}
</script>

<svelte:head>
  <title>{t.training.rings} | Darink</title>
</svelte:head>

<PageHeader title={t.training.rings} back="/training" />

<section class="form">
	<label>{t.common.date} <input type="date" bind:value={date} /></label>
	<label>{t.training.progression} <input type="text" bind:value={progression} placeholder="Front lever, Muscle-up..." /></label>
	<div class="row">
		<label>{t.training.holdSec} <input type="number" min="0" bind:value={holdTime} /></label>
		<label>{t.training.reps} <input type="number" min="1" max="50" bind:value={reps} /></label>
		<label>{t.training.level} <input type="number" min="1" max="10" bind:value={level} /></label>
	</div>
	<label>{t.training.assistance}
		<select bind:value={assistance}>
			<option value="none">{t.training.noneAssist}</option>
			<option value="band">{t.training.bandAssist}</option>
			<option value="partial">{t.training.partialAssist}</option>
			<option value="negative">{t.training.negativeAssist}</option>
		</select>
	</label>
	<label>{t.common.notes} <textarea bind:value={notes} rows="2"></textarea></label>
	<div class="form-actions">
		<button class="primary" onclick={submit}>{t.training.logProgression}</button>
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
			progression: (fd.get('progression') as string).trim(),
			holdTime: Number(fd.get('holdTime')),
			reps: Number(fd.get('reps')),
			level: Number(fd.get('level')),
			assistance: fd.get('assistance') as string,
			notes: (fd.get('notes') as string).trim()
		});
		toast.show(t.common.updated);
		done();
	}}>
		<label>{t.common.date} <input type="date" name="date" value={data.date || ''} /></label>
		<label>{t.training.progression} <input type="text" name="progression" value={data.progression} /></label>
		<div class="row">
			<label>{t.training.holdSec} <input type="number" name="holdTime" min="0" value={data.holdTime} /></label>
			<label>{t.training.reps} <input type="number" name="reps" min="1" max="50" value={data.reps} /></label>
			<label>{t.training.level} <input type="number" name="level" min="1" max="10" value={data.level} /></label>
		</div>
		<label>{t.training.assistance}
			<select name="assistance">
				<option value="none" selected={data.assistance === 'none'}>{t.training.noneAssist}</option>
				<option value="band" selected={data.assistance === 'band'}>{t.training.bandAssist}</option>
				<option value="partial" selected={data.assistance === 'partial'}>{t.training.partialAssist}</option>
				<option value="negative" selected={data.assistance === 'negative'}>{t.training.negativeAssist}</option>
			</select>
		</label>
		<label>{t.common.notes} <textarea name="notes" rows="2">{data.notes}</textarea></label>
		<div class="edit-actions">
			<button type="submit">{t.common.save}</button>
			<button type="button" onclick={done}>{t.common.cancel}</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div><strong>{item.data.progression}</strong> <span class="meta">L{item.data.level} · {item.data.holdTime}s × {item.data.reps}</span></div>
	{/snippet}
</EntryList>

<!-- Analytics -->
{#if store.items.length > 0}

<!-- 1. Quick Stats Row -->
<section class="analytics-section">
	<h3 class="analytics-title">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
		{t.training.quickStats}
	</h3>
	<div class="stats-row">
		<div class="stat-card">
			<span class="stat-value">{totalSessions}</span>
			<span class="stat-label">{t.training.sessions}</span>
		</div>
		<div class="stat-card">
			<span class="stat-value">L{highestLevel}</span>
			<span class="stat-label">{t.training.highestLevel}</span>
		</div>
		<div class="stat-card">
			<span class="stat-value">{bestHoldTime}s</span>
			<span class="stat-label">{t.training.bestHold}</span>
		</div>
	</div>
</section>

<!-- 2. Level Progression Chart -->
{#if progressionFreq.length > 0}
<section class="analytics-section">
	<h3 class="analytics-title">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
		{t.training.levelProgression}
	</h3>
	<div class="chart-container">
		<svg viewBox="0 0 280 80" class="chart-svg">
			{#each levelSeriesData as series, idx}
				{#if series.pts.length > 0}
					{@const maxLvl = Math.max(...levelSeriesData.flatMap(s => s.pts.map(p => p.level)), 1)}
					<polyline
						points={polylinePoints(series.pts.map(p => p.level), maxLvl)}
						fill="none"
						stroke={levelChartColors[idx]}
						stroke-width="2"
						stroke-linecap="round"
						stroke-linejoin="round"
					/>
				{/if}
			{/each}
		</svg>
	</div>
	<div class="chart-legend">
		{#each levelSeriesData as series, idx}
			<span class="legend-item">
				<span class="legend-dot" style="background:{levelChartColors[idx]}"></span>
				{series.name} <span class="meta">L{series.currentLevel}</span>
			</span>
		{/each}
	</div>
</section>
{/if}

<!-- 3. Hold Time Trends -->
{#if holdTimeTrend.length > 1}
{@const maxHold = Math.max(...holdTimeTrend, 1)}
{@const avgY = 8 + (80 - 16) * (1 - avgHoldTime / maxHold)}
<section class="analytics-section">
	<h3 class="analytics-title">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
		{t.training.holdTimeTrend}
		<span class="meta" style="margin-left:auto">avg {avgHoldTime}s</span>
	</h3>
	<div class="chart-container">
		<svg viewBox="0 0 280 80" class="chart-svg">
			<line x1="0" y1={avgY} x2="280" y2={avgY} stroke="var(--c-text-muted)" stroke-width="0.5" stroke-dasharray="4 3" />
			<polyline
				points={polylinePoints(holdTimeTrend, maxHold)}
				fill="none"
				stroke="var(--c-done)"
				stroke-width="2"
				stroke-linecap="round"
				stroke-linejoin="round"
			/>
		</svg>
	</div>
	<p class="chart-caption">{holdTimeTrend.length} {t.common.entries}</p>
</section>
{/if}

<!-- 4. Assistance Progression -->
{#if assistanceProgress.length > 0}
<section class="analytics-section">
	<h3 class="analytics-title">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
		{t.training.assistanceProgression}
	</h3>
	<div class="assist-table">
		{#each assistanceProgress as row}
			<div class="assist-row" class:mastered={row.latest === 'none'}>
				<span class="assist-name">{row.name}</span>
				<span class="assist-flow">
					<span class="assist-tag">{assistanceLabel[row.first] || row.first}</span>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
					<span class="assist-tag" class:assist-none={row.latest === 'none'}>{assistanceLabel[row.latest] || row.latest}</span>
				</span>
			</div>
		{/each}
	</div>
</section>
{/if}

<!-- 5. Movement Mastery Grid -->
{#if masteryGrid.length > 0}
<section class="analytics-section">
	<h3 class="analytics-title">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
		{t.training.movementMastery}
	</h3>
	<div class="mastery-grid">
		{#each masteryGrid as m}
			<div class="mastery-card" class:mastery-done={m.currentAssistance === 'none'}>
				<strong class="mastery-name">{m.name}</strong>
				<div class="mastery-stats">
					<span>L{m.currentLevel}</span>
					<span>{m.bestHold}s {t.training.best}</span>
					<span>{m.sessions} {t.training.sess}</span>
				</div>
				<span class="mastery-assist" class:assist-none={m.currentAssistance === 'none'}>
					{assistanceLabel[m.currentAssistance] || m.currentAssistance}
				</span>
			</div>
		{/each}
	</div>
</section>
{/if}

{/if}

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }
	.form-actions { display: flex; gap: 0.5rem; }
	.form-actions button { flex: 1; }

	/* Analytics */
	.analytics-section { padding: 0 1rem; margin-top: 1.5rem; }
	.analytics-title {
		display: flex; align-items: center; gap: 0.4rem;
		font-size: 0.95rem; font-weight: 600; margin: 0 0 0.75rem;
		color: var(--c-text-muted);
	}

	/* Quick Stats */
	.stats-row { display: flex; gap: 0.5rem; }
	.stat-card {
		flex: 1; display: flex; flex-direction: column; align-items: center;
		padding: 0.6rem 0.4rem; border-radius: var(--radius);
		background: var(--c-bg-card); border: 1px solid var(--c-border);
	}
	.stat-value { font-size: 1.3rem; font-weight: 700; color: var(--c-accent); }
	.stat-label { font-size: 0.75rem; color: var(--c-text-muted); margin-top: 0.15rem; }

	/* Charts */
	.chart-container {
		background: var(--c-bg-card); border: 1px solid var(--c-border);
		border-radius: var(--radius); padding: 0.75rem;
	}
	.chart-svg { width: 100%; height: auto; display: block; }
	.chart-legend { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.5rem; }
	.legend-item { display: flex; align-items: center; gap: 0.3rem; font-size: 0.82rem; }
	.legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
	.chart-caption { font-size: 0.75rem; color: var(--c-text-muted); margin: 0.4rem 0 0; }

	/* Assistance Progression */
	.assist-table { display: flex; flex-direction: column; gap: 0.35rem; }
	.assist-row {
		display: flex; align-items: center; justify-content: space-between;
		padding: 0.5rem 0.65rem; border-radius: var(--radius);
		background: var(--c-bg-card); border: 1px solid var(--c-border);
		font-size: 0.85rem;
	}
	.assist-row.mastered { border-color: var(--c-done); }
	.assist-name { font-weight: 600; }
	.assist-flow { display: flex; align-items: center; gap: 0.3rem; color: var(--c-text-muted); }
	.assist-tag {
		font-size: 0.75rem; padding: 0.1rem 0.4rem; border-radius: 4px;
		background: var(--c-accent-bg); color: var(--c-text-muted);
	}
	.assist-tag.assist-none { background: var(--c-done); color: #fff; font-weight: 600; }

	/* Mastery Grid */
	.mastery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.5rem; }
	.mastery-card {
		display: flex; flex-direction: column; gap: 0.3rem;
		padding: 0.6rem; border-radius: var(--radius);
		background: var(--c-bg-card); border: 2px solid var(--c-border);
	}
	.mastery-card.mastery-done { border-color: var(--c-done); }
	.mastery-name { font-size: 0.85rem; }
	.mastery-stats { display: flex; gap: 0.5rem; font-size: 0.75rem; color: var(--c-text-muted); }
	.mastery-assist {
		font-size: 0.7rem; padding: 0.1rem 0.35rem; border-radius: 4px;
		background: var(--c-accent-bg); color: var(--c-text-muted);
		align-self: flex-start;
	}
	.mastery-assist.assist-none { background: var(--c-done); color: #fff; font-weight: 600; }
</style>
