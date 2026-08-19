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

	// --- Composite Signal Score ---
	const signalTypesWithData = $derived(
		[sleepLast, skinLast, hairLast, genitalLast].filter(Boolean).length
	);

	const compositeScore = $derived.by(() => {
		if (signalTypesWithData < 2) return null;
		let total = 0;
		let weight = 0;
		if (sleepLast) {
			const hours = Math.max(0, Math.min(10, ((Number(sleepLast.data.hours) - 4) / 4) * 10));
			const quality = Number(sleepLast.data.quality);
			const sleepScore = (hours + quality) / 2;
			total += sleepScore * 0.4;
			weight += 0.4;
		}
		if (skinLast) {
			total += Number(skinLast.data.elasticity) * 0.2;
			weight += 0.2;
		}
		if (hairLast) {
			total += Number(hairLast.data.density) * 0.15;
			weight += 0.15;
		}
		if (genitalLast) {
			total += Number(genitalLast.data.libido) * 0.25;
			weight += 0.25;
		}
		return Math.round((total / weight) * 10);
	});

	function gaugeColor(score: number): string {
		if (score >= 70) return 'var(--c-done)';
		if (score >= 40) return '#e6a817';
		return 'var(--c-cancel)';
	}

	function gaugeArc(score: number): string {
		const startAngle = -225;
		const sweep = (score / 100) * 270;
		const endAngle = startAngle + sweep;
		const r = 40;
		const cx = 50;
		const cy = 50;
		const toRad = (deg: number) => (deg * Math.PI) / 180;
		const x1 = cx + r * Math.cos(toRad(startAngle));
		const y1 = cy + r * Math.sin(toRad(startAngle));
		const x2 = cx + r * Math.cos(toRad(endAngle));
		const y2 = cy + r * Math.sin(toRad(endAngle));
		const largeArc = sweep > 180 ? 1 : 0;
		return `M ${x1} ${y1} A ${r} ${r} 0 ${largeArc} 1 ${x2} ${y2}`;
	}

	function gaugeTrackArc(): string {
		const r = 40;
		const cx = 50;
		const cy = 50;
		const toRad = (deg: number) => (deg * Math.PI) / 180;
		const startAngle = -225;
		const endAngle = startAngle + 270;
		const x1 = cx + r * Math.cos(toRad(startAngle));
		const y1 = cy + r * Math.sin(toRad(startAngle));
		const x2 = cx + r * Math.cos(toRad(endAngle));
		const y2 = cy + r * Math.sin(toRad(endAngle));
		return `M ${x1} ${y1} A ${r} ${r} 0 1 1 ${x2} ${y2}`;
	}

	// --- Cross-Signal Correlation ---
	function pearson(xs: number[], ys: number[]): number | null {
		const n = Math.min(xs.length, ys.length);
		if (n < 5) return null;
		const ax = xs.slice(-n);
		const ay = ys.slice(-n);
		const mx = ax.reduce((s, v) => s + v, 0) / n;
		const my = ay.reduce((s, v) => s + v, 0) / n;
		let num = 0, dx = 0, dy = 0;
		for (let i = 0; i < n; i++) {
			const xd = ax[i] - mx;
			const yd = ay[i] - my;
			num += xd * yd;
			dx += xd * xd;
			dy += yd * yd;
		}
		const denom = Math.sqrt(dx * dy);
		if (denom === 0) return null;
		return num / denom;
	}

	function corrLabel(r: number): string {
		const abs = Math.abs(r);
		if (abs >= 0.7) return 'Strong';
		if (abs >= 0.4) return 'Moderate';
		if (abs >= 0.2) return 'Weak';
		return 'None';
	}

	function corrColor(r: number): string {
		const abs = Math.abs(r);
		if (abs < 0.2) return 'var(--c-text-muted)';
		return r > 0 ? 'var(--c-done)' : 'var(--c-cancel)';
	}

	const sleepQualityVals = $derived(sleepSorted.map(e => Number(e.data.quality)));
	const sleepHoursVals = $derived(sleepSorted.map(e => Number(e.data.hours)));
	const skinElasticityVals = $derived(skinSorted.map(e => Number(e.data.elasticity)));
	const skinOilinessVals = $derived(skinSorted.map(e => Number(e.data.oiliness)));
	const hairDensityVals = $derived(hairSorted.map(e => Number(e.data.density)));
	const genitalLibidoVals = $derived(genitalSorted.map(e => Number(e.data.libido)));

	interface CorrPair {
		labelA: string;
		labelB: string;
		r: number | null;
	}

	const correlationPairs = $derived.by((): CorrPair[] => {
		return [
			{ labelA: 'Sleep Quality', labelB: 'Skin Elasticity', r: pearson(sleepQualityVals, skinElasticityVals) },
			{ labelA: 'Sleep Hours', labelB: 'Libido', r: pearson(sleepHoursVals, genitalLibidoVals) },
			{ labelA: 'Skin Oiliness', labelB: 'Hair Density', r: pearson(skinOilinessVals, hairDensityVals) }
		];
	});

	const hasCorrelations = $derived(correlationPairs.some(p => p.r !== null));

	// --- Trend Alerts ---
	interface TrendAlert {
		label: string;
		direction: 'declining' | 'improving';
	}

	function detectTrend(values: number[]): 'declining' | 'improving' | null {
		if (values.length < 3) return null;
		const last3 = values.slice(-3);
		if (last3[0] > last3[1] && last3[1] > last3[2]) return 'declining';
		if (last3[0] < last3[1] && last3[1] < last3[2]) return 'improving';
		return null;
	}

	const trendAlerts = $derived.by((): TrendAlert[] => {
		const alerts: TrendAlert[] = [];
		const checks: [string, number[]][] = [
			['Sleep', sleepSorted.map(e => Number(e.data.quality))],
			['Skin', skinSorted.map(e => Number(e.data.elasticity))],
			['Hair', hairSorted.map(e => Number(e.data.density))],
			['Genital', genitalSorted.map(e => Number(e.data.libido))]
		];
		for (const [label, vals] of checks) {
			const dir = detectTrend(vals);
			if (dir) alerts.push({ label, direction: dir });
		}
		return alerts;
	});

	// --- Signal Summary Stats ---
	const totalEntries = $derived(
		sleepSorted.length + skinSorted.length + hairSorted.length + genitalSorted.length
	);

	const mostTracked = $derived.by(() => {
		const counts = [
			{ label: 'Sleep', count: sleepSorted.length },
			{ label: 'Skin', count: skinSorted.length },
			{ label: 'Hair', count: hairSorted.length },
			{ label: 'Genital', count: genitalSorted.length }
		];
		const max = counts.reduce((a, b) => (b.count > a.count ? b : a));
		return max.count > 0 ? max.label : 'None';
	});

	const avgFrequency = $derived.by(() => {
		const all = [
			...sleepSorted.map(e => e.createdAt),
			...skinSorted.map(e => e.createdAt),
			...hairSorted.map(e => e.createdAt),
			...genitalSorted.map(e => e.createdAt)
		];
		if (all.length < 2) return 0;
		all.sort();
		const firstMs = new Date(all[0]).getTime();
		const lastMs = new Date(all[all.length - 1]).getTime();
		const weeks = Math.max((lastMs - firstMs) / (7 * 86400000), 1);
		return Math.round((all.length / weeks) * 10) / 10;
	});
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

