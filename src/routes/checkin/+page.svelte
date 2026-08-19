<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('checkin');

	let date = $state(new Date().toISOString().slice(0, 10));
	let mood = $state(5);
	let energy = $state(5);
	let sleep = $state(7);
	let stress = $state(3);
	let morningErection = $state(false);
	let notes = $state('');
	let errors = $state<Record<string, string>>({});

	function validate(): boolean {
		const e: Record<string, string> = {};
		const m = Number(mood);
		const en = Number(energy);
		const st = Number(stress);
		const sl = Number(sleep);
		if (!Number.isInteger(m) || m < 1 || m > 10) e.mood = 'Mood must be between 1 and 10';
		if (!Number.isInteger(en) || en < 1 || en > 10) e.energy = 'Energy must be between 1 and 10';
		if (!Number.isInteger(st) || st < 1 || st > 10) e.stress = 'Stress must be between 1 and 10';
		if (isNaN(sl) || sl < 0 || sl > 24) e.sleep = 'Sleep must be between 0 and 24 hours';
		errors = e;
		return Object.keys(e).length === 0;
	}

	function clearError(field: string) {
		if (errors[field]) {
			const next = { ...errors };
			delete next[field];
			errors = next;
		}
	}

	function submit() {
		if (!validate()) return;
		entries.add('checkin', {
			date, mood, energy, sleep, stress, morningErection, notes,
			period: new Date().getHours() < 14 ? 'morning' : 'night'
		});
		date = new Date().toISOString().slice(0, 10);
		mood = 5; energy = 5; sleep = 7; stress = 3;
		morningErection = false; notes = '';
		errors = {};
		toast.show('Check-in saved');
	}

	function repeatLast() {
		const last = store.items.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt))[0];
		if (!last) return;
		mood = Number(last.data.mood) || 5;
		energy = Number(last.data.energy) || 5;
		stress = Number(last.data.stress) || 3;
		sleep = Number(last.data.sleep) || 7;
		toast.show('Fields pre-filled');
	}
</script>

<svelte:head>
  <title>Check-in | Darink</title>
</svelte:head>

<PageHeader title="Check-in" />

