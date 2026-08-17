<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('training.strength');

	let date = $state(new Date().toISOString().slice(0, 10));
	let exercise = $state('');
	let sets = $state(3);
	let reps = $state(10);
	let weight = $state(0);
	let rir = $state(2);
	let notes = $state('');

	function submit() {
		if (!exercise.trim()) return;
		entries.add('training.strength', { date, exercise: exercise.trim(), sets, reps, weight, rir, notes });
		exercise = ''; notes = '';
		date = new Date().toISOString().slice(0, 10);
		toast.show('Set logged');
	}

	function repeatLast() {
		const last = store.items.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt))[0];
		if (!last) return;
		exercise = last.data.exercise as string;
		sets = last.data.sets as number;
		reps = last.data.reps as number;
		weight = last.data.weight as number;
		rir = last.data.rir as number;
		notes = (last.data.notes as string) || '';
		toast.show('Fields pre-filled');
	}

	const quickExercises = $derived.by(() => {
		const counts = new Map<string, { count: number; last: Record<string, unknown>; lastCreated: string }>();
		for (const e of store.items) {
			const key = (e.data.exercise as string)?.toLowerCase().trim();
			if (!key) continue;
			const existing = counts.get(key);
			if (!existing) {
				counts.set(key, { count: 1, last: e.data, lastCreated: e.createdAt });
			} else {
				existing.count++;
				if (e.createdAt > existing.lastCreated) {
					existing.last = e.data;
					existing.lastCreated = e.createdAt;
				}
			}
		}
		return [...counts.entries()]
			.sort((a, b) => b[1].count - a[1].count)
			.slice(0, 5)
			.map(([, info]) => ({ name: info.last.exercise as string, count: info.count, last: info.last }));
	});

	function prefillExercise(item: { name: string; last: Record<string, unknown> }) {
		exercise = item.last.exercise as string;
		sets = item.last.sets as number;
		reps = item.last.reps as number;
		weight = item.last.weight as number;
		rir = item.last.rir as number;
		toast.show('Pre-filled');
	}
</script>

<svelte:head>
  <title>Strength | Darink</title>
</svelte:head>

<PageHeader title="Strength" back="/training" />

{#if quickExercises.length > 0}
<section class="quick-add">
	<h2>Quick add</h2>
	<div class="quick-chips">
		{#each quickExercises as item}
			<button class="quick-chip" onclick={() => prefillExercise(item)}>
				{item.name}
				<span class="quick-count">{item.count}</span>
			</button>
		{/each}
	</div>
</section>
{/if}

<section class="form">
	<label>Date <input type="date" bind:value={date} /></label>
	<label>Exercise <input type="text" bind:value={exercise} placeholder="Squat, Bench..." /></label>
	<div class="row">
		<label>Sets <input type="number" min="1" max="20" bind:value={sets} /></label>
		<label>Reps <input type="number" min="1" max="100" bind:value={reps} /></label>
		<label>Weight (kg) <input type="number" min="0" step="0.5" bind:value={weight} /></label>
		<label>RIR <input type="number" min="0" max="10" bind:value={rir} /></label>
	</div>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<div class="form-actions">
		<button class="primary" onclick={submit}>Log set</button>
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
			exercise: (fd.get('exercise') as string).trim(),
			sets: Number(fd.get('sets')),
			reps: Number(fd.get('reps')),
			weight: Number(fd.get('weight')),
			rir: Number(fd.get('rir')),
			notes: (fd.get('notes') as string).trim()
		});
		toast.show('Updated');
		done();
	}}>
		<label>Date <input type="date" name="date" value={data.date || ''} /></label>
		<label>Exercise <input type="text" name="exercise" value={data.exercise} /></label>
		<div class="row">
			<label>Sets <input type="number" name="sets" min="1" max="20" value={data.sets} /></label>
			<label>Reps <input type="number" name="reps" min="1" max="100" value={data.reps} /></label>
			<label>Weight (kg) <input type="number" name="weight" min="0" step="0.5" value={data.weight} /></label>
			<label>RIR <input type="number" name="rir" min="0" max="10" value={data.rir} /></label>
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
		<div><strong>{item.data.exercise}</strong> <span class="meta">{item.data.sets}×{item.data.reps} @ {item.data.weight}kg RIR{item.data.rir}</span></div>
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
	.quick-add { padding: 0 1rem 0.5rem; }
	h2 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
	.quick-chips { display: flex; flex-wrap: wrap; gap: 0.25rem; }
	.quick-chip {
		display: inline-flex; align-items: center; gap: 0.25rem;
		padding: 0.3rem 0.6rem; background: var(--c-bg-card);
		border: 1px solid var(--c-border); border-radius: 16px;
		font-size: 0.8rem; cursor: pointer; transition: border-color 0.15s;
	}
	.quick-chip:hover { border-color: var(--c-accent); background: var(--c-accent-bg); }
	.quick-count { font-size: 0.65rem; color: var(--c-text-muted); }
</style>
