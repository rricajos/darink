<script lang="ts">
	import { onMount } from 'svelte';
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { ui } from '$lib/db';
	import type { Entry } from '$lib/db';

	const store = useEntries('habit');

	const defaultHabits = [
		{ id: 'cold', label: 'Cold exposure', unit: 'min' },
		{ id: 'sun', label: 'Sun exposure', unit: 'min' },
		{ id: 'fasting', label: 'Fasting', unit: 'hours' },
		{ id: 'meditation', label: 'Meditation', unit: 'min' },
		{ id: 'wimhof', label: 'Wim Hof', unit: 'rounds' },
		{ id: 'ejaculation', label: 'Ejaculation control', unit: 'days' }
	];

	let customHabits = $state<Array<{id: string, label: string, unit: string}>>([]);
	const allHabitTypes = $derived([...defaultHabits, ...customHabits]);

	onMount(() => {
		const stored = ui.get().customHabits;
		if (Array.isArray(stored)) {
			customHabits = stored as Array<{id: string, label: string, unit: string}>;
		}
	});

	let date = $state(new Date().toISOString().slice(0, 10));
	let selectedHabit = $state('cold');
	let duration = $state(0);
	let notes = $state('');

	let manageOpen = $state(false);
	let newLabel = $state('');
	let newUnit = $state('');

	const todayItems = $derived.by(() => {
		const today = new Date().toISOString().slice(0, 10);
		return store.items.filter((e) => e.createdAt.startsWith(today));
	});

	const selectedUnit = $derived(allHabitTypes.find((h) => h.id === selectedHabit)?.unit ?? '');

	const streaks = $derived.by(() => {
		const result: Record<string, { current: number; best: number; total: number }> = {};
		for (const h of allHabitTypes) {
			const dates = new Set(
				store.items
					.filter((e) => e.data.habit === h.id)
					.map((e) => (e.data.date as string) ?? e.createdAt.slice(0, 10))
			);
			const total = dates.size;
			let current = 0;
			let best = 0;
			let streak = 0;
			const today = new Date();
			for (let i = 0; i < 365; i++) {
				const d = new Date(today);
				d.setDate(d.getDate() - i);
				const key = d.toISOString().slice(0, 10);
				if (dates.has(key)) {
					streak++;
					if (i === current) current = streak;
					if (streak > best) best = streak;
				} else {
					streak = 0;
					if (i === 0) current = 0;
				}
			}
			if (total > 0) result[h.id] = { current, best, total };
		}
		return result;
	});

	const defaultIds = new Set(defaultHabits.map((h) => h.id));

	function submit() {
		entries.add('habit', { date, habit: selectedHabit, duration, notes });
		date = new Date().toISOString().slice(0, 10);
		duration = 0; notes = '';
		toast.show('Habit logged');
	}

	function addCustomHabit() {
		const label = newLabel.trim();
		const unit = newUnit.trim();
		if (!label || !unit) {
			toast.show('Label and unit are required');
			return;
		}
		const id = label.toLowerCase().replace(/\s+/g, '');
		if (allHabitTypes.some((h) => h.id === id)) {
			toast.show('A habit with that name already exists');
			return;
		}
		customHabits = [...customHabits, { id, label, unit }];
		ui.patch({ customHabits });
		newLabel = '';
		newUnit = '';
		toast.show('Habit type added');
	}

	function removeCustomHabit(id: string) {
		customHabits = customHabits.filter((h) => h.id !== id);
		ui.patch({ customHabits });
		if (selectedHabit === id) selectedHabit = 'cold';
		toast.show('Habit type removed');
	}

	function getLabel(id: string): string {
		return allHabitTypes.find((h) => h.id === id)?.label ?? id;
	}

	function getUnit(id: string): string {
		return allHabitTypes.find((h) => h.id === id)?.unit ?? '';
	}
</script>

<PageHeader title="Habits" />

