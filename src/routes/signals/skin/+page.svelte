<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('signal.skin');

	let acneZone = $state('');
	let oiliness = $state(3);
	let elasticity = $state(5);
	let healing = $state(5);
	let notes = $state('');

	const sorted = $derived(store.items.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt)));
	const last30 = $derived(sorted.slice(-30));
	const oilinessChart = $derived(last30.map(e => ({ value: Number(e.data.oiliness) * 2 })));
	const elasticityChart = $derived(last30.map(e => ({ value: Number(e.data.elasticity) })));

	function submit() {
		entries.add('signal.skin', { acneZone, oiliness, elasticity, healing, notes });
		acneZone = ''; notes = '';
		toast.show('Skin logged');
	}
</script>

<svelte:head>
  <title>Skin | Darink</title>
</svelte:head>

<PageHeader title="Skin" back="/signals" />

<section class="form">
	<label>Acne zone <input type="text" bind:value={acneZone} placeholder="Forehead, jaw, back..." /></label>
	<label>Oiliness ({oiliness}/5) <input type="range" min="1" max="5" bind:value={oiliness} /></label>
	<label>Elasticity ({elasticity}/10) <input type="range" min="1" max="10" bind:value={elasticity} /></label>
	<label>Healing speed ({healing}/10) <input type="range" min="1" max="10" bind:value={healing} /></label>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log skin</button>
</section>

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			acneZone: (fd.get('acneZone') as string).trim(),
			oiliness: Number(fd.get('oiliness')),
			elasticity: Number(fd.get('elasticity')),
			healing: Number(fd.get('healing')),
			notes: (fd.get('notes') as string).trim()
		});
		toast.show('Updated');
		done();
	}}>
		<label>Acne zone <input type="text" name="acneZone" value={data.acneZone ?? ''} /></label>
		<label>Oiliness <input type="range" name="oiliness" min="1" max="5" value={data.oiliness} /></label>
		<label>Elasticity <input type="range" name="elasticity" min="1" max="10" value={data.elasticity} /></label>
		<label>Healing speed <input type="range" name="healing" min="1" max="5" value={data.healing} /></label>
		<label>Notes <textarea name="notes" rows="2">{data.notes ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
	<p>No skin entries yet</p>
	<p class="empty-hint">Track skin health to spot patterns in oiliness and elasticity.</p>
</div>
{/if}

<EntryList items={store.items} {editForm} limit={10}>
	{#snippet row(item)}
		<div><span class="date">{new Date(item.createdAt).toLocaleDateString()}</span> <span class="meta">Oiliness: {item.data.oiliness}/5 · Elasticity: {item.data.elasticity}/10{item.data.acneZone ? ` · ${item.data.acneZone}` : ''}</span></div>
	{/snippet}
</EntryList>

{#if oilinessChart.length > 1}
{@const ptsO = oilinessChart}
{@const ptsE = elasticityChart}
{@const allVals = [...ptsO.map(p => p.value), ...ptsE.map(p => p.value)]}
{@const minV = Math.min(...allVals)}
{@const maxV = Math.max(...allVals)}
{@const rangeV = maxV - minV || 1}
{@const stepX = 280 / Math.max(ptsO.length - 1, 1)}
<section class="chart-section">
	<h2>Oiliness &amp; Elasticity Trend</h2>
	<svg class="line-chart" viewBox="0 0 280 100" preserveAspectRatio="none">
		<polyline fill="none" stroke="#ff9800" stroke-width="2" stroke-linejoin="round"
			points={ptsO.map((p, i) => `${i * stepX},${100 - ((p.value - minV) / rangeV) * 80 - 10}`).join(' ')} />
		<polyline fill="none" stroke="var(--c-accent)" stroke-width="2" stroke-linejoin="round"
			points={ptsE.map((p, i) => `${i * stepX},${100 - ((p.value - minV) / rangeV) * 80 - 10}`).join(' ')} />
	</svg>
	<div class="legend">
		<span><span class="dot" style="background:#ff9800"></span> Oiliness (x2)</span>
		<span><span class="dot" style="background:var(--c-accent)"></span> Elasticity</span>
	</div>
</section>
{/if}

{#if last30.length > 0}
{@const avgOiliness = (last30.reduce((s, e) => s + Number(e.data.oiliness), 0) / last30.length).toFixed(1)}
{@const avgElasticity = (last30.reduce((s, e) => s + Number(e.data.elasticity), 0) / last30.length).toFixed(1)}
<section class="metrics">
	<h2>Averages</h2>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value">{avgOiliness}</span>
			<span class="metric-label">Avg Oiliness</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{avgElasticity}</span>
			<span class="metric-label">Avg Elasticity</span>
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
</style>
