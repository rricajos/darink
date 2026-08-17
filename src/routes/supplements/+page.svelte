<script lang="ts">
	import { onMount } from 'svelte';
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { ui } from '$lib/db';
	import type { Entry } from '$lib/db';

	const store = useEntries('supplement');

	/* --- Log form state --- */
	let date = $state(new Date().toISOString().slice(0, 10));
	let name = $state('');
	let dose = $state('');
	let timing = $state('morning');
	let notes = $state('');

	/* --- Planned stack state --- */
	interface StackItem { name: string; dose: string; timing: string }
	let stack: StackItem[] = $state([]);
	let stackName = $state('');
	let stackDose = $state('');
	let stackTiming = $state('morning');

	onMount(() => {
		const saved = ui.get();
		if (Array.isArray(saved.supplementStack)) {
			stack = saved.supplementStack as StackItem[];
		}
	});

	function addToStack() {
		if (!stackName.trim()) return;
		const item: StackItem = { name: stackName.trim(), dose: stackDose.trim(), timing: stackTiming };
		stack = [...stack, item];
		ui.patch({ supplementStack: stack });
		stackName = ''; stackDose = '';
		toast.show('Added to stack');
	}

	function removeFromStack(index: number) {
		stack = stack.filter((_, i) => i !== index);
		ui.patch({ supplementStack: stack });
	}

	/* --- Today's adherence --- */
	const todayStr = $derived(new Date().toISOString().slice(0, 10));

	const todayEntries = $derived(
		store.items.filter((e) => {
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			return d === todayStr;
		})
	);

	const adherence = $derived.by(() => {
		if (stack.length === 0) return { items: [] as { planned: StackItem; taken: boolean }[], taken: 0, total: 0, pct: 0 };
		const items = stack.map((planned) => {
			const taken = todayEntries.some(
				(e) => (e.data.name as string).toLowerCase() === planned.name.toLowerCase()
			);
			return { planned, taken };
		});
		const taken = items.filter((i) => i.taken).length;
		return { items, taken, total: stack.length, pct: Math.round((taken / stack.length) * 100) };
	});

	/* --- Weekly adherence chart --- */
	const weeklyAdherence = $derived.by(() => {
		if (stack.length === 0) return [];
		const days: { label: string; pct: number }[] = [];
		for (let i = 6; i >= 0; i--) {
			const d = new Date();
			d.setDate(d.getDate() - i);
			const key = d.toISOString().slice(0, 10);
			const weekday = d.toLocaleDateString('en', { weekday: 'short' });
			const dayEntries = store.items.filter((e) => {
				const ed = (e.data.date as string) ?? e.createdAt.slice(0, 10);
				return ed === key;
			});
			const matched = stack.filter((s) =>
				dayEntries.some((e) => (e.data.name as string).toLowerCase() === s.name.toLowerCase())
			).length;
			days.push({ label: weekday, pct: Math.round((matched / stack.length) * 100) });
		}
		return days;
	});

	/* --- Most logged supplements --- */
	const topSupplements = $derived.by(() => {
		const counts = new Map<string, number>();
		for (const e of store.items) {
			const n = ((e.data.name as string) ?? '').toLowerCase();
			if (n) counts.set(n, (counts.get(n) ?? 0) + 1);
		}
		return [...counts.entries()]
			.sort((a, b) => b[1] - a[1])
			.slice(0, 5)
			.map(([n, count]) => ({ name: n, count }));
	});

	/* --- Timing labels --- */
	const timingLabels: Record<string, string> = {
		morning: 'Morning',
		preworkout: 'Pre-workout',
		afternoon: 'Afternoon',
		night: 'Night',
		withfood: 'With food'
	};

	function submit() {
		if (!name.trim()) return;
		entries.add('supplement', { date, name: name.trim(), dose, timing, notes });
		date = new Date().toISOString().slice(0, 10);
		name = ''; dose = ''; notes = '';
		toast.show('Supplement logged');
	}
</script>

<PageHeader title="Supplements" />

