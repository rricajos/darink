<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { useLocale } from '$lib/stores/locale.svelte';
	import type { Entry } from '$lib/db';

	const { t } = useLocale();

	const store = useEntries('training.strength');

	let date = $state(new Date().toISOString().slice(0, 10));
	let exercise = $state('');
	let sets = $state(3);
	let reps = $state(10);
	let weight = $state(0);
	let rir = $state(2);
	let notes = $state('');

	function submit() {
		if (!exercise.trim()) return;
		entries.add('training.strength', { date, exercise: exercise.trim(), sets, reps, weight, rir, notes });
		exercise = ''; notes = '';
		date = new Date().toISOString().slice(0, 10);
		toast.show(t.training.setLogged);
	}

	function repeatLast() {
		const last = store.items.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt))[0];
		if (!last) return;
		exercise = last.data.exercise as string;
		sets = last.data.sets as number;
		reps = last.data.reps as number;
		weight = last.data.weight as number;
		rir = last.data.rir as number;
		notes = (last.data.notes as string) || '';
		toast.show(t.common.prefilled);
	}

	const quickExercises = $derived.by(() => {
		const counts = new Map<string, { count: number; last: Record<string, unknown>; lastCreated: string }>();
		for (const e of store.items) {
			const key = (e.data.exercise as string)?.toLowerCase().trim();
			if (!key) continue;
			const existing = counts.get(key);
			if (!existing) {
				counts.set(key, { count: 1, last: e.data, lastCreated: e.createdAt });
			} else {
				existing.count++;
				if (e.createdAt > existing.lastCreated) {
					existing.last = e.data;
					existing.lastCreated = e.createdAt;
				}
			}
		}
		return [...counts.entries()]
			.sort((a, b) => b[1].count - a[1].count)
			.slice(0, 5)
			.map(([, info]) => ({ name: info.last.exercise as string, count: info.count, last: info.last }));
	});

	function prefillExercise(item: { name: string; last: Record<string, unknown> }) {
		exercise = item.last.exercise as string;
		sets = item.last.sets as number;
		reps = item.last.reps as number;
		weight = item.last.weight as number;
		rir = item.last.rir as number;
		toast.show(t.common.prefilled);
	}

	/* ── Analytics ── */

	const totalSets = $derived(store.items.reduce((s, e) => s + (Number(e.data.sets) || 0), 0));
	const totalVolume = $derived(store.items.reduce((s, e) => s + (Number(e.data.sets) || 0) * (Number(e.data.reps) || 0) * (Number(e.data.weight) || 0), 0));

	const sessionsThisWeek = $derived.by(() => {
		const now = new Date();
		const weekAgo = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 6);
		const weekStr = weekAgo.toISOString().slice(0, 10);
		const dates = new Set<string>();
		for (const e of store.items) {
			const d = e.data.date as string;
			if (d >= weekStr) dates.add(d);
		}
		return dates.size;
	});

	/* Top 3 exercises by frequency with weight+e1RM progression */
	const topExerciseCharts = $derived.by(() => {
		const freq = new Map<string, number>();
		for (const e of store.items) {
			const key = (e.data.exercise as string)?.toLowerCase().trim();
			if (!key) continue;
			freq.set(key, (freq.get(key) || 0) + 1);
		}
		const top3 = [...freq.entries()].sort((a, b) => b[1] - a[1]).slice(0, 3).map(([k]) => k);
		return top3.map(exKey => {
			const points = store.items
				.filter(e => (e.data.exercise as string)?.toLowerCase().trim() === exKey)
				.toSorted((a, b) => ((a.data.date as string) || '').localeCompare((b.data.date as string) || '') || a.createdAt.localeCompare(b.createdAt))
				.map(e => ({
					date: e.data.date as string,
					weight: Number(e.data.weight) || 0,
					reps: Number(e.data.reps) || 1,
					e1rm: (Number(e.data.weight) || 0) * (1 + (Number(e.data.reps) || 1) / 30)
				}));
			const displayName = store.items.find(e => (e.data.exercise as string)?.toLowerCase().trim() === exKey)?.data.exercise as string || exKey;
			const latestWeight = points.length > 0 ? points[points.length - 1].weight : 0;
			const latestE1rm = points.length > 0 ? points[points.length - 1].e1rm : 0;
			return { name: displayName, points, latestWeight, latestE1rm };
		});
	});

	/* Volume per session — last 14 sessions */
	const volumePerSession = $derived.by(() => {
		const map = new Map<string, number>();
		for (const e of store.items) {
			const d = e.data.date as string;
			if (!d) continue;
			const vol = (Number(e.data.sets) || 0) * (Number(e.data.reps) || 0) * (Number(e.data.weight) || 0);
			map.set(d, (map.get(d) || 0) + vol);
		}
		return [...map.entries()].sort((a, b) => a[0].localeCompare(b[0])).slice(-14).map(([d, v]) => ({ date: d, volume: v }));
	});

	const volumePerSessionMax = $derived(Math.max(...volumePerSession.map(s => s.volume), 1));

	/* Personal records */
	const personalRecords = $derived.by(() => {
		const heaviest = new Map<string, { weight: number; date: string; name: string }>();
		for (const e of store.items) {
			const key = (e.data.exercise as string)?.toLowerCase().trim();
			const name = e.data.exercise as string;
			const w = Number(e.data.weight) || 0;
			const d = e.data.date as string;
			if (!key || !w) continue;
			const existing = heaviest.get(key);
			if (!existing || w > existing.weight) {
				heaviest.set(key, { weight: w, date: d, name });
			}
		}
		const heaviestList = [...heaviest.values()].sort((a, b) => b.weight - a.weight);

		let bestSessionDate = '';
		let bestSessionVol = 0;
		const sessionMap = new Map<string, number>();
		for (const e of store.items) {
			const d = e.data.date as string;
			if (!d) continue;
			const vol = (Number(e.data.sets) || 0) * (Number(e.data.reps) || 0) * (Number(e.data.weight) || 0);
			sessionMap.set(d, (sessionMap.get(d) || 0) + vol);
		}
		for (const [d, v] of sessionMap) {
			if (v > bestSessionVol) { bestSessionVol = v; bestSessionDate = d; }
		}

		return { heaviest: heaviestList, bestSessionVol, bestSessionDate };
	});

	/* Muscle group balance */
	const muscleGroupKeywords: [string, string[]][] = [
		['chest', ['bench', 'push-up', 'pushup', 'press banca', 'fly', 'pec']],
		['back', ['deadlift', 'row', 'pull-up', 'pullup', 'pulldown', 'lat']],
		['legs', ['squat', 'leg press', 'lunge', 'leg curl', 'leg extension', 'calf']],
		['shoulders', ['ohp', 'overhead press', 'military press', 'lateral raise', 'shoulder']],
		['arms', ['curl', 'bicep', 'tricep', 'hammer', 'skull crusher', 'dip']],
	];

	const muscleBalance = $derived.by(() => {
		const totals = new Map<string, number>();
		for (const e of store.items) {
			const name = ((e.data.exercise as string) || '').toLowerCase();
			const vol = (Number(e.data.sets) || 0) * (Number(e.data.reps) || 0) * (Number(e.data.weight) || 0);
			if (!vol) continue;
			let matched = false;
			for (const [group, keywords] of muscleGroupKeywords) {
				if (keywords.some(kw => name.includes(kw))) {
					totals.set(group, (totals.get(group) || 0) + vol);
					matched = true;
					break;
				}
			}
			if (!matched) totals.set('other', (totals.get('other') || 0) + vol);
		}
		const max = Math.max(...totals.values(), 1);
		return [...totals.entries()].sort((a, b) => b[1] - a[1]).map(([group, vol]) => ({ group, vol, pct: vol / max }));
	});

	function fmtVol(v: number): string {
		if (v >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M';
		if (v >= 1_000) return (v / 1_000).toFixed(1) + 'k';
		return String(Math.round(v));
	}

	function svgPolyline(values: number[], w: number, h: number, pad = 6): string {
		if (values.length < 2) return '';
		const min = Math.min(...values);
		const max = Math.max(...values);
		const range = max - min || 1;
		return values.map((v, i) => {
			const x = pad + (i / (values.length - 1)) * (w - pad * 2);
			const y = pad + (1 - (v - min) / range) * (h - pad * 2);
			return `${x.toFixed(1)},${y.toFixed(1)}`;
		}).join(' ');
	}

	function svgDots(values: number[], w: number, h: number, pad = 6): { x: number; y: number }[] {
		if (values.length < 2) return [];
		const min = Math.min(...values);
		const max = Math.max(...values);
		const range = max - min || 1;
		return values.map((v, i) => ({
			x: pad + (i / (values.length - 1)) * (w - pad * 2),
			y: pad + (1 - (v - min) / range) * (h - pad * 2)
		}));
	}
</script>

<svelte:head>
  <title>{t.training.strength} | Darink</title>
</svelte:head>

<PageHeader title={t.training.strength} back="/training" />

{#if quickExercises.length > 0}
<section class="quick-add">
	<h2>{t.training.quickAdd}</h2>
	<div class="quick-chips">
		{#each quickExercises as item}
			<button class="quick-chip" onclick={() => prefillExercise(item)}>
				{item.name}
				<span class="quick-count">{item.count}</span>
			</button>
		{/each}
	</div>
</section>
{/if}

<section class="form">
	<label>{t.common.date} <input type="date" bind:value={date} /></label>
	<label>{t.training.exercise} <input type="text" bind:value={exercise} placeholder="Squat, Bench..." /></label>
	<div class="row">
		<label>{t.training.sets} <input type="number" min="1" max="20" bind:value={sets} /></label>
		<label>{t.training.reps} <input type="number" min="1" max="100" bind:value={reps} /></label>
		<label>{t.training.weightKg} <input type="number" min="0" step="0.5" bind:value={weight} /></label>
		<label>{t.training.rir} <input type="number" min="0" max="10" bind:value={rir} /></label>
	</div>
	<label>{t.common.notes} <textarea bind:value={notes} rows="2"></textarea></label>
	<div class="form-actions">
		<button class="primary" onclick={submit}>{t.training.logSet}</button>
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
			exercise: (fd.get('exercise') as string).trim(),
			sets: Number(fd.get('sets')),
			reps: Number(fd.get('reps')),
			weight: Number(fd.get('weight')),
			rir: Number(fd.get('rir')),
			notes: (fd.get('notes') as string).trim()
		});
		toast.show(t.common.updated);
		done();
	}}>
		<label>{t.common.date} <input type="date" name="date" value={data.date || ''} /></label>
		<label>{t.training.exercise} <input type="text" name="exercise" value={data.exercise} /></label>
		<div class="row">
			<label>{t.training.sets} <input type="number" name="sets" min="1" max="20" value={data.sets} /></label>
			<label>{t.training.reps} <input type="number" name="reps" min="1" max="100" value={data.reps} /></label>
			<label>{t.training.weightKg} <input type="number" name="weight" min="0" step="0.5" value={data.weight} /></label>
			<label>{t.training.rir} <input type="number" name="rir" min="0" max="10" value={data.rir} /></label>
		</div>
		<label>{t.common.notes} <textarea name="notes" rows="2">{data.notes}</textarea></label>
		<div class="edit-actions">
			<button type="submit">{t.common.save}</button>
			<button type="button" onclick={done}>{t.common.cancel}</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div><strong>{item.data.exercise}</strong> <span class="meta">{item.data.sets}×{item.data.reps} @ {item.data.weight}kg RIR{item.data.rir}</span></div>
	{/snippet}
