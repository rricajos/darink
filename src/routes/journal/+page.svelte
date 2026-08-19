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

	// --- Search ---
	let searchQuery = $state('');

	const filteredItems = $derived.by(() => {
		if (!searchQuery.trim()) return store.items;
		const q = searchQuery.trim().toLowerCase();
		return store.items.filter(e => String(e.data.text ?? '').toLowerCase().includes(q));
	});

	// --- Average Mood ---
	const avgMood = $derived.by(() => {
		if (store.items.length === 0) return 0;
		const sum = store.items.reduce((acc, e) => acc + Number(e.data.mood ?? 0), 0);
		return sum / store.items.length;
	});

	// --- Streak ---
	const streaks = $derived.by(() => {
		if (store.items.length === 0) return { current: 0, longest: 0 };
		const dates = new Set(store.items.map(e => String(e.data.date ?? '')));
		// Current streak (from today backwards)
		let current = 0;
		const today = new Date();
		for (let i = 0; ; i++) {
			const d = new Date(today.getFullYear(), today.getMonth(), today.getDate() - i);
			const key = d.toISOString().slice(0, 10);
			if (dates.has(key)) {
				current++;
			} else {
				break;
			}
		}
		// Longest streak
		const sorted = [...dates].sort();
		let longest = 0;
		let run = 1;
		for (let i = 1; i < sorted.length; i++) {
			const prev = new Date(sorted[i - 1]);
			const curr = new Date(sorted[i]);
			const diff = (curr.getTime() - prev.getTime()) / (1000 * 60 * 60 * 24);
			if (Math.round(diff) === 1) {
				run++;
			} else {
				if (run > longest) longest = run;
				run = 1;
			}
		}
		if (run > longest) longest = run;
		return { current, longest };
	});

	// --- Mood chart data (last 30 entries) ---
	const moodChartData = $derived.by(() => {
		const sorted = [...store.items]
			.sort((a, b) => String(a.data.date ?? '').localeCompare(String(b.data.date ?? '')))
			.slice(-30);
		return sorted.map(e => Number(e.data.mood ?? 5));
	});

	// --- Writing prompts ---
	const prompts = [
		'How is your body feeling today?',
		'What are you grateful for?',
		'Describe your energy levels',
		'What challenged you today?',
		'How did your training feel?',
		'Any new symptoms or changes?',
		'What did you eat that made you feel good?',
		'How is your sleep quality lately?',
		'What habit are you most proud of?',
		'What would you change about today?'
	];

	let promptIndex = $state(Math.floor(Math.random() * 10));

	function nextPrompt() {
		let next = Math.floor(Math.random() * prompts.length);
		while (next === promptIndex && prompts.length > 1) {
			next = Math.floor(Math.random() * prompts.length);
		}
		promptIndex = next;
	}
</script>

<svelte:head>
  <title>Journal | Darink</title>
</svelte:head>

<PageHeader title="Journal" />

{#if store.items.length > 0}
<section class="search-section">
	<div class="search-box">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
		<input type="text" class="search-input" placeholder="Search journal entries..." bind:value={searchQuery} />
	</div>
	{#if searchQuery.trim()}
		<p class="search-count">{filteredItems.length} result{filteredItems.length !== 1 ? 's' : ''} found</p>
	{/if}
</section>
{/if}

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

<section class="prompts-section">
	<p class="prompts-label">Need inspiration?</p>
	<div class="prompt-card">
		<p class="prompt-text">{prompts[promptIndex]}</p>
		<button class="prompt-btn" onclick={nextPrompt}>
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
			Another prompt
		</button>
	</div>
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

<EntryList items={filteredItems} {editForm}>
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

	<section class="metrics">
		<h2>Streak</h2>
		<div class="metrics-row">
			<div class="metric-card">
				<span class="metric-value">{streaks.current} day{streaks.current !== 1 ? 's' : ''}</span>
				<span class="metric-label">Streak</span>
			</div>
			<div class="metric-card">
				<span class="metric-value">{streaks.longest} day{streaks.longest !== 1 ? 's' : ''}</span>
				<span class="metric-label">Longest streak</span>
			</div>
		</div>
	</section>

	<section class="metrics">
		<h2>Average mood</h2>
		<div class="metrics-row">
			<div class="metric-card">
				<span class="metric-value avg-mood" class:mood-good={avgMood >= 7} class:mood-mid={avgMood >= 4 && avgMood < 7} class:mood-low={avgMood < 4}>{avgMood.toFixed(1)}</span>
				<span class="metric-label">Avg mood</span>
			</div>
		</div>
	</section>

	{#if moodChartData.length >= 2}
		<section class="metrics">
			<h2>Mood trend</h2>
			<div class="chart-card">
				<svg viewBox="0 0 280 100" class="mood-chart">
					{#each moodChartData as mood, i}
						{@const x = moodChartData.length === 1 ? 140 : 10 + (i / (moodChartData.length - 1)) * 260}
						{@const y = 90 - ((mood - 1) / 9) * 80}
						<circle cx={x} cy={y} r="3" fill="var(--c-accent)" />
					{/each}
					<polyline
						fill="none"
						stroke="var(--c-accent)"
						stroke-width="2"
						stroke-linejoin="round"
						stroke-linecap="round"
						points={moodChartData.map((mood, i) => {
							const x = moodChartData.length === 1 ? 140 : 10 + (i / (moodChartData.length - 1)) * 260;
							const y = 90 - ((mood - 1) / 9) * 80;
							return `${x},${y}`;
						}).join(' ')}
					/>
				</svg>
				<div class="chart-labels">
					<span class="chart-range">Min: {Math.min(...moodChartData)}</span>
					<span class="chart-range">Max: {Math.max(...moodChartData)}</span>
				</div>
			</div>
		</section>
	{/if}
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

	/* Search */
	.search-section { padding: 0 1rem 0.5rem; }
	.search-box { display: flex; align-items: center; gap: 0.5rem; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.5rem 0.75rem; }
	.search-box svg { flex-shrink: 0; color: var(--c-text-muted); }
	.search-input { border: none; background: transparent; flex: 1; font-size: 0.9rem; outline: none; color: inherit; }
	.search-count { font-size: 0.75rem; color: var(--c-text-muted); margin-top: 0.35rem; }

	/* Writing prompts */
	.prompts-section { padding: 0.75rem 1rem 0; }
	.prompts-label { font-size: 0.8rem; font-weight: 600; color: var(--c-text-muted); margin-bottom: 0.4rem; }
	.prompt-card { background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; display: flex; align-items: center; gap: 0.75rem; justify-content: space-between; }
	.prompt-text { font-size: 0.9rem; font-style: italic; flex: 1; }
	.prompt-btn { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; padding: 0.35rem 0.6rem; background: var(--c-accent-bg); border: 1px solid var(--c-border); border-radius: var(--radius); cursor: pointer; white-space: nowrap; color: var(--c-accent); }

	/* Average mood color */
	.avg-mood.mood-good { color: var(--c-done, #22c55e); }
	.avg-mood.mood-mid { color: #f59e0b; }
	.avg-mood.mood-low { color: #ef4444; }

	/* Mood chart */
	.chart-card { background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; }
	.mood-chart { width: 100%; height: auto; display: block; }
	.chart-labels { display: flex; justify-content: space-between; margin-top: 0.35rem; }
	.chart-range { font-size: 0.7rem; color: var(--c-text-muted); font-weight: 600; }
</style>
