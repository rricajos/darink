<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { ui } from '$lib/db';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { useLocale } from '$lib/stores/locale.svelte';
	import { onMount } from 'svelte';

	const { t } = useLocale();

	let height = $state(175);
	let weight = $state(88);
	let age = $state(25);
	let bodyFat = $state(14);
	let stressBaseline = $state(7);
	let sleepTarget = $state('22:00');
	let wakeTarget = $state('06:00');
	let notes = $state('');
	let activityLevel = $state(1.55);
	let targetWeight = $state(0);

	const activityOptions = $derived.by(() => [
		{ value: 1.2, label: t.profile.sedentary, desc: t.profile.sedentaryDesc },
		{ value: 1.375, label: t.profile.light, desc: t.profile.lightDesc },
		{ value: 1.55, label: t.profile.moderateLevel, desc: t.profile.moderateDesc },
		{ value: 1.725, label: t.profile.active, desc: t.profile.activeDesc },
		{ value: 1.9, label: t.profile.veryActive, desc: t.profile.veryActiveDesc }
	]);

	const weightStore = useEntries('weight');
	const intakeStore = useEntries('intake');
	const measurementStore = useEntries('measurement');

	const latestMeasurement = $derived.by(() => {
		const items = measurementStore.items;
		if (items.length === 0) return null;
		return items.toSorted((a, b) => {
			const da = (a.data.date as string) ?? a.createdAt.slice(0, 10);
			const db2 = (b.data.date as string) ?? b.createdAt.slice(0, 10);
			return db2.localeCompare(da);
		})[0];
	});

	onMount(() => {
		const profile = ui.get().profile as Record<string, any> | undefined;
		if (profile) {
			height = profile.height ?? height;
			weight = profile.weight ?? weight;
			age = profile.age ?? age;
			bodyFat = profile.bodyFat ?? bodyFat;
			stressBaseline = profile.stressBaseline ?? stressBaseline;
			sleepTarget = profile.sleepTarget ?? sleepTarget;
			wakeTarget = profile.wakeTarget ?? wakeTarget;
			notes = profile.notes ?? notes;
			activityLevel = profile.activityLevel ?? activityLevel;
			targetWeight = profile.targetWeight ?? targetWeight;
		}
	});

	// Calculated metrics
	const bmi = $derived(weight > 0 && height > 0 ? +(weight / ((height / 100) ** 2)).toFixed(1) : 0);
	const bmiCategory = $derived.by(() => {
		if (bmi < 18.5) return t.profile.underweight;
		if (bmi < 25) return t.profile.normal;
		if (bmi < 30) return t.profile.overweight;
		return t.profile.obese;
	});
	const bmr = $derived(weight > 0 && height > 0 && age > 0
		? Math.round(10 * weight + 6.25 * height - 5 * age + 5)
		: 0);
	const tdee = $derived(bmr > 0 ? Math.round(bmr * activityLevel) : 0);
	const activityLabel = $derived(activityOptions.find((o) => o.value === activityLevel)?.label ?? t.profile.moderateLevel);

	// Weight history (last 30 entries for existing charts)
	const weightHistory = $derived.by(() => {
		return weightStore.items
			.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt))
			.slice(-30)
			.map((e) => ({
				date: (e.data.date as string) ?? e.createdAt.slice(0, 10),
				weight: e.data.weight as number,
				bodyFat: (e.data.bodyFat as number) ?? null
			}));
	});

	// Extended weight history (last 60 entries for body composition timeline)
	const weightHistory60 = $derived.by(() => {
		return weightStore.items
			.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt))
			.slice(-60)
			.map((e) => ({
				date: (e.data.date as string) ?? e.createdAt.slice(0, 10),
				weight: e.data.weight as number,
				bodyFat: (e.data.bodyFat as number) ?? null
			}));
	});

	// Goal progress calculations
	const goalProgress = $derived.by(() => {
		if (targetWeight <= 0 || weightHistory.length < 2) return null;
		const currentW = weightHistory[weightHistory.length - 1].weight;
		const firstW = weightHistory[0].weight;
		const firstDate = new Date(weightHistory[0].date);
		const lastDate = new Date(weightHistory[weightHistory.length - 1].date);
		const daysDiff = Math.max((lastDate.getTime() - firstDate.getTime()) / (1000 * 60 * 60 * 24), 1);
		const weeksDiff = daysDiff / 7;
		const ratePerWeek = weeksDiff > 0 ? +((currentW - firstW) / weeksDiff).toFixed(2) : 0;
		const remaining = targetWeight - currentW;
		const weeksToGoal = ratePerWeek !== 0 ? remaining / ratePerWeek : null;
		let estimatedDate: string | null = null;
		if (weeksToGoal !== null && weeksToGoal > 0 && weeksToGoal < 520) {
			const est = new Date(lastDate.getTime() + weeksToGoal * 7 * 24 * 60 * 60 * 1000);
			estimatedDate = est.toISOString().slice(0, 10);
		}
		// Determine starting point for progress calculation
		const startW = firstW;
		const totalChange = targetWeight - startW;
		const currentChange = currentW - startW;
		const progressPct = totalChange !== 0 ? Math.min(Math.max((currentChange / totalChange) * 100, 0), 100) : 0;
		// Status: green if moving toward goal, yellow if stalling, red if moving away
		let status: 'green' | 'yellow' | 'red' = 'yellow';
		if (targetWeight < startW) {
			// Trying to lose weight
			if (ratePerWeek < -0.1) status = 'green';
			else if (ratePerWeek > 0.1) status = 'red';
		} else {
			// Trying to gain weight
			if (ratePerWeek > 0.1) status = 'green';
			else if (ratePerWeek < -0.1) status = 'red';
		}
		return { currentW, targetWeight, progressPct, ratePerWeek, estimatedDate, status, remaining };
	});

	// Daily average calories from intake entries (last 30 days)
	const dailyAvgCalories = $derived.by(() => {
		const items = intakeStore.items;
		if (items.length === 0) return null;
		// Check if any intake has a calories field
		const withCalories = items.filter((e) => typeof e.data.calories === 'number' && (e.data.calories as number) > 0);
		if (withCalories.length === 0) return null;
		const now = new Date();
		const thirtyDaysAgo = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
		const recent = withCalories.filter((e) => {
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			return d >= thirtyDaysAgo;
		});
		if (recent.length === 0) return null;
		// Group by day and sum
		const byDay = new Map<string, number>();
		for (const e of recent) {
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			byDay.set(d, (byDay.get(d) ?? 0) + (e.data.calories as number));
		}
		const days = byDay.size;
		const total = [...byDay.values()].reduce((s, v) => s + v, 0);
		return days > 0 ? Math.round(total / days) : null;
	});

	// Body metrics summary cards data
	const metricsCards = $derived.by(() => {
		const cards: Array<{ label: string; value: string; sub: string; trend: string; color: string }> = [];
		// BMI
		const bmiColor = bmi < 18.5 ? 'var(--c-accent)' : bmi < 25 ? '#38a169' : bmi < 30 ? '#e8a735' : '#e53e3e';
		cards.push({ label: 'BMI', value: bmi > 0 ? String(bmi) : '--', sub: bmiCategory, trend: '', color: bmiColor });
		// BMR
		cards.push({ label: 'BMR', value: bmr > 0 ? `${bmr}` : '--', sub: 'kcal/day', trend: '', color: 'var(--c-text)' });
		// TDEE
		cards.push({ label: 'TDEE', value: tdee > 0 ? `${tdee}` : '--', sub: 'kcal/day', trend: '', color: 'var(--c-text)' });
		// Weight change (last 30 days)
		if (weightHistory.length >= 2) {
			const first = weightHistory[0].weight;
			const last = weightHistory[weightHistory.length - 1].weight;
			const diff = +(last - first).toFixed(1);
			const arrow = diff > 0.1 ? '↑' : diff < -0.1 ? '↓' : '→';
			const col = diff > 0.1 ? '#e53e3e' : diff < -0.1 ? '#38a169' : '#e8a735';
			cards.push({ label: 'Weight Δ', value: `${diff > 0 ? '+' : ''}${diff} kg`, sub: 'last 30 entries', trend: arrow, color: col });
		} else {
			cards.push({ label: 'Weight Δ', value: '--', sub: 'last 30 entries', trend: '→', color: 'var(--c-text-muted)' });
		}
		// Body fat change (last 30 days)
		const bfEntries = weightHistory.filter((p) => p.bodyFat != null);
		if (bfEntries.length >= 2) {
			const firstBf = bfEntries[0].bodyFat!;
			const lastBf = bfEntries[bfEntries.length - 1].bodyFat!;
			const diff = +(lastBf - firstBf).toFixed(1);
			const arrow = diff > 0.1 ? '↑' : diff < -0.1 ? '↓' : '→';
			const col = diff > 0.1 ? '#e53e3e' : diff < -0.1 ? '#38a169' : '#e8a735';
			cards.push({ label: 'Body Fat Δ', value: `${diff > 0 ? '+' : ''}${diff}%`, sub: 'last 30 entries', trend: arrow, color: col });
		} else {
			cards.push({ label: 'Body Fat Δ', value: '--', sub: 'last 30 entries', trend: '→', color: 'var(--c-text-muted)' });
		}
		// Lean mass estimate
		if (weight > 0 && bodyFat > 0 && bodyFat < 100) {
			const lean = +(weight * (1 - bodyFat / 100)).toFixed(1);
			cards.push({ label: 'Lean Mass', value: `${lean} kg`, sub: `at ${bodyFat}% BF`, trend: '', color: 'var(--c-text)' });
		} else {
			cards.push({ label: 'Lean Mass', value: '--', sub: 'need BF%', trend: '', color: 'var(--c-text-muted)' });
		}
		return cards;
	});

	function save() {
		ui.patch({
			profile: { height, weight, age, bodyFat, stressBaseline, sleepTarget, wakeTarget, notes, activityLevel, targetWeight }
		});
		entries.add('weight', { weight, bodyFat, date: new Date().toISOString().slice(0, 10) });
		toast.show(t.profile.profileSaved);
	}
