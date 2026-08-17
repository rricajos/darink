<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';

	const store = useEntries('training.cardio');

	let activity = $state('');
	let distanceKm = $state(0);
	let durationMin = $state(0);
	let zone = $state(2);
	let notes = $state('');

	function submit() {
		if (!activity.trim()) return;
		entries.add('training.cardio', { activity: activity.trim(), distanceKm, durationMin, zone, notes });
		activity = ''; notes = '';
		toast.show('Cardio logged');
	}
</script>

<PageHeader title="Cardio" back="/training" />

<section class="form">
	<label>Activity <input type="text" bind:value={activity} placeholder="Run, Bike, Swim..." /></label>
	<div class="row">
		<label>Distance (km) <input type="number" min="0" step="0.1" bind:value={distanceKm} /></label>
		<label>Duration (min) <input type="number" min="0" bind:value={durationMin} /></label>
		<label>Zone (1-5) <input type="number" min="1" max="5" bind:value={zone} /></label>
	</div>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log cardio</button>
</section>

<EntryList items={store.items}>
	{#snippet row(item)}
		<div><strong>{item.data.activity}</strong> <span class="meta">{item.data.distanceKm}km · {item.data.durationMin}min · Z{item.data.zone}</span></div>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
</style>
