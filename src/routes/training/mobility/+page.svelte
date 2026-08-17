<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

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

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			routine: (fd.get('routine') as string).trim(),
			durationMin: Number(fd.get('durationMin')),
			notes: (fd.get('notes') as string).trim()
		});
		toast.show('Updated');
		done();
	}}>
		<label>Routine <input type="text" name="routine" value={data.routine} /></label>
		<label>Duration (min) <input type="number" name="durationMin" min="1" value={data.durationMin} /></label>
		<label>Notes <textarea name="notes" rows="2">{data.notes}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div><strong>{item.data.routine}</strong> <span class="meta">{item.data.durationMin}min</span></div>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }
</style>
