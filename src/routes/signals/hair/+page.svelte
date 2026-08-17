<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('signal.hair');

	let zone = $state('');
	let density = $state(5);
	let shedding = $state(3);
	let miniaturization = $state(false);
	let notes = $state('');

	const sorted = $derived(store.items.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt)));
	const last30 = $derived(sorted.slice(-30));
	const densityChart = $derived(last30.map(e => ({ value: Number(e.data.density) })));
	const sheddingChart = $derived(last30.map(e => ({ value: Number(e.data.shedding) })));

	function submit() {
		entries.add('signal.hair', { zone, density, shedding, miniaturization, notes });
		zone = ''; notes = '';
		toast.show('Hair logged');
	}
</script>

<svelte:head>
  <title>Hair | Darink</title>
</svelte:head>

<PageHeader title="Hair" back="/signals" />

<section class="form">
	<label>Zone <input type="text" bind:value={zone} placeholder="Temples, crown, beard..." /></label>
	<label>Density ({density}/10) <input type="range" min="1" max="10" bind:value={density} /></label>
	<label>Shedding ({shedding}/10) <input type="range" min="1" max="10" bind:value={shedding} /></label>
	<label class="checkbox"><input type="checkbox" bind:checked={miniaturization} /> Miniaturization visible</label>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log hair</button>
</section>

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			zone: (fd.get('zone') as string).trim(),
			density: Number(fd.get('density')),
			shedding: Number(fd.get('shedding')),
			miniaturization: Number(fd.get('miniaturization')),
			notes: (fd.get('notes') as string).trim()
		});
		toast.show('Updated');
		done();
	}}>
		<label>Zone <input type="text" name="zone" value={data.zone ?? ''} /></label>
		<label>Density <input type="range" name="density" min="1" max="10" value={data.density} /></label>
		<label>Shedding <input type="range" name="shedding" min="1" max="10" value={data.shedding} /></label>
		<label>Miniaturization <input type="range" name="miniaturization" min="1" max="10" value={data.miniaturization} /></label>
		<label>Notes <textarea name="notes" rows="2">{data.notes ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c-4.97 0-9-2.24-9-5v-4c0-2.76 4.03-5 9-5s9 2.24 9 5v4c0 2.76-4.03 5-9 5z"/><path d="M12 8V2"/><path d="M8 8V4"/><path d="M16 8V4"/><path d="M6 9V6"/><path d="M18 9V6"/></svg>
	<p>No hair entries yet</p>
	<p class="empty-hint">Monitor hair health to detect changes in density and shedding.</p>
</div>
{/if}

<EntryList items={store.items} {editForm} limit={10}>
	{#snippet row(item)}
		<div><span class="date">{new Date(item.createdAt).toLocaleDateString()}</span> <span class="meta">Zone: {item.data.zone || '—'} · Density: {item.data.density}/10 · Shedding: {item.data.shedding}/10</span></div>
	{/snippet}
</EntryList>

{#if densityChart.length > 1}
{@const ptsD = densityChart}
{@const ptsS = sheddingChart}
{@const allVals = [...ptsD.map(p => p.value), ...ptsS.map(p => p.value)]}
{@const minV = Math.min(...allVals)}
{@const maxV = Math.max(...allVals)}
{@const rangeV = maxV - minV || 1}
{@const stepX = 280 / Math.max(ptsD.length - 1, 1)}
<section class="chart-section">
	<h2>Density vs Shedding Trend</h2>
	<svg class="line-chart" viewBox="0 0 280 100" preserveAspectRatio="none">
		<polyline fill="none" stroke="var(--c-accent)" stroke-width="2" stroke-linejoin="round"
			points={ptsD.map((p, i) => `${i * stepX},${100 - ((p.value - minV) / rangeV) * 80 - 10}`).join(' ')} />
		<polyline fill="none" stroke="#ef5350" stroke-width="2" stroke-linejoin="round"
			points={ptsS.map((p, i) => `${i * stepX},${100 - ((p.value - minV) / rangeV) * 80 - 10}`).join(' ')} />
	</svg>
	<div class="legend">
		<span><span class="dot" style="background:var(--c-accent)"></span> Density</span>
		<span><span class="dot" style="background:#ef5350"></span> Shedding</span>
	</div>
</section>
{/if}

{#if last30.length > 0}
{@const avgDensity = (last30.reduce((s, e) => s + Number(e.data.density), 0) / last30.length).toFixed(1)}
{@const avgShedding = (last30.reduce((s, e) => s + Number(e.data.shedding), 0) / last30.length).toFixed(1)}
<section class="metrics">
	<h2>Averages</h2>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value">{avgDensity}</span>
			<span class="metric-label">Avg Density</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{avgShedding}</span>
			<span class="metric-label">Avg Shedding</span>
		</div>
	</div>
</section>
{/if}

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	input[type="range"] { padding: 0; }
	.checkbox { flex-direction: row; align-items: center; gap: 0.5rem; }
	.checkbox input { width: auto; }
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
</style>
