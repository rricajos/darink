<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries';
	import { toast } from '$lib/stores/toast';

	const store = useEntries('training.mobility');

	let routine = $state('');
	let durationMin = $state(15);
	let notes = $state('');

	function submit() {
		if (!routine.trim()) return;
		entries.add('training.mobility', { routine: routine.trim(), durationMin, notes });
		routine = ''; notes = '';
		toast.show('Mobility logged');
	}
</script>

<PageHeader title="Mobility" back="/training" />

<section class="form">
	<label>Routine <input type="text" bind:value={routine} placeholder="Hip opener, Shoulder..." /></label>
	<label>Duration (min) <input type="number" min="1" bind:value={durationMin} /></label>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log session</button>
</section>

<EntryList items={store.items}>
	{#snippet row(item)}
		<div><strong>{item.data.routine}</strong> <span class="meta">{item.data.durationMin}min</span></div>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
</style>
