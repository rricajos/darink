<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('supplement');

	let date = $state(new Date().toISOString().slice(0, 10));
	let name = $state('');
	let dose = $state('');
	let timing = $state('morning');
	let notes = $state('');

	function submit() {
		if (!name.trim()) return;
		entries.add('supplement', { date, name: name.trim(), dose, timing, notes });
		date = new Date().toISOString().slice(0, 10);
		name = ''; dose = ''; notes = '';
		toast.show('Supplement logged');
	}
</script>

<PageHeader title="Supplements" />

<section class="form">
	<label>Date <input type="date" bind:value={date} /></label>
	<label>Name <input type="text" bind:value={name} placeholder="Zinc, Magnesium, Ashwagandha..." /></label>
	<div class="row">
		<label>Dose <input type="text" bind:value={dose} placeholder="30mg, 2 caps..." /></label>
		<label>
			Timing
			<select bind:value={timing}>
				<option value="morning">Morning</option>
				<option value="preworkout">Pre-workout</option>
				<option value="afternoon">Afternoon</option>
				<option value="night">Night</option>
				<option value="withfood">With food</option>
			</select>
		</label>
	</div>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log supplement</button>
</section>

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			name: (fd.get('name') as string).trim(),
			dose: fd.get('dose') as string,
			timing: fd.get('timing') as string,
			notes: fd.get('notes') as string
		});
		toast.show('Updated');
		done();
	}}>
		<label>Name <input type="text" name="name" value={data.name} /></label>
		<div class="row">
			<label>Dose <input type="text" name="dose" value={data.dose} /></label>
			<label>
				Timing
				<select name="timing">
					<option value="morning" selected={data.timing === 'morning'}>Morning</option>
					<option value="preworkout" selected={data.timing === 'preworkout'}>Pre-workout</option>
					<option value="afternoon" selected={data.timing === 'afternoon'}>Afternoon</option>
					<option value="night" selected={data.timing === 'night'}>Night</option>
					<option value="withfood" selected={data.timing === 'withfood'}>With food</option>
				</select>
			</label>
		</div>
		<label>Notes <textarea name="notes" rows="2">{data.notes ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div><strong>{item.data.name}</strong> <span class="meta">{item.data.dose} · {item.data.timing}</span></div>
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