<section class="form">
	<label>Date <input type="date" bind:value={date} /></label>
	<label>
		Habit
		<select bind:value={selectedHabit}>
			{#each allHabitTypes as h}
				<option value={h.id}>{h.label}</option>
			{/each}
		</select>
	</label>
	<label>Duration ({selectedUnit}) <input type="number" min="0" step="1" bind:value={duration} /></label>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log habit</button>
</section>

<section class="manage-section">
	<button class="manage-toggle" onclick={() => manageOpen = !manageOpen}>
		<span>Manage habit types</span>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class:rotate={manageOpen}><path d="m6 9 6 6 6-6"/></svg>
	</button>
	{#if manageOpen}
		<div class="manage-body">
			<div class="manage-add">
				<label>Label <input type="text" bind:value={newLabel} placeholder="e.g. Stretching" /></label>
				<label>Unit <input type="text" bind:value={newUnit} placeholder="e.g. min" /></label>
				<button class="primary" onclick={addCustomHabit}>Add habit type</button>
			</div>
			<ul class="habit-type-list">
				{#each allHabitTypes as h}
					<li class="habit-type-item">
						<span><strong>{h.label}</strong> <span class="unit-tag">({h.unit})</span></span>
						{#if !defaultIds.has(h.id)}
							<button class="remove-btn" onclick={() => removeCustomHabit(h.id)} title="Remove">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
							</button>
						{/if}
					</li>
				{/each}
			</ul>
		</div>
	{/if}
</section>

{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
	<p>No habits logged yet</p>
	<p class="empty-hint">Log your first habit to start building streaks.</p>
</div>
{/if}

{#if todayItems.length > 0}
	<section class="today">
		<h2>Today</h2>
		<div class="chips">
			{#each todayItems as item}
				<span class="chip">{getLabel(item.data.habit as string)} · {item.data.duration}{getUnit(item.data.habit as string)}</span>
			{/each}
		</div>
	</section>
{/if}

{#if Object.keys(streaks).length > 0}
	<section class="streaks">
		<h2>Streaks</h2>
		<div class="streak-grid">
			{#each Object.entries(streaks) as [id, s]}
				<div class="streak-card" class:active={s.current > 0}>
					<strong>{getLabel(id)}</strong>
					<div class="streak-nums">
						<span class="streak-current">{s.current}d</span>
						<span class="streak-meta">Best {s.best}d · {s.total} total</span>
					</div>
				</div>
			{/each}
		</div>
	</section>
{/if}

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			date: (fd.get('date') as string),
			habit: fd.get('habit') as string,
			duration: Number(fd.get('duration')),
			notes: (fd.get('notes') as string).trim()
		});
		toast.show('Updated');
		done();
	}}>
		<label>Date <input type="date" name="date" value={data.date as string ?? ''} /></label>
		<label>
			Habit
			<select name="habit">
				{#each allHabitTypes as h}
					<option value={h.id} selected={data.habit === h.id}>{h.label}</option>
				{/each}
			</select>
		</label>
		<label>Duration <input type="number" name="duration" min="0" step="1" value={data.duration} /></label>
		<label>Notes <textarea name="notes" rows="2">{data.notes ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm} limit={20}>
	{#snippet row(item)}
		<span>
			<strong>{getLabel(item.data.habit as string)}</strong>
			{item.data.duration}{getUnit(item.data.habit as string)}
			<span class="date">{(item.data.date as string) ?? item.createdAt.slice(0, 10)}</span>
		</span>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.today { padding: 1rem; }
	h2 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
	.chips { display: flex; flex-wrap: wrap; gap: 0.25rem; }
	.chip {
		display: inline-block;
		padding: 0.35rem 0.75rem;
		background: var(--c-accent-bg);
		border: 1px solid var(--c-accent);
		border-radius: 20px;
		font-size: 0.85rem;
	}

	.streaks { padding: 1rem; }
	.streak-grid {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 0.5rem;
	}
	.streak-card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem;
	}
	.streak-card.active {
		border-color: var(--c-accent);
		background: var(--c-accent-bg);
	}
	.streak-card strong {
		display: block;
		font-size: 0.8rem;
		margin-bottom: 0.25rem;
	}
	.streak-nums { display: flex; align-items: baseline; gap: 0.5rem; }
	.streak-current { font-size: 1.5rem; font-weight: 700; color: var(--c-accent); }
	.streak-meta { font-size: 0.75rem; color: var(--c-text-muted); }

	.date { font-size: 0.8rem; color: var(--c-text-muted); margin-left: 0.25rem; }

	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }

	.manage-section { padding: 0 1rem; margin-top: 0.5rem; }
	.manage-toggle {
		display: flex;
		align-items: center;
		justify-content: space-between;
		width: 100%;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.6rem 0.75rem;
		font-size: 0.85rem;
		font-weight: 600;
		color: var(--c-text-muted);
		cursor: pointer;
	}
	.manage-toggle svg { transition: transform 0.2s; }
	.manage-toggle svg.rotate { transform: rotate(180deg); }
	.manage-body {
		border: 1px solid var(--c-border);
		border-top: none;
		border-radius: 0 0 var(--radius) var(--radius);
		padding: 0.75rem;
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}
	.manage-add {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}
	.habit-type-list {
		list-style: none;
		margin: 0;
		padding: 0;
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
	}
	.habit-type-item {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0.4rem 0.5rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		font-size: 0.85rem;
	}
	.unit-tag { color: var(--c-text-muted); font-size: 0.8rem; }
	.remove-btn {
		display: flex;
		align-items: center;
		justify-content: center;
		background: none;
		border: none;
		color: var(--c-text-muted);
		cursor: pointer;
		padding: 0.15rem;
		border-radius: 4px;
	}
	.remove-btn:hover { color: var(--c-danger, #e53e3e); background: var(--c-accent-bg); }

	@media (min-width: 600px) {
		.streak-grid { grid-template-columns: repeat(3, 1fr); }
	}
</style>
