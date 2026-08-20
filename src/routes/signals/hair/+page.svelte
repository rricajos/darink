<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { useLocale } from '$lib/stores/locale.svelte';
	import type { Entry } from '$lib/db';

	const { t } = useLocale();
	const store = useEntries('signal.hair');
	const allStore = useEntries();

	let zone = $state('');
	let density = $state(5);
	let shedding = $state(3);
	let miniaturization = $state(false);
	let notes = $state('');

	const sorted = $derived(store.items.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt)));
	const last30 = $derived(sorted.slice(-30));
	const densityChart = $derived(last30.map(e => ({ value: Number(e.data.density) })));
	const sheddingChart = $derived(last30.map(e => ({ value: Number(e.data.shedding) })));

	const stressHairLink = $derived.by(() => {
		const checkins = allStore.items.filter(e => e.type === 'checkin');
		if (checkins.length < 5 || store.items.length < 5) return null;
		const stressByDate: Record<string, number> = {};
		for (const c of checkins) {
			const d = (c.data.date as string) ?? c.createdAt.slice(0, 10);
			stressByDate[d] = Number(c.data.stress);
		}
		let highStressShed: number[] = [], lowStressShed: number[] = [];
		for (const e of store.items) {
			const d = e.createdAt.slice(0, 10);
			const stress = stressByDate[d];
			if (stress === undefined) continue;
			const shed = Number(e.data.shedding);
			if (stress >= 7) highStressShed.push(shed);
			else if (stress <= 4) lowStressShed.push(shed);
		}
		if (highStressShed.length === 0 || lowStressShed.length === 0) return null;
		const avg = (a: number[]) => +(a.reduce((s, v) => s + v, 0) / a.length).toFixed(1);
		return { highStress: avg(highStressShed), lowStress: avg(lowStressShed), highN: highStressShed.length, lowN: lowStressShed.length };
	});

	const zoneFreq = $derived.by(() => {
		const counts: Record<string, number> = {};
		for (const e of store.items) {
			const z = (e.data.zone as string)?.trim();
			if (z) counts[z] = (counts[z] || 0) + 1;
		}
		const sorted = Object.entries(counts).toSorted((a, b) => b[1] - a[1]).slice(0, 5);
		const max = sorted.length > 0 ? sorted[0][1] : 1;
		return { zones: sorted, max };
	});

	const monthlyTrend = $derived.by(() => {
		if (store.items.length < 5) return null;
		const byMonth: Record<string, { density: number[]; shedding: number[] }> = {};
		for (const e of store.items) {
			const m = e.createdAt.slice(0, 7);
			if (!byMonth[m]) byMonth[m] = { density: [], shedding: [] };
			byMonth[m].density.push(Number(e.data.density));
			byMonth[m].shedding.push(Number(e.data.shedding));
		}
		const months = Object.keys(byMonth).toSorted();
		const last6 = months.slice(-6);
		const avg = (a: number[]) => +(a.reduce((s, v) => s + v, 0) / a.length).toFixed(1);
		return last6.map(m => ({
			month: m.slice(2),
			avgDensity: avg(byMonth[m].density),
			avgShedding: avg(byMonth[m].shedding)
		}));
	});

	const supplementHairLink = $derived.by(() => {
		const suppEntries = allStore.items.filter(e => e.type === 'supplement');
		if (suppEntries.length < 5 || store.items.length < 5) return null;
		const suppDates = new Set(suppEntries.map(e => (e.data.date as string) ?? e.createdAt.slice(0, 10)));
		let suppDays: number[] = [], noSuppDays: number[] = [];
		for (const e of store.items) {
			const d = e.createdAt.slice(0, 10);
			const density = Number(e.data.density);
			if (suppDates.has(d)) suppDays.push(density);
			else noSuppDays.push(density);
		}
		if (suppDays.length === 0 || noSuppDays.length === 0) return null;
		const avg = (a: number[]) => +(a.reduce((s, v) => s + v, 0) / a.length).toFixed(1);
		return { supp: avg(suppDays), noSupp: avg(noSuppDays) };
	});

	function submit() {
		entries.add('signal.hair', { zone, density, shedding, miniaturization, notes });
		zone = ''; notes = '';
		toast.show(t.hair.hairLogged);
	}
