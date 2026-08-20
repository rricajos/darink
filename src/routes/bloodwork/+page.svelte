<script lang="ts">
	import { onMount } from 'svelte';
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { ui } from '$lib/db';
	import type { Entry } from '$lib/db';
	import { findRelevantSupplements, findSuggestedSupplements } from '$lib/utils/marker-supplement-map';
	import { useLocale } from '$lib/stores/locale.svelte';

	const { t } = useLocale();
	const store = useEntries('bloodwork');

	/* --- Marker definitions --- */
	interface Marker { key: string; label: string; unit: string; min: number; max: number }
	interface Category { name: string; markers: Marker[] }

	const categories: Category[] = $derived.by(() => [
		{
			name: t.bloodwork.catHormonal, markers: [
				{ key: 'testosterone', label: 'Testosterone', unit: 'ng/dL', min: 300, max: 1000 },
				{ key: 'freeT', label: 'Free T', unit: 'pg/mL', min: 5, max: 25 },
				{ key: 'estradiol', label: 'Estradiol', unit: 'pg/mL', min: 10, max: 40 },
				{ key: 'cortisol', label: 'Cortisol', unit: 'ug/dL', min: 6, max: 18 },
				{ key: 'tsh', label: 'TSH', unit: 'mIU/L', min: 0.4, max: 4.0 },
				{ key: 'freeT4', label: 'Free T4', unit: 'ng/dL', min: 0.8, max: 1.8 }
			]
		},
		{
			name: t.bloodwork.catMetabolic, markers: [
				{ key: 'glucose', label: 'Glucose', unit: 'mg/dL', min: 70, max: 100 },
				{ key: 'hba1c', label: 'HbA1c', unit: '%', min: 4.0, max: 5.6 },
				{ key: 'totalCholesterol', label: 'Total Cholesterol', unit: 'mg/dL', min: 0, max: 200 },
				{ key: 'ldl', label: 'LDL', unit: 'mg/dL', min: 0, max: 100 },
				{ key: 'hdl', label: 'HDL', unit: 'mg/dL', min: 40, max: 999 },
				{ key: 'triglycerides', label: 'Triglycerides', unit: 'mg/dL', min: 0, max: 150 }
			]
		},
		{
			name: t.bloodwork.catBlood, markers: [
				{ key: 'hemoglobin', label: 'Hemoglobin', unit: 'g/dL', min: 13.5, max: 17.5 },
				{ key: 'hematocrit', label: 'Hematocrit', unit: '%', min: 38, max: 49 },
				{ key: 'ferritin', label: 'Ferritin', unit: 'ng/mL', min: 30, max: 400 },
				{ key: 'platelets', label: 'Platelets', unit: 'K/uL', min: 150, max: 400 }
			]
		},
		{
			name: t.bloodwork.catLiverKidney, markers: [
				{ key: 'alt', label: 'ALT', unit: 'U/L', min: 7, max: 56 },
				{ key: 'ast', label: 'AST', unit: 'U/L', min: 10, max: 40 },
				{ key: 'creatinine', label: 'Creatinine', unit: 'mg/dL', min: 0.7, max: 1.3 },
				{ key: 'egfr', label: 'eGFR', unit: 'mL/min', min: 90, max: 999 }
			]
		},
		{
			name: t.bloodwork.catVitamins, markers: [
				{ key: 'vitaminD', label: 'Vitamin D', unit: 'ng/mL', min: 30, max: 100 },
				{ key: 'b12', label: 'B12', unit: 'pg/mL', min: 200, max: 900 },
				{ key: 'iron', label: 'Iron', unit: 'ug/dL', min: 60, max: 170 },
				{ key: 'zinc', label: 'Zinc', unit: 'ug/dL', min: 60, max: 120 },
				{ key: 'magnesium', label: 'Magnesium', unit: 'mg/dL', min: 1.7, max: 2.2 }
			]
		}
	]);

	const allMarkers = $derived(categories.flatMap((c) => c.markers));
	const markerByKey = $derived(new Map(allMarkers.map((m) => [m.key, m])));

	/* --- Form state --- */
	let date = $state(new Date().toISOString().slice(0, 10));
	let activeCategory = $state(0);
	let lab = $state('');
	let notes = $state('');
	let markerValues = $state<Record<string, number>>({});

	function resetForm() {
		date = new Date().toISOString().slice(0, 10);
		lab = '';
		notes = '';
		markerValues = {};
	}

	function submit() {
		const filled: Record<string, number> = {};
		for (const [key, val] of Object.entries(markerValues)) {
			if (val > 0) filled[key] = val;
		}
		if (Object.keys(filled).length === 0) {
			toast.show(t.bloodwork.enterAtLeast);
			return;
		}
		entries.add('bloodwork', { date, lab: lab.trim(), markers: filled, notes: notes.trim() });
		resetForm();
		toast.show(t.bloodwork.bloodworkLogged);
	}

	/* --- Supplement stack (loaded from ui store) --- */
	let supplementStack = $state<Array<{ name: string }>>([]);

	onMount(() => {
		const saved = ui.get();
		if (Array.isArray(saved.supplementStack)) {
			supplementStack = saved.supplementStack as Array<{ name: string }>;
		}
	});

	/* --- Sorted entries (oldest first for charts) --- */
	const sorted = $derived(
		store.items.toSorted((a, b) => {
			const da = (a.data.date as string) ?? a.createdAt.slice(0, 10);
			const db2 = (b.data.date as string) ?? b.createdAt.slice(0, 10);
			return da.localeCompare(db2);
		})
	);

	const latest = $derived(sorted.length > 0 ? sorted[sorted.length - 1] : null);
	const previous = $derived(sorted.length > 1 ? sorted[sorted.length - 2] : null);

	/* --- Latest results summary --- */
	const latestResults = $derived.by(() => {
		const results: { marker: Marker; value: number; date: string }[] = [];
		const seenKeys = new Set<string>();
		// Walk from newest to oldest to find most recent value for each marker
		for (let i = sorted.length - 1; i >= 0; i--) {
			const entry = sorted[i];
			const markers = entry.data.markers as Record<string, number> | undefined;
			if (!markers) continue;
			const entryDate = (entry.data.date as string) ?? entry.createdAt.slice(0, 10);
			for (const [key, val] of Object.entries(markers)) {
				if (!seenKeys.has(key) && val > 0) {
					const def = markerByKey.get(key);
					if (def) {
						results.push({ marker: def, value: val, date: entryDate });
						seenKeys.add(key);
					}
				}
			}
		}
		return results.sort((a, b) => {
			const ai = allMarkers.findIndex((m) => m.key === a.marker.key);
			const bi = allMarkers.findIndex((m) => m.key === b.marker.key);
			return ai - bi;
		});
	});

	/* --- Color for value vs reference range --- */
	function rangeColor(value: number, min: number, max: number): string {
		if (value >= min && value <= max) return 'var(--c-done)';
		const range = max - min;
		const tolerance = range * 0.1;
		if (value >= min - tolerance && value <= max + tolerance) return '#e8a735';
		return 'var(--c-cancel)';
	}

	function rangeLabel(value: number, min: number, max: number): string {
		if (value >= min && value <= max) return t.bloodwork.normal;
		const range = max - min;
		const tolerance = range * 0.1;
		if (value >= min - tolerance && value <= max + tolerance) return t.bloodwork.borderline;
		return value < min ? t.bloodwork.low : t.bloodwork.high;
	}

	/* --- Trend chart --- */
	let chartMarkerKey = $state('');

	const chartMarker = $derived(chartMarkerKey ? markerByKey.get(chartMarkerKey) ?? null : null);

	const chartData = $derived.by(() => {
		if (!chartMarkerKey) return null;
		const points: { date: string; value: number }[] = [];
		for (const entry of sorted) {
			const markers = entry.data.markers as Record<string, number> | undefined;
			if (!markers) continue;
			const v = markers[chartMarkerKey];
			if (v && v > 0) {
				points.push({
					date: (entry.data.date as string) ?? entry.createdAt.slice(0, 10),
					value: v
				});
			}
		}
		if (points.length === 0) return null;
		return points;
	});

	/* --- Delta comparison --- */
	const deltas = $derived.by(() => {
		if (!latest || !previous) return null;
		const latestMarkers = latest.data.markers as Record<string, number> | undefined;
		const prevMarkers = previous.data.markers as Record<string, number> | undefined;
		if (!latestMarkers || !prevMarkers) return null;
		const result: { marker: Marker; latest: number; previous: number; delta: number }[] = [];
		for (const m of allMarkers) {
			const lv = latestMarkers[m.key];
			const pv = prevMarkers[m.key];
			if (lv && lv > 0 && pv && pv > 0) {
				result.push({ marker: m, latest: lv, previous: pv, delta: +(lv - pv).toFixed(2) });
			}
		}
		return result.length > 0 ? result : null;
	});

	/* --- Entry list helpers --- */
	function countMarkers(entry: Entry): number {
		const markers = entry.data.markers as Record<string, number> | undefined;
		if (!markers) return 0;
		return Object.values(markers).filter((v) => v > 0).length;
	}
