<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';

	const store = useEntries('training.rings');

	let progression = $state('');
	let holdTime = $state(0);
	let reps = $state(1);
	let assistance = $state('none');
	let level = $state(1);
	let notes = $state('');

	function submit() {
		if (!progression.trim()) return;
		entries.add('training.rings', { progression: progression.trim(), holdTime, reps, assistance, level, notes });
		progression = ''; notes = '';
		toast.show('Progression logged');
	}
</script>

<PageHeader title="Rings" back="/training" />

<section class="form">
	<label>Progression <input type="text" bind:value={progression} placeholder="Front lever, Muscle-up..." /></label>
	<div class="row">
		<label>Hold (s) <input type="number" min="0" bind:value={holdTime} /></label>
		<label>Reps <input type="number" min="1" max="50" bind:value={reps} /></label>
		<label>Level <input type="number" min="1" max="10" bind:value={level} /></label>
	</div>
	<label>Assistance
		<select bind:value={assistance}>
			<option value="none">None</option>
			<option value="band">Band</option>
			<option value="partial">Partial</option>
			<option value="negative">Negative</option>
		</select>
	</label>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log progression</button>
</section>

<EntryList items={store.items}>
	{#snippet row(item)}
		<div><strong>{item.data.progression}</strong> <span class="meta">L{item.data.level} · {item.data.holdTime}s × {item.data.reps}</span></div>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
</style>
