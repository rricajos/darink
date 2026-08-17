<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';

	const store = useEntries('supplement');

	let name = $state('');
	let dose = $state('');
	let timing = $state('morning');
	let notes = $state('');

	function submit() {
		if (!name.trim()) return;
		entries.add('supplement', { name: name.trim(), dose, timing, notes });
		name = ''; dose = ''; notes = '';
		toast.show('Supplement logged');
	}
</script>

<PageHeader title="Supplements" />

<section class="form">
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

<EntryList items={store.items}>
	{#snippet row(item)}
		<div><strong>{item.data.name}</strong> <span class="meta">{item.data.dose} · {item.data.timing}</span></div>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.5rem; }
	.row label { flex: 1; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
</style>
