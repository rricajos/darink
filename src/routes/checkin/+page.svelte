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

	function submit() {
		entries.add('checkin', {
			date, mood, energy, sleep, stress, morningErection, notes,
			period: new Date().getHours() < 14 ? 'morning' : 'night'
		});
		date = new Date().toISOString().slice(0, 10);
		mood = 5; energy = 5; sleep = 7; stress = 3;
		morningErection = false; notes = '';
		toast.show('Check-in saved');
	}
</script>

<PageHeader title="Check-in" />

<section class="form">
	<label>Date <input type="date" bind:value={date} /></label>
	<label>Mood ({mood}/10) <input type="range" min="1" max="10" bind:value={mood} /></label>
	<label>Energy ({energy}/10) <input type="range" min="1" max="10" bind:value={energy} /></label>
	<label>Sleep hours ({sleep}) <input type="number" min="0" max="14" step="0.5" bind:value={sleep} /></label>
	<label>Stress ({stress}/10) <input type="range" min="1" max="10" bind:value={stress} /></label>
	<label class="checkbox"><input type="checkbox" bind:checked={morningErection} /> Morning erection</label>
	<label>Notes <textarea bind:value={notes} placeholder="How do you feel?" rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Save check-in</button>
</section>

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

<!-- Analytics: Mood + Energy + Stress trend (last 30 entries) -->
{#if store.items.length > 1}
	{@const sorted = [...store.items].sort((a, b) => a.createdAt.localeCompare(b.createdAt)).slice(-30)}
	{@const stepX = 280 / Math.max(sorted.length - 1, 1)}
	{@const moodPts = sorted.map((e, i) => ({ x: i * stepX, y: Number(e.data.mood) || 5 }))}
	{@const energyPts = sorted.map((e, i) => ({ x: i * stepX, y: Number(e.data.energy) || 5 }))}
	{@const stressPts = sorted.map((e, i) => ({ x: i * stepX, y: Number(e.data.stress) || 3 }))}
	{@const allVals = [...moodPts.map(p => p.y), ...energyPts.map(p => p.y), ...stressPts.map(p => p.y)]}
	{@const minV = Math.min(...allVals)}
	{@const maxV = Math.max(...allVals)}
	{@const rangeV = maxV - minV || 1}
	<section class="chart-section">
		<h2>Mood / Energy / Stress trend</h2>
		<svg class="line-chart" viewBox="0 0 280 100" preserveAspectRatio="none">
			<polyline
				points={moodPts.map(p => `${p.x},${90 - ((p.y - minV) / rangeV) * 80}`).join(' ')}
				fill="none" stroke="var(--c-accent)" stroke-width="2" stroke-linejoin="round"
			/>
			<polyline
				points={energyPts.map(p => `${p.x},${90 - ((p.y - minV) / rangeV) * 80}`).join(' ')}
				fill="none" stroke="var(--c-done)" stroke-width="2" stroke-linejoin="round"
			/>
			<polyline
				points={stressPts.map(p => `${p.x},${90 - ((p.y - minV) / rangeV) * 80}`).join(' ')}
				fill="none" stroke="var(--c-cancel)" stroke-width="2" stroke-linejoin="round" stroke-dasharray="4 3"
			/>
		</svg>
		<div class="legend">
			<span class="legend-item"><span class="dot" style="background:var(--c-accent)"></span> Mood</span>
			<span class="legend-item"><span class="dot" style="background:var(--c-done)"></span> Energy</span>
			<span class="legend-item"><span class="dot" style="background:var(--c-cancel)"></span> Stress</span>
		</div>
	</section>
{/if}

<!-- Analytics: Weekly averages (last 7 days) -->
{#if store.items.length > 0}
	{@const now = new Date()}
	{@const weekAgo = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 7)}
	{@const weekItems = store.items.filter(e => new Date(e.createdAt) >= weekAgo)}
	{#if weekItems.length > 0}
		{@const avgMood = (weekItems.reduce((s, e) => s + (Number(e.data.mood) || 0), 0) / weekItems.length).toFixed(1)}
		{@const avgEnergy = (weekItems.reduce((s, e) => s + (Number(e.data.energy) || 0), 0) / weekItems.length).toFixed(1)}
		{@const avgStress = (weekItems.reduce((s, e) => s + (Number(e.data.stress) || 0), 0) / weekItems.length).toFixed(1)}
		{@const avgSleep = (weekItems.reduce((s, e) => s + (Number(e.data.sleep) || 0), 0) / weekItems.length).toFixed(1)}
		<section class="metrics">
			<h2>Weekly averages</h2>
			<div class="metrics-row">
				<div class="metric-card">
					<span class="metric-value">{avgMood}</span>
					<span class="metric-label">Mood</span>
				</div>
				<div class="metric-card">
					<span class="metric-value">{avgEnergy}</span>
					<span class="metric-label">Energy</span>
				</div>
				<div class="metric-card">
					<span class="metric-value">{avgStress}</span>
					<span class="metric-label">Stress</span>
				</div>
				<div class="metric-card">
					<span class="metric-value">{avgSleep}</span>
					<span class="metric-label">Sleep (h)</span>
				</div>
			</div>
		</section>
	{/if}
{/if}

<!-- Analytics: Best / worst mood days (last 30 entries) -->
{#if store.items.length > 0}
	{@const sorted30 = [...store.items].sort((a, b) => a.createdAt.localeCompare(b.createdAt)).slice(-30)}
	{@const best = sorted30.reduce((best, e) => (Number(e.data.mood) || 0) > (Number(best.data.mood) || 0) ? e : best, sorted30[0])}
	{@const worst = sorted30.reduce((worst, e) => (Number(e.data.mood) || 10) < (Number(worst.data.mood) || 10) ? e : worst, sorted30[0])}
	<section class="metrics">
		<h2>Best / Worst days</h2>
		<div class="metrics-row">
			<div class="metric-card">
				<span class="metric-value" style="color: var(--c-done)">{Number(best.data.mood)}</span>
				<span class="metric-label">Best mood</span>
				<span class="metric-sub">{best.createdAt.slice(0, 10)}</span>
			</div>
			<div class="metric-card">
				<span class="metric-value" style="color: var(--c-cancel)">{Number(worst.data.mood)}</span>
				<span class="metric-label">Worst mood</span>
				<span class="metric-sub">{worst.createdAt.slice(0, 10)}</span>
			</div>
		</div>
	</section>
{/if}

<style>
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

	h2 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
	.chart-section { padding: 1.5rem 1rem 0; }
	.line-chart { width: 100%; height: 100px; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.5rem; }
	.metrics { padding: 1.5rem 1rem 0; }
	.metrics-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.metric-card { flex: 1; min-width: 80px; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; text-align: center; display: flex; flex-direction: column; gap: 0.15rem; }
	.metric-value { font-size: 1.4rem; font-weight: 700; }
	.metric-label { font-size: 0.75rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; }
	.metric-sub { font-size: 0.7rem; color: var(--c-text-muted); }
	.legend { display: flex; gap: 0.75rem; flex-wrap: wrap; font-size: 0.7rem; color: var(--c-text-muted); margin-top: 0.4rem; }
	.legend-item { display: flex; align-items: center; gap: 0.25rem; }
	.dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
</style>
