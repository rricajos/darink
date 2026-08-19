<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('signal.genital');
	const allStore = useEntries();

	let morningErection = $state(0);
	let libido = $state(5);
	let sensitivity = $state(5);
	let notes = $state('');

	const sorted = $derived(store.items.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt)));
	const last30 = $derived(sorted.slice(-30));
	const libidoChart = $derived(last30.map(e => ({ value: Number(e.data.libido) })));
	const sensitivityChart = $derived(last30.map(e => ({ value: Number(e.data.sensitivity) })));

	const sleepLibidoLink = $derived.by(() => {
		const sleepEntries = allStore.items.filter(e => e.type === 'signal.sleep');
		if (sleepEntries.length < 5 || store.items.length < 5) return null;
		const sleepByDate: Record<string, number> = {};
		for (const e of sleepEntries) {
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			sleepByDate[d] = Number(e.data.hours);
		}
		let goodSleepLibido: number[] = [], poorSleepLibido: number[] = [];
		for (const e of store.items) {
			const d = e.createdAt.slice(0, 10);
			const h = sleepByDate[d];
			if (h === undefined) continue;
			const lib = Number(e.data.libido);
			if (h >= 7) goodSleepLibido.push(lib);
			else poorSleepLibido.push(lib);
		}
		if (goodSleepLibido.length === 0 || poorSleepLibido.length === 0) return null;
		const avg = (a: number[]) => +(a.reduce((s, v) => s + v, 0) / a.length).toFixed(1);
		return { goodSleep: avg(goodSleepLibido), poorSleep: avg(poorSleepLibido), goodN: goodSleepLibido.length, poorN: poorSleepLibido.length };
	});

	const trainingLibidoLink = $derived.by(() => {
		const trainings = allStore.items.filter(e => e.type.startsWith('training.'));
		if (trainings.length < 3 || store.items.length < 5) return null;
		const trainDates = new Set(trainings.map(e => e.createdAt.slice(0, 10)));
		let trainDayLib: number[] = [], restDayLib: number[] = [];
		for (const e of store.items) {
			const d = e.createdAt.slice(0, 10);
			const lib = Number(e.data.libido);
			if (trainDates.has(d)) trainDayLib.push(lib);
			else restDayLib.push(lib);
		}
		if (trainDayLib.length === 0 || restDayLib.length === 0) return null;
		const avg = (a: number[]) => +(a.reduce((s, v) => s + v, 0) / a.length).toFixed(1);
		return { train: avg(trainDayLib), rest: avg(restDayLib) };
	});

	const meWeeklyPattern = $derived.by(() => {
		if (store.items.length < 7) return null;
		const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
		const buckets: number[][] = Array.from({ length: 7 }, () => []);
		for (const e of store.items) {
			const d = e.createdAt.slice(0, 10);
			const day = new Date(d + 'T12:00:00').getDay();
			buckets[day].push(Number(e.data.morningErection));
		}
		const data = dayNames.map((name, i) => ({
			name,
			avg: buckets[i].length ? +(buckets[i].reduce((s, v) => s + v, 0) / buckets[i].length).toFixed(1) : 0,
			count: buckets[i].length
		}));
		const maxAvg = Math.max(...data.map(d => d.avg), 1);
		return { data, maxAvg };
	});

	const supplementLibidoLink = $derived.by(() => {
		const suppEntries = allStore.items.filter(e => e.type === 'supplement');
		if (suppEntries.length < 5 || store.items.length < 5) return null;
		const suppDates = new Set(suppEntries.map(e => (e.data.date as string) ?? e.createdAt.slice(0, 10)));
		let suppDays: number[] = [], noSuppDays: number[] = [];
		for (const e of store.items) {
			const d = e.createdAt.slice(0, 10);
			const lib = Number(e.data.libido);
			if (suppDates.has(d)) suppDays.push(lib);
			else noSuppDays.push(lib);
		}
		if (suppDays.length === 0 || noSuppDays.length === 0) return null;
		const avg = (a: number[]) => +(a.reduce((s, v) => s + v, 0) / a.length).toFixed(1);
		return { supp: avg(suppDays), noSupp: avg(noSuppDays) };
	});

	function submit() {
		entries.add('signal.genital', { morningErection, libido, sensitivity, notes });
		notes = '';
		toast.show('Signal logged');
	}
