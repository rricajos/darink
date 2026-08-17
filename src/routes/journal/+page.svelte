<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('journal');

	let date = $state(new Date().toISOString().slice(0, 10));
	let text = $state('');
	let moodVal = $state(5);

	function submit() {
		if (!text.trim()) return;
		entries.add('journal', {
			date,
			text: text.trim(),
			mood: moodVal
		});
		text = '';
		moodVal = 5;
		date = new Date().toISOString().slice(0, 10);
		toast.show('Journal entry saved');
	}

	function wordCount(str: string): number {
		const trimmed = str.trim();
		if (!trimmed) return 0;
		return trimmed.split(/\s+/).length;
	}

	const allTimeWords = $derived(
		store.items.reduce((sum, e) => sum + wordCount(String(e.data.text ?? '')), 0)
	);

	const weekWords = $derived.by(() => {
		const now = new Date();
		const weekAgo = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 7);
		return store.items
			.filter(e => new Date(e.createdAt) >= weekAgo)
			.reduce((sum, e) => sum + wordCount(String(e.data.text ?? '')), 0);
	});
</script>

<PageHeader title="Journal" />

<section class="form">
	<label>Date <input type="date" bind:value={date} /></label>
	<label>Entry <textarea rows="5" bind:value={text} placeholder="Write your thoughts..."></textarea></label>
	<div class="row">
		<label>
			Mood (1-10)
			<select bind:value={moodVal}>
				{#each Array.from({ length: 10 }, (_, i) => i + 1) as v}
					<option value={v}>{v}</option>
				{/each}
			</select>
		</label>
	</div>
	<button class="primary" onclick={submit}>Save entry</button>
</section>

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			date: fd.get('date') as string,
			text: (fd.get('text') as string).trim(),
			mood: Number(fd.get('mood'))
		});
		toast.show('Updated');
		done();
	}}>
		<label>Date <input type="date" name="date" value={data.date} /></label>
		<label>Entry <textarea rows="5" name="text">{data.text}</textarea></label>
		<div class="row">
			<label>
				Mood (1-10)
				<select name="mood">
					{#each Array.from({ length: 10 }, (_, i) => i + 1) as v}
						<option value={v} selected={Number(data.mood) === v}>{v}</option>
					{/each}
				</select>
			</label>
		</div>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/><path d="M8 7h6"/><path d="M8 11h8"/></svg>
	<p>No journal entries yet</p>
	<p class="empty-hint">Start journaling to capture thoughts, reflections, and context around your health journey.</p>
</div>
{/if}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div class="journal-row">
			<span class="journal-date">{item.data.date}</span>
			<span class="journal-preview">{String(item.data.text ?? '').length > 100 ? String(item.data.text ?? '').slice(0, 100) + '...' : item.data.text}</span>
			<span class="journal-mood">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
				{item.data.mood}/10
			</span>
		</div>
	{/snippet}
</EntryList>

{#if store.items.length > 0}
	<section class="metrics">
		<h2>Word count</h2>
		<div class="metrics-row">
			<div class="metric-card">
				<span class="metric-value">{weekWords}</span>
				<span class="metric-label">This week</span>
			</div>
			<div class="metric-card">
				<span class="metric-value">{allTimeWords}</span>
				<span class="metric-label">All time</span>
			</div>
		</div>
	</section>
{/if}

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.75rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }

	.journal-row { display: flex; flex-direction: column; gap: 0.25rem; }
	.journal-date { font-size: 0.75rem; font-weight: 600; color: var(--c-text-muted); }
	.journal-preview {
		font-size: 0.9rem;
		font-style: italic;
		color: var(--c-text);
		background: var(--c-accent-bg);
		padding: 0.3rem 0.5rem;
		border-radius: var(--radius);
		line-height: 1.4;
	}
	.journal-mood {
		display: flex;
		align-items: center;
		gap: 0.25rem;
		font-size: 0.75rem;
		color: var(--c-text-muted);
		font-weight: 600;
	}

	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }

	h2 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
	.metrics { padding: 1.5rem 1rem 0; }
	.metrics-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.metric-card { flex: 1; min-width: 80px; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; text-align: center; display: flex; flex-direction: column; gap: 0.15rem; }
	.metric-value { font-size: 1.4rem; font-weight: 700; }
	.metric-label { font-size: 0.75rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; }
</style>
