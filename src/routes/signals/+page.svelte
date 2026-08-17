<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { useEntries } from '$lib/stores/entries.svelte';
	import type { Entry } from '$lib/db';

	const types = [
		{ href: '/signals/sleep', label: 'Sleep', desc: 'Hours, quality, dreams, wake time', icon: 'moon' },
		{ href: '/signals/skin', label: 'Skin', desc: 'Acne zones, oiliness, elasticity', icon: 'skin' },
		{ href: '/signals/hair', label: 'Hair', desc: 'Density, shedding, miniaturization', icon: 'hair' },
		{ href: '/signals/genital', label: 'Genital', desc: 'Erections, libido, sensitivity', icon: 'genital' }
	];

	const sleepStore = useEntries('signal.sleep');
	const skinStore = useEntries('signal.skin');
	const hairStore = useEntries('signal.hair');
	const genitalStore = useEntries('signal.genital');

	const sleepSorted = $derived(sleepStore.items.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt)));
	const skinSorted = $derived(skinStore.items.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt)));
	const hairSorted = $derived(hairStore.items.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt)));
	const genitalSorted = $derived(genitalStore.items.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt)));

	const sleepLast = $derived(sleepSorted.at(-1));
	const skinLast = $derived(skinSorted.at(-1));
	const hairLast = $derived(hairSorted.at(-1));
	const genitalLast = $derived(genitalSorted.at(-1));

	const hasAnyData = $derived(sleepLast || skinLast || hairLast || genitalLast);

	// Sparkline data: last 14 entries
	const sleepSparkline = $derived(sleepSorted.slice(-14).map(e => Number(e.data.hours)));
	const skinSparkline = $derived(skinSorted.slice(-14).map(e => Number(e.data.elasticity)));
	const hairSparkline = $derived(hairSorted.slice(-14).map(e => Number(e.data.density)));
	const genitalSparkline = $derived(genitalSorted.slice(-14).map(e => Number(e.data.libido)));

	// Weekly coverage
	const weekAgo = $derived(new Date(Date.now() - 7 * 86400000).toISOString());
	const sleepWeek = $derived(sleepSorted.filter(e => e.createdAt >= weekAgo).length);
	const skinWeek = $derived(skinSorted.filter(e => e.createdAt >= weekAgo).length);
	const hairWeek = $derived(hairSorted.filter(e => e.createdAt >= weekAgo).length);
	const genitalWeek = $derived(genitalSorted.filter(e => e.createdAt >= weekAgo).length);

	function sparklinePath(values: number[]): string {
		if (values.length < 2) return '';
		const min = Math.min(...values);
		const max = Math.max(...values);
		const range = max - min || 1;
		const stepX = 60 / Math.max(values.length - 1, 1);
		return values.map((v, i) => `${i * stepX},${18 - ((v - min) / range) * 16}`).join(' ');
	}

	function fmtDate(iso: string): string {
		return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
	}
</script>

<svelte:head>
  <title>Signals | Darink</title>
</svelte:head>

<PageHeader title="Body Signals" />