</script>

<svelte:head>
  <title>Genital | Darink</title>
</svelte:head>

<PageHeader title="Genital Signals" back="/signals" />

<section class="form">
	<label>Morning erection ({morningErection}/3) <input type="range" min="0" max="3" bind:value={morningErection} /></label>
	<label>Libido ({libido}/10) <input type="range" min="1" max="10" bind:value={libido} /></label>
	<label>Sensitivity ({sensitivity}/10) <input type="range" min="1" max="10" bind:value={sensitivity} /></label>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log signals</button>
</section>

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			morningErection: Number(fd.get('morningErection')),
			libido: Number(fd.get('libido')),
			sensitivity: Number(fd.get('sensitivity')),
			notes: (fd.get('notes') as string).trim()
		});
		toast.show('Updated');
		done();
	}}>
		<label>
			Morning erection
			<select name="morningErection">
				<option value="1" selected={Number(data.morningErection) === 1}>1</option>
				<option value="2" selected={Number(data.morningErection) === 2}>2</option>
				<option value="3" selected={Number(data.morningErection) === 3}>3</option>
			</select>
		</label>
		<label>Libido <input type="range" name="libido" min="1" max="10" value={data.libido} /></label>
		<label>Sensitivity <input type="range" name="sensitivity" min="1" max="10" value={data.sensitivity} /></label>
		<label>Notes <textarea name="notes" rows="2">{data.notes ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
	<p>No genital signal entries yet</p>
	<p class="empty-hint">Track these signals to monitor hormonal health markers.</p>
</div>
{/if}

<EntryList items={store.items} {editForm} limit={10}>
	{#snippet row(item)}
		<div><span class="date">{new Date(item.createdAt).toLocaleDateString()}</span> <span class="meta">Libido: {item.data.libido}/10 · Sensitivity: {item.data.sensitivity}/10 · ME: {item.data.morningErection}/3</span></div>
	{/snippet}
</EntryList>

{#if libidoChart.length > 1}
{@const ptsL = libidoChart}
{@const ptsS = sensitivityChart}
{@const allVals = [...ptsL.map(p => p.value), ...ptsS.map(p => p.value)]}
{@const minV = Math.min(...allVals)}
{@const maxV = Math.max(...allVals)}
{@const rangeV = maxV - minV || 1}
{@const stepX = 280 / Math.max(ptsL.length - 1, 1)}
<section class="chart-section">
	<h2>Libido &amp; Sensitivity Trend</h2>
	<svg class="line-chart" viewBox="0 0 280 100" preserveAspectRatio="none">
		<polyline fill="none" stroke="var(--c-accent)" stroke-width="2" stroke-linejoin="round"
			points={ptsL.map((p, i) => `${i * stepX},${100 - ((p.value - minV) / rangeV) * 80 - 10}`).join(' ')} />
		<polyline fill="none" stroke="#ab47bc" stroke-width="2" stroke-linejoin="round"
			points={ptsS.map((p, i) => `${i * stepX},${100 - ((p.value - minV) / rangeV) * 80 - 10}`).join(' ')} />
	</svg>
	<div class="legend">
		<span><span class="dot" style="background:var(--c-accent)"></span> Libido</span>
		<span><span class="dot" style="background:#ab47bc"></span> Sensitivity</span>
	</div>
</section>
{/if}

{#if last30.length > 0}
{@const avgLibido = (last30.reduce((s, e) => s + Number(e.data.libido), 0) / last30.length).toFixed(1)}
{@const avgSensitivity = (last30.reduce((s, e) => s + Number(e.data.sensitivity), 0) / last30.length).toFixed(1)}
{@const avgME = (last30.reduce((s, e) => s + Number(e.data.morningErection), 0) / last30.length).toFixed(1)}
<section class="metrics">
	<h2>Averages</h2>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value">{avgLibido}</span>
			<span class="metric-label">Avg Libido</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{avgSensitivity}</span>
			<span class="metric-label">Avg Sensitivity</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{avgME}</span>
			<span class="metric-label">Avg ME</span>
		</div>
	</div>
</section>
{/if}

{#if sleepLibidoLink}
<section class="analytics">
	<h2>Sleep–Libido Link</h2>
	<p class="hint">Avg libido on good sleep (≥7h) vs poor sleep (&lt;7h) days</p>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value" style="color:var(--c-done)">{sleepLibidoLink.goodSleep}/10</span>
			<span class="metric-label">≥7h sleep (n={sleepLibidoLink.goodN})</span>
		</div>
		<div class="metric-card">
			<span class="metric-value" style="color:#e8a735">{sleepLibidoLink.poorSleep}/10</span>
			<span class="metric-label">&lt;7h sleep (n={sleepLibidoLink.poorN})</span>
		</div>
	</div>
</section>
{/if}

{#if trainingLibidoLink}
<section class="analytics">
	<h2>Training–Libido Link</h2>
	<p class="hint">Avg libido on training days vs rest days</p>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value">{trainingLibidoLink.train}/10</span>
			<span class="metric-label">Training days</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{trainingLibidoLink.rest}/10</span>
			<span class="metric-label">Rest days</span>
		</div>
	</div>
</section>
{/if}

{#if meWeeklyPattern}
<section class="analytics">
	<h2>Morning Erection Pattern</h2>
	<div class="week-chart">
		{#each meWeeklyPattern.data as day}
			<div class="week-bar-col">
				<span class="week-val">{day.avg}</span>
				<div class="week-bar" style="height:{(day.avg / meWeeklyPattern.maxAvg) * 100}%;background:{day.avg >= 2.5 ? 'var(--c-done)' : day.avg >= 1.5 ? '#e8a735' : '#e53e3e'}"></div>
				<span class="week-label">{day.name}</span>
			</div>
		{/each}
	</div>
</section>
{/if}

{#if supplementLibidoLink}
<section class="analytics">
	<h2>Supplement Impact</h2>
	<p class="hint">Avg libido on supplement days vs non-supplement days</p>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value" style="color:var(--c-done)">{supplementLibidoLink.supp}/10</span>
			<span class="metric-label">Supplement days</span>
		</div>
		<div class="metric-card">
			<span class="metric-value" style="color:var(--c-text-muted)">{supplementLibidoLink.noSupp}/10</span>
			<span class="metric-label">No supplement</span>
		</div>
	</div>
</section>
{/if}

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	input[type="range"] { padding: 0; }
	.date { font-weight: 600; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }
	h2 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
	.chart-section { padding: 1.5rem 1rem 0; }
	.line-chart { width: 100%; height: 100px; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.5rem; }
	.legend { display: flex; gap: 1rem; font-size: 0.75rem; color: var(--c-text-muted); margin-top: 0.25rem; align-items: center; }
	.dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
	.metrics { padding: 1.5rem 1rem 0; }
	.metrics-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.metric-card { flex: 1; min-width: 80px; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; text-align: center; display: flex; flex-direction: column; gap: 0.15rem; }
	.metric-value { font-size: 1.4rem; font-weight: 700; }
	.metric-label { font-size: 0.75rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; }
	.analytics { padding: 1.5rem 1rem 0; }
	.hint { font-size: 0.8rem; color: var(--c-text-muted); margin-bottom: 0.75rem; }
	.week-chart { display: flex; align-items: flex-end; gap: 0.25rem; height: 120px; padding: 0.5rem; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); }
	.week-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; }
	.week-val { font-size: 0.65rem; color: var(--c-text-muted); margin-bottom: 2px; }
	.week-bar { width: 70%; border-radius: 3px 3px 0 0; min-height: 4px; transition: height 0.3s; }
	.week-label { font-size: 0.7rem; color: var(--c-text-muted); margin-top: 4px; }
</style>
