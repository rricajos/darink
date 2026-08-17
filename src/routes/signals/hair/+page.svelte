<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';

	const store = useEntries('signal.hair');

	let zone = $state('');
	let density = $state(5);
	let shedding = $state(3);
	let miniaturization = $state(false);
	let notes = $state('');

	function submit() {
		entries.add('signal.hair', { zone, density, shedding, miniaturization, notes });
		zone = ''; notes = '';
		toast.show('Hair logged');
	}
</script>

<PageHeader title="Hair" back="/signals" />

<section class="form">
	<label>Zone <input type="text" bind:value={zone} placeholder="Temples, crown, beard..." /></label>
	<label>Density ({density}/10) <input type="range" min="1" max="10" bind:value={density} /></label>
	<label>Shedding ({shedding}/10) <input type="range" min="1" max="10" bind:value={shedding} /></label>
	<label class="checkbox"><input type="checkbox" bind:checked={miniaturization} /> Miniaturization visible</label>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log hair</button>
</section>

<EntryList items={store.items} limit={10}>
	{#snippet row(item)}
		<div><span class="date">{new Date(item.createdAt).toLocaleDateString()}</span> <span class="meta">Zone: {item.data.zone || '—'} · Density: {item.data.density}/10 · Shedding: {item.data.shedding}/10</span></div>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	input[type="range"] { padding: 0; }
	.checkbox { flex-direction: row; align-items: center; gap: 0.5rem; }
	.checkbox input { width: auto; }
	.date { font-weight: 600; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
</style>
