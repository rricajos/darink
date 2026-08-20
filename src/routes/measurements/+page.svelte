<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';
	import { useLocale } from '$lib/stores/locale.svelte';

	const { t } = useLocale();
	const store = useEntries('measurement');

	const today = new Date().toISOString().slice(0, 10);
	let date = $state(today);
	let waist = $state(0);
	let chest = $state(0);
	let hips = $state(0);
	let neck = $state(0);
	let bicepsL = $state(0);
	let bicepsR = $state(0);
	let thighL = $state(0);
	let thighR = $state(0);
	let notes = $state('');

	const bodyParts = ['waist', 'chest', 'hips', 'neck', 'bicepsL', 'bicepsR', 'thighL', 'thighR'] as const;
	const bodyPartLabels = $derived.by(() => ({
		waist: t.measurements.waist, chest: t.measurements.chest, hips: t.measurements.hips, neck: t.measurements.neck,
		bicepsL: t.measurements.bicepsL, bicepsR: t.measurements.bicepsR, thighL: t.measurements.thighL, thighR: t.measurements.thighR
	}) as Record<string, string>);

	const chartColors: Record<string, string> = {
		waist: '#4aa3ff', chest: '#e8a735', hips: '#2e8b57', neck: '#e05577',
		bicepsL: '#9b59b6', bicepsR: '#c084fc', thighL: '#f97316', thighR: '#fb923c'
	};

	function submit() {
		const data: Record<string, unknown> = { date };
		if (waist > 0) data.waist = waist;
		if (chest > 0) data.chest = chest;
		if (hips > 0) data.hips = hips;
		if (neck > 0) data.neck = neck;
		if (bicepsL > 0) data.bicepsL = bicepsL;
		if (bicepsR > 0) data.bicepsR = bicepsR;
		if (thighL > 0) data.thighL = thighL;
		if (thighR > 0) data.thighR = thighR;
		if (notes.trim()) data.notes = notes.trim();

		const hasValues = Object.keys(data).some((k) => k !== 'date' && k !== 'notes');
		if (!hasValues) {
			toast.show(t.measurements.enterAtLeast);
			return;
		}
		entries.add('measurement', data);
		waist = 0; chest = 0; hips = 0; neck = 0;
		bicepsL = 0; bicepsR = 0; thighL = 0; thighR = 0;
		notes = '';
		date = new Date().toISOString().slice(0, 10);
		toast.show(t.measurements.measurementLogged);
	}

	// Sorted history (oldest first for charts)
	const sorted = $derived(
		store.items.toSorted((a, b) => {
			const da = (a.data.date as string) ?? a.createdAt.slice(0, 10);
			const db2 = (b.data.date as string) ?? b.createdAt.slice(0, 10);
			return da.localeCompare(db2);
		})
	);

	const latest = $derived(sorted.length > 0 ? sorted[sorted.length - 1] : null);
	const first = $derived(sorted.length > 0 ? sorted[0] : null);

	// Waist-to-hip ratio
	const whr = $derived.by(() => {
		if (!latest) return null;
		const w = latest.data.waist as number | undefined;
		const h = latest.data.hips as number | undefined;
		if (!w || !h || h === 0) return null;
		return +(w / h).toFixed(3);
	});

	// Delta since first
	const deltaFirst = $derived.by(() => {
		if (!latest || !first || latest.id === first.id) return null;
		const result: { part: string; first: number; latest: number; delta: number }[] = [];
		for (const p of bodyParts) {
			const fv = first.data[p] as number | undefined;
			const lv = latest.data[p] as number | undefined;
			if (fv && lv) {
				result.push({ part: p, first: fv, latest: lv, delta: +(lv - fv).toFixed(1) });
			}
		}
		return result.length > 0 ? result : null;
	});

	// Last 30 days change
	const delta30 = $derived.by(() => {
		if (sorted.length < 2) return null;
		const latestEntry = sorted[sorted.length - 1];
		const latestDate = (latestEntry.data.date as string) ?? latestEntry.createdAt.slice(0, 10);
		const cutoff = new Date(latestDate);
		cutoff.setDate(cutoff.getDate() - 30);
		const cutoffStr = cutoff.toISOString().slice(0, 10);

		// Find closest entry to 30 days ago
		let closest: Entry | null = null;
		let closestDist = Infinity;
		for (const e of sorted) {
			if (e.id === latestEntry.id) continue;
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			const dist = Math.abs(new Date(d).getTime() - cutoff.getTime());
			if (d <= latestDate && dist < closestDist) {
				closest = e;
				closestDist = dist;
			}
		}
		if (!closest) return null;

		const result: { part: string; old: number; latest: number; delta: number }[] = [];
		for (const p of bodyParts) {
			const ov = closest.data[p] as number | undefined;
			const lv = latestEntry.data[p] as number | undefined;
			if (ov && lv) {
				result.push({ part: p, old: ov, latest: lv, delta: +(lv - ov).toFixed(1) });
			}
		}
		return result.length > 0 ? result : null;
	});

	// Chart toggles
	let chartVisible = $state<Record<string, boolean>>({
		waist: true, chest: true, hips: true, neck: false,
		bicepsL: false, bicepsR: false, thighL: false, thighR: false
	});

	// Chart data
	const chartData = $derived.by(() => {
		if (sorted.length < 2) return null;
		const activeParts = bodyParts.filter((p) => chartVisible[p]);
		if (activeParts.length === 0) return null;

		// Filter entries that have at least one active part
		const pts = sorted.filter((e) => activeParts.some((p) => (e.data[p] as number) > 0));
		if (pts.length < 2) return null;

		// Find global min/max across all active parts
		let gMin = Infinity;
		let gMax = -Infinity;
		for (const e of pts) {
			for (const p of activeParts) {
				const v = e.data[p] as number | undefined;
				if (v && v > 0) {
					if (v < gMin) gMin = v;
					if (v > gMax) gMax = v;
				}
			}
		}
		const range = gMax - gMin || 1;
		const padding = range * 0.1;
		const adjMin = gMin - padding;
		const adjRange = range + padding * 2;

		return { pts, activeParts, adjMin, adjRange };
	});
