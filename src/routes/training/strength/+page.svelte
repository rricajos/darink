<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries';
	import { toast } from '$lib/stores/toast';

	const store = useEntries('training.strength');

	let exercise = $state('');
	let sets = $state(3);
	let reps = $state(10);
	let weight = $state(0);
	let rir = $state(2);
	let notes = $state('');

	function submit() {
		if (!exercise.trim()) return;
		entries.add('training.strength', { exercise: exercise.trim(), sets, reps, weight, rir, notes });
		exercise = ''; notes = '';
		toast.show('Set logged');
	}
</script>

<PageHeader title="Strength" back="/training" />

<section class="form">
	<label>Exercise <input type="text" bind:value={exercise} placeholder="Squat, Bench..." /></label>
	<div class="row">
		<label>Sets <input type="number" min="1" max="20" bind:value={sets} /></label>
		<label>Reps <input type="number" min="1" max="100" bind:value={reps} /></label>
		<label>Weight (kg) <input type="number" min="0" step="0.5" bind:value={weight} /></label>
		<label>RIR <input type="number" min="0" max="10" bind:value={rir} /></label>
	</div>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log set</button>
</section>

<EntryList items={store.items}>
	{#snippet row(item)}
		<div><strong>{item.data.exercise}</strong> <span class="meta">{item.data.sets}×{item.data.reps} @ {item.data.weight}kg RIR{item.data.rir}</span></div>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.5rem; }
	.row label { flex: 1; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
</style>
