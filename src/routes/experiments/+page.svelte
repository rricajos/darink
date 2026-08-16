<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries';
	import { toast } from '$lib/stores/toast';

	const store = useEntries('experiment');

	let hypothesis = $state('');
	let variable = $state('');
	let protocol = $state('');
	let duration = $state('7 days');
	let result = $state('');
	let status = $state<'active' | 'completed' | 'abandoned'>('active');

	function submit() {
		if (!hypothesis.trim()) return;
		entries.add('experiment', {
			hypothesis: hypothesis.trim(), variable: variable.trim(),
			protocol: protocol.trim(), duration, result: result.trim(), status
		});
		hypothesis = ''; variable = ''; protocol = ''; result = '';
		toast.show('Experiment logged');
	}
</script>

<PageHeader title="Experiments (n=1)" />

<section class="form">
	<label>Hypothesis <input type="text" bind:value={hypothesis} placeholder="If I do X, then Y..." /></label>
	<label>Variable <input type="text" bind:value={variable} placeholder="What you're changing" /></label>
	<label>Protocol <textarea bind:value={protocol} rows="2" placeholder="Steps to follow"></textarea></label>
	<div class="row">
		<label>Duration <input type="text" bind:value={duration} /></label>
		<label>
			Status
			<select bind:value={status}>
				<option value="active">Active</option>
				<option value="completed">Completed</option>
				<option value="abandoned">Abandoned</option>
			</select>
		</label>
	</div>
	<label>Result <textarea bind:value={result} rows="2" placeholder="Observations, outcome..."></textarea></label>
	<button class="primary" onclick={submit}>Log experiment</button>
</section>

<EntryList items={store.items}>
	{#snippet row(item)}
		<div class="exp">
			<strong>{item.data.hypothesis}</strong>
			<span class="badge" class:active={item.data.status === 'active'} class:completed={item.data.status === 'completed'} class:abandoned={item.data.status === 'abandoned'}>{item.data.status}</span>
		</div>
		<div class="meta">{item.data.variable} · {item.data.duration}</div>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.5rem; }
	.row label { flex: 1; }
	.exp { display: flex; align-items: center; gap: 0.5rem; }
	.exp strong { flex: 1; }
	.badge { font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 12px; color: #fff; }
	.badge.active { background: var(--c-accent); }
	.badge.completed { background: var(--c-done); }
	.badge.abandoned { background: var(--c-cancel); }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
</style>
