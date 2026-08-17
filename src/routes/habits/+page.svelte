<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';

	const store = useEntries('habit');

	const habitTypes = [
		{ id: 'cold', label: 'Cold exposure', unit: 'min' },
		{ id: 'sun', label: 'Sun exposure', unit: 'min' },
		{ id: 'fasting', label: 'Fasting', unit: 'hours' },
		{ id: 'meditation', label: 'Meditation', unit: 'min' },
		{ id: 'wimhof', label: 'Wim Hof', unit: 'rounds' },
		{ id: 'ejaculation', label: 'Ejaculation control', unit: 'days' }
	];

	let date = $state(new Date().toISOString().slice(0, 10));
	let selectedHabit = $state('cold');
	let duration = $state(0);
	let notes = $state('');

	const todayItems = $derived.by(() => {
		const today = new Date().toISOString().slice(0, 10);
		return store.items.filter((e) => e.createdAt.startsWith(today));
	});

	const selectedUnit = $derived(habitTypes.find((h) => h.id === selectedHabit)?.unit ?? '');

	const streaks = $derived.by(() => {
		const result: Record<string, { current: number; best: number; total: number }> = {};
		for (const h of habitTypes) {
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

	function submit() {
		entries.add('habit', { date, habit: selectedHabit, duration, notes });
		date = new Date().toISOString().slice(0, 10);
		duration = 0; notes = '';
		toast.show('Habit logged');
	}

	function getLabel(id: string): string {
		return habitTypes.find((h) => h.id === id)?.label ?? id;
	}

	function getUnit(id: string): string {
		return habitTypes.find((h) => h.id === id)?.unit ?? '';
	}
</script>

<PageHeader title="Habits" />

<section class="form">
	<label>Date <input type="date" bind:value={date} /></label>
	<label>
		Habit
		<select bind:value={selectedHabit}>
			{#each habitTypes as h}
				<option value={h.id}>{h.label}</option>
			{/each}
		</select>
	</label>
	<label>Duration ({selectedUnit}) <input type="number" min="0" step="1" bind:value={duration} /></label>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log habit</button>
</section>

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

<EntryList items={store.items} limit={20}>
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

	@media (min-width: 600px) {
		.streak-grid { grid-template-columns: repeat(3, 1fr); }
	}
</style>
