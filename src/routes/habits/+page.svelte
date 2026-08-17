<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
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

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.today { padding: 1rem; }
	h2 { font-size: 1rem; margin-bottom: 0.5rem; }
	.chips { display: flex; flex-wrap: wrap; gap: 0.25rem; }
	.chip {
		display: inline-block;
		padding: 0.35rem 0.75rem;
		background: var(--c-accent-bg);
		border: 1px solid var(--c-accent);
		border-radius: 20px;
		font-size: 0.85rem;
	}
</style>
