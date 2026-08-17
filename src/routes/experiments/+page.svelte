<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('experiment');

	let hypothesis = $state('');
	let variable = $state('');
	let protocol = $state('');
	let duration = $state('7 days');
	let result = $state('');
	let status = $state<'active' | 'completed' | 'abandoned'>('active');

	/* --- Status Overview --- */
	const statusCounts = $derived.by(() => {
		const items = store.items;
		return {
			active: items.filter((e) => e.data.status === 'active').length,
			completed: items.filter((e) => e.data.status === 'completed').length,
			abandoned: items.filter((e) => e.data.status === 'abandoned').length,
			total: items.length
		};
	});

	/* --- Active Experiments Timeline --- */
	const activeExperiments = $derived.by(() => {
		const now = Date.now();
		return store.items
			.filter((e) => e.data.status === 'active')
			.map((e) => {
				const elapsed = Math.floor((now - new Date(e.createdAt).getTime()) / 86400000);
				const durStr = (e.data.duration as string) ?? '';
				const durMatch = durStr.match(/(\d+)/);
				const durDays = durMatch ? parseInt(durMatch[1], 10) : null;
				const pct = durDays && durDays > 0 ? Math.min(100, Math.round((elapsed / durDays) * 100)) : null;
				return {
					id: e.id,
					hypothesis: (e.data.hypothesis as string) ?? '',
					variable: (e.data.variable as string) ?? '',
					duration: durStr,
					elapsed,
					durDays,
					pct
				};
			});
	});

	/* --- Completed Results --- */
	const completedExperiments = $derived.by(() => {
		return store.items
			.filter((e) => e.data.status === 'completed')
			.map((e) => {
				const created = new Date(e.createdAt).getTime();
				const updated = new Date(e.updatedAt).getTime();
				const diffDays = Math.max(0, Math.floor((updated - created) / 86400000));
				return {
					id: e.id,
					hypothesis: (e.data.hypothesis as string) ?? '',
					result: (e.data.result as string) ?? '',
					durationDays: diffDays
				};
			});
	});

	/* --- Success Rate --- */
	const successRate = $derived.by(() => {
		const completed = store.items.filter((e) => e.data.status === 'completed');
		if (completed.length === 0) return null;
		const withResult = completed.filter((e) => ((e.data.result as string) ?? '').trim().length > 0).length;
		return {
			pct: Math.round((withResult / completed.length) * 100),
			withResult,
			total: completed.length
		};
	});

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

<svelte:head>
  <title>Experiments | Darink</title>
</svelte:head>

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

