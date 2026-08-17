<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';

	const store = useEntries('checkin');

	let mood = $state(5);
	let energy = $state(5);
	let sleep = $state(7);
	let stress = $state(3);
	let morningErection = $state(false);
	let notes = $state('');

	function submit() {
		entries.add('checkin', {
			mood, energy, sleep, stress, morningErection, notes,
			period: new Date().getHours() < 14 ? 'morning' : 'night'
		});
		mood = 5; energy = 5; sleep = 7; stress = 3;
		morningErection = false; notes = '';
		toast.show('Check-in saved');
	}
</script>

<PageHeader title="Check-in" />

<section class="form">
	<label>Mood ({mood}/10) <input type="range" min="1" max="10" bind:value={mood} /></label>
	<label>Energy ({energy}/10) <input type="range" min="1" max="10" bind:value={energy} /></label>
	<label>Sleep hours ({sleep}) <input type="number" min="0" max="14" step="0.5" bind:value={sleep} /></label>
	<label>Stress ({stress}/10) <input type="range" min="1" max="10" bind:value={stress} /></label>
	<label class="checkbox"><input type="checkbox" bind:checked={morningErection} /> Morning erection</label>
	<label>Notes <textarea bind:value={notes} placeholder="How do you feel?" rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Save check-in</button>
</section>

<EntryList items={store.items} limit={7}>
	{#snippet row(item)}
		<span><strong>{item.data.period === 'morning' ? '☀' : '🌙'}</strong> M{item.data.mood} E{item.data.energy} S{item.data.stress} · {item.data.sleep}h</span>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem 1rem; }
	.checkbox { flex-direction: row; align-items: center; gap: 0.5rem; }
	.checkbox input { width: auto; }
	input[type="range"] { padding: 0; }
</style>
