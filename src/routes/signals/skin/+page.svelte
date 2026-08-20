<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { useLocale } from '$lib/stores/locale.svelte';
	import type { Entry } from '$lib/db';

	const { t } = useLocale();
	const store = useEntries('signal.skin');
	const allStore = useEntries();

	let acneZone = $state('');
	let oiliness = $state(3);
	let elasticity = $state(5);
	let healing = $state(5);
	let notes = $state('');

	const sorted = $derived(store.items.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt)));
	const last30 = $derived(sorted.slice(-30));
	const oilinessChart = $derived(last30.map(e => ({ value: Number(e.data.oiliness) * 2 })));
	const elasticityChart = $derived(last30.map(e => ({ value: Number(e.data.elasticity) })));

	const hydrationSkinLink = $derived.by(() => {
		const hydEntries = allStore.items.filter(e => e.type === 'hydration');
		if (hydEntries.length < 5 || store.items.length < 5) return null;
		const hydByDate: Record<string, number> = {};
		for (const e of hydEntries) {
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			hydByDate[d] = (hydByDate[d] || 0) + Number(e.data.amount || 0);
		}
		const skinByDate: Record<string, { o: number; e: number }> = {};
		for (const e of store.items) {
			const d = e.createdAt.slice(0, 10);
			skinByDate[d] = { o: Number(e.data.oiliness), e: Number(e.data.elasticity) };
		}
		const allHyd = Object.values(hydByDate);
		if (allHyd.length === 0) return null;
		const median = allHyd.toSorted((a, b) => a - b)[Math.floor(allHyd.length / 2)];
		let highElast: number[] = [], lowElast: number[] = [];
		for (const [d, skin] of Object.entries(skinByDate)) {
			const h = hydByDate[d];
			if (h === undefined) continue;
			if (h >= median) highElast.push(skin.e);
			else lowElast.push(skin.e);
		}
		if (highElast.length === 0 || lowElast.length === 0) return null;
		const avg = (a: number[]) => +(a.reduce((s, v) => s + v, 0) / a.length).toFixed(1);
		return { highHyd: avg(highElast), lowHyd: avg(lowElast), highN: highElast.length, lowN: lowElast.length };
	});

	const acneZoneFreq = $derived.by(() => {
		const counts: Record<string, number> = {};
		for (const e of store.items) {
			const zone = (e.data.acneZone as string)?.trim();
			if (zone) counts[zone] = (counts[zone] || 0) + 1;
		}
		const sorted = Object.entries(counts).toSorted((a, b) => b[1] - a[1]).slice(0, 5);
		const max = sorted.length > 0 ? sorted[0][1] : 1;
		return { zones: sorted, max };
	});

	const skinMoodLink = $derived.by(() => {
		const checkins = allStore.items.filter(e => e.type === 'checkin');
		if (checkins.length < 5 || store.items.length < 5) return null;
		const moodByDate: Record<string, number> = {};
		for (const c of checkins) {
			const d = (c.data.date as string) ?? c.createdAt.slice(0, 10);
			moodByDate[d] = Number(c.data.mood);
		}
		const pairs: { elast: number; mood: number }[] = [];
		for (const e of store.items) {
			const d = e.createdAt.slice(0, 10);
			if (moodByDate[d] !== undefined) pairs.push({ elast: Number(e.data.elasticity), mood: moodByDate[d] });
		}
		if (pairs.length < 3) return null;
		const highElast = pairs.filter(p => p.elast >= 7);
		const lowElast = pairs.filter(p => p.elast < 5);
		if (highElast.length === 0 || lowElast.length === 0) return null;
		const avg = (a: number[]) => +(a.reduce((s, v) => s + v, 0) / a.length).toFixed(1);
		return { highSkinMood: avg(highElast.map(p => p.mood)), lowSkinMood: avg(lowElast.map(p => p.mood)) };
	});

	function submit() {
		entries.add('signal.skin', { acneZone, oiliness, elasticity, healing, notes });
		acneZone = ''; notes = '';
		toast.show(t.skin.skinLogged);
	}
</script>

<svelte:head>
  <title>{t.skin.title} | Darink</title>
</svelte:head>

<PageHeader title={t.skin.title} back="/signals" breadcrumbs={[{ href: "/signals", label: t.more.signals }]} />