</script>

<svelte:head>
  <title>{t.hair.title} | Darink</title>
</svelte:head>

<PageHeader title={t.hair.title} back="/signals" />

<section class="form">
	<label>{t.hair.zone} <input type="text" bind:value={zone} placeholder={t.hair.zonePlaceholder} /></label>
	<label>{t.hair.density} ({density}/10) <input type="range" min="1" max="10" bind:value={density} /></label>
	<label>{t.hair.shedding} ({shedding}/10) <input type="range" min="1" max="10" bind:value={shedding} /></label>
	<label class="checkbox"><input type="checkbox" bind:checked={miniaturization} /> {t.hair.miniVisible}</label>
	<label>{t.common.notes} <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>{t.hair.logHair}</button>
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
		toast.show(t.common.updated);
		done();
	}}>
		<label>{t.hair.zone} <input type="text" name="zone" value={data.zone ?? ''} /></label>
		<label>{t.hair.density} <input type="range" name="density" min="1" max="10" value={data.density} /></label>
		<label>{t.hair.shedding} <input type="range" name="shedding" min="1" max="10" value={data.shedding} /></label>
		<label>{t.hair.miniaturization} <input type="range" name="miniaturization" min="1" max="10" value={data.miniaturization} /></label>
		<label>{t.common.notes} <textarea name="notes" rows="2">{data.notes ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">{t.common.save}</button>
			<button type="button" onclick={done}>{t.common.cancel}</button>
		</div>
	</form>
{/snippet}

{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c-4.97 0-9-2.24-9-5v-4c0-2.76 4.03-5 9-5s9 2.24 9 5v4c0 2.76-4.03 5-9 5z"/><path d="M12 8V2"/><path d="M8 8V4"/><path d="M16 8V4"/><path d="M6 9V6"/><path d="M18 9V6"/></svg>
	<p>{t.hair.noEntries}</p>
	<p class="empty-hint">{t.hair.noEntriesHint}</p>
</div>
{/if}

<EntryList items={store.items} {editForm} limit={10}>
	{#snippet row(item)}
		<div><span class="date">{new Date(item.createdAt).toLocaleDateString()}</span> <span class="meta">{t.hair.zone}: {item.data.zone || '—'} · {t.hair.density}: {item.data.density}/10 · {t.hair.shedding}: {item.data.shedding}/10</span></div>
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
	<h2>{t.hair.densityVsShedding}</h2>
	<svg class="line-chart" viewBox="0 0 280 100" preserveAspectRatio="none">
		<polyline fill="none" stroke="var(--c-accent)" stroke-width="2" stroke-linejoin="round"
			points={ptsD.map((p, i) => `${i * stepX},${100 - ((p.value - minV) / rangeV) * 80 - 10}`).join(' ')} />
		<polyline fill="none" stroke="#ef5350" stroke-width="2" stroke-linejoin="round"
			points={ptsS.map((p, i) => `${i * stepX},${100 - ((p.value - minV) / rangeV) * 80 - 10}`).join(' ')} />
	</svg>
	<div class="legend">
		<span><span class="dot" style="background:var(--c-accent)"></span> {t.hair.density}</span>
		<span><span class="dot" style="background:#ef5350"></span> {t.hair.shedding}</span>
	</div>
</section>
{/if}

{#if last30.length > 0}
{@const avgDensity = (last30.reduce((s, e) => s + Number(e.data.density), 0) / last30.length).toFixed(1)}
{@const avgShedding = (last30.reduce((s, e) => s + Number(e.data.shedding), 0) / last30.length).toFixed(1)}
<section class="metrics">
	<h2>{t.hair.averages}</h2>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value">{avgDensity}</span>
			<span class="metric-label">{t.hair.avgDensity}</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{avgShedding}</span>
			<span class="metric-label">{t.hair.avgShedding}</span>
		</div>
	</div>
</section>
{/if}

{#if stressHairLink}
<section class="analytics">
	<h2>{t.hair.stressSheddingLink}</h2>
	<p class="hint">{t.hair.stressHint}</p>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value" style="color:#e53e3e">{stressHairLink.highStress}/10</span>
			<span class="metric-label">{t.hair.highStress} (n={stressHairLink.highN})</span>
		</div>
		<div class="metric-card">
			<span class="metric-value" style="color:var(--c-done)">{stressHairLink.lowStress}/10</span>
			<span class="metric-label">{t.hair.lowStress} (n={stressHairLink.lowN})</span>
		</div>
	</div>
</section>
{/if}

{#if zoneFreq.zones.length > 0}
<section class="analytics">
	<h2>{t.hair.zoneFrequency}</h2>
	<div class="zone-bars">
		{#each zoneFreq.zones as [z, count]}
			<div class="zone-row">
				<span class="zone-name">{z}</span>
				<div class="bar-bg"><div class="bar-fill" style="width:{(count / zoneFreq.max) * 100}%;background:var(--c-accent)"></div></div>
				<span class="zone-count">{count}</span>
			</div>
		{/each}
	</div>
</section>
{/if}

{#if supplementHairLink}
<section class="analytics">
	<h2>{t.hair.supplementHairLink}</h2>
	<p class="hint">{t.hair.suppHairHint}</p>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value" style="color:var(--c-done)">{supplementHairLink.supp}/10</span>
			<span class="metric-label">{t.hair.supplementDays}</span>
		</div>
		<div class="metric-card">
			<span class="metric-value" style="color:var(--c-text-muted)">{supplementHairLink.noSupp}/10</span>
			<span class="metric-label">{t.hair.noSupplement}</span>
		</div>
	</div>
</section>
{/if}

{#if monthlyTrend && monthlyTrend.length > 1}
<section class="analytics">
	<h2>{t.hair.monthlyTrend}</h2>
	<div class="monthly-table">
		<div class="monthly-header">
			<span>{t.hair.monthHeader}</span><span>{t.hair.density}</span><span>{t.hair.shedding}</span>
		</div>
		{#each monthlyTrend as m}
			<div class="monthly-row">
				<span>{m.month}</span>
				<span style="color:var(--c-accent)">{m.avgDensity}</span>
				<span style="color:#ef5350">{m.avgShedding}</span>
			</div>
		{/each}
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
	.analytics { padding: 1.5rem 1rem 0; }
	.hint { font-size: 0.8rem; color: var(--c-text-muted); margin-bottom: 0.75rem; }
	.zone-bars { display: flex; flex-direction: column; gap: 0.5rem; }
	.zone-row { display: flex; align-items: center; gap: 0.5rem; }
	.zone-name { width: 5rem; font-size: 0.8rem; text-align: right; color: var(--c-text-muted); }
	.zone-count { width: 2rem; font-size: 0.8rem; font-weight: 600; }
	.bar-bg { flex: 1; height: 12px; background: var(--c-border); border-radius: 6px; overflow: hidden; }
	.bar-fill { height: 100%; border-radius: 6px; transition: width 0.3s; }
	.monthly-table { background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); overflow: hidden; }
	.monthly-header, .monthly-row { display: grid; grid-template-columns: 1fr 1fr 1fr; padding: 0.5rem 0.75rem; font-size: 0.8rem; }
	.monthly-header { font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; font-size: 0.7rem; border-bottom: 1px solid var(--c-border); }
	.monthly-row:not(:last-child) { border-bottom: 1px solid var(--c-border); }
</style>