<section class="form">
	<label>Date <input type="date" bind:value={date} /></label>
	<label class:field-has-error={!!errors.mood}>Mood ({mood}/10) <input type="range" min="1" max="10" bind:value={mood} oninput={() => clearError('mood')} /></label>
	{#if errors.mood}<span class="field-error">{errors.mood}</span>{/if}
	<label class:field-has-error={!!errors.energy}>Energy ({energy}/10) <input type="range" min="1" max="10" bind:value={energy} oninput={() => clearError('energy')} /></label>
	{#if errors.energy}<span class="field-error">{errors.energy}</span>{/if}
	<label class:field-has-error={!!errors.sleep}>Sleep hours ({sleep}) <input type="number" min="0" max="14" step="0.5" bind:value={sleep} oninput={() => clearError('sleep')} /></label>
	{#if errors.sleep}<span class="field-error">{errors.sleep}</span>{/if}
	<label class:field-has-error={!!errors.stress}>Stress ({stress}/10) <input type="range" min="1" max="10" bind:value={stress} oninput={() => clearError('stress')} /></label>
	{#if errors.stress}<span class="field-error">{errors.stress}</span>{/if}
	<label class="checkbox"><input type="checkbox" bind:checked={morningErection} /> Morning erection</label>
	<label>Notes <textarea bind:value={notes} placeholder="How do you feel?" rows="2"></textarea></label>
	<div class="form-actions">
		<button class="primary" onclick={submit}>Save check-in</button>
		{#if store.items.length > 0}
			<button onclick={repeatLast}>Repeat last</button>
		{/if}
	</div>
</section>

<!-- Quick Stats Row -->
{#if store.items.length > 0}
	{@const totalCheckins = store.items.length}
	{@const avgMoodAll = (store.items.reduce((s, e) => s + (Number(e.data.mood) || 0), 0) / totalCheckins).toFixed(1)}
	{@const daySet = new Set(store.items.map(e => e.createdAt.slice(0, 10)))}
	{@const sortedDays = [...daySet].sort().reverse()}
	{@const streak = (() => {
		let count = 0;
		const today = new Date();
		for (let i = 0; i < sortedDays.length; i++) {
			const expected = new Date(today.getFullYear(), today.getMonth(), today.getDate() - i).toISOString().slice(0, 10);
			if (sortedDays[i] === expected) count++;
			else break;
		}
		return count;
	})()}
	<section class="quick-stats">
		<div class="quick-stat-card">
			<span class="quick-stat-value">{totalCheckins}</span>
			<span class="quick-stat-label">Check-ins</span>
		</div>
		<div class="quick-stat-card">
			<span class="quick-stat-value">{avgMoodAll}</span>
			<span class="quick-stat-label">Avg mood</span>
		</div>
		<div class="quick-stat-card">
			<span class="quick-stat-value">{streak}<span class="quick-stat-unit">d</span></span>
			<span class="quick-stat-label">Streak</span>
		</div>
	</section>
{/if}

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			mood: Number(fd.get('mood')),
			energy: Number(fd.get('energy')),
			sleep: Number(fd.get('sleep')),
			stress: Number(fd.get('stress')),
			morningErection: fd.has('morningErection'),
			notes: fd.get('notes') ?? ''
		});
		toast.show('Updated');
		done();
	}}>
		<label>Mood ({data.mood}/10) <input type="range" name="mood" min="1" max="10" value={data.mood} /></label>
		<label>Energy ({data.energy}/10) <input type="range" name="energy" min="1" max="10" value={data.energy} /></label>
		<label>Sleep hours <input type="number" name="sleep" min="0" max="14" step="0.5" value={data.sleep} /></label>
		<label>Stress ({data.stress}/10) <input type="range" name="stress" min="1" max="10" value={data.stress} /></label>
		<label class="checkbox"><input type="checkbox" name="morningErection" checked={!!data.morningErection} /> Morning erection</label>
		<label>Notes <textarea name="notes" rows="2">{data.notes ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M9 14h.01"/><path d="M13 14h.01"/><path d="M9 18h.01"/><path d="M13 18h.01"/></svg>
	<p>No check-ins yet</p>
	<p class="empty-hint">Log your first check-in to start tracking mood, energy, and sleep patterns.</p>
</div>
{/if}

<EntryList items={store.items} limit={7} {editForm}>
	{#snippet row(item)}
		<span class="checkin-row">{#if item.data.period === 'morning'}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; display: inline-block;"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>{:else}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; display: inline-block;"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>{/if} M{item.data.mood} E{item.data.energy} S{item.data.stress} · {item.data.sleep}h</span>
	{/snippet}
</EntryList>

<!-- Analytics: Trend Charts (last 30 check-ins) -->
{#if store.items.length > 1}
	{@const sorted = [...store.items].sort((a, b) => a.createdAt.localeCompare(b.createdAt)).slice(-30)}
	{@const stepX = 280 / Math.max(sorted.length - 1, 1)}
	{@const charts = [
		{ key: 'mood', label: 'Mood', color: '#4aa3ff', min: 1, max: 10, vals: sorted.map(e => Number(e.data.mood) || 5) },
		{ key: 'energy', label: 'Energy', color: '#2e8b57', min: 1, max: 10, vals: sorted.map(e => Number(e.data.energy) || 5) },
		{ key: 'sleep', label: 'Sleep (h)', color: '#9b59b6', min: 0, max: 14, vals: sorted.map(e => Number(e.data.sleep) || 7) },
		{ key: 'stress', label: 'Stress', color: '#e53e3e', min: 1, max: 10, vals: sorted.map(e => Number(e.data.stress) || 3) }
	]}
	<section class="trend-section">
		<h2>Trends (last {sorted.length})</h2>
		{#each charts as chart}
			{@const rangeY = chart.max - chart.min || 1}
			{@const pts = chart.vals.map((v, i) => `${i * stepX},${52 - ((v - chart.min) / rangeY) * 44}`)}
			{@const current = chart.vals[chart.vals.length - 1]}
			{@const compare = chart.vals.length >= 8 ? chart.vals[chart.vals.length - 8] : chart.vals[0]}
			{@const diff = current - compare}
			{@const arrow = diff > 0.5 ? 'up' : diff < -0.5 ? 'down' : 'flat'}
			<div class="trend-row">
				<div class="trend-meta">
					<span class="trend-label" style="color:{chart.color}">{chart.label}</span>
					<span class="trend-current">{chart.key === 'sleep' ? current.toFixed(1) : current}
						{#if arrow === 'up'}
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#2e8b57" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
						{:else if arrow === 'down'}
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#e53e3e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
						{:else}
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--c-text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
						{/if}
					</span>
				</div>
				<svg class="mini-chart" viewBox="0 0 280 60">
					<polyline points={pts.join(' ')} fill="none" stroke={chart.color} stroke-width="2" stroke-linejoin="round" />
					{#each chart.vals as v, i}
						<circle cx={i * stepX} cy={52 - ((v - chart.min) / rangeY) * 44} r="2.5" fill={chart.color} />
					{/each}
				</svg>
			</div>
		{/each}
	</section>
{/if}

<!-- Analytics: Weekly Averages (last 4 weeks) -->
{#if store.items.length > 0}
	{@const now = new Date()}
	{@const weeks = [0, 1, 2, 3].map(w => {
		const end = new Date(now.getFullYear(), now.getMonth(), now.getDate() - w * 7);
		const start = new Date(now.getFullYear(), now.getMonth(), now.getDate() - (w + 1) * 7);
		const items = store.items.filter(e => {
			const d = new Date(e.createdAt);
			return d > start && d <= end;
		});
		if (items.length === 0) return null;
		return {
			label: w === 0 ? 'This week' : w === 1 ? 'Last week' : `${w}w ago`,
			count: items.length,
			mood: items.reduce((s, e) => s + (Number(e.data.mood) || 0), 0) / items.length,
			energy: items.reduce((s, e) => s + (Number(e.data.energy) || 0), 0) / items.length,
			sleep: items.reduce((s, e) => s + (Number(e.data.sleep) || 0), 0) / items.length,
			stress: items.reduce((s, e) => s + (Number(e.data.stress) || 0), 0) / items.length
		};
	})}
	{@const validWeeks = weeks.filter(Boolean) as { label: string; count: number; mood: number; energy: number; sleep: number; stress: number }[]}
	{#if validWeeks.length > 0}
		{@const bestMoodIdx = validWeeks.reduce((bi, w, i, arr) => w.mood > arr[bi].mood ? i : bi, 0)}
		{@const bestEnergyIdx = validWeeks.reduce((bi, w, i, arr) => w.energy > arr[bi].energy ? i : bi, 0)}
		{@const bestSleepIdx = validWeeks.reduce((bi, w, i, arr) => w.sleep > arr[bi].sleep ? i : bi, 0)}
		{@const bestStressIdx = validWeeks.reduce((bi, w, i, arr) => w.stress < arr[bi].stress ? i : bi, 0)}
		<section class="weekly-section">
			<h2>Weekly averages</h2>
			<div class="weekly-table">
				<div class="weekly-header">
					<span class="weekly-cell weekly-label-cell"></span>
					<span class="weekly-cell" style="color:#4aa3ff">Mood</span>
					<span class="weekly-cell" style="color:#2e8b57">Energy</span>
					<span class="weekly-cell" style="color:#9b59b6">Sleep</span>
					<span class="weekly-cell" style="color:#e53e3e">Stress</span>
				</div>
				{#each validWeeks as week, wi}
					<div class="weekly-row">
						<span class="weekly-cell weekly-label-cell">{week.label}</span>
						<span class="weekly-cell" class:weekly-best={wi === bestMoodIdx}>{week.mood.toFixed(1)}</span>
						<span class="weekly-cell" class:weekly-best={wi === bestEnergyIdx}>{week.energy.toFixed(1)}</span>
						<span class="weekly-cell" class:weekly-best={wi === bestSleepIdx}>{week.sleep.toFixed(1)}</span>
						<span class="weekly-cell" class:weekly-best={wi === bestStressIdx}>{week.stress.toFixed(1)}</span>
					</div>
				{/each}
			</div>
		</section>
	{/if}
{/if}

<!-- Analytics: Morning vs Night Comparison -->
{#if store.items.length > 0}
	{@const morningItems = store.items.filter(e => e.data.period === 'morning')}
	{@const nightItems = store.items.filter(e => e.data.period === 'night')}
	{#if morningItems.length > 0 && nightItems.length > 0}
		{@const mornMood = (morningItems.reduce((s, e) => s + (Number(e.data.mood) || 0), 0) / morningItems.length).toFixed(1)}
		{@const nightMood = (nightItems.reduce((s, e) => s + (Number(e.data.mood) || 0), 0) / nightItems.length).toFixed(1)}
		{@const mornEnergy = (morningItems.reduce((s, e) => s + (Number(e.data.energy) || 0), 0) / morningItems.length).toFixed(1)}
		{@const nightEnergy = (nightItems.reduce((s, e) => s + (Number(e.data.energy) || 0), 0) / nightItems.length).toFixed(1)}
		<section class="period-section">
			<h2>Morning vs Night</h2>
			<div class="period-grid">
				<div class="period-card">
					<div class="period-header">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
						<span>Morning mood</span>
					</div>
					<span class="period-value">{mornMood}</span>
					<span class="period-count">{morningItems.length} entries</span>
				</div>
				<div class="period-card">
					<div class="period-header">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
						<span>Night mood</span>
					</div>
					<span class="period-value">{nightMood}</span>
					<span class="period-count">{nightItems.length} entries</span>
				</div>
				<div class="period-card">
					<div class="period-header">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
						<span>Morning energy</span>
					</div>
					<span class="period-value">{mornEnergy}</span>
					<span class="period-count">{morningItems.length} entries</span>
				</div>
				<div class="period-card">
					<div class="period-header">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
						<span>Night energy</span>
					</div>
					<span class="period-value">{nightEnergy}</span>
					<span class="period-count">{nightItems.length} entries</span>
				</div>
			</div>
		</section>
	{/if}
{/if}

<style>
	.field-error { font-size: 0.75rem; color: var(--c-cancel); margin-top: 0.15rem; display: block; }
	.field-has-error { color: var(--c-cancel); }
	.field-has-error input { border-color: var(--c-cancel); }
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem 1rem; }
	.checkbox { flex-direction: row; align-items: center; gap: 0.5rem; }
	.checkbox input { width: auto; }
	input[type="range"] { padding: 0; }
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-inline input[type="range"] { padding: 0; }
	.edit-inline .checkbox { flex-direction: row; align-items: center; gap: 0.5rem; }
	.edit-inline .checkbox input { width: auto; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }
	.form-actions { display: flex; gap: 0.5rem; }
	.form-actions button { flex: 1; }

	h2 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

	/* Quick Stats */
	.quick-stats { display: flex; gap: 0.5rem; padding: 0 1rem 0.5rem; }
	.quick-stat-card { flex: 1; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.5rem 0.25rem; text-align: center; display: flex; flex-direction: column; gap: 0.1rem; }
	.quick-stat-value { font-size: 1.25rem; font-weight: 700; }
	.quick-stat-unit { font-size: 0.75rem; font-weight: 400; color: var(--c-text-muted); }
	.quick-stat-label { font-size: 0.65rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.03em; }

	/* Trend Charts */
	.trend-section { padding: 1.5rem 1rem 0; }
	.trend-row { background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.5rem 0.75rem; margin-bottom: 0.5rem; }
	.trend-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem; }
	.trend-label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
	.trend-current { font-size: 0.85rem; font-weight: 700; display: flex; align-items: center; gap: 0.2rem; }
	.mini-chart { width: 100%; height: 60px; }

	/* Weekly Averages */
	.weekly-section { padding: 1.5rem 1rem 0; }
	.weekly-table { background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); overflow: hidden; }
	.weekly-header, .weekly-row { display: flex; }
	.weekly-header { border-bottom: 1px solid var(--c-border); }
	.weekly-row + .weekly-row { border-top: 1px solid var(--c-border); }
	.weekly-cell { flex: 1; padding: 0.5rem 0.25rem; text-align: center; font-size: 0.8rem; }
	.weekly-label-cell { flex: 1.2; text-align: left; padding-left: 0.75rem; font-weight: 600; color: var(--c-text-muted); font-size: 0.75rem; }
	.weekly-header .weekly-cell { font-weight: 700; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.03em; }
	.weekly-best { background: var(--c-accent-bg); font-weight: 700; }

	/* Morning vs Night */
	.period-section { padding: 1.5rem 1rem 0; }
	.period-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
	.period-card { background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; display: flex; flex-direction: column; gap: 0.2rem; }
	.period-header { display: flex; align-items: center; gap: 0.35rem; font-size: 0.7rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; }
	.period-value { font-size: 1.4rem; font-weight: 700; }
	.period-count { font-size: 0.65rem; color: var(--c-text-muted); }
</style>