</script>

<svelte:head>
	<title>{t.measurements.title} | Darink</title>
</svelte:head>

<PageHeader title={t.measurements.title} />

<section class="form">
	<label>{t.common.date} <input type="date" bind:value={date} /></label>
	<div class="row">
		<label>{t.measurements.waist} (cm) <input type="number" step="0.1" bind:value={waist} min="0" /></label>
		<label>{t.measurements.chest} (cm) <input type="number" step="0.1" bind:value={chest} min="0" /></label>
	</div>
	<div class="row">
		<label>{t.measurements.hips} (cm) <input type="number" step="0.1" bind:value={hips} min="0" /></label>
		<label>{t.measurements.neck} (cm) <input type="number" step="0.1" bind:value={neck} min="0" /></label>
	</div>
	<div class="row">
		<label>{t.measurements.bicepsL} (cm) <input type="number" step="0.1" bind:value={bicepsL} min="0" /></label>
		<label>{t.measurements.bicepsR} (cm) <input type="number" step="0.1" bind:value={bicepsR} min="0" /></label>
	</div>
	<div class="row">
		<label>{t.measurements.thighL} (cm) <input type="number" step="0.1" bind:value={thighL} min="0" /></label>
		<label>{t.measurements.thighR} (cm) <input type="number" step="0.1" bind:value={thighR} min="0" /></label>
	</div>
	<label>{t.common.notes} <textarea bind:value={notes} rows="2" placeholder={t.measurements.contextPlaceholder}></textarea></label>
	<button class="primary" onclick={submit}>{t.measurements.logMeasurement}</button>
</section>

