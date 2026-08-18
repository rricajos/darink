<script lang="ts">
	import { onMount } from 'svelte';
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { ui } from '$lib/db';
	import type { Entry } from '$lib/db';

	const store = useEntries('hydration');

	/* --- Target --- */
	let target = $state(3000);

	onMount(() => {
		const saved = ui.get();
		if (typeof saved.hydrationTarget === 'number') {
			target = saved.hydrationTarget;
		}
	});

	function saveTarget() {
		ui.patch({ hydrationTarget: target });
		toast.show('Target updated');
	}

	/* --- Quick log --- */
	const todayStr = $derived(new Date().toISOString().slice(0, 10));

	function quickLog(amount: number, source: string = 'water') {
		entries.add('hydration', { date: todayStr, amount, source });
		toast.show(`+${amount}ml logged`);
	}

	/* --- Custom form --- */
	let customAmount = $state('');
	let customSource = $state('water');

	function submitCustom() {
		const amt = parseInt(customAmount);
		if (isNaN(amt) || amt <= 0) return;
		entries.add('hydration', { date: todayStr, amount: amt, source: customSource });
		customAmount = '';
		toast.show(`+${amt}ml logged`);
	}

	/* --- Today's progress --- */
	const todayEntries = $derived(
		store.items.filter((e) => {
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			return d === todayStr;
		})
	);

	const todayTotal = $derived(
		todayEntries.reduce((sum, e) => sum + (e.data.amount as number), 0)
	);

	const todayPct = $derived(target > 0 ? Math.min(Math.round((todayTotal / target) * 100), 100) : 0);

	/* --- SVG progress ring --- */
	const ringRadius = 70;
	const ringCircumference = 2 * Math.PI * ringRadius;
	const ringOffset = $derived(ringCircumference - (todayPct / 100) * ringCircumference);

	/* --- Weekly bar chart --- */
	const weeklyData = $derived.by(() => {
		const days: { label: string; date: string; total: number; ratio: number }[] = [];
		for (let i = 6; i >= 0; i--) {
			const d = new Date();
			d.setDate(d.getDate() - i);
			const key = d.toISOString().slice(0, 10);
			const weekday = d.toLocaleDateString('en', { weekday: 'short' });
			const dayTotal = store.items
				.filter((e) => {
					const ed = (e.data.date as string) ?? e.createdAt.slice(0, 10);
					return ed === key;
				})
				.reduce((sum, e) => sum + (e.data.amount as number), 0);
			days.push({ label: weekday, date: key, total: dayTotal, ratio: target > 0 ? dayTotal / target : 0 });
		}
		return days;
	});

	/* --- Source labels --- */
	const sourceLabels: Record<string, string> = {
		water: 'Water',
		tea: 'Tea',
		coffee: 'Coffee',
		juice: 'Juice',
		other: 'Other'
	};
</script>

<svelte:head>
  <title>Hydration | Darink</title>
</svelte:head>

<PageHeader title="Hydration" />

<!-- Daily Target -->
<section class="target-section">
	<h2>Daily Target</h2>
	<div class="target-row">
		<input
			type="number"
			class="target-input"
			bind:value={target}
			min="500"
			max="10000"
			step="100"
		/>
		<span class="target-unit">ml</span>
		<button class="target-save" onclick={saveTarget} aria-label="Save target">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
		</button>
	</div>
</section>

<!-- Progress Ring -->
<section class="progress-section">
	<div class="ring-container">
		<svg class="progress-ring" viewBox="0 0 180 180">
			<circle
				cx="90"
				cy="90"
				r={ringRadius}
				fill="none"
				stroke="var(--c-border)"
				stroke-width="12"
			/>
			<circle
				cx="90"
				cy="90"
				r={ringRadius}
				fill="none"
				stroke={todayPct >= 80 ? 'var(--c-done)' : todayPct >= 50 ? '#f59e0b' : 'var(--c-accent)'}
				stroke-width="12"
				stroke-linecap="round"
				stroke-dasharray={ringCircumference}
				stroke-dashoffset={ringOffset}
				transform="rotate(-90 90 90)"
				class="ring-progress"
			/>
			<text x="90" y="82" text-anchor="middle" class="ring-pct">{todayPct}%</text>
			<text x="90" y="105" text-anchor="middle" class="ring-total">{todayTotal}ml</text>
		</svg>
	</div>
	<div class="progress-label">of {target}ml target</div>
