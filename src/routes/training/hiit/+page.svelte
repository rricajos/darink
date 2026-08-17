<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('training.hiit');

	let name = $state('');
	let rounds = $state(8);
	let workSec = $state(20);
	let restSec = $state(10);
	let maxHr = $state(0);
	let notes = $state('');

	function submit() {
		if (!name.trim()) return;
		entries.add('training.hiit', { name: name.trim(), rounds, workSec, restSec, maxHr, notes });
		name = ''; notes = '';
		toast.show('HIIT logged');
	}
</script>

<PageHeader title="HIIT" back="/training" />

<section class="form">
	<label>Name <input type="text" bind:value={name} placeholder="Tabata, Sprint..." /></label>
	<div class="row">
		<label>Rounds <input type="number" min="1" max="50" bind:value={rounds} /></label>
		<label>Work (s) <input type="number" min="1" bind:value={workSec} /></label>
		<label>Rest (s) <input type="number" min="0" bind:value={restSec} /></label>
	</div>
	<label>Max HR <input type="number" min="0" max="250" bind:value={maxHr} /></label>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log session</button>
</section>

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			name: (fd.get('name') as string).trim(),
			rounds: Number(fd.get('rounds')),
			workSec: Number(fd.get('workSec')),
			restSec: Number(fd.get('restSec')),
			maxHr: Number(fd.get('maxHr')),
			notes: (fd.get('notes') as string).trim()
		});
		toast.show('Updated');
		done();
	}}>
		<label>Name <input type="text" name="name" value={data.name} /></label>
		<div class="row">
			<label>Rounds <input type="number" name="rounds" min="1" max="50" value={data.rounds} /></label>
			<label>Work (s) <input type="number" name="workSec" min="1" value={data.workSec} /></label>
			<label>Rest (s) <input type="number" name="restSec" min="0" value={data.restSec} /></label>
		</div>
		<label>Max HR <input type="number" name="maxHr" min="0" max="250" value={data.maxHr} /></label>
		<label>Notes <textarea name="notes" rows="2">{data.notes}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div><strong>{item.data.name}</strong> <span class="meta">{item.data.rounds}r · {item.data.workSec}s/{item.data.restSec}s</span></div>
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
