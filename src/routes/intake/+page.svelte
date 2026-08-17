<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { onMount } from 'svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('intake');

	let what = $state('');
	let amount = $state('normal');
	let moodVal = $state('verde');
	let timeStart = $state('');
	let timeEnd = $state('');

	onMount(() => {
		const now = new Date();
		const hh = String(now.getHours()).padStart(2, '0');
		const mm = String(now.getMinutes()).padStart(2, '0');
		timeStart = `${hh}:${mm}`;
		timeEnd = `${hh}:${mm}`;
	});

	function submit() {
		if (!what.trim()) return;
		const today = new Date().toISOString().slice(0, 10);
		entries.add('intake', {
			what: what.trim(), amount, mood: moodVal,
			whenStart: `${today} ${timeStart}`,
			whenEnd: `${today} ${timeEnd}`
		});
		what = '';
		toast.show('Intake logged');
	}
</script>

<PageHeader title="Intake" />

<section class="form">
	<label>What <input type="text" bind:value={what} placeholder="Food, drink..." /></label>
	<div class="row">
		<label>
			Amount
			<select bind:value={amount}>
				<option value="poco">Small</option>
				<option value="normal">Normal</option>
				<option value="mucho">Large</option>
			</select>
		</label>
		<label>
			Mood
			<select bind:value={moodVal}>
				<option value="verde">Good</option>
				<option value="ambar">Neutral</option>
				<option value="rojo">Bad</option>
			</select>
		</label>
	</div>
	<div class="row">
		<label>Start <input type="time" bind:value={timeStart} /></label>
		<label>End <input type="time" bind:value={timeEnd} /></label>
	</div>
	<button class="primary" onclick={submit}>Add entry</button>
</section>

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		const today = new Date().toISOString().slice(0, 10);
		entries.update(item.id, {
			what: (fd.get('what') as string).trim(),
			amount: fd.get('amount') as string,
			mood: fd.get('mood') as string,
			whenStart: `${today} ${fd.get('timeStart')}`,
			whenEnd: `${today} ${fd.get('timeEnd')}`
		});
		toast.show('Updated');
		done();
	}}>
		<label>What <input type="text" name="what" value={data.what} /></label>
		<div class="row">
			<label>
				Amount
				<select name="amount">
					<option value="poco" selected={data.amount === 'poco'}>Small</option>
					<option value="normal" selected={data.amount === 'normal'}>Normal</option>
					<option value="mucho" selected={data.amount === 'mucho'}>Large</option>
				</select>
			</label>
			<label>
				Mood
				<select name="mood">
					<option value="verde" selected={data.mood === 'verde'}>Good</option>
					<option value="ambar" selected={data.mood === 'ambar'}>Neutral</option>
					<option value="rojo" selected={data.mood === 'rojo'}>Bad</option>
				</select>
			</label>
		</div>
		<div class="row">
			<label>Start <input type="time" name="timeStart" value={(data.whenStart as string)?.split(' ')[1] ?? ''} /></label>
			<label>End <input type="time" name="timeEnd" value={(data.whenEnd as string)?.split(' ')[1] ?? ''} /></label>
		</div>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div class="intake-row">
			<span class="ball {item.data.mood} {item.data.amount === 'poco' ? 'small' : item.data.amount === 'mucho' ? 'large' : ''}"></span>
			<strong>{item.data.what}</strong>
			<span class="time">{(item.data.whenStart as string)?.split(' ')[1] ?? ''}</span>
		</div>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.75rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }

	.intake-row { display: flex; align-items: center; gap: 0.5rem; }
	.intake-row strong { flex: 1; }
	.time { font-size: 0.85rem; color: var(--c-text-muted); }

	.ball { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
	.ball:global(.verde) { background: #228b22; }
	.ball:global(.ambar) { background: #ff8c00; }
	.ball:global(.rojo) { background: #dc143c; }
	.ball:global(.small) { transform: scale(0.7); }
	.ball:global(.large) { transform: scale(1.3); }

	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }
</style>