</section>

<!-- Quick Tap Buttons -->
<section class="quick-section">
	<h2>Quick Add</h2>
	<div class="quick-row">
		<button class="quick-btn" onclick={() => quickLog(250)}>
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><line x1="6" y1="2" x2="6" y2="4"/><line x1="10" y1="2" x2="10" y2="4"/><line x1="14" y1="2" x2="14" y2="4"/></svg>
			<span class="quick-label">250ml</span>
			<span class="quick-desc">Glass</span>
		</button>
		<button class="quick-btn" onclick={() => quickLog(500)}>
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2v6a6 6 0 0 0 12 0V2"/><path d="M6 8h12"/><path d="M10 18h4"/><path d="M12 12v10"/></svg>
			<span class="quick-label">500ml</span>
			<span class="quick-desc">Bottle</span>
		</button>
		<button class="quick-btn" onclick={() => quickLog(750)}>
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.5 20H8"/><path d="M17 3l1 12"/><path d="M12 3l1 12"/><path d="M6.5 3l1 12"/><path d="M4.5 15h16"/><path d="M5.5 20h13"/></svg>
			<span class="quick-label">750ml</span>
			<span class="quick-desc">Large</span>
		</button>
	</div>
</section>

<!-- Custom Amount -->
<section class="custom-section">
	<h2>Custom Amount</h2>
	<div class="custom-row">
		<input
			type="number"
			class="custom-input"
			bind:value={customAmount}
			placeholder="ml"
			min="1"
		/>
		<select class="custom-select" bind:value={customSource}>
			<option value="water">Water</option>
			<option value="tea">Tea</option>
			<option value="coffee">Coffee</option>
			<option value="juice">Juice</option>
			<option value="other">Other</option>
		</select>
		<button class="primary custom-add" onclick={submitCustom}>Add</button>
	</div>
</section>

