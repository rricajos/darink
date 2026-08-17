<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('signal.sleep');

	let date = $state(new Date().toISOString().slice(0, 10));
	let hours = $state(7);
	let quality = $state(5);
	let dreams = $state(false);
	let bedtime = $state('23:00');
	let wakeTime = $state('07:00');
	let notes = $state('');

	const sorted = $derived(store.items.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt)));
	const last30 = $derived(sorted.slice(-30));
	const qualityChart = $derived(last30.map(e => ({ value: Number(e.data.quality) })));
	const hoursChart = $derived(last30.map(e => ({ value: Number(e.data.hours) })));

	const thirtyDaysAgo = $derived(new Date(Date.now() - 30 * 86400000).toISOString());
	const recent30 = $derived(sorted.filter(e => e.createdAt >= thirtyDaysAgo));

	function submit() {
		entries.add('signal.sleep', { date, hours, quality, dreams, bedtime, wakeTime, notes });
		date = new Date().toISOString().slice(0, 10);
		notes = '';
		toast.show('Sleep logged');
	}
</script>

<PageHeader title="Sleep" back="/signals" />

<section class="form">
	<label>Date <input type="date" bind:value={date} /></label>
	<label>Hours ({hours}) <input type="number" min="0" max="14" step="0.5" bind:value={hours} /></label>
	<label>Quality ({quality}/10) <input type="range" min="1" max="10" bind:value={quality} /></label>
	<div class="row">
		<label>Bedtime <input type="time" bind:value={bedtime} /></label>
		<label>Wake <input type="time" bind:value={wakeTime} /></label>
	</div>
	<label class="checkbox"><input type="checkbox" bind:checked={dreams} /> Vivid dreams</label>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log sleep</button>
</section>

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			date: fd.get('date') as string,
			hours: Number(fd.get('hours')),
			quality: Number(fd.get('quality')),
			bedtime: fd.get('bedtime') as string,
			wakeTime: fd.get('wakeTime') as string,
			dreams: (fd.get('dreams') as string).trim()
		});
		toast.show('Updated');
		done();
	}}>
		<label>Date <input type="date" name="date" value={data.date as string ?? ''} /></label>
		<label>Hours <input type="number" name="hours" min="0" max="14" step="0.5" value={data.hours} /></label>
		<label>Quality <input type="range" name="quality" min="1" max="10" value={data.quality} /></label>
		<div class="row">
			<label>Bedtime <input type="time" name="bedtime" value={data.bedtime} /></label>
			<label>Wake <input type="time" name="wakeTime" value={data.wakeTime} /></label>
		</div>
		<label>Dreams <textarea name="dreams" rows="2">{data.dreams ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm} limit={7}>
	{#snippet row(item)}
		<div><span class="date">{new Date(item.createdAt).toLocaleDateString()}</span> <span class="meta">{item.data.hours}h · Q{item.data.quality}/10</span></div>
	{/snippet}
</EntryList>

{#if qualityChart.length > 1}
{@const pts = qualityChart}
{@const minV = Math.min(...pts.map(p => p.value))}
{@const maxV = Math.max(...pts.map(p => p.value))}
{@const rangeV = maxV - minV || 1}
{@const stepX = 280 / Math.max(pts.length - 1, 1)}
<section class="chart-section">
	<h2>Sleep Quality Trend</h2>
	<svg class="line-chart" viewBox="0 0 280 100" preserveAspectRatio="none">
		<polyline fill="none" stroke="var(--c-accent)" stroke-width="2" stroke-linejoin="round"
			points={pts.map((p, i) => `${i * stepX},${100 - ((p.value - minV) / rangeV) * 80 - 10}`).join(' ')} />
	</svg>
</section>
{/if}

{#if hoursChart.length > 1}
{@const pts = hoursChart}
{@const minV = Math.min(...pts.map(p => p.value))}
{@const maxV = Math.max(...pts.map(p => p.value))}
{@const rangeV = maxV - minV || 1}
{@const stepX = 280 / Math.max(pts.length - 1, 1)}
<section class="chart-section">
	<h2>Hours Trend</h2>
	<svg class="line-chart" viewBox="0 0 280 100" preserveAspectRatio="none">
		<polyline fill="none" stroke="#6ec6ff" stroke-width="2" stroke-linejoin="round"
			points={pts.map((p, i) => `${i * stepX},${100 - ((p.value - minV) / rangeV) * 80 - 10}`).join(' ')} />
	</svg>
</section>
{/if}

{#if recent30.length > 0}
{@const avgHours = (recent30.reduce((s, e) => s + Number(e.data.hours), 0) / recent30.length).toFixed(1)}
{@const avgQuality = (recent30.reduce((s, e) => s + Number(e.data.quality), 0) / recent30.length).toFixed(1)}
{@const bestEntry = recent30.reduce((a, b) => Number(a.data.quality) >= Number(b.data.quality) ? a : b)}
{@const worstEntry = recent30.reduce((a, b) => Number(a.data.quality) <= Number(b.data.quality) ? a : b)}
<section class="metrics">
	<h2>Averages (last 30 days)</h2>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value">{avgHours}</span>
			<span class="metric-label">Avg Hours</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{avgQuality}</span>
			<span class="metric-label">Avg Quality</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{new Date(bestEntry.createdAt).toLocaleDateString()}</span>
			<span class="metric-label">Best Quality</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{new Date(worstEntry.createdAt).toLocaleDateString()}</span>
			<span class="metric-label">Worst Quality</span>
		</div>
	</div>
</section>
{/if}

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }
	.checkbox { flex-direction: row; align-items: center; gap: 0.5rem; }
	.checkbox input { width: auto; }
	input[type="range"] { padding: 0; }
	.date { font-weight: 600; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }
	h2 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
	.chart-section { padding: 1.5rem 1rem 0; }
	.line-chart { width: 100%; height: 100px; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.5rem; }
	.metrics { padding: 1.5rem 1rem 0; }
	.metrics-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.metric-card { flex: 1; min-width: 80px; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; text-align: center; display: flex; flex-direction: column; gap: 0.15rem; }
	.metric-value { font-size: 1.4rem; font-weight: 700; }
	.metric-label { font-size: 0.75rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; }
</style>
