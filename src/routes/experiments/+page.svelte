<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { useLocale } from '$lib/stores/locale.svelte';
	import type { Entry } from '$lib/db';

	const { t } = useLocale();
	const store = useEntries('experiment');
	const checkinStore = useEntries('checkin');

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

	/* --- Before/After Check-in Comparison for Active Experiments --- */
	const beforeAfterMap = $derived.by(() => {
		const checkins = checkinStore.items;
		const map = new Map<string, { beforeMood: number; afterMood: number; beforeEnergy: number; afterEnergy: number }>();
		for (const exp of store.items.filter((e) => e.data.status === 'active')) {
			const startMs = new Date(exp.createdAt).getTime();
			const nowMs = Date.now();
			const elapsedMs = nowMs - startMs;
			if (elapsedMs < 86400000) continue;
			const beforeStart = startMs - elapsedMs;
			const beforeCheckins = checkins.filter((c) => {
				const t = new Date(c.createdAt).getTime();
				return t >= beforeStart && t < startMs;
			});
			const afterCheckins = checkins.filter((c) => {
				const t = new Date(c.createdAt).getTime();
				return t >= startMs && t <= nowMs;
			});
			if (beforeCheckins.length < 2 || afterCheckins.length < 2) continue;
			const avgMoodBefore = beforeCheckins.reduce((s, c) => s + Number(c.data.mood ?? 0), 0) / beforeCheckins.length;
			const avgMoodAfter = afterCheckins.reduce((s, c) => s + Number(c.data.mood ?? 0), 0) / afterCheckins.length;
			const avgEnergyBefore = beforeCheckins.reduce((s, c) => s + Number(c.data.energy ?? 0), 0) / beforeCheckins.length;
			const avgEnergyAfter = afterCheckins.reduce((s, c) => s + Number(c.data.energy ?? 0), 0) / afterCheckins.length;
			map.set(exp.id, {
				beforeMood: Math.round(avgMoodBefore * 10) / 10,
				afterMood: Math.round(avgMoodAfter * 10) / 10,
				beforeEnergy: Math.round(avgEnergyBefore * 10) / 10,
				afterEnergy: Math.round(avgEnergyAfter * 10) / 10
			});
		}
		return map;
	});

	/* --- Outcome Tag Classification --- */
	function classifyOutcome(resultText: string): { label: string; cls: string } {
		const lower = resultText.toLowerCase();
		const positiveWords = ['improved', 'better', 'increased', 'successful', 'works', 'mejorado', 'mejor', 'éxito'];
		const negativeWords = ['worse', 'failed', 'no effect', 'no change', 'decreased', 'peor', 'fallido', 'sin efecto'];
		if (positiveWords.some((w) => lower.includes(w))) return { label: t.experiments.positive, cls: 'tag-positive' };
		if (negativeWords.some((w) => lower.includes(w))) return { label: t.experiments.negative, cls: 'tag-negative' };
		return { label: t.experiments.neutral, cls: 'tag-neutral' };
	}

	/* --- Experiment Duration Stats --- */
	const durationStats = $derived.by(() => {
		const completed = store.items.filter((e) => e.data.status === 'completed');
		if (completed.length === 0) return null;
		const durations = completed.map((e) => {
			const created = new Date(e.createdAt).getTime();
			const updated = new Date(e.updatedAt).getTime();
			return Math.max(0, Math.floor((updated - created) / 86400000));
		});
		const avg = Math.round(durations.reduce((a, b) => a + b, 0) / durations.length);
		const longest = Math.max(...durations);
		return { avg, longest };
	});

	/* --- Experiment Timeline Data --- */
	const timelineData = $derived.by(() => {
		const items = store.items;
		if (items.length === 0) return null;
		const now = Date.now();
		const entries = items.map((e) => {
			const start = new Date(e.createdAt).getTime();
			const st = e.data.status as string;
			let end: number;
			if (st === 'active') {
				end = now;
			} else {
				end = new Date(e.updatedAt).getTime();
			}
			return {
				id: e.id,
				hypothesis: (e.data.hypothesis as string) ?? '',
				status: st,
				start,
				end: Math.max(end, start + 86400000)
			};
		});
		const minStart = Math.min(...entries.map((e) => e.start));
		const maxEnd = Math.max(...entries.map((e) => e.end));
		const span = maxEnd - minStart || 1;
		return {
			entries: entries.map((e) => ({
				...e,
				leftPct: ((e.start - minStart) / span) * 100,
				widthPct: Math.max(2, ((e.end - e.start) / span) * 100),
				hypothesisTrunc: e.hypothesis.length > 40 ? e.hypothesis.slice(0, 37) + '...' : e.hypothesis
			})),
			minDate: new Date(minStart).toLocaleDateString(),
			maxDate: new Date(maxEnd).toLocaleDateString()
		};
	});

	function submit() {
		if (!hypothesis.trim()) return;
		entries.add('experiment', {
			hypothesis: hypothesis.trim(), variable: variable.trim(),
			protocol: protocol.trim(), duration, result: result.trim(), status
		});
		hypothesis = ''; variable = ''; protocol = ''; result = '';
		toast.show(t.experiments.logged);
	}