<section class="grid">
	{#each types as t}
		<a href={t.href} class="card">
			<div class="card-header">
				{#if t.icon === 'moon'}
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
				{:else if t.icon === 'skin'}
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
				{:else if t.icon === 'hair'}
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/></svg>
				{:else if t.icon === 'genital'}
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
				{/if}
				<strong>{t.label}</strong>
			</div>
			<span>{t.desc}</span>
		</a>
	{/each}
</section>

{#if !hasAnyData}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h6"/><path d="M22 12h-6"/><path d="M12 2v6"/><path d="M12 22v-6"/><circle cx="12" cy="12" r="4"/></svg>
	<p>No body signals tracked yet</p>
	<p class="empty-hint">Track body signals to monitor your health over time.</p>
</div>
{/if}

<!-- Latest Values Summary -->
{#if hasAnyData}
<section class="overview">
	<h2>Latest Values</h2>
	<div class="metrics-grid">
		{#if sleepLast}
			{@const d = sleepLast.data}
			<a href="/signals/sleep" class="metric-card">
				<span class="metric-value">{d.hours}h</span>
				<span class="metric-sub">Q{d.quality}/10</span>
				<span class="metric-label">{fmtDate(sleepLast.createdAt)}</span>
			</a>
		{/if}
		{#if skinLast}
			{@const d = skinLast.data}
			<a href="/signals/skin" class="metric-card">
				<span class="metric-value">Oil {d.oiliness}/5</span>
				<span class="metric-sub">Elast {d.elasticity}/10</span>
				<span class="metric-label">{fmtDate(skinLast.createdAt)}</span>
			</a>
		{/if}
		{#if hairLast}
			{@const d = hairLast.data}
			<a href="/signals/hair" class="metric-card">
				<span class="metric-value">Dens {d.density}/10</span>
				<span class="metric-sub">Shed {d.shedding}/10</span>
				<span class="metric-label">{fmtDate(hairLast.createdAt)}</span>
			</a>
		{/if}
		{#if genitalLast}
			{@const d = genitalLast.data}
			<a href="/signals/genital" class="metric-card">
				<span class="metric-value">Lib {d.libido}/10</span>
				<span class="metric-sub">Sens {d.sensitivity}/10 · ME {d.morningErection}/3</span>
				<span class="metric-label">{fmtDate(genitalLast.createdAt)}</span>
			</a>
		{/if}
	</div>
</section>
{/if}

<!-- Sparkline Trends -->
{#if sleepSparkline.length > 3 || skinSparkline.length > 3 || hairSparkline.length > 3 || genitalSparkline.length > 3}
<section class="overview">
	<h2>Trends (last 14)</h2>
	<div class="sparkline-grid">
		{#if sleepSparkline.length > 3}
			{@const path = sparklinePath(sleepSparkline)}
			<div class="sparkline-card">
				<span class="sparkline-label">Sleep (hours)</span>
				<svg class="sparkline" viewBox="0 0 60 20">
					<polyline fill="none" stroke="var(--c-accent)" stroke-width="1.5" stroke-linejoin="round" points={path} />
				</svg>
			</div>
		{/if}
		{#if skinSparkline.length > 3}
			{@const path = sparklinePath(skinSparkline)}
			<div class="sparkline-card">
				<span class="sparkline-label">Skin (elasticity)</span>
				<svg class="sparkline" viewBox="0 0 60 20">
					<polyline fill="none" stroke="var(--c-accent)" stroke-width="1.5" stroke-linejoin="round" points={path} />
				</svg>
			</div>
		{/if}
		{#if hairSparkline.length > 3}
			{@const path = sparklinePath(hairSparkline)}
			<div class="sparkline-card">
				<span class="sparkline-label">Hair (density)</span>
				<svg class="sparkline" viewBox="0 0 60 20">
					<polyline fill="none" stroke="var(--c-accent)" stroke-width="1.5" stroke-linejoin="round" points={path} />
				</svg>
			</div>
		{/if}
		{#if genitalSparkline.length > 3}
			{@const path = sparklinePath(genitalSparkline)}
			<div class="sparkline-card">
				<span class="sparkline-label">Genital (libido)</span>
				<svg class="sparkline" viewBox="0 0 60 20">
					<polyline fill="none" stroke="var(--c-accent)" stroke-width="1.5" stroke-linejoin="round" points={path} />
				</svg>
			</div>
		{/if}
	</div>
</section>
{/if}

<!-- Weekly Signal Coverage -->
<section class="overview">
	<h2>This Week</h2>
	<div class="coverage-row">
		{#each [
			{ label: 'Sleep', count: sleepWeek },
			{ label: 'Skin', count: skinWeek },
			{ label: 'Hair', count: hairWeek },
			{ label: 'Genital', count: genitalWeek }
		] as sig}
			<span class="chip" class:chip-done={sig.count > 0}>
				{#if sig.count > 0}
					<span class="chip-icon done-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
				{:else}
					<span class="chip-icon miss-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></span>
				{/if}
				{sig.label} ({sig.count}/7)
			</span>
		{/each}
	</div>
</section>

<style>
	.grid { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.card { display: flex; flex-direction: column; padding: 1rem; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); text-decoration: none; color: var(--c-text); transition: border-color 0.15s; }
	.card:hover { border-color: var(--c-accent); }
	.card span { font-size: 0.85rem; color: var(--c-text-muted); }
	.card-header { display: flex; align-items: center; gap: 0.5rem; }

	.overview { padding: 1.5rem 1rem 0; }
	h2 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

	/* Latest Values */
	.metrics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
	.metric-card { background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; text-align: center; display: flex; flex-direction: column; gap: 0.15rem; text-decoration: none; color: var(--c-text); transition: border-color 0.15s; }
	.metric-card:hover { border-color: var(--c-accent); }
	.metric-value { font-size: 1.1rem; font-weight: 700; }
	.metric-sub { font-size: 0.8rem; color: var(--c-text-muted); }
	.metric-label { font-size: 0.7rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; }

	/* Sparklines */
	.sparkline-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
	.sparkline-card { background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.5rem 0.75rem; display: flex; align-items: center; gap: 0.5rem; }
	.sparkline-label { font-size: 0.75rem; color: var(--c-text-muted); white-space: nowrap; flex-shrink: 0; }
	.sparkline { width: 60px; height: 20px; flex-shrink: 0; }

	/* Coverage */
	.coverage-row { display: flex; flex-wrap: wrap; gap: 0.5rem; }
	.chip { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.35rem 0.65rem; border-radius: var(--radius); font-size: 0.8rem; font-weight: 500; background: var(--c-bg-card); border: 1px solid var(--c-border); color: var(--c-text-muted); }
	.chip-done { border-color: var(--c-done); color: var(--c-text); }
	.chip-icon { display: inline-flex; align-items: center; line-height: 1; }
	.done-icon { color: var(--c-done); }
	.miss-icon { color: var(--c-text-muted); opacity: 0.5; }
</style>