<!-- Composite Signal Score -->
{#if compositeScore !== null}
<section class="overview">
	<h2>Composite Signal Score</h2>
	<div class="gauge-container">
		<svg class="gauge-svg" viewBox="0 0 100 75">
			<path d={gaugeTrackArc()} fill="none" stroke="var(--c-border)" stroke-width="7" stroke-linecap="round" />
			<path d={gaugeArc(compositeScore)} fill="none" stroke={gaugeColor(compositeScore)} stroke-width="7" stroke-linecap="round" />
			<text x="50" y="52" text-anchor="middle" font-size="18" font-weight="700" fill="currentColor">{compositeScore}</text>
			<text x="50" y="63" text-anchor="middle" font-size="6" fill="var(--c-text-muted)">/100</text>
		</svg>
		<div class="gauge-breakdown">
			{#if sleepLast}
				<span class="gauge-item">Sleep 40%</span>
			{/if}
			{#if skinLast}
				<span class="gauge-item">Skin 20%</span>
			{/if}
			{#if hairLast}
				<span class="gauge-item">Hair 15%</span>
			{/if}
			{#if genitalLast}
				<span class="gauge-item">Genital 25%</span>
			{/if}
		</div>
	</div>
</section>
{/if}

<!-- Trend Alerts -->
{#if trendAlerts.length > 0}
<section class="overview">
	<h2>Trend Alerts</h2>
	<div class="alerts-grid">
		{#each trendAlerts as alert}
			<div class="alert-card" class:alert-declining={alert.direction === 'declining'} class:alert-improving={alert.direction === 'improving'}>
				{#if alert.direction === 'declining'}
					<svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--c-cancel)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>
				{:else}
					<svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--c-done)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
				{/if}
				<div class="alert-text">
					<strong>{alert.label}</strong>
					{#if alert.direction === 'declining'}
						<span>Declining trend detected</span>
					{:else}
						<span>Improving trend</span>
					{/if}
				</div>
			</div>
		{/each}
	</div>
</section>
{/if}

<!-- Cross-Signal Correlation Matrix -->
{#if hasCorrelations}
<section class="overview">
	<h2>Cross-Signal Correlations</h2>
	<div class="corr-grid">
		{#each correlationPairs as pair}
			{#if pair.r !== null}
				<div class="corr-card">
					<div class="corr-labels">
						<span class="corr-signal">{pair.labelA}</span>
						<span class="corr-vs">vs</span>
						<span class="corr-signal">{pair.labelB}</span>
					</div>
					<div class="corr-value" style="color: {corrColor(pair.r)}">
						{pair.r >= 0 ? '+' : ''}{pair.r.toFixed(2)}
					</div>
					<div class="corr-strength" style="color: {corrColor(pair.r)}">
						{corrLabel(pair.r)}
					</div>
				</div>
			{/if}
		{/each}
	</div>
</section>
{/if}

<!-- Signal Summary Stats -->
{#if totalEntries > 0}
<section class="overview summary-section">
	<h2>Signal Summary</h2>
	<div class="summary-grid">
		<div class="summary-card">
			<span class="summary-value">{totalEntries}</span>
			<span class="summary-label">Total entries</span>
		</div>
		<div class="summary-card">
			<span class="summary-value">{mostTracked}</span>
			<span class="summary-label">Most tracked</span>
		</div>
		<div class="summary-card">
			<span class="summary-value">{avgFrequency}/wk</span>
			<span class="summary-label">Avg frequency</span>
		</div>
	</div>
</section>
{/if}

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

	/* Composite Gauge */
	.gauge-container { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
	.gauge-svg { width: 160px; height: 120px; }
	.gauge-breakdown { display: flex; flex-wrap: wrap; gap: 0.4rem; justify-content: center; }
	.gauge-item { font-size: 0.7rem; color: var(--c-text-muted); background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.2rem 0.5rem; }

	/* Trend Alerts */
	.alerts-grid { display: flex; flex-direction: column; gap: 0.5rem; }
	.alert-card { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); }
	.alert-declining { border-color: var(--c-cancel); background: color-mix(in srgb, var(--c-cancel) 6%, var(--c-bg-card)); }
	.alert-improving { border-color: var(--c-done); background: color-mix(in srgb, var(--c-done) 6%, var(--c-bg-card)); }
	.alert-icon { flex-shrink: 0; }
	.alert-text { display: flex; flex-direction: column; gap: 0.1rem; }
	.alert-text strong { font-size: 0.85rem; }
	.alert-text span { font-size: 0.75rem; color: var(--c-text-muted); }

	/* Correlation Matrix */
	.corr-grid { display: grid; grid-template-columns: 1fr; gap: 0.5rem; }
	.corr-card { background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
	.corr-labels { display: flex; align-items: center; gap: 0.3rem; flex: 1; min-width: 0; }
	.corr-signal { font-size: 0.8rem; font-weight: 600; }
	.corr-vs { font-size: 0.7rem; color: var(--c-text-muted); }
	.corr-value { font-size: 1rem; font-weight: 700; font-variant-numeric: tabular-nums; flex-shrink: 0; }
	.corr-strength { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; flex-shrink: 0; }

	/* Signal Summary */
	.summary-section { padding-bottom: 2rem; }
	.summary-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.5rem; }
	.summary-card { background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; text-align: center; display: flex; flex-direction: column; gap: 0.2rem; }
	.summary-value { font-size: 1.1rem; font-weight: 700; }
	.summary-label { font-size: 0.65rem; color: var(--c-text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.03em; }
</style>
