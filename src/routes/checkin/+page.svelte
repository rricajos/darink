<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('checkin');

	let date = $state(new Date().toISOString().slice(0, 10));
	let mood = $state(5);
	let energy = $state(5);
	let sleep = $state(7);
	let stress = $state(3);
	let morningErection = $state(false);
	let notes = $state('');

	function submit() {
		entries.add('checkin', {
			date, mood, energy, sleep, stress, morningErection, notes,
			period: new Date().getHours() < 14 ? 'morning' : 'night'
		});
		date = new Date().toISOString().slice(0, 10);
		mood = 5; energy = 5; sleep = 7; stress = 3;
		morningErection = false; notes = '';
		toast.show('Check-in saved');
	}
</script>

<PageHeader title="Check-in" />

<section class="form">
	<label>Date <input type="date" bind:value={date} /></label>
	<label>Mood ({mood}/10) <input type="range" min="1" max="10" bind:value={mood} /></label>
	<label>Energy ({energy}/10) <input type="range" min="1" max="10" bind:value={energy} /></label>
	<label>Sleep hours ({sleep}) <input type="number" min="0" max="14" step="0.5" bind:value={sleep} /></label>
	<label>Stress ({stress}/10) <input type="range" min="1" max="10" bind:value={stress} /></label>
	<label class="checkbox"><input type="checkbox" bind:checked={morningErection} /> Morning erection</label>
	<label>Notes <textarea bind:value={notes} placeholder="How do you feel?" rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Save check-in</button>
</section>

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			mood: Number(fd.get('mood')),
			energy: Number(fd.get('energy')),
			sleep: Number(fd.get('sleep')),
			stress: Number(fd.get('stress')),
			morningErection: fd.has('morningErection'),
			notes: fd.get('notes') ?? ''
		});
		toast.show('Updated');
		done();
	}}>
		<label>Mood ({data.mood}/10) <input type="range" name="mood" min="1" max="10" value={data.mood} /></label>
		<label>Energy ({data.energy}/10) <input type="range" name="energy" min="1" max="10" value={data.energy} /></label>
		<label>Sleep hours <input type="number" name="sleep" min="0" max="14" step="0.5" value={data.sleep} /></label>
		<label>Stress ({data.stress}/10) <input type="range" name="stress" min="1" max="10" value={data.stress} /></label>
		<label class="checkbox"><input type="checkbox" name="morningErection" checked={!!data.morningErection} /> Morning erection</label>
		<label>Notes <textarea name="notes" rows="2">{data.notes ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} limit={7} {editForm}>
	{#snippet row(item)}
		<span><strong>{item.data.period === 'morning' ? '☀' : '🌙'}</strong> M{item.data.mood} E{item.data.energy} S{item.data.stress} · {item.data.sleep}h</span>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem 1rem; }
	.checkbox { flex-direction: row; align-items: center; gap: 0.5rem; }
	.checkbox input { width: auto; }
	input[type="range"] { padding: 0; }
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-inline input[type="range"] { padding: 0; }
	.edit-inline .checkbox { flex-direction: row; align-items: center; gap: 0.5rem; }
	.edit-inline .checkbox input { width: auto; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }
</style>