<!-- Weekly Bar Chart -->
<section class="chart-section">
	<h2>Last 7 Days</h2>
	<svg class="week-chart" viewBox="0 0 280 120" preserveAspectRatio="xMidYMid meet">
		<!-- Target line -->
		<line x1="0" y1="10" x2="280" y2="10" stroke="var(--c-text-muted)" stroke-width="0.5" stroke-dasharray="4 2" />
		<text x="275" y="8" text-anchor="end" font-size="6" fill="var(--c-text-muted)">target</text>
		{#each weeklyData as day, i}
			{@const barW = 280 / 7}
			{@const maxRatio = Math.max(...weeklyData.map((d) => d.ratio), 1)}
			{@const normalizedH = maxRatio > 0 ? (day.ratio / maxRatio) * 90 : 0}
			{@const barColor = day.ratio >= 0.8 ? 'var(--c-done)' : day.ratio >= 0.5 ? '#f59e0b' : '#ef4444'}
			<rect
				x={i * barW + barW * 0.15}
				y={100 - normalizedH}
				width={barW * 0.7}
				height={normalizedH}
				rx="2"
				fill={day.total > 0 ? barColor : 'var(--c-border)'}
			/>
			<text
				x={i * barW + barW / 2}
				y="115"
				text-anchor="middle"
				font-size="8"
				fill="var(--c-text-muted)"
			>{day.label}</text>
			{#if day.total > 0}
				<text
					x={i * barW + barW / 2}
					y={100 - normalizedH - 3}
					text-anchor="middle"
					font-size="7"
					fill="var(--c-text)"
				>{day.total >= 1000 ? (day.total / 1000).toFixed(1) + 'L' : day.total + ''}</text>
			{/if}
		{/each}
	</svg>
</section>

<!-- Empty State -->
{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
	<p>No hydration entries yet</p>
	<p class="empty-hint">Use the quick buttons above to start tracking your water intake.</p>
</div>
{/if}

<!-- Entry History -->
{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		const amt = parseInt(fd.get('amount') as string);
		if (isNaN(amt) || amt <= 0) return;
		entries.update(item.id, {
			date: fd.get('date') as string,
			amount: amt,
			source: fd.get('source') as string
		});
		toast.show('Updated');
		done();
	}}>
		<div class="row">
			<label>Amount (ml) <input type="number" name="amount" value={data.amount} min="1" /></label>
			<label>
				Source
				<select name="source">
					<option value="water" selected={data.source === 'water'}>Water</option>
					<option value="tea" selected={data.source === 'tea'}>Tea</option>
					<option value="coffee" selected={data.source === 'coffee'}>Coffee</option>
					<option value="juice" selected={data.source === 'juice'}>Juice</option>
					<option value="other" selected={data.source === 'other'}>Other</option>
				</select>
			</label>
		</div>
		<label>Date <input type="date" name="date" value={data.date} /></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div><strong>{item.data.amount}ml</strong> <span class="meta">{sourceLabels[(item.data.source as string)] ?? item.data.source}</span></div>
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

	/* Target */
	.target-section { padding: 0 1rem 1rem; }
	.target-row {
		display: flex;
		align-items: center;
		gap: 0.5rem;
	}
	.target-input {
		width: 90px;
		text-align: center;
		font-weight: 600;
	}
	.target-unit {
		font-size: 0.85rem;
		color: var(--c-text-muted);
	}
	.target-save {
		background: none;
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		color: var(--c-done);
		padding: 0.35rem 0.5rem;
		cursor: pointer;
		display: flex;
		align-items: center;
	}
	.target-save:hover {
		border-color: var(--c-done);
	}

	/* Progress Ring */
	.progress-section {
		padding: 0 1rem 1.5rem;
		text-align: center;
	}
	.ring-container {
		display: flex;
		justify-content: center;
	}
	.progress-ring {
		width: 180px;
		height: 180px;
	}
	.ring-progress {
		transition: stroke-dashoffset 0.4s ease;
	}
	.ring-pct {
		font-size: 28px;
		font-weight: 700;
		fill: var(--c-text);
	}
	.ring-total {
		font-size: 14px;
		font-weight: 500;
		fill: var(--c-text-muted);
	}
	.progress-label {
		font-size: 0.85rem;
		color: var(--c-text-muted);
		margin-top: 0.25rem;
	}

	/* Quick Tap */
	.quick-section { padding: 0 1rem 1.5rem; }
	.quick-row {
		display: flex;
		gap: 0.5rem;
	}
	.quick-btn {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 0.3rem;
		padding: 0.75rem 0.5rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		cursor: pointer;
		transition: all 0.15s;
		color: var(--c-text);
	}
	.quick-btn:hover {
		border-color: var(--c-accent);
		background: var(--c-accent-bg);
	}
	.quick-btn:active {
		transform: scale(0.96);
	}
	.quick-label {
		font-size: 0.95rem;
		font-weight: 700;
	}
	.quick-desc {
		font-size: 0.75rem;
		color: var(--c-text-muted);
	}

	/* Custom */
	.custom-section { padding: 0 1rem 1.5rem; }
	.custom-row {
		display: flex;
		gap: 0.5rem;
		align-items: stretch;
	}
	.custom-input {
		flex: 1;
		min-width: 80px;
	}
	.custom-select {
		flex: 1;
		min-width: 80px;
	}
	.custom-add {
		white-space: nowrap;
	}

	/* Weekly Chart */
	.chart-section { padding: 0 1rem 1.5rem; }
	.week-chart {
		width: 100%;
		height: 120px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}

	/* Form helpers */
	.row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }
	.empty-hint {
		font-size: 0.85rem;
		color: var(--c-text-muted);
	}
</style>
