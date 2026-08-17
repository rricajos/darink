<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';

	const store = useEntries('signal.skin');

	let acneZone = $state('');
	let oiliness = $state(3);
	let elasticity = $state(5);
	let healing = $state(5);
	let notes = $state('');

	function submit() {
		entries.add('signal.skin', { acneZone, oiliness, elasticity, healing, notes });
		acneZone = ''; notes = '';
		toast.show('Skin logged');
	}
</script>

<PageHeader title="Skin" back="/signals" />

<section class="form">
	<label>Acne zone <input type="text" bind:value={acneZone} placeholder="Forehead, jaw, back..." /></label>
	<label>Oiliness ({oiliness}/5) <input type="range" min="1" max="5" bind:value={oiliness} /></label>
	<label>Elasticity ({elasticity}/10) <input type="range" min="1" max="10" bind:value={elasticity} /></label>
	<label>Healing speed ({healing}/10) <input type="range" min="1" max="10" bind:value={healing} /></label>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log skin</button>
</section>

<EntryList items={store.items} limit={10}>
	{#snippet row(item)}
		<div><span class="date">{new Date(item.createdAt).toLocaleDateString()}</span> <span class="meta">Oiliness: {item.data.oiliness}/5 · Elasticity: {item.data.elasticity}/10{item.data.acneZone ? ` · ${item.data.acneZone}` : ''}</span></div>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	input[type="range"] { padding: 0; }
	.date { font-weight: 600; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
</style>
