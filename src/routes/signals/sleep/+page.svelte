<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';

	const store = useEntries('signal.sleep');

	let hours = $state(7);
	let quality = $state(5);
	let dreams = $state(false);
	let bedtime = $state('23:00');
	let wakeTime = $state('07:00');
	let notes = $state('');

	function submit() {
		entries.add('signal.sleep', { hours, quality, dreams, bedtime, wakeTime, notes });
		notes = '';
		toast.show('Sleep logged');
	}
</script>

<PageHeader title="Sleep" back="/signals" />

<section class="form">
	<label>Hours ({hours}) <input type="number" min="0" max="14" step="0.5" bind:value={hours} /></label>
	<label>Quality ({quality}/10) <input type="range" min="1" max="10" bind:value={quality} /></label>
	<div class="row">
		<label>Bedtime <input type="time" bind:value={bedtime} /></label>
		<label>Wake <input type="time" bind:value={wakeTime} /></label>
	</div>
	<label class="checkbox"><input type="checkbox" bind:checked={dreams} /> Vivid dreams</label>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log sleep</button>
</section>

<EntryList items={store.items} limit={7}>
	{#snippet row(item)}
		<div><span class="date">{new Date(item.createdAt).toLocaleDateString()}</span> <span class="meta">{item.data.hours}h · Q{item.data.quality}/10</span></div>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }
	.checkbox { flex-direction: row; align-items: center; gap: 0.5rem; }
	.checkbox input { width: auto; }
	input[type="range"] { padding: 0; }
	.date { font-weight: 600; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
</style>