</script>

<svelte:head>
	<title>{t.bloodwork.title} | Darink</title>
</svelte:head>

<PageHeader title={t.bloodwork.title} />

<!-- Log Form -->
<section class="form">
	<label>{t.common.date} <input type="date" bind:value={date} /></label>

	<div class="cat-tabs">
		{#each categories as cat, i}
			<button
				class="cat-tab"
				class:active={activeCategory === i}
				onclick={() => activeCategory = i}
			>{cat.name}</button>
		{/each}
	</div>

	<div class="marker-grid">
		{#each categories[activeCategory].markers as m}
			<div class="marker-input">
				<div class="marker-label">
					<span class="marker-name">{m.label}</span>
					<span class="marker-ref">{m.unit} ({m.min}-{m.max})</span>
				</div>
				<input
					type="number"
					step="0.1"
					min="0"
					placeholder="0"
					value={markerValues[m.key] ?? ''}
					oninput={(e) => {
						const v = parseFloat((e.target as HTMLInputElement).value);
						if (!isNaN(v) && v > 0) markerValues[m.key] = v;
						else delete markerValues[m.key];
					}}
				/>
			</div>
		{/each}
	</div>

	<label>{t.bloodwork.labName} <input type="text" bind:value={lab} placeholder={t.bloodwork.labPlaceholder} /></label>
	<label>{t.common.notes} <textarea bind:value={notes} rows="2" placeholder={t.bloodwork.notesPlaceholder}></textarea></label>
	<button class="primary" onclick={submit}>{t.bloodwork.logBloodwork}</button>
</section>

<!-- Latest Results Summary -->
{#if latestResults.length > 0}
<section class="results-section">
	<h2>{t.bloodwork.latestResults}</h2>
	<div class="results-grid">
		{#each latestResults as r}
			{@const outOfRange = r.value < r.marker.min || r.value > r.marker.max}
			{@const taking = outOfRange ? findRelevantSupplements(r.marker.key, supplementStack) : []}
			{@const suggested = outOfRange ? findSuggestedSupplements(r.marker.key).filter((s) => !taking.map((t) => t.toLowerCase()).some((t) => t.includes(s))).slice(0, 2) : []}
			<div class="result-card" style="border-left: 3px solid {rangeColor(r.value, r.marker.min, r.marker.max)}">
				<div class="result-header">
					<span class="result-name">{r.marker.label}</span>
					<span class="result-status" style="color: {rangeColor(r.value, r.marker.min, r.marker.max)}">{rangeLabel(r.value, r.marker.min, r.marker.max)}</span>
				</div>
				<div class="result-value" style="color: {rangeColor(r.value, r.marker.min, r.marker.max)}">{r.value} <span class="result-unit">{r.marker.unit}</span></div>
				<div class="result-ref">{t.bloodwork.ref}: {r.marker.min}-{r.marker.max} {r.marker.unit}</div>
				<div class="result-date">{r.date}</div>
				{#if outOfRange && (taking.length > 0 || suggested.length > 0)}
					<div class="result-supplement">
						{#each taking as name}
							<div class="result-supplement-taking">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--c-done)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M12 4v16"/><path d="M3 12h18"/></svg>
								{t.bloodwork.taking}: {name}
							</div>
						{/each}
						{#each suggested as name}
							<div class="result-supplement-consider">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
								{t.bloodwork.consider}: {name}
							</div>
						{/each}
					</div>
				{/if}
			</div>
		{/each}
	</div>
</section>
{/if}

<!-- Trend Chart -->
{#if sorted.length > 0}
<section class="chart-section">
	<h2>{t.bloodwork.trendChart}</h2>
	<select class="chart-select" bind:value={chartMarkerKey}>
		<option value="">{t.bloodwork.selectMarker}</option>
		{#each categories as cat}
			<optgroup label={cat.name}>
				{#each cat.markers as m}
					<option value={m.key}>{m.label} ({m.unit})</option>
				{/each}
			</optgroup>
		{/each}
	</select>

	{#if chartData && chartData.length > 0 && chartMarker}
		{@const pts = chartData}
		{@const ref = chartMarker}
		{@const values = pts.map((p) => p.value)}
		{@const allVals = [...values, ref.min, ref.max]}
		{@const gMin = Math.min(...allVals)}
		{@const gMax = Math.max(...allVals)}
		{@const range = gMax - gMin || 1}
		{@const pad = range * 0.15}
		{@const adjMin = gMin - pad}
		{@const adjRange = range + pad * 2}
		{@const W = 280}
		{@const H = 120}
		{@const stepX = pts.length > 1 ? (W - 20) / (pts.length - 1) : 0}
		{@const refY1 = H - ((ref.max - adjMin) / adjRange) * (H - 20) - 10}
		{@const refY2 = H - ((ref.min - adjMin) / adjRange) * (H - 20) - 10}
		<svg class="line-chart" viewBox="0 0 {W} {H}" preserveAspectRatio="xMidYMid meet">
			<!-- Reference range band -->
			<rect
				x="10" y={refY1}
				width={W - 20} height={refY2 - refY1}
				fill="var(--c-done)" opacity="0.12" rx="2"
			/>
			<!-- Reference range lines -->
			<line x1="10" y1={refY1} x2={W - 10} y2={refY1} stroke="var(--c-done)" stroke-width="0.5" stroke-dasharray="3,3" />
			<line x1="10" y1={refY2} x2={W - 10} y2={refY2} stroke="var(--c-done)" stroke-width="0.5" stroke-dasharray="3,3" />
			<!-- Data line -->
			{#if pts.length >= 2}
				<polyline
					fill="none"
					stroke="var(--c-accent)"
					stroke-width="2"
					stroke-linejoin="round"
					stroke-linecap="round"
					points={pts.map((p, i) => `${10 + i * stepX},${H - ((p.value - adjMin) / adjRange) * (H - 20) - 10}`).join(' ')}
				/>
			{/if}
			<!-- Data points -->
			{#each pts as p, i}
				{@const cx = 10 + i * stepX}
				{@const cy = H - ((p.value - adjMin) / adjRange) * (H - 20) - 10}
				<circle {cx} {cy} r="3" fill="var(--c-accent)" />
				<text
					x={cx} y={cy - 6}
					text-anchor="middle" font-size="6" fill="var(--c-text)"
				>{p.value}</text>
			{/each}
		</svg>
		<div class="chart-range">
			<span>{pts[0].date.slice(5)}</span>
			<span class="chart-ref-label">ref: {ref.min}-{ref.max}</span>
			<span>{pts[pts.length - 1].date.slice(5)}</span>
		</div>
	{:else if chartMarkerKey}
		<p class="empty-hint">{t.bloodwork.noDataMarker}</p>
	{/if}
</section>
{/if}

<!-- Delta Comparison -->
{#if deltas}
<section class="delta-section">
	<h2>{t.bloodwork.changeLast}</h2>
	<div class="delta-grid">
		{#each deltas as d}
			<div class="delta-card">
				<span class="delta-label">{d.marker.label}</span>
				<span
					class="delta-value"
					class:positive={d.delta > 0}
					class:negative={d.delta < 0}
				>
					{d.delta > 0 ? '+' : ''}{d.delta} {d.marker.unit}
				</span>
				<span class="delta-range">{d.previous} &rarr; {d.latest}</span>
			</div>
		{/each}
	</div>
</section>
{/if}

<!-- Empty State -->
{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
	<p>{t.bloodwork.noBloodwork}</p>
	<p class="empty-hint">{t.bloodwork.noBloodworkHint}</p>
</div>
{/if}

<!-- Entry History -->
{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	{@const existingMarkers = (data.markers ?? {}) as Record<string, number>}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		const updated: Record<string, unknown> = {
			date: fd.get('date') as string,
			lab: (fd.get('lab') as string).trim(),
			notes: (fd.get('notes') as string).trim()
		};
		const markers: Record<string, number> = {};
		for (const m of allMarkers) {
			const v = parseFloat(fd.get(m.key) as string);
			if (!isNaN(v) && v > 0) markers[m.key] = v;
		}
		updated.markers = markers;
		entries.update(item.id, updated);
		toast.show(t.common.updated);
		done();
	}}>
		<label>{t.common.date} <input type="date" name="date" value={data.date ?? ''} /></label>
		<label>{t.bloodwork.labName} <input type="text" name="lab" value={data.lab ?? ''} /></label>
		{#each categories as cat}
			<details class="edit-cat">
				<summary>{cat.name}</summary>
				<div class="edit-marker-grid">
					{#each cat.markers as m}
						<label class="edit-marker">
							<span>{m.label} <span class="meta">({m.unit})</span></span>
							<input type="number" step="0.1" min="0" name={m.key} value={existingMarkers[m.key] ?? ''} />
						</label>
					{/each}
				</div>
			</details>
		{/each}
		<label>{t.common.notes} <textarea name="notes" rows="2">{data.notes ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">{t.common.save}</button>
			<button type="button" onclick={done}>{t.common.cancel}</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		{@const markerCount = countMarkers(item)}
		<div class="bw-row">
			<span class="bw-date">{(item.data.date as string) ?? item.createdAt.slice(0, 10)}</span>
			<span class="bw-count">{markerCount} {markerCount !== 1 ? t.bloodwork.markers : t.bloodwork.marker}</span>
			{#if item.data.lab}
				<span class="bw-lab">{item.data.lab}</span>
			{/if}
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
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }

	/* Category tabs */
	.cat-tabs {
		display: flex;
		gap: 0.25rem;
		flex-wrap: wrap;
	}
	.cat-tab {
		padding: 0.35rem 0.65rem;
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		background: var(--c-bg-card);
		color: var(--c-text-muted);
		font-size: 0.8rem;
		font-weight: 500;
		cursor: pointer;
	}
	.cat-tab.active {
		background: var(--c-accent);
		color: #fff;
		border-color: var(--c-accent);
	}

	/* Marker grid */
	.marker-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
		gap: 0.5rem;
	}
	.marker-input {
		display: flex;
		flex-direction: column;
		gap: 0.2rem;
	}
	.marker-label {
		display: flex;
		flex-direction: column;
		gap: 0;
	}
	.marker-name {
		font-size: 0.85rem;
		font-weight: 600;
	}
	.marker-ref {
		font-size: 0.7rem;
		color: var(--c-text-muted);
	}
	.marker-input input {
		width: 100%;
	}

	/* Latest results */
	.results-section { padding: 1.5rem 1rem 0; }
	.results-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
		gap: 0.5rem;
	}
	.result-card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.6rem 0.75rem;
		display: flex;
		flex-direction: column;
		gap: 0.15rem;
	}
	.result-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
	}
	.result-name {
		font-size: 0.8rem;
		font-weight: 600;
	}
	.result-status {
		font-size: 0.65rem;
		font-weight: 600;
		text-transform: uppercase;
	}
	.result-value {
		font-size: 1.2rem;
		font-weight: 700;
	}
	.result-unit {
		font-size: 0.7rem;
		font-weight: 400;
	}
	.result-ref {
		font-size: 0.65rem;
		color: var(--c-text-muted);
	}
	.result-date {
		font-size: 0.6rem;
		color: var(--c-text-muted);
	}
	.result-supplement {
		display: flex;
		flex-direction: column;
		gap: 0.15rem;
		margin-top: 0.25rem;
		padding-top: 0.25rem;
		border-top: 1px solid var(--c-border);
	}
	.result-supplement-taking {
		display: flex;
		align-items: center;
		gap: 0.3rem;
		font-size: 0.65rem;
		color: var(--c-done);
		font-weight: 500;
	}
	.result-supplement-consider {
		display: flex;
		align-items: center;
		gap: 0.3rem;
		font-size: 0.65rem;
		color: var(--c-text-muted);
	}

	/* Trend chart */
	.chart-section { padding: 1.5rem 1rem 0; }
	.chart-select {
		width: 100%;
		margin-bottom: 0.75rem;
	}
	.line-chart {
		width: 100%;
		height: 140px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}
	.chart-range {
		display: flex;
		justify-content: space-between;
		font-size: 0.7rem;
		color: var(--c-text-muted);
		margin-top: 0.2rem;
		padding: 0 0.25rem;
	}
	.chart-ref-label {
		font-size: 0.65rem;
		color: var(--c-done);
	}

	/* Delta comparison */
	.delta-section { padding: 1.5rem 1rem 0; }
	.delta-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
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
	.delta-label {
		font-size: 0.75rem;
		font-weight: 600;
		color: var(--c-text-muted);
		text-transform: uppercase;
	}
	.delta-value {
		font-size: 1.1rem;
		font-weight: 700;
	}
	.delta-value.positive { color: #e8a735; }
	.delta-value.negative { color: var(--c-done); }
	.delta-range {
		font-size: 0.7rem;
		color: var(--c-text-muted);
	}

	/* Empty state */
	.empty-hint {
		font-size: 0.85rem;
		color: var(--c-text-muted);
	}

	/* Edit form */
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }
	.edit-cat { margin-bottom: 0.25rem; }
	.edit-cat summary {
		font-size: 0.8rem;
		font-weight: 600;
		color: var(--c-text-muted);
		cursor: pointer;
		padding: 0.3rem 0;
	}
	.edit-marker-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
		gap: 0.4rem;
		padding: 0.25rem 0;
	}
	.edit-marker {
		display: flex;
		flex-direction: column;
		gap: 0.1rem;
		font-size: 0.8rem;
	}

	/* Entry list row */
	.bw-row {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		flex-wrap: wrap;
	}
	.bw-date {
		font-size: 0.85rem;
		font-weight: 600;
		color: var(--c-text-muted);
		white-space: nowrap;
	}
	.bw-count {
		font-size: 0.75rem;
		padding: 0.1rem 0.4rem;
		border-radius: 6px;
		background: var(--c-accent-bg);
		color: var(--c-accent);
		white-space: nowrap;
	}
	.bw-lab {
		font-size: 0.8rem;
		color: var(--c-text-muted);
	}
</style>