<!-- Summary cards -->
{#if sorted.length > 0}
<section class="metrics">
	<h2>{t.measurements.summary}</h2>
	<div class="metrics-row">
		{#if whr !== null}
			<div class="metric-card">
				<span class="metric-value">{whr}</span>
				<span class="metric-label">{t.measurements.waistHip}</span>
				<span class="metric-sub">&lt;0.85 (W) / &lt;0.90 (M) = healthy</span>
				{#if whr < 0.85}
					<span class="metric-sub" style="color: var(--c-done)">{t.measurements.healthyRange}</span>
				{:else if whr < 0.90}
					<span class="metric-sub" style="color: #e8a735">{t.measurements.moderateRange}</span>
				{:else}
					<span class="metric-sub" style="color: var(--c-cancel)">{t.measurements.elevated}</span>
				{/if}
			</div>
		{/if}
		{#if latest}
			<div class="metric-card">
				<span class="metric-value">{sorted.length}</span>
				<span class="metric-label">{t.measurements.totalEntries}</span>
				<span class="metric-sub">{(latest.data.date as string) ?? latest.createdAt.slice(0, 10)}</span>
			</div>
		{/if}
	</div>
</section>
{/if}

<!-- Delta since first -->
{#if deltaFirst}
<section class="metrics">
	<h2>{t.measurements.changeSinceFirst}</h2>
	<div class="delta-grid">
		{#each deltaFirst as d}
			<div class="delta-card">
				<span class="delta-label">{bodyPartLabels[d.part]}</span>
				<span class="delta-value" class:positive={d.delta > 0} class:negative={d.delta < 0}>
					{d.delta > 0 ? '+' : ''}{d.delta} cm
				</span>
				<span class="delta-range">{d.first} &rarr; {d.latest}</span>
			</div>
		{/each}
	</div>
</section>
{/if}

<!-- Last 30 days change -->
{#if delta30}
<section class="metrics">
	<h2>{t.measurements.last30days}</h2>
	<div class="delta-grid">
		{#each delta30 as d}
			<div class="delta-card">
				<span class="delta-label">{bodyPartLabels[d.part]}</span>
				<span class="delta-value" class:positive={d.delta > 0} class:negative={d.delta < 0}>
					{d.delta > 0 ? '+' : ''}{d.delta} cm
				</span>
				<span class="delta-range">{d.old} &rarr; {d.latest}</span>
			</div>
		{/each}
	</div>
</section>
{/if}

<!-- Multi-line chart -->
{#if sorted.length >= 2}
<section class="chart-section">
	<h2>{t.measurements.trends}</h2>
	<div class="chart-toggles">
		{#each bodyParts as part}
			<label class="toggle-chip">
				<input type="checkbox" bind:checked={chartVisible[part]} />
				<span class="toggle-dot" style="background: {chartColors[part]}"></span>
				{bodyPartLabels[part]}
			</label>
		{/each}
	</div>
	{#if chartData}
		{@const cd = chartData}
		{@const stepX = 280 / Math.max(cd.pts.length - 1, 1)}
		<svg class="line-chart" viewBox="0 0 280 100" preserveAspectRatio="none">
			{#each cd.activeParts as part}
				{@const points = cd.pts
					.map((e, i) => {
						const v = e.data[part] as number | undefined;
						if (!v || v <= 0) return null;
						return { x: i * stepX, y: 100 - ((v - cd.adjMin) / cd.adjRange) * 80 - 10 };
					})
					.filter((p) => p !== null)}
				{#if points.length >= 2}
					<polyline
						fill="none"
						stroke={chartColors[part]}
						stroke-width="1.8"
						stroke-linejoin="round"
						points={points.map((p) => `${p.x},${p.y}`).join(' ')}
					/>
					{#each points as p}
						<circle cx={p.x} cy={p.y} r="2" fill={chartColors[part]} />
					{/each}
				{/if}
			{/each}
		</svg>
		<div class="chart-range">
			<span>{(cd.pts[0].data.date as string)?.slice(5) ?? ''}</span>
			<span>{(cd.pts[cd.pts.length - 1].data.date as string)?.slice(5) ?? ''}</span>
		</div>
	{/if}
</section>
{/if}

<!-- Entry history -->
{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		const updated: Record<string, unknown> = { date: fd.get('date') as string };
		const fields = ['waist', 'chest', 'hips', 'neck', 'bicepsL', 'bicepsR', 'thighL', 'thighR'] as const;
		for (const f of fields) {
			const v = parseFloat(fd.get(f) as string);
			if (v > 0) updated[f] = v;
		}
		const n = (fd.get('notes') as string)?.trim();
		if (n) updated.notes = n;
		entries.update(item.id, updated);
		toast.show(t.common.updated);
		done();
	}}>
		<label>{t.common.date} <input type="date" name="date" value={data.date ?? ''} /></label>
		<div class="row">
			<label>{t.measurements.waist} <input type="number" step="0.1" name="waist" value={data.waist ?? 0} min="0" /></label>
			<label>{t.measurements.chest} <input type="number" step="0.1" name="chest" value={data.chest ?? 0} min="0" /></label>
		</div>
		<div class="row">
			<label>{t.measurements.hips} <input type="number" step="0.1" name="hips" value={data.hips ?? 0} min="0" /></label>
			<label>{t.measurements.neck} <input type="number" step="0.1" name="neck" value={data.neck ?? 0} min="0" /></label>
		</div>
		<div class="row">
			<label>{t.measurements.bicepsL} <input type="number" step="0.1" name="bicepsL" value={data.bicepsL ?? 0} min="0" /></label>
			<label>{t.measurements.bicepsR} <input type="number" step="0.1" name="bicepsR" value={data.bicepsR ?? 0} min="0" /></label>
		</div>
		<div class="row">
			<label>{t.measurements.thighL} <input type="number" step="0.1" name="thighL" value={data.thighL ?? 0} min="0" /></label>
			<label>{t.measurements.thighR} <input type="number" step="0.1" name="thighR" value={data.thighR ?? 0} min="0" /></label>
		</div>
		<label>{t.common.notes} <textarea name="notes" rows="2">{data.notes ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">{t.common.save}</button>
			<button type="button" onclick={done}>{t.common.cancel}</button>
		</div>
	</form>
{/snippet}

{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Z"/><path d="M12 6v6l4 2"/></svg>
	<p>{t.measurements.noMeasurements}</p>
	<p class="empty-hint">{t.measurements.noMeasurementsHint}</p>
</div>
{/if}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div class="meas-row">
			<span class="meas-date">{(item.data.date as string) ?? item.createdAt.slice(0, 10)}</span>
			<span class="meas-parts">
				{#if item.data.waist}<span class="meas-tag">W {item.data.waist}</span>{/if}
				{#if item.data.chest}<span class="meas-tag">C {item.data.chest}</span>{/if}
				{#if item.data.hips}<span class="meas-tag">H {item.data.hips}</span>{/if}
				{#if item.data.neck}<span class="meas-tag">N {item.data.neck}</span>{/if}
				{#if item.data.bicepsL}<span class="meas-tag">BL {item.data.bicepsL}</span>{/if}
				{#if item.data.bicepsR}<span class="meas-tag">BR {item.data.bicepsR}</span>{/if}
				{#if item.data.thighL}<span class="meas-tag">TL {item.data.thighL}</span>{/if}
				{#if item.data.thighR}<span class="meas-tag">TR {item.data.thighR}</span>{/if}
			</span>
		</div>
	{/snippet}
</EntryList>

<style>
	h2 {
		font-size: 0.9rem;
		font-weight: 600;
		margin-bottom: 0.5rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}

	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.75rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }

	.metrics { padding: 1.5rem 1rem 0; }
	.metrics-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.metric-card {
		flex: 1;
		min-width: 100px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem;
		text-align: center;
		display: flex;
		flex-direction: column;
		gap: 0.15rem;
	}
	.metric-value { font-size: 1.4rem; font-weight: 700; }
	.metric-label { font-size: 0.75rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; }
	.metric-sub { font-size: 0.65rem; color: var(--c-text-muted); }

	.delta-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
		gap: 0.5rem;
	}
	.delta-card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.6rem;
		text-align: center;
		display: flex;
		flex-direction: column;
		gap: 0.1rem;
	}
	.delta-label { font-size: 0.75rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; }
	.delta-value { font-size: 1.1rem; font-weight: 700; }
	.delta-value.positive { color: #e8a735; }
	.delta-value.negative { color: var(--c-done); }
	.delta-range { font-size: 0.7rem; color: var(--c-text-muted); }

	.chart-section { padding: 1.5rem 1rem 0; }
	.chart-toggles {
		display: flex;
		flex-wrap: wrap;
		gap: 0.35rem;
		margin-bottom: 0.75rem;
	}
	.toggle-chip {
		display: inline-flex;
		align-items: center;
		gap: 0.3rem;
		flex-direction: row;
		font-size: 0.75rem;
		font-weight: 500;
		padding: 0.2rem 0.5rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: 12px;
		cursor: pointer;
	}
	.toggle-chip input[type="checkbox"] { width: auto; margin: 0; accent-color: var(--c-accent); }
	.toggle-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

	.line-chart {
		width: 100%;
		height: 120px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.5rem;
	}
	.chart-range {
		display: flex;
		justify-content: space-between;
		font-size: 0.7rem;
		color: var(--c-text-muted);
		margin-top: 0.2rem;
		padding: 0 0.25rem;
	}

	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }

	.meas-row { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
	.meas-date { font-size: 0.85rem; font-weight: 600; color: var(--c-text-muted); white-space: nowrap; }
	.meas-parts { display: flex; flex-wrap: wrap; gap: 0.25rem; }
	.meas-tag {
		font-size: 0.7rem;
		padding: 0.1rem 0.35rem;
		border-radius: 6px;
		background: var(--c-accent-bg);
		color: var(--c-accent);
		white-space: nowrap;
	}
</style>