<!-- Planned Stack -->
<section class="stack-section">
	<h2>Planned Stack</h2>
	{#if stack.length > 0}
		<div class="stack-list">
			{#each stack as item, i}
				<div class="stack-card">
					<div class="stack-info">
						<strong>{item.name}</strong>
						{#if item.dose}
							<span class="meta">{item.dose}</span>
						{/if}
						<span class="meta">{timingLabels[item.timing] ?? item.timing}</span>
					</div>
					<button class="stack-remove" onclick={() => removeFromStack(i)} aria-label="Remove"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
				</div>
			{/each}
		</div>
	{:else}
		<p class="empty-hint">No supplements in your planned stack yet.</p>
	{/if}
	<div class="stack-form">
		<input type="text" bind:value={stackName} placeholder="Name" />
		<input type="text" bind:value={stackDose} placeholder="Dose" />
		<select bind:value={stackTiming}>
			<option value="morning">Morning</option>
			<option value="preworkout">Pre-workout</option>
			<option value="afternoon">Afternoon</option>
			<option value="night">Night</option>
			<option value="withfood">With food</option>
		</select>
		<button onclick={addToStack}>Add to stack</button>
	</div>
</section>

<!-- Today's Adherence -->
{#if stack.length > 0}
<section class="adherence-section">
	<h2>Today's Adherence</h2>
	<div class="adherence-summary">{adherence.taken}/{adherence.total} taken ({adherence.pct}%)</div>
	<div class="adherence-list">
		{#each adherence.items as ai}
			<div class="adherence-item" class:taken={ai.taken}>
				<span class="adherence-check">{#if ai.taken}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>{:else}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>{/if}</span>
				<span class="adherence-name">{ai.planned.name}</span>
				{#if ai.planned.dose}
					<span class="meta">{ai.planned.dose}</span>
				{/if}
			</div>
		{/each}
	</div>
</section>
{/if}

<!-- Weekly Adherence Chart -->
{#if weeklyAdherence.length > 0}
<section class="chart-section">
	<h2>Weekly Adherence</h2>
	<svg class="week-chart" viewBox="0 0 280 100" preserveAspectRatio="xMidYMid meet">
		{#each weeklyAdherence as day, i}
			{@const barW = 280 / 7}
			{@const barH = day.pct}
			<rect
				x={i * barW + barW * 0.15}
				y={100 - barH}
				width={barW * 0.7}
				height={barH}
				rx="2"
				fill={day.pct >= 80 ? 'var(--c-done)' : day.pct >= 40 ? 'var(--c-accent)' : 'var(--c-border)'}
			/>
			<text
				x={i * barW + barW / 2}
				y="98"
				text-anchor="middle"
				font-size="8"
				fill="var(--c-text-muted)"
			>{day.label}</text>
			{#if day.pct > 0}
				<text
					x={i * barW + barW / 2}
					y={100 - barH - 3}
					text-anchor="middle"
					font-size="7"
					fill="var(--c-text)"
				>{day.pct}%</text>
			{/if}
		{/each}
	</svg>
</section>
{/if}

<!-- Most Logged Supplements -->
{#if topSupplements.length > 0}
<section class="top-section">
	<h2>Most Logged</h2>
	<ol class="top-list">
		{#each topSupplements as s, i}
			<li class="top-item">
				<span class="top-rank">{i + 1}.</span>
				<span class="top-name">{s.name}</span>
				<span class="top-count">{s.count}x</span>
			</li>
		{/each}
	</ol>
</section>
{/if}

{#if store.items.length === 0 && stack.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>
	<p>No supplements tracked yet</p>
	<p class="empty-hint">Define your supplement stack and start tracking adherence.</p>
</div>
{/if}

<!-- Log Form -->
<section class="form">
	<label>Date <input type="date" bind:value={date} /></label>
	<label>Name <input type="text" bind:value={name} placeholder="Zinc, Magnesium, Ashwagandha..." /></label>
	<div class="row">
		<label>Dose <input type="text" bind:value={dose} placeholder="30mg, 2 caps..." /></label>
		<label>
			Timing
			<select bind:value={timing}>
				<option value="morning">Morning</option>
				<option value="preworkout">Pre-workout</option>
				<option value="afternoon">Afternoon</option>
				<option value="night">Night</option>
				<option value="withfood">With food</option>
			</select>
		</label>
	</div>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log supplement</button>
</section>

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			name: (fd.get('name') as string).trim(),
			dose: fd.get('dose') as string,
			timing: fd.get('timing') as string,
			notes: fd.get('notes') as string
		});
		toast.show('Updated');
		done();
	}}>
		<label>Name <input type="text" name="name" value={data.name} /></label>
		<div class="row">
			<label>Dose <input type="text" name="dose" value={data.dose} /></label>
			<label>
				Timing
				<select name="timing">
					<option value="morning" selected={data.timing === 'morning'}>Morning</option>
					<option value="preworkout" selected={data.timing === 'preworkout'}>Pre-workout</option>
					<option value="afternoon" selected={data.timing === 'afternoon'}>Afternoon</option>
					<option value="night" selected={data.timing === 'night'}>Night</option>
					<option value="withfood" selected={data.timing === 'withfood'}>With food</option>
				</select>
			</label>
		</div>
		<label>Notes <textarea name="notes" rows="2">{data.notes ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div><strong>{item.data.name}</strong> <span class="meta">{item.data.dose} · {item.data.timing}</span></div>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }

	h2 {
		font-size: 0.9rem;
		font-weight: 600;
		margin-bottom: 0.5rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}

	/* Planned Stack */
	.stack-section { padding: 0 1rem 1rem; }
	.stack-list { display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 0.75rem; }
	.stack-card {
		display: flex;
		align-items: center;
		justify-content: space-between;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.6rem 0.75rem;
	}
	.stack-info { display: flex; align-items: baseline; gap: 0.5rem; flex-wrap: wrap; }
	.stack-info strong { font-size: 0.9rem; }
	.stack-remove {
		background: none;
		border: none;
		color: var(--c-text-muted);
		cursor: pointer;
		padding: 0 0.25rem;
		line-height: 1;
		display: flex;
		align-items: center;
	}
	.stack-remove:hover { color: var(--c-cancel); }
	.stack-form {
		display: flex;
		gap: 0.4rem;
		flex-wrap: wrap;
	}
	.stack-form input,
	.stack-form select {
		flex: 1;
		min-width: 80px;
	}
	.stack-form button {
		white-space: nowrap;
	}
	.empty-hint {
		font-size: 0.85rem;
		color: var(--c-text-muted);
		margin-bottom: 0.75rem;
	}

	/* Adherence */
	.adherence-section { padding: 0 1rem 1rem; }
	.adherence-summary {
		font-size: 0.95rem;
		font-weight: 600;
		margin-bottom: 0.5rem;
	}
	.adherence-list { display: flex; flex-direction: column; gap: 0.3rem; }
	.adherence-item {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.4rem 0.6rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		font-size: 0.9rem;
	}
	.adherence-item.taken {
		border-color: var(--c-done);
		background: color-mix(in srgb, var(--c-done) 8%, transparent);
	}
	.adherence-check {
		width: 1.2rem;
		flex-shrink: 0;
		display: flex;
		align-items: center;
		justify-content: center;
	}
	.adherence-item.taken .adherence-check { color: var(--c-done); font-weight: 700; }
	.adherence-item:not(.taken) .adherence-check { color: var(--c-text-muted); }
	.adherence-name { font-weight: 500; }

	/* Weekly Chart */
	.chart-section { padding: 0 1rem 1.5rem; }
	.week-chart {
		width: 100%;
		height: 110px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}

	/* Most Logged */
	.top-section { padding: 0 1rem 1rem; }
	.top-list {
		list-style: none;
		margin: 0;
		padding: 0;
		display: flex;
		flex-direction: column;
		gap: 0.3rem;
	}
	.top-item {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.4rem 0.6rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		font-size: 0.9rem;
	}
	.top-rank { color: var(--c-text-muted); font-weight: 600; width: 1.5rem; }
	.top-name { flex: 1; text-transform: capitalize; }
	.top-count { color: var(--c-accent); font-weight: 600; }
</style>
