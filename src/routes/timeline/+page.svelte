<script lang="ts">
	import { useEntries } from '$lib/stores/entries.svelte';
	import PageHeader from '$lib/components/PageHeader.svelte';

	const store = useEntries();

	/* --- Tag filter state --- */
	let selectedTags = $state<string[]>([]);
	let tagFilterOpen = $state(false);

	const allTagsData = $derived.by(() => {
		const counts: Record<string, number> = {};
		for (const e of store.items) {
			const tags = e.data.tags;
			if (Array.isArray(tags)) {
				for (const t of tags) {
					if (typeof t === 'string') {
						counts[t] = (counts[t] || 0) + 1;
					}
				}
			}
		}
		return counts;
	});

	const allTags = $derived(Object.keys(allTagsData).sort());

	function toggleTagFilter(tag: string): void {
		if (selectedTags.includes(tag)) {
			selectedTags = selectedTags.filter((t) => t !== tag);
		} else {
			selectedTags = [...selectedTags, tag];
		}
		visibleCount = 50;
	}

	function clearTagFilter(): void {
		selectedTags = [];
		visibleCount = 50;
	}

	/* --- Type color map --- */
	const typeColors: Record<string, string> = {
		checkin: '#4aa3ff',
		intake: '#22c55e',
		'training.strength': '#f97316',
		'training.cardio': '#f97316',
		'training.hiit': '#f97316',
		'training.rings': '#f97316',
		'training.mobility': '#f97316',
		habit: '#a855f7',
		supplement: '#14b8a6',
		'signal.sleep': '#ec4899',
		'signal.skin': '#ec4899',
		'signal.hair': '#ec4899',
		'signal.genital': '#ec4899',
		experiment: '#f59e0b'
	};

	function getTypeColor(type: string): string {
		if (typeColors[type]) return typeColors[type];
		if (type.startsWith('training.')) return '#f97316';
		if (type.startsWith('signal.')) return '#ec4899';
		return 'var(--c-text-muted)';
	}

	/* --- Type labels --- */
	function getTypeLabel(type: string): string {
		if (type === 'checkin') return 'Check-in';
		if (type === 'intake') return 'Intake';
		if (type === 'training.strength') return 'Strength';
		if (type === 'training.cardio') return 'Cardio';
		if (type === 'training.hiit') return 'HIIT';
		if (type === 'training.rings') return 'Rings';
		if (type === 'training.mobility') return 'Mobility';
		if (type === 'habit') return 'Habit';
		if (type === 'supplement') return 'Supplement';
		if (type === 'signal.sleep') return 'Sleep';
		if (type === 'signal.skin') return 'Skin';
		if (type === 'signal.hair') return 'Hair';
		if (type === 'signal.genital') return 'Genital';
		if (type === 'experiment') return 'Experiment';
		return type;
	}

	/* --- Entry summary --- */
	function summarize(type: string, data: Record<string, unknown>): string {
		switch (type) {
			case 'checkin':
				return `Mood ${data.mood ?? '?'}/10 · Energy ${data.energy ?? '?'}/10`;
			case 'intake':
				return `${data.what ?? '?'} (${data.amount ?? '?'})`;
			case 'training.strength':
				return `${data.exercise ?? '?'} ${data.sets ?? '?'}x${data.reps ?? '?'} @ ${data.weight ?? '?'}kg`;
			case 'training.cardio':
				return `${data.activity ?? '?'} ${data.distanceKm ?? '?'}km ${data.durationMin ?? '?'}min`;
			case 'training.hiit':
				return `${data.name ?? '?'} ${data.rounds ?? '?'}r`;
			case 'training.rings':
				return `${data.progression ?? '?'} L${data.level ?? '?'}`;
			case 'training.mobility':
				return `${data.routine ?? '?'} ${data.durationMin ?? '?'}min`;
			case 'habit':
				return `${data.habit ?? '?'} ${data.duration ?? ''}${data.unit ?? ''}`;
			case 'supplement':
				return `${data.name ?? '?'} ${data.dose ?? ''}`;
			case 'signal.sleep':
				return `Sleep ${data.hours ?? '?'}h Q${data.quality ?? '?'}/10`;
			case 'signal.skin':
				return `Skin O${data.oiliness ?? '?'} E${data.elasticity ?? '?'}`;
			case 'signal.hair':
				return `Hair D${data.density ?? '?'} S${data.shedding ?? '?'}`;
			case 'signal.genital':
				return `Libido ${data.libido ?? '?'}/10`;
			case 'experiment':
				return `${data.hypothesis ?? '?'} [${data.status ?? '?'}]`;
			default:
				return JSON.stringify(data).slice(0, 60);
		}
	}

	/* --- All known types for filter --- */
	const allTypes = [
		'checkin', 'intake',
		'training.strength', 'training.cardio', 'training.hiit', 'training.rings', 'training.mobility',
		'habit', 'supplement',
		'signal.sleep', 'signal.skin', 'signal.hair', 'signal.genital',
		'experiment'
	];

	/* --- Filter state --- */
	const today = new Date();
	const weekAgo = new Date(today.getTime() - 6 * 86400000);

	let dateFrom = $state(weekAgo.toISOString().slice(0, 10));
	let dateTo = $state(today.toISOString().slice(0, 10));
	let enabledTypes = $state<Set<string>>(new Set(allTypes));
	let filtersOpen = $state(false);
	let visibleCount = $state(50);

	function toggleType(type: string): void {
		const next = new Set(enabledTypes);
		if (next.has(type)) {
			next.delete(type);
		} else {
			next.add(type);
		}
		enabledTypes = next;
		visibleCount = 50;
	}

	function selectAllTypes(): void {
		enabledTypes = new Set(allTypes);
		visibleCount = 50;
	}

	function deselectAllTypes(): void {
		enabledTypes = new Set();
		visibleCount = 50;
	}

	/* --- Filtered + sorted entries --- */
	const filtered = $derived.by(() => {
		const from = dateFrom + 'T00:00:00';
		const to = dateTo + 'T23:59:59';
		const hasTags = selectedTags.length > 0;
		return store.items
			.filter((e) => {
				if (!enabledTypes.has(e.type)) return false;
				if (e.createdAt < from || e.createdAt > to) return false;
				if (hasTags) {
					const tags = e.data.tags;
					if (!Array.isArray(tags)) return false;
					if (!selectedTags.every((st) => tags.includes(st))) return false;
				}
				return true;
			})
			.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt));
	});

	/* --- Grouped by date --- */
	interface DateGroup {
		date: string;
		entries: typeof filtered;
	}

	const grouped = $derived.by((): DateGroup[] => {
		const groups: DateGroup[] = [];
		let currentDate = '';
		let currentEntries: typeof filtered = [];

		const visible = filtered.slice(0, visibleCount);

		for (const entry of visible) {
			const entryDate = entry.createdAt.slice(0, 10);
			if (entryDate !== currentDate) {
				if (currentEntries.length > 0) {
					groups.push({ date: currentDate, entries: currentEntries });
				}
				currentDate = entryDate;
				currentEntries = [entry];
			} else {
				currentEntries.push(entry);
			}
		}
		if (currentEntries.length > 0) {
			groups.push({ date: currentDate, entries: currentEntries });
		}

		return groups;
	});

	const hasMore = $derived(filtered.length > visibleCount);

	function loadMore(): void {
		visibleCount += 50;
	}

	function formatDateHeader(dateStr: string): string {
		const d = new Date(dateStr + 'T12:00:00');
		const todayStr = new Date().toISOString().slice(0, 10);
		const yesterday = new Date(Date.now() - 86400000).toISOString().slice(0, 10);

		if (dateStr === todayStr) return 'Today';
		if (dateStr === yesterday) return 'Yesterday';

		return d.toLocaleDateString('en', { weekday: 'short', month: 'short', day: 'numeric' });
	}

	function formatTime(iso: string): string {
		return iso.slice(11, 16);
	}
