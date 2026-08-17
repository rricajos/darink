<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { ui } from '$lib/db';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { onMount } from 'svelte';

	let height = $state(175);
	let weight = $state(88);
	let age = $state(25);
	let bodyFat = $state(14);
	let stressBaseline = $state(7);
	let sleepTarget = $state('22:00');
	let wakeTarget = $state('06:00');
	let notes = $state('');
	let activityLevel = $state(1.55);

	const activityOptions = [
		{ value: 1.2, label: 'Sedentary', desc: 'Desk job, little exercise' },
		{ value: 1.375, label: 'Light', desc: 'Light exercise 1-3 days/week' },
		{ value: 1.55, label: 'Moderate', desc: 'Moderate exercise 3-5 days/week' },
		{ value: 1.725, label: 'Active', desc: 'Hard exercise 6-7 days/week' },
		{ value: 1.9, label: 'Very Active', desc: 'Very hard exercise, physical job' }
	] as const;

	const weightStore = useEntries('weight');

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
		}
	});

	// Calculated metrics
	const bmi = $derived(weight > 0 && height > 0 ? +(weight / ((height / 100) ** 2)).toFixed(1) : 0);
	const bmiCategory = $derived.by(() => {
		if (bmi < 18.5) return 'Underweight';
		if (bmi < 25) return 'Normal';
		if (bmi < 30) return 'Overweight';
		return 'Obese';
	});
	const bmr = $derived(weight > 0 && height > 0 && age > 0
		? Math.round(10 * weight + 6.25 * height - 5 * age + 5)
		: 0);
	const tdee = $derived(bmr > 0 ? Math.round(bmr * activityLevel) : 0);
	const activityLabel = $derived(activityOptions.find((o) => o.value === activityLevel)?.label ?? 'Moderate');

	// Weight history (last 30 entries)
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

	function save() {
		ui.patch({
			profile: { height, weight, age, bodyFat, stressBaseline, sleepTarget, wakeTarget, notes, activityLevel }
		});
		entries.add('weight', { weight, bodyFat, date: new Date().toISOString().slice(0, 10) });
		toast.show('Profile saved');
	}
</script>

<svelte:head>
  <title>Profile | Darink</title>
</svelte:head>

<PageHeader title="Profile" />

<section class="form">
	<div class="row">
		<label>Height (cm) <input type="number" bind:value={height} /></label>
		<label>Weight (kg) <input type="number" step="0.1" bind:value={weight} /></label>
	</div>
	<div class="row">
		<label>Age <input type="number" bind:value={age} /></label>
		<label>Body fat (%) <input type="number" step="0.5" bind:value={bodyFat} /></label>
	</div>
	<label>Stress baseline ({stressBaseline}/10) <input type="range" min="1" max="10" bind:value={stressBaseline} /></label>
	<div class="row">
		<label>Sleep target <input type="time" bind:value={sleepTarget} /></label>
		<label>Wake target <input type="time" bind:value={wakeTarget} /></label>
	</div>
	<label>Activity level
		<select bind:value={activityLevel}>
			{#each activityOptions as opt}
				<option value={opt.value}>{opt.label} -- {opt.desc}</option>
			{/each}
		</select>
	</label>
	<label>Notes <textarea bind:value={notes} rows="3" placeholder="Conditions, medications, genetic notes..."></textarea></label>
	<button class="primary" onclick={save}>Save profile</button>
</section>

{#if bmi > 0}
<section class="metrics">
	<h2>Metrics</h2>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value">{bmi}</span>
			<span class="metric-label">BMI</span>
			<span class="metric-sub {bmiCategory.toLowerCase()}">{bmiCategory}</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{bmr}</span>
			<span class="metric-label">BMR</span>
			<span class="metric-sub">kcal/day</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{tdee}</span>
			<span class="metric-label">TDEE</span>
			<span class="metric-sub">kcal/day</span>
			<span class="metric-sub activity-tag">{activityLabel}</span>
		</div>
	</div>
</section>
{/if}

{#if weightHistory.length > 1}
{@const pts = weightHistory}
{@const minW = Math.min(...pts.map((p) => p.weight))}
{@const maxW = Math.max(...pts.map((p) => p.weight))}
{@const rangeW = maxW - minW || 1}
{@const stepX = 280 / Math.max(pts.length - 1, 1)}
<section class="chart-section">
	<h2>Weight History (last {pts.length})</h2>
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
		<span class="dot weight"></span> Weight
		{#if pts.some((p) => p.bodyFat != null)}
			<span class="dot bf"></span> Body fat
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

	@media (max-width: 359px) {
		.metrics-row { flex-direction: column; }
		.metric-card { min-width: auto; }
	}
</style>
