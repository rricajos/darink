<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('training.rings');

	let date = $state(new Date().toISOString().slice(0, 10));
	let progression = $state('');
	let holdTime = $state(0);
	let reps = $state(1);
	let assistance = $state('none');
	let level = $state(1);
	let notes = $state('');

	function submit() {
		if (!progression.trim()) return;
		entries.add('training.rings', { date, progression: progression.trim(), holdTime, reps, assistance, level, notes });
		progression = ''; notes = '';
		date = new Date().toISOString().slice(0, 10);
		toast.show('Progression logged');
	}

	function repeatLast() {
		const last = store.items.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt))[0];
		if (!last) return;
		progression = last.data.progression as string;
		holdTime = last.data.holdTime as number;
		reps = last.data.reps as number;
		assistance = (last.data.assistance as string) || 'none';
		level = last.data.level as number;
		notes = (last.data.notes as string) || '';
		toast.show('Fields pre-filled');
	}
</script>

<PageHeader title="Rings" back="/training" />

<section class="form">
	<label>Date <input type="date" bind:value={date} /></label>
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
	<div class="form-actions">
		<button class="primary" onclick={submit}>Log progression</button>
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
			progression: (fd.get('progression') as string).trim(),
			holdTime: Number(fd.get('holdTime')),
			reps: Number(fd.get('reps')),
			level: Number(fd.get('level')),
			assistance: fd.get('assistance') as string,
			notes: (fd.get('notes') as string).trim()
		});
		toast.show('Updated');
		done();
	}}>
		<label>Date <input type="date" name="date" value={data.date || ''} /></label>
		<label>Progression <input type="text" name="progression" value={data.progression} /></label>
		<div class="row">
			<label>Hold (s) <input type="number" name="holdTime" min="0" value={data.holdTime} /></label>
			<label>Reps <input type="number" name="reps" min="1" max="50" value={data.reps} /></label>
			<label>Level <input type="number" name="level" min="1" max="10" value={data.level} /></label>
		</div>
		<label>Assistance
			<select name="assistance">
				<option value="none" selected={data.assistance === 'none'}>None</option>
				<option value="band" selected={data.assistance === 'band'}>Band</option>
				<option value="partial" selected={data.assistance === 'partial'}>Partial</option>
				<option value="negative" selected={data.assistance === 'negative'}>Negative</option>
			</select>
		</label>
		<label>Notes <textarea name="notes" rows="2">{data.notes}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div><strong>{item.data.progression}</strong> <span class="meta">L{item.data.level} · {item.data.holdTime}s × {item.data.reps}</span></div>
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
	.form-actions { display: flex; gap: 0.5rem; }
	.form-actions button { flex: 1; }
</style>