</script>

<svelte:head>
  <title>{t.profile.title} | Darink</title>
</svelte:head>

<PageHeader title={t.profile.title} />

<section class="form">
	<div class="row">
		<label>{t.profile.heightCm} <input type="number" bind:value={height} /></label>
		<label>{t.profile.weightKg} <input type="number" step="0.1" bind:value={weight} /></label>
	</div>
	<div class="row">
		<label>{t.profile.age} <input type="number" bind:value={age} /></label>
		<label>{t.profile.bodyFat} <input type="number" step="0.5" bind:value={bodyFat} /></label>
	</div>
	<label>{t.profile.stressBaseline} ({stressBaseline}/10) <input type="range" min="1" max="10" bind:value={stressBaseline} /></label>
	<div class="row">
		<label>{t.profile.sleepTarget} <input type="time" bind:value={sleepTarget} /></label>
		<label>{t.profile.wakeTarget} <input type="time" bind:value={wakeTarget} /></label>
	</div>
	<label>{t.profile.activityLevel}
		<select bind:value={activityLevel}>
			{#each activityOptions as opt}
				<option value={opt.value}>{opt.label} -- {opt.desc}</option>
			{/each}
		</select>
	</label>
	<label>{t.common.notes} <textarea bind:value={notes} rows="3" placeholder={t.profile.notesPlaceholder}></textarea></label>
	<button class="primary" onclick={save}>{t.profile.saveProfile}</button>
</section>

{#if bmi > 0}
<section class="metrics">
	<h2>{t.profile.metrics}</h2>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value">{bmi}</span>
			<span class="metric-label">{t.profile.bmi}</span>
			<span class="metric-sub">{bmiCategory}</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{bmr}</span>
			<span class="metric-label">{t.profile.bmr}</span>
			<span class="metric-sub">{t.profile.kcalDay}</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{tdee}</span>
			<span class="metric-label">{t.profile.tdee}</span>
			<span class="metric-sub">{t.profile.kcalDay}</span>
			<span class="metric-sub activity-tag">{activityLabel}</span>
		</div>
	</div>
</section>
{/if}

{#if latestMeasurement}
<section class="metrics">
	<h2>{t.profile.latestMeasurements}</h2>
	<div class="metrics-row">
		{#if latestMeasurement.data.waist}
			<div class="metric-card">
				<span class="metric-value">{latestMeasurement.data.waist}</span>
				<span class="metric-label">{t.profile.waist}</span>
				<span class="metric-sub">cm</span>
			</div>
		{/if}
		{#if latestMeasurement.data.chest}
			<div class="metric-card">
				<span class="metric-value">{latestMeasurement.data.chest}</span>
				<span class="metric-label">{t.profile.chest}</span>
				<span class="metric-sub">cm</span>
			</div>
		{/if}
		{#if latestMeasurement.data.hips}
			<div class="metric-card">
				<span class="metric-value">{latestMeasurement.data.hips}</span>
				<span class="metric-label">{t.profile.hips}</span>
				<span class="metric-sub">cm</span>
			</div>
		{/if}
	</div>
	<a href="/measurements" class="meas-link">
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
		{t.profile.viewAllMeasurements}
	</a>
</section>
{/if}

{#if weightHistory.length > 1}
{@const pts = weightHistory}
{@const minW = Math.min(...pts.map((p) => p.weight))}
{@const maxW = Math.max(...pts.map((p) => p.weight))}
{@const rangeW = maxW - minW || 1}
{@const stepX = 280 / Math.max(pts.length - 1, 1)}
<section class="chart-section">
	<h2>{t.profile.weightHistoryLast.replace('{n}', String(pts.length))}</h2>
	<svg class="line-chart" viewBox="0 0 280 100" preserveAspectRatio="none">
		<polyline
			fill="none" stroke="var(--c-accent)" stroke-width="2" stroke-linejoin="round"
			points={pts.map((p, i) => `${i * stepX},${100 - ((p.weight - minW) / rangeW) * 80 - 10}`).join(' ')}
		/>
		{#each pts as p, i}
			<circle
				cx={i * stepX} cy={100 - ((p.weight - minW) / rangeW) * 80 - 10}
				r="2.5" fill="var(--c-accent)"
			/>
		{/each}
	</svg>
	<div class="chart-range">
		<span>{minW} kg</span>
		<span>{maxW} kg</span>
	</div>
	{#if pts.some((p) => p.bodyFat != null)}
	{@const bfPts = pts.filter((p) => p.bodyFat != null)}
	{@const minBf = Math.min(...bfPts.map((p) => p.bodyFat!))}
	{@const maxBf = Math.max(...bfPts.map((p) => p.bodyFat!))}
	{@const rangeBf = maxBf - minBf || 1}
	{@const stepBf = 280 / Math.max(bfPts.length - 1, 1)}
	<svg class="line-chart" viewBox="0 0 280 100" preserveAspectRatio="none" style="margin-top: 0.5rem;">
		<polyline
			fill="none" stroke="var(--c-done)" stroke-width="2" stroke-linejoin="round"
			points={bfPts.map((p, i) => `${i * stepBf},${100 - ((p.bodyFat! - minBf) / rangeBf) * 80 - 10}`).join(' ')}
		/>
		{#each bfPts as p, i}
			<circle
				cx={i * stepBf} cy={100 - ((p.bodyFat! - minBf) / rangeBf) * 80 - 10}
				r="2.5" fill="var(--c-done)"
			/>
		{/each}
	</svg>
	<div class="chart-range">
		<span>{minBf}%</span>
		<span>{maxBf}%</span>
	</div>
	{/if}
	<div class="legend">
		<span class="dot weight"></span> {t.profile.weightLegend}
		{#if pts.some((p) => p.bodyFat != null)}
			<span class="dot bf"></span> {t.profile.bodyFatLegend}
		{/if}
	</div>
</section>
{/if}

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
	.row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }
	input[type="range"] { padding: 0; }

	.metrics {
		padding: 1.5rem 1rem 0;
	}
	.metrics-row {
		display: flex;
		gap: 0.5rem;
		flex-wrap: wrap;
	}
	.metric-card {
		flex: 1;
		min-width: 80px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem;
		text-align: center;
		display: flex;
		flex-direction: column;
		gap: 0.15rem;
	}
	.metric-value {
		font-size: 1.4rem;
		font-weight: 700;
	}
	.metric-label {
		font-size: 0.75rem;
		font-weight: 600;
		color: var(--c-text-muted);
		text-transform: uppercase;
	}
	.metric-sub {
		font-size: 0.7rem;
		color: var(--c-text-muted);
	}
	.metric-sub.activity-tag {
		font-size: 0.65rem;
		color: var(--c-accent);
		font-weight: 500;
	}
	.metric-sub.normal { color: var(--c-done); }
	.metric-sub.underweight { color: var(--c-accent); }
	.metric-sub.overweight { color: #e8a735; }
	.metric-sub.obese { color: var(--c-danger, #e53e3e); }

	.chart-section {
		padding: 1.5rem 1rem 0;
	}
	.line-chart {
		width: 100%;
		height: 100px;
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
	.legend {
		display: flex;
		gap: 1rem;
		font-size: 0.75rem;
		color: var(--c-text-muted);
		margin-top: 0.5rem;
		align-items: center;
	}
	.dot {
		display: inline-block;
		width: 8px;
		height: 8px;
		border-radius: 50%;
	}
	.dot.weight { background: var(--c-accent); }
	.dot.bf { background: var(--c-done); }

	.meas-link {
		display: inline-flex;
		align-items: center;
		gap: 0.35rem;
		margin-top: 0.5rem;
		font-size: 0.8rem;
		font-weight: 500;
		color: var(--c-accent);
	}

	@media (max-width: 359px) {
		.metrics-row { flex-direction: column; }
		.metric-card { min-width: auto; }
	}
</style>