</script>

<svelte:head>
  <title>Timeline | Darink</title>
</svelte:head>

<PageHeader title="Timeline" back="/more" />

<!-- Filter bar -->
<section class="filter-bar">
	<div class="date-filters">
		<label class="date-label">
			From
			<input type="date" bind:value={dateFrom} />
		</label>
		<label class="date-label">
			To
			<input type="date" bind:value={dateTo} />
		</label>
	</div>

	<button class="toggle-filters" onclick={() => filtersOpen = !filtersOpen}>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
		Types ({enabledTypes.size}/{allTypes.length})
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="chevron" class:open={filtersOpen}><path d="m6 9 6 6 6-6"/></svg>
	</button>

	{#if filtersOpen}
	<div class="type-filters">
		<div class="type-actions">
			<button class="sm" onclick={selectAllTypes}>All</button>
			<button class="sm" onclick={deselectAllTypes}>None</button>
		</div>
		<div class="type-chips">
			{#each allTypes as type}
				<button
					class="type-chip"
					class:active={enabledTypes.has(type)}
					style="--chip-color: {getTypeColor(type)}"
					onclick={() => toggleType(type)}
				>
					{getTypeLabel(type)}
				</button>
			{/each}
		</div>
	</div>
	{/if}

	{#if allTags.length > 0}
	<button class="toggle-filters tag-toggle" onclick={() => tagFilterOpen = !tagFilterOpen}>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
		Tags ({selectedTags.length}/{allTags.length})
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="chevron" class:open={tagFilterOpen}><path d="m6 9 6 6 6-6"/></svg>
	</button>

	{#if tagFilterOpen}
	<div class="type-filters">
		{#if selectedTags.length > 0}
		<div class="type-actions">
			<button class="sm" onclick={clearTagFilter}>Clear tags</button>
		</div>
		{/if}
		<div class="type-chips">
			{#each allTags as tag}
				<button
					class="tag-filter-chip"
					class:active={selectedTags.includes(tag)}
					onclick={() => toggleTagFilter(tag)}
				>
					{tag}
					<span class="tag-chip-count">{allTagsData[tag]}</span>
				</button>
			{/each}
		</div>
	</div>
	{/if}
	{/if}
</section>

<!-- Timeline feed -->
{#if filtered.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
	<p>No entries in this range</p>
	<p class="empty-hint">Adjust the date range or type filters above.</p>
</div>
{:else}
<div class="feed">
	<div class="feed-count">{filtered.length} entries</div>

	{#each grouped as group}
		<div class="date-header">
			<span>{formatDateHeader(group.date)}</span>
			<span class="date-iso">{group.date}</span>
		</div>
		{#each group.entries as entry}
			{@const color = getTypeColor(entry.type)}
			<div class="entry-card" style="--entry-color: {color}">
				<div class="entry-top">
					<span class="type-badge" style="background: {color}">{getTypeLabel(entry.type)}</span>
					<span class="entry-time">{formatTime(entry.createdAt)}</span>
				</div>
				<div class="entry-summary">{summarize(entry.type, entry.data)}</div>
				{#if Array.isArray(entry.data.tags) && entry.data.tags.length > 0}
					<div class="entry-tags">
						{#each entry.data.tags as tag}
							<span class="entry-tag">{tag}</span>
						{/each}
					</div>
				{/if}
			</div>
		{/each}
	{/each}

	{#if hasMore}
	<div class="load-more-wrap">
		<button class="load-more" onclick={loadMore}>
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>
			Load more ({filtered.length - visibleCount} remaining)
		</button>
	</div>
	{/if}
</div>
{/if}

<style>
	/* --- Filter bar --- */
	.filter-bar {
		padding: 0 1rem 0.5rem;
	}

	.date-filters {
		display: flex;
		gap: 0.5rem;
		margin-bottom: 0.5rem;
	}

	.date-label {
		flex: 1;
		font-size: 0.8rem;
		font-weight: 500;
		color: var(--c-text-muted);
	}

	.date-label input {
		margin-top: 0.15rem;
		font-size: 0.85rem;
		padding: 0.4rem 0.5rem;
	}

	.toggle-filters {
		display: flex;
		align-items: center;
		gap: 0.4rem;
		width: 100%;
		font-size: 0.8rem;
		padding: 0.4rem 0.6rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		color: var(--c-text-muted);
		cursor: pointer;
		font-weight: 500;
	}

	.toggle-filters:hover {
		border-color: var(--c-accent);
		color: var(--c-text);
		background: var(--c-bg-card);
		transform: none;
		box-shadow: none;
	}

	.chevron {
		margin-left: auto;
		transition: transform 0.2s;
	}

	.chevron.open {
		transform: rotate(180deg);
	}

	.type-filters {
		margin-top: 0.5rem;
		padding: 0.75rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}

	.type-actions {
		display: flex;
		gap: 0.35rem;
		margin-bottom: 0.5rem;
	}

	.type-actions button.sm {
		font-size: 0.7rem;
		padding: 0.2rem 0.5rem;
		border-radius: 4px;
	}

	.type-chips {
		display: flex;
		flex-wrap: wrap;
		gap: 0.3rem;
	}

	.type-chip {
		font-size: 0.72rem;
		padding: 0.2rem 0.55rem;
		border-radius: 20px;
		border: 1px solid var(--chip-color);
		background: transparent;
		color: var(--chip-color);
		cursor: pointer;
		font-weight: 500;
		transition: background 0.15s, color 0.15s;
		line-height: 1.4;
	}

	.type-chip:hover {
		transform: none;
		box-shadow: none;
	}

	.type-chip.active {
		background: var(--chip-color);
		color: #fff;
	}

	/* --- Feed --- */
	.feed {
		padding: 0 1rem;
	}

	.feed-count {
		font-size: 0.75rem;
		color: var(--c-text-muted);
		margin-bottom: 0.5rem;
		font-weight: 500;
	}

	/* --- Date headers --- */
	.date-header {
		position: sticky;
		top: 0;
		z-index: 10;
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0.4rem 0;
		margin-top: 0.5rem;
		background: var(--c-bg);
		font-size: 0.75rem;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: var(--c-text-muted);
		border-bottom: 1px solid var(--c-border);
	}

	.date-iso {
		font-weight: 400;
		font-size: 0.7rem;
		font-variant-numeric: tabular-nums;
	}

	/* --- Entry cards --- */
	.entry-card {
		padding: 0.5rem 0.65rem;
		margin: 0.3rem 0;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-left: 3px solid var(--entry-color);
		border-radius: var(--radius);
	}

	.entry-top {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 0.15rem;
	}

	.type-badge {
		display: inline-block;
		font-size: 0.65rem;
		font-weight: 600;
		padding: 0.1rem 0.45rem;
		border-radius: 10px;
		color: #fff;
		text-transform: uppercase;
		letter-spacing: 0.03em;
		line-height: 1.5;
	}

	.entry-time {
		font-size: 0.7rem;
		color: var(--c-text-muted);
		font-variant-numeric: tabular-nums;
	}

	.entry-summary {
		font-size: 0.85rem;
		color: var(--c-text);
		line-height: 1.4;
		word-break: break-word;
	}

	/* --- Tag filter --- */
	.tag-toggle {
		margin-top: 0.5rem;
	}

	.tag-filter-chip {
		display: inline-flex;
		align-items: center;
		gap: 0.2rem;
		font-size: 0.72rem;
		padding: 0.2rem 0.55rem;
		border-radius: 20px;
		border: 1px solid var(--c-accent);
		background: transparent;
		color: var(--c-accent);
		cursor: pointer;
		font-weight: 500;
		transition: background 0.15s, color 0.15s;
		line-height: 1.4;
	}

	.tag-filter-chip:hover {
		transform: none;
		box-shadow: none;
	}

	.tag-filter-chip.active {
		background: var(--c-accent);
		color: #fff;
	}

	.tag-chip-count {
		font-size: 0.6rem;
		opacity: 0.7;
	}

	/* --- Entry tags --- */
	.entry-tags {
		display: flex;
		flex-wrap: wrap;
		gap: 0.2rem;
		margin-top: 0.25rem;
	}

	.entry-tag {
		font-size: 0.6rem;
		font-weight: 500;
		padding: 0.08rem 0.3rem;
		border-radius: 8px;
		background: var(--c-accent-bg);
		color: var(--c-text-muted);
	}

	/* --- Load more --- */
	.load-more-wrap {
		padding: 1rem 0 2rem;
		text-align: center;
	}

	.load-more {
		display: inline-flex;
		align-items: center;
		gap: 0.4rem;
		font-size: 0.85rem;
		padding: 0.5rem 1.25rem;
	}

	/* --- Responsive --- */
	@media (min-width: 600px) {
		.date-filters {
			max-width: 400px;
		}

		.entry-card {
			padding: 0.6rem 0.85rem;
		}
	}

	@media (min-width: 900px) {
		.filter-bar {
			padding: 0 1rem 0.75rem;
		}

		.date-filters {
			max-width: 450px;
		}

		.entry-card {
			padding: 0.65rem 1rem;
		}
	}
</style>