{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v7.527a2 2 0 0 1-.211.896L4.72 20.55a1 1 0 0 0 .9 1.45h12.76a1 1 0 0 0 .9-1.45l-5.069-10.127A2 2 0 0 1 14 9.527V2"/><path d="M8.5 2h7"/><path d="M7 16h10"/></svg>
	<p>No experiments yet</p>
	<p class="empty-hint">Design your first n=1 experiment to start testing hypotheses.</p>
</div>
{/if}

<!-- Status Overview Cards -->
{#if store.items.length > 0}
<section class="overview-cards">
	<div class="metric-card accent">
		<span class="metric-val">{statusCounts.active}</span>
		<span class="metric-lbl">Active</span>
	</div>
	<div class="metric-card done">
		<span class="metric-val">{statusCounts.completed}</span>
		<span class="metric-lbl">Completed</span>
	</div>
	<div class="metric-card muted">
		<span class="metric-val">{statusCounts.abandoned}</span>
		<span class="metric-lbl">Abandoned</span>
	</div>
	<div class="metric-card">
		<span class="metric-val">{statusCounts.total}</span>
		<span class="metric-lbl">Total</span>
	</div>
</section>
{/if}

<!-- Active Experiments Timeline -->
{#if store.items.length > 0 && activeExperiments.length > 0}
<section class="timeline-section">
	<h2>Active experiments</h2>
	<div class="timeline-list">
		{#each activeExperiments as exp}
		<div class="timeline-card border-active">
			<strong>{exp.hypothesis}</strong>
			<div class="timeline-meta">
				<span>Variable: {exp.variable}</span>
				<span>Duration: {exp.duration}</span>
				<span>{exp.elapsed} day{exp.elapsed !== 1 ? 's' : ''} elapsed</span>
			</div>
			{#if exp.pct !== null}
			{@const clamped = Math.min(exp.pct, 100)}
			<div class="progress-track">
				<div class="progress-fill" style="width: {clamped}%"></div>
			</div>
			<span class="progress-label">{clamped}% of {exp.durDays} days</span>
			{/if}
		</div>
		{/each}
	</div>
</section>
{/if}

<!-- Completed Results Summary -->
{#if store.items.length > 0 && completedExperiments.length > 0}
<section class="results-section">
	<h2>Completed results</h2>
	<div class="results-list">
		{#each completedExperiments as exp}
		<div class="timeline-card border-completed">
			<strong>{exp.hypothesis}</strong>
			{#if exp.result}
			<div class="result-text">{exp.result}</div>
			{:else}
			<div class="result-text empty">No result recorded</div>
			{/if}
			<div class="timeline-meta">
				<span>{exp.durationDays} day{exp.durationDays !== 1 ? 's' : ''} from start to completion</span>
			</div>
		</div>
		{/each}
	</div>
</section>
{/if}

<!-- Success Rate -->
{#if store.items.length > 0 && successRate !== null}
<section class="success-section">
	<h2>Success rate</h2>
	<div class="success-card">
		{#if successRate.pct >= 75}
		<span class="success-indicator high"></span>
		{:else if successRate.pct >= 40}
		<span class="success-indicator mid"></span>
		{:else}
		<span class="success-indicator low"></span>
		{/if}
		<span class="success-pct">{successRate.pct}%</span>
		<span class="success-detail">{successRate.withResult} of {successRate.total} completed experiments have recorded results</span>
	</div>
</section>
{/if}

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			hypothesis: (fd.get('hypothesis') as string).trim(),
			variable: (fd.get('variable') as string).trim(),
			protocol: (fd.get('protocol') as string).trim(),
			duration: fd.get('duration') as string,
			result: (fd.get('result') as string).trim(),
			status: fd.get('status') as string
		});
		toast.show('Updated');
		done();
	}}>
		<label>Hypothesis <input type="text" name="hypothesis" value={data.hypothesis} /></label>
		<label>Variable <input type="text" name="variable" value={data.variable} /></label>
		<label>Protocol <textarea name="protocol" rows="2">{data.protocol ?? ''}</textarea></label>
		<div class="row">
			<label>Duration <input type="text" name="duration" value={data.duration} /></label>
			<label>
				Status
				<select name="status">
					<option value="active" selected={data.status === 'active'}>Active</option>
					<option value="completed" selected={data.status === 'completed'}>Completed</option>
					<option value="abandoned" selected={data.status === 'abandoned'}>Abandoned</option>
				</select>
			</label>
		</div>
		<label>Result <textarea name="result" rows="2">{data.result ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
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
	.row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }
	.exp { display: flex; align-items: center; gap: 0.5rem; }
	.exp strong { flex: 1; }
	.badge { font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 12px; color: #fff; }
	.badge.active { background: var(--c-accent); }
	.badge.completed { background: var(--c-done); }
	.badge.abandoned { background: var(--c-cancel); }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }

	/* Status Overview Cards */
	.overview-cards {
		display: grid;
		grid-template-columns: repeat(4, 1fr);
		gap: 0.5rem;
		padding: 1rem;
	}
	.metric-card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem;
		text-align: center;
	}
	.metric-card.accent { border-color: var(--c-accent); }
	.metric-card.done { border-color: var(--c-done); }
	.metric-card.muted { border-color: var(--c-text-muted); }
	.metric-val {
		display: block;
		font-size: 1.4rem;
		font-weight: 700;
	}
	.metric-lbl {
		font-size: 0.7rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.03em;
	}

	/* Timeline Section */
	.timeline-section, .results-section, .success-section {
		padding: 0 1rem 1rem;
	}
	.timeline-section h2, .results-section h2, .success-section h2 {
		font-size: 0.9rem;
		font-weight: 600;
		margin-bottom: 0.5rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}
	.timeline-list, .results-list {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}
	.timeline-card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem 1rem;
		border-left: 4px solid var(--c-border);
	}
	.timeline-card.border-active { border-left-color: var(--c-accent); }
	.timeline-card.border-completed { border-left-color: var(--c-done); }
	.timeline-card strong {
		display: block;
		margin-bottom: 0.25rem;
	}
	.timeline-meta {
		display: flex;
		flex-wrap: wrap;
		gap: 0.5rem;
		font-size: 0.8rem;
		color: var(--c-text-muted);
	}
	.timeline-meta span:not(:last-child)::after {
		content: '·';
		margin-left: 0.5rem;
	}

	/* Progress bar */
	.progress-track {
		height: 6px;
		background: var(--c-border);
		border-radius: 3px;
		margin-top: 0.5rem;
		overflow: hidden;
	}
	.progress-fill {
		height: 100%;
		background: var(--c-accent);
		border-radius: 3px;
		transition: width 0.3s;
	}
	.progress-label {
		font-size: 0.7rem;
		color: var(--c-text-muted);
		margin-top: 0.15rem;
		display: block;
	}

	/* Results */
	.result-text {
		font-size: 0.85rem;
		margin: 0.25rem 0;
	}
	.result-text.empty {
		color: var(--c-text-muted);
		font-style: italic;
	}

	/* Success Rate */
	.success-card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem 1rem;
		display: flex;
		align-items: center;
		gap: 0.75rem;
	}
	.success-indicator {
		width: 12px;
		height: 12px;
		border-radius: 50%;
		flex-shrink: 0;
	}
	.success-indicator.high { background: var(--c-done); }
	.success-indicator.mid { background: #f59e0b; }
	.success-indicator.low { background: var(--c-cancel); }
	.success-pct {
		font-size: 1.25rem;
		font-weight: 700;
		flex-shrink: 0;
	}
	.success-detail {
		font-size: 0.8rem;
		color: var(--c-text-muted);
	}

	@media (max-width: 400px) {
		.overview-cards {
			grid-template-columns: repeat(2, 1fr);
		}
	}
</style>