</EntryList>

<!-- ── Analytics ── -->
{#if store.items.length > 0}

<!-- 1. Quick Stats -->
<section class="analytics-section">
	<h2>{t.training.stats}</h2>
	<div class="stats-row">
		<div class="stat-card">
			<span class="stat-value">{totalSets}</span>
			<span class="stat-label">{t.training.totalSets}</span>
		</div>
		<div class="stat-card">
			<span class="stat-value">{fmtVol(totalVolume)}</span>
			<span class="stat-label">{t.training.volumeKg}</span>
		</div>
		<div class="stat-card">
			<span class="stat-value">{sessionsThisWeek}</span>
			<span class="stat-label">{t.common.thisWeek}</span>
		</div>
	</div>
</section>

<!-- 2. Exercise Progression Charts -->
{#if topExerciseCharts.length > 0}
<section class="analytics-section">
	<h2>{t.training.progression}</h2>
	{#each topExerciseCharts as chart}
		<div class="chart-card">
			<div class="chart-header">
				<span class="chart-title">{chart.name}</span>
				<span class="chart-latest">{chart.latestWeight}kg <span class="chart-e1rm">e1RM {Math.round(chart.latestE1rm)}kg</span></span>
			</div>
			{#if chart.points.length >= 2}
				{@const weights = chart.points.map(p => p.weight)}
				{@const e1rms = chart.points.map(p => p.e1rm)}
				{@const wPoly = svgPolyline(weights, 280, 80)}
				{@const ePoly = svgPolyline(e1rms, 280, 80)}
				{@const wDots = svgDots(weights, 280, 80)}
				<svg class="chart-svg" viewBox="0 0 280 80" preserveAspectRatio="none">
					<polyline points={ePoly} fill="none" stroke="var(--c-done)" stroke-width="1.2" stroke-dasharray="4 2" opacity="0.5" />
					<polyline points={wPoly} fill="none" stroke="var(--c-accent)" stroke-width="1.8" />
					{#each wDots as dot}
						<circle cx={dot.x} cy={dot.y} r="2.5" fill="var(--c-accent)" />
					{/each}
				</svg>
				<div class="chart-legend">
					<span class="legend-item"><span class="legend-line accent"></span> {t.training.weight}</span>
					<span class="legend-item"><span class="legend-line e1rm"></span> {t.training.e1rm}</span>
				</div>
			{:else}
				<p class="chart-empty">{t.training.needMoreEntries}</p>
			{/if}
		</div>
	{/each}
</section>
{/if}

<!-- 3. Volume Per Session -->
{#if volumePerSession.length > 1}
<section class="analytics-section">
	<h2>{t.training.volumePerSession}</h2>
	<div class="chart-card">
		<svg class="chart-svg bar-chart" viewBox="0 0 280 100">
			{#each volumePerSession as session, i}
				{@const barW = Math.max((280 - (volumePerSession.length - 1) * 2) / volumePerSession.length, 4)}
				{@const barH = (session.volume / volumePerSessionMax) * 78}
				{@const x = i * (barW + 2)}
				<rect x={x} y={80 - barH} width={barW} height={barH} rx="2" fill="var(--c-accent)" opacity="0.85" />
			{/each}
			{#each volumePerSession as session, i}
				{@const barW = Math.max((280 - (volumePerSession.length - 1) * 2) / volumePerSession.length, 4)}
				{@const x = i * (barW + 2) + barW / 2}
				{#if i === 0 || i === volumePerSession.length - 1 || i === Math.floor(volumePerSession.length / 2)}
					<text x={x} y="96" text-anchor="middle" font-size="7" fill="var(--c-text-muted)">{session.date.slice(5)}</text>
				{/if}
			{/each}
		</svg>
	</div>
</section>
{/if}

<!-- 4. Personal Records -->
<section class="analytics-section">
	<h2>{t.training.personalRecords}</h2>
	{#if personalRecords.bestSessionVol > 0}
	<div class="pr-best-session">
		<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
		<span>{t.training.bestSession}: <strong>{fmtVol(personalRecords.bestSessionVol)}kg</strong> {personalRecords.bestSessionDate}</span>
	</div>
	{/if}
	<div class="pr-grid">
		{#each personalRecords.heaviest.slice(0, 8) as pr}
			<div class="pr-card">
				<span class="pr-exercise">{pr.name}</span>
				<span class="pr-weight">{pr.weight}kg</span>
				<span class="pr-date">{pr.date}</span>
			</div>
		{/each}
	</div>
</section>

<!-- 5. Muscle Group Balance -->
{#if muscleBalance.length > 0}
<section class="analytics-section">
	<h2>{t.training.muscleBalance}</h2>
	<div class="muscle-bars">
		{#each muscleBalance as mg}
			<div class="muscle-row">
				<span class="muscle-label">{mg.group}</span>
				<div class="muscle-bar-track">
					<div class="muscle-bar-fill" style="width: {Math.round(mg.pct * 100)}%"></div>
				</div>
				<span class="muscle-vol">{fmtVol(mg.vol)}</span>
			</div>
		{/each}
	</div>
</section>
{/if}

{/if}
<!-- ── /Analytics ── -->

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
	.quick-add { padding: 0 1rem 0.5rem; }
	h2 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
	.quick-chips { display: flex; flex-wrap: wrap; gap: 0.25rem; }
	.quick-chip {
		display: inline-flex; align-items: center; gap: 0.25rem;
		padding: 0.3rem 0.6rem; background: var(--c-bg-card);
		border: 1px solid var(--c-border); border-radius: 16px;
		font-size: 0.8rem; cursor: pointer; transition: border-color 0.15s;
	}
	.quick-chip:hover { border-color: var(--c-accent); background: var(--c-accent-bg); }
	.quick-count { font-size: 0.65rem; color: var(--c-text-muted); }

	/* Analytics */
	.analytics-section { padding: 0 1rem 0.5rem; margin-top: 0.5rem; }
	.stats-row { display: flex; gap: 0.5rem; }
	.stat-card {
		flex: 1; display: flex; flex-direction: column; align-items: center;
		padding: 0.6rem 0.4rem; background: var(--c-bg-card);
		border: 1px solid var(--c-border); border-radius: var(--radius);
	}
	.stat-value { font-size: 1.3rem; font-weight: 700; color: var(--c-accent); }
	.stat-label { font-size: 0.7rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.04em; }

	.chart-card {
		background: var(--c-bg-card); border: 1px solid var(--c-border);
		border-radius: var(--radius); padding: 0.6rem; margin-bottom: 0.5rem;
	}
	.chart-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.3rem; }
	.chart-title { font-weight: 600; font-size: 0.9rem; }
	.chart-latest { font-size: 0.8rem; color: var(--c-accent); font-weight: 600; }
	.chart-e1rm { font-size: 0.7rem; color: var(--c-text-muted); font-weight: 400; }
	.chart-svg { width: 100%; height: auto; display: block; }
	.chart-empty { font-size: 0.8rem; color: var(--c-text-muted); margin: 0; }
	.chart-legend { display: flex; gap: 0.75rem; margin-top: 0.3rem; }
	.legend-item { display: flex; align-items: center; gap: 0.25rem; font-size: 0.7rem; color: var(--c-text-muted); }
	.legend-line { display: inline-block; width: 14px; height: 2px; }
	.legend-line.accent { background: var(--c-accent); }
	.legend-line.e1rm { background: var(--c-done); opacity: 0.5; border-top: 1px dashed var(--c-done); height: 0; }

	.bar-chart { height: auto; }

	.pr-best-session {
		display: flex; align-items: center; gap: 0.4rem;
		padding: 0.5rem 0.6rem; margin-bottom: 0.5rem;
		background: var(--c-accent-bg); border: 1px solid var(--c-accent);
		border-radius: var(--radius); font-size: 0.85rem; color: var(--c-accent);
	}
	.pr-best-session svg { flex-shrink: 0; color: var(--c-accent); }
	.pr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 0.4rem; }
	.pr-card {
		display: flex; flex-direction: column; padding: 0.5rem;
		background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius);
	}
	.pr-exercise { font-size: 0.8rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
	.pr-weight { font-size: 1.1rem; font-weight: 700; color: var(--c-accent); }
	.pr-date { font-size: 0.65rem; color: var(--c-text-muted); }

	.muscle-bars { display: flex; flex-direction: column; gap: 0.4rem; }
	.muscle-row { display: flex; align-items: center; gap: 0.5rem; }
	.muscle-label { width: 70px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; flex-shrink: 0; }
	.muscle-bar-track { flex: 1; height: 14px; background: var(--c-border); border-radius: 7px; overflow: hidden; }
	.muscle-bar-fill { height: 100%; background: var(--c-accent); border-radius: 7px; transition: width 0.3s; min-width: 2px; }
	.muscle-vol { font-size: 0.7rem; color: var(--c-text-muted); width: 40px; text-align: right; flex-shrink: 0; }
</style>
