<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

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

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			exercise: (fd.get('exercise') as string).trim(),
			sets: Number(fd.get('sets')),
			reps: Number(fd.get('reps')),
			weight: Number(fd.get('weight')),
			rir: Number(fd.get('rir')),
			notes: (fd.get('notes') as string).trim()
		});
		toast.show('Updated');
		done();
	}}>
		<label>Exercise <input type="text" name="exercise" value={data.exercise} /></label>
		<div class="row">
			<label>Sets <input type="number" name="sets" min="1" max="20" value={data.sets} /></label>
			<label>Reps <input type="number" name="reps" min="1" max="100" value={data.reps} /></label>
			<label>Weight (kg) <input type="number" name="weight" min="0" step="0.5" value={data.weight} /></label>
			<label>RIR <input type="number" name="rir" min="0" max="10" value={data.rir} /></label>
		</div>
		<label>Notes <textarea name="notes" rows="2">{data.notes}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div><strong>{item.data.exercise}</strong> <span class="meta">{item.data.sets}×{item.data.reps} @ {item.data.weight}kg RIR{item.data.rir}</span></div>
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
</style>
