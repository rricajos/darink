<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('training.cardio');

	let date = $state(new Date().toISOString().slice(0, 10));
	let activity = $state('');
	let distanceKm = $state(0);
	let durationMin = $state(0);
	let zone = $state(2);
	let notes = $state('');

	function submit() {
		if (!activity.trim()) return;
		entries.add('training.cardio', { date, activity: activity.trim(), distanceKm, durationMin, zone, notes });
		activity = ''; notes = '';
		date = new Date().toISOString().slice(0, 10);
		toast.show('Cardio logged');
	}

	function repeatLast() {
		const last = store.items.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt))[0];
		if (!last) return;
		activity = last.data.activity as string;
		distanceKm = last.data.distanceKm as number;
		durationMin = last.data.durationMin as number;
		zone = last.data.zone as number;
		notes = (last.data.notes as string) || '';
		toast.show('Fields pre-filled');
	}
</script>

<PageHeader title="Cardio" back="/training" />

<section class="form">
	<label>Date <input type="date" bind:value={date} /></label>
	<label>Activity <input type="text" bind:value={activity} placeholder="Run, Bike, Swim..." /></label>
	<div class="row">
		<label>Distance (km) <input type="number" min="0" step="0.1" bind:value={distanceKm} /></label>
		<label>Duration (min) <input type="number" min="0" bind:value={durationMin} /></label>
		<label>Zone (1-5) <input type="number" min="1" max="5" bind:value={zone} /></label>
	</div>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<div class="form-actions">
		<button class="primary" onclick={submit}>Log cardio</button>
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
			activity: (fd.get('activity') as string).trim(),
			distanceKm: Number(fd.get('distanceKm')),
			durationMin: Number(fd.get('durationMin')),
			zone: Number(fd.get('zone')),
			notes: (fd.get('notes') as string).trim()
		});
		toast.show('Updated');
		done();
	}}>
		<label>Date <input type="date" name="date" value={data.date || ''} /></label>
		<label>Activity <input type="text" name="activity" value={data.activity} /></label>
		<div class="row">
			<label>Distance (km) <input type="number" name="distanceKm" min="0" step="0.1" value={data.distanceKm} /></label>
			<label>Duration (min) <input type="number" name="durationMin" min="0" value={data.durationMin} /></label>
			<label>Zone (1-5) <input type="number" name="zone" min="1" max="5" value={data.zone} /></label>
		</div>
		<label>Notes <textarea name="notes" rows="2">{data.notes}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div><strong>{item.data.activity}</strong> <span class="meta">{item.data.distanceKm}km · {item.data.durationMin}min · Z{item.data.zone}</span></div>
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