</script>

<svelte:head>
  <title>{t.experiments.title} | Darink</title>
</svelte:head>

<PageHeader title={t.experiments.titleN1} />

<section class="form">
	<label>{t.experiments.hypothesis} <input type="text" bind:value={hypothesis} placeholder={t.experiments.hypothesisPlaceholder} /></label>
	<label>{t.experiments.variable} <input type="text" bind:value={variable} placeholder={t.experiments.variablePlaceholder} /></label>
	<label>{t.experiments.protocol} <textarea bind:value={protocol} rows="2" placeholder={t.experiments.protocolPlaceholder}></textarea></label>
	<div class="row">
		<label>{t.experiments.duration} <input type="text" bind:value={duration} /></label>
		<label>
			{t.experiments.status}
			<select bind:value={status}>
				<option value="active">{t.experiments.active}</option>
				<option value="completed">{t.experiments.completed}</option>
				<option value="abandoned">{t.experiments.abandoned}</option>
			</select>
		</label>
	</div>
	<label>{t.experiments.result} <textarea bind:value={result} rows="2" placeholder={t.experiments.resultPlaceholder}></textarea></label>
	<button class="primary" onclick={submit}>{t.experiments.logExperiment}</button>
</section>

{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v7.527a2 2 0 0 1-.211.896L4.72 20.55a1 1 0 0 0 .9 1.45h12.76a1 1 0 0 0 .9-1.45l-5.069-10.127A2 2 0 0 1 14 9.527V2"/><path d="M8.5 2h7"/><path d="M7 16h10"/></svg>
	<p>{t.experiments.noExperiments}</p>
	<p class="empty-hint">{t.experiments.noExperimentsHint}</p>
</div>
{/if}

<!-- Status Overview Cards -->
{#if store.items.length > 0}
<section class="overview-cards">
	<div class="metric-card accent">
		<span class="metric-val">{statusCounts.active}</span>
		<span class="metric-lbl">{t.experiments.active}</span>
	</div>
	<div class="metric-card done">
		<span class="metric-val">{statusCounts.completed}</span>
		<span class="metric-lbl">{t.experiments.completed}</span>
	</div>
	<div class="metric-card muted">
		<span class="metric-val">{statusCounts.abandoned}</span>
		<span class="metric-lbl">{t.experiments.abandoned}</span>
	</div>
	<div class="metric-card">
		<span class="metric-val">{statusCounts.total}</span>
		<span class="metric-lbl">{t.common.total}</span>
	</div>
	{#if durationStats}
	<div class="metric-card done">
		<span class="metric-val">{durationStats.avg}d</span>
		<span class="metric-lbl">{t.experiments.avgDuration}</span>
	</div>
	<div class="metric-card">
		<span class="metric-val">{durationStats.longest}d</span>
		<span class="metric-lbl">{t.experiments.longest}</span>
	</div>
	{/if}
</section>
{/if}

<!-- Active Experiments Timeline -->
{#if store.items.length > 0 && activeExperiments.length > 0}
<section class="timeline-section">
	<h2>{t.experiments.activeExperiments}</h2>
	<div class="timeline-list">
		{#each activeExperiments as exp}
		<div class="timeline-card border-active">
			<strong>{exp.hypothesis}</strong>
			<div class="timeline-meta">
				<span>{t.experiments.variable}: {exp.variable}</span>
				<span>{t.experiments.duration}: {exp.duration}</span>
				<span>{exp.elapsed} {exp.elapsed !== 1 ? t.experiments.daysElapsed : t.experiments.dayElapsed}</span>
			</div>
			{#if exp.pct !== null}
			{@const clamped = Math.min(exp.pct, 100)}
			<div class="progress-track">
				<div class="progress-fill" style="width: {clamped}%"></div>
			</div>
			<span class="progress-label">{clamped}% {t.experiments.ofDays.replace('{n}', String(exp.durDays))}</span>
			{/if}
			{#if beforeAfterMap.has(exp.id)}
			{@const ba = beforeAfterMap.get(exp.id)!}
			<div class="ba-card">
				<span class="ba-title">{t.experiments.beforeAfter}</span>
				<div class="ba-row">
					<span class="ba-label">{t.common.mood}</span>
					<span class="ba-val">{ba.beforeMood}</span>
					<svg class="ba-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
					<span class="ba-val" class:ba-up={ba.afterMood > ba.beforeMood} class:ba-down={ba.afterMood < ba.beforeMood}>{ba.afterMood}</span>
					{#if ba.afterMood > ba.beforeMood}
					<svg class="ba-indicator up" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
					{:else if ba.afterMood < ba.beforeMood}
					<svg class="ba-indicator down" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
					{/if}
				</div>
				<div class="ba-row">
					<span class="ba-label">{t.common.energy}</span>
					<span class="ba-val">{ba.beforeEnergy}</span>
					<svg class="ba-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
					<span class="ba-val" class:ba-up={ba.afterEnergy > ba.beforeEnergy} class:ba-down={ba.afterEnergy < ba.beforeEnergy}>{ba.afterEnergy}</span>
					{#if ba.afterEnergy > ba.beforeEnergy}
					<svg class="ba-indicator up" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
					{:else if ba.afterEnergy < ba.beforeEnergy}
					<svg class="ba-indicator down" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
					{/if}
				</div>
			</div>
			{/if}
		</div>
		{/each}
	</div>
</section>
{/if}

<!-- Completed Results Summary -->
{#if store.items.length > 0 && completedExperiments.length > 0}
<section class="results-section">
	<h2>{t.experiments.completedResults}</h2>
	<div class="results-list">
		{#each completedExperiments as exp}
		{@const outcome = classifyOutcome(exp.result)}
		<div class="timeline-card border-completed">
			<div class="completed-header">
				<strong>{exp.hypothesis}</strong>
				<span class="outcome-tag {outcome.cls}">{outcome.label}</span>
			</div>
			{#if exp.result}
			<div class="result-text">{exp.result}</div>
			{:else}
			<div class="result-text empty">{t.experiments.noResultRecorded}</div>
			{/if}
			<div class="timeline-meta">
				<span>{exp.durationDays} {t.common.days} {t.experiments.daysFromStart}</span>
			</div>
		</div>
		{/each}
	</div>
</section>
{/if}

<!-- Success Rate -->
{#if store.items.length > 0 && successRate !== null}
<section class="success-section">
	<h2>{t.experiments.successRate}</h2>
	<div class="success-card">
		{#if successRate.pct >= 75}
		<span class="success-indicator high"></span>
		{:else if successRate.pct >= 40}
		<span class="success-indicator mid"></span>
		{:else}
		<span class="success-indicator low"></span>
		{/if}
		<span class="success-pct">{successRate.pct}%</span>
		<span class="success-detail">{t.experiments.hasRecordedResults.replace('{n}', String(successRate.withResult)).replace('{total}', String(successRate.total))}</span>
	</div>
</section>
{/if}

<!-- Experiment Timeline -->
{#if timelineData}
<section class="timeline-visual-section">
	<h2>{t.experiments.timeline}</h2>
	<div class="tl-axis">
		<span class="tl-date">{timelineData.minDate}</span>
		<span class="tl-date">{timelineData.maxDate}</span>
	</div>
	<div class="tl-chart">
		{#each timelineData.entries as bar}
		<div class="tl-row">
			<div class="tl-bar-wrap">
				<div
					class="tl-bar"
					class:tl-active={bar.status === 'active'}
					class:tl-completed={bar.status === 'completed'}
					class:tl-abandoned={bar.status === 'abandoned'}
					style="left: {bar.leftPct}%; width: {bar.widthPct}%"
					title={bar.hypothesis}
				></div>
			</div>
			<span class="tl-label">{bar.hypothesisTrunc}</span>
		</div>
		{/each}
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
		toast.show(t.experiments.updated);
		done();
	}}>
		<label>{t.experiments.hypothesis} <input type="text" name="hypothesis" value={data.hypothesis} /></label>
		<label>{t.experiments.variable} <input type="text" name="variable" value={data.variable} /></label>
		<label>{t.experiments.protocol} <textarea name="protocol" rows="2">{data.protocol ?? ''}</textarea></label>
		<div class="row">
			<label>{t.experiments.duration} <input type="text" name="duration" value={data.duration} /></label>
			<label>
				{t.experiments.status}
				<select name="status">
					<option value="active" selected={data.status === 'active'}>{t.experiments.active}</option>
					<option value="completed" selected={data.status === 'completed'}>{t.experiments.completed}</option>
					<option value="abandoned" selected={data.status === 'abandoned'}>{t.experiments.abandoned}</option>
				</select>
			</label>
		</div>
		<label>{t.experiments.result} <textarea name="result" rows="2">{data.result ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">{t.common.save}</button>
			<button type="button" onclick={done}>{t.common.cancel}</button>
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
		grid-template-columns: repeat(3, 1fr);
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

	/* Before/After Card */
	.ba-card {
		margin-top: 0.5rem;
		padding: 0.5rem 0.75rem;
		background: var(--c-accent-bg);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}
	.ba-title {
		font-size: 0.7rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: var(--c-text-muted);
		display: block;
		margin-bottom: 0.35rem;
	}
	.ba-row {
		display: flex;
		align-items: center;
		gap: 0.35rem;
		font-size: 0.82rem;
		margin-bottom: 0.15rem;
	}
	.ba-label {
		color: var(--c-text-muted);
		min-width: 3.2rem;
	}
	.ba-val {
		font-weight: 600;
	}
	.ba-val.ba-up { color: var(--c-done); }
	.ba-val.ba-down { color: var(--c-cancel); }
	.ba-arrow {
		color: var(--c-text-muted);
		flex-shrink: 0;
	}
	.ba-indicator {
		flex-shrink: 0;
	}
	.ba-indicator.up { color: var(--c-done); }
	.ba-indicator.down { color: var(--c-cancel); }

	/* Outcome Tags */
	.completed-header {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		margin-bottom: 0.25rem;
	}
	.completed-header strong {
		flex: 1;
		margin-bottom: 0;
	}
	.outcome-tag {
		font-size: 0.7rem;
		font-weight: 600;
		padding: 0.15rem 0.5rem;
		border-radius: 12px;
		flex-shrink: 0;
		text-transform: uppercase;
		letter-spacing: 0.03em;
	}
	.tag-positive {
		background: var(--c-done);
		color: #fff;
	}
	.tag-negative {
		background: var(--c-cancel);
		color: #fff;
	}
	.tag-neutral {
		background: var(--c-border);
		color: var(--c-text-muted);
	}

	/* Timeline Visual */
	.timeline-visual-section {
		padding: 0 1rem 1rem;
	}
	.timeline-visual-section h2 {
		font-size: 0.9rem;
		font-weight: 600;
		margin-bottom: 0.5rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}
	.tl-axis {
		display: flex;
		justify-content: space-between;
		font-size: 0.7rem;
		color: var(--c-text-muted);
		margin-bottom: 0.35rem;
	}
	.tl-date {
		font-variant-numeric: tabular-nums;
	}
	.tl-chart {
		display: flex;
		flex-direction: column;
		gap: 0.35rem;
	}
	.tl-row {
		display: flex;
		align-items: center;
		gap: 0.5rem;
	}
	.tl-bar-wrap {
		flex: 1;
		position: relative;
		height: 14px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: 3px;
		overflow: hidden;
	}
	.tl-bar {
		position: absolute;
		top: 1px;
		bottom: 1px;
		border-radius: 2px;
	}
	.tl-bar.tl-active { background: #4aa3ff; }
	.tl-bar.tl-completed { background: #2e8b57; }
	.tl-bar.tl-abandoned { background: #e53e3e; }
	.tl-label {
		font-size: 0.72rem;
		color: var(--c-text-muted);
		max-width: 10rem;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		flex-shrink: 0;
	}

	@media (max-width: 400px) {
		.overview-cards {
			grid-template-columns: repeat(2, 1fr);
		}
		.tl-label {
			max-width: 5rem;
		}
	}
</style>