<section class="form">
	<label>{t.skin.acneZone} <input type="text" bind:value={acneZone} placeholder={t.skin.acnePlaceholder} /></label>
	<label>{t.skin.oiliness} ({oiliness}/5) <input type="range" min="1" max="5" bind:value={oiliness} /></label>
	<label>{t.skin.elasticity} ({elasticity}/10) <input type="range" min="1" max="10" bind:value={elasticity} /></label>
	<label>{t.skin.healingSpeed} ({healing}/10) <input type="range" min="1" max="10" bind:value={healing} /></label>
	<label>{t.common.notes} <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>{t.skin.logSkin}</button>
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
		toast.show(t.common.updated);
		done();
	}}>
		<label>{t.skin.acneZone} <input type="text" name="acneZone" value={data.acneZone ?? ''} /></label>
		<label>{t.skin.oiliness} <input type="range" name="oiliness" min="1" max="5" value={data.oiliness} /></label>
		<label>{t.skin.elasticity} <input type="range" name="elasticity" min="1" max="10" value={data.elasticity} /></label>
		<label>{t.skin.healingSpeed} <input type="range" name="healing" min="1" max="5" value={data.healing} /></label>
		<label>{t.common.notes} <textarea name="notes" rows="2">{data.notes ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">{t.common.save}</button>
			<button type="button" onclick={done}>{t.common.cancel}</button>
		</div>
	</form>
{/snippet}

{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
	<p>{t.skin.noEntries}</p>
	<p class="empty-hint">{t.skin.noEntriesHint}</p>
</div>
{/if}

<EntryList items={store.items} {editForm} limit={10}>
	{#snippet row(item)}
		<div><span class="date">{new Date(item.createdAt).toLocaleDateString()}</span> <span class="meta">{t.skin.oiliness}: {item.data.oiliness}/5 · {t.skin.elasticity}: {item.data.elasticity}/10{item.data.acneZone ? ` · ${item.data.acneZone}` : ''}</span></div>
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
	<h2>{t.skin.oilElastTrend}</h2>
	<svg class="line-chart" viewBox="0 0 280 100" preserveAspectRatio="none">
		<polyline fill="none" stroke="#ff9800" stroke-width="2" stroke-linejoin="round"
			points={ptsO.map((p, i) => `${i * stepX},${100 - ((p.value - minV) / rangeV) * 80 - 10}`).join(' ')} />
		<polyline fill="none" stroke="var(--c-accent)" stroke-width="2" stroke-linejoin="round"
			points={ptsE.map((p, i) => `${i * stepX},${100 - ((p.value - minV) / rangeV) * 80 - 10}`).join(' ')} />
	</svg>
	<div class="legend">
		<span><span class="dot" style="background:#ff9800"></span> {t.skin.oilX2}</span>
		<span><span class="dot" style="background:var(--c-accent)"></span> {t.skin.elasticity}</span>
	</div>
</section>
{/if}

{#if last30.length > 0}
{@const avgOiliness = (last30.reduce((s, e) => s + Number(e.data.oiliness), 0) / last30.length).toFixed(1)}
{@const avgElasticity = (last30.reduce((s, e) => s + Number(e.data.elasticity), 0) / last30.length).toFixed(1)}
<section class="metrics">
	<h2>{t.skin.averages}</h2>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value">{avgOiliness}</span>
			<span class="metric-label">{t.skin.avgOiliness}</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{avgElasticity}</span>
			<span class="metric-label">{t.skin.avgElasticity}</span>
		</div>
	</div>
</section>
{/if}

{#if hydrationSkinLink}
<section class="analytics">
	<h2>{t.skin.hydrationLink}</h2>
	<p class="hint">{t.skin.hydrationHint}</p>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value" style="color:var(--c-done)">{hydrationSkinLink.highHyd}/10</span>
			<span class="metric-label">{t.skin.highHydration} (n={hydrationSkinLink.highN})</span>
		</div>
		<div class="metric-card">
			<span class="metric-value" style="color:#e8a735">{hydrationSkinLink.lowHyd}/10</span>
			<span class="metric-label">{t.skin.lowHydration} (n={hydrationSkinLink.lowN})</span>
		</div>
	</div>
</section>
{/if}

{#if acneZoneFreq.zones.length > 0}
<section class="analytics">
	<h2>{t.skin.acneFrequency}</h2>
	<div class="zone-bars">
		{#each acneZoneFreq.zones as [zone, count]}
			<div class="zone-row">
				<span class="zone-name">{zone}</span>
				<div class="bar-bg"><div class="bar-fill" style="width:{(count / acneZoneFreq.max) * 100}%;background:#ff9800"></div></div>
				<span class="zone-count">{count}</span>
			</div>
		{/each}
	</div>
</section>
{/if}

{#if skinMoodLink}
<section class="analytics">
	<h2>{t.skin.skinMoodLink}</h2>
	<p class="hint">{t.skin.skinMoodHint}</p>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value" style="color:var(--c-done)">{skinMoodLink.highSkinMood}</span>
			<span class="metric-label">{t.skin.goodSkinDays}</span>
		</div>
		<div class="metric-card">
			<span class="metric-value" style="color:#e8a735">{skinMoodLink.lowSkinMood}</span>
			<span class="metric-label">{t.skin.poorSkinDays}</span>
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
	.zone-bars { display: flex; flex-direction: column; gap: 0.5rem; }
	.zone-row { display: flex; align-items: center; gap: 0.5rem; }
	.zone-name { width: 5rem; font-size: 0.8rem; text-align: right; color: var(--c-text-muted); }
	.zone-count { width: 2rem; font-size: 0.8rem; font-weight: 600; }
	.bar-bg { flex: 1; height: 12px; background: var(--c-border); border-radius: 6px; overflow: hidden; }
	.bar-fill { height: 100%; border-radius: 6px; transition: width 0.3s; }
</style>
