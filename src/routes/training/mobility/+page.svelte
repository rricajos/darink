<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('training.mobility');

	let date = $state(new Date().toISOString().slice(0, 10));
	let routine = $state('');
	let durationMin = $state(15);
	let notes = $state('');

	function submit() {
		if (!routine.trim()) return;
		entries.add('training.mobility', { date, routine: routine.trim(), durationMin, notes });
		routine = ''; notes = '';
		date = new Date().toISOString().slice(0, 10);
		toast.show('Mobility logged');
	}

	function repeatLast() {
		const last = store.items.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt))[0];
		if (!last) return;
		routine = last.data.routine as string;
		durationMin = last.data.durationMin as number;
		notes = (last.data.notes as string) || '';
		toast.show('Fields pre-filled');
	}
</script>

<svelte:head>
  <title>Mobility | Darink</title>
</svelte:head>

<PageHeader title="Mobility" back="/training" />

<section class="form">
	<label>Date <input type="date" bind:value={date} /></label>
	<label>Routine <input type="text" bind:value={routine} placeholder="Hip opener, Shoulder..." /></label>
	<label>Duration (min) <input type="number" min="1" bind:value={durationMin} /></label>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<div class="form-actions">
		<button class="primary" onclick={submit}>Log session</button>
		{#if store.items.length > 0}
			<button onclick={repeatLast}>Repeat last</button>
		{/if}
	</div>
</section>

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			date: fd.get('date') as string,
			routine: (fd.get('routine') as string).trim(),
			durationMin: Number(fd.get('durationMin')),
			notes: (fd.get('notes') as string).trim()
		});
		toast.show('Updated');
		done();
	}}>
		<label>Date <input type="date" name="date" value={data.date || ''} /></label>
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
	.form-actions { display: flex; gap: 0.5rem; }
	.form-actions button { flex: 1; }
</style>
