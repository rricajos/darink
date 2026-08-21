<script lang="ts">
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { useLocale } from '$lib/stores/locale.svelte';

	const { t } = useLocale();
	const store = useEntries();

	/* --- Tag filter state --- */
	let selectedTags = $state<string[]>([]);
	let tagFilterOpen = $state(false);

	const allTagsData = $derived.by(() => {
		const counts: Record<string, number> = {};
		for (const e of store.items) {
			const tags = e.data.tags;
			if (Array.isArray(tags)) {
				for (const tag of tags) {
					if (typeof tag === 'string') {
						counts[tag] = (counts[tag] || 0) + 1;
					}
				}
			}
		}
		return counts;
	});

	const allTags = $derived(Object.keys(allTagsData).sort());

	function toggleTagFilter(tag: string): void {
		if (selectedTags.includes(tag)) {
			selectedTags = selectedTags.filter((st) => st !== tag);
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
		experiment: '#f59e0b',
		journal: '#6366f1',
		hydration: '#06b6d4',
		weight: '#8b5cf6',
		measurement: '#d946ef',
		bloodwork: '#ef4444',
		medication: '#f97316',
		symptom: '#dc2626'
	};

	function getTypeColor(type: string): string {
		if (typeColors[type]) return typeColors[type];
		if (type.startsWith('training.')) return '#f97316';
		if (type.startsWith('signal.')) return '#ec4899';
		return 'var(--c-text-muted)';
	}

	/* --- Type labels --- */
	function getTypeLabel(type: string): string {
		const map: Record<string, string> = {
			'checkin': t.timeline.checkin,
			'intake': t.timeline.intake,
			'training.strength': t.timeline.strength,
			'training.cardio': t.timeline.cardio,
			'training.hiit': t.timeline.hiit,
			'training.rings': t.timeline.rings,
			'training.mobility': t.timeline.mobility,
			'habit': t.timeline.habit,
			'supplement': t.timeline.supplement,
			'signal.sleep': t.timeline.sleep,
			'signal.skin': t.timeline.skin,
			'signal.hair': t.timeline.hair,
			'signal.genital': t.timeline.genital,
			'experiment': t.timeline.experiment,
			'journal': t.timeline.journal,
			'hydration': t.timeline.hydration,
			'weight': t.timeline.weight,
			'measurement': t.timeline.measurement,
			'bloodwork': t.timeline.bloodWork,
			'medication': t.timeline.medication,
			'symptom': t.timeline.symptom
		};
		return map[type] ?? type;
	}

	/* --- Entry summary --- */
	function summarize(type: string, data: Record<string, unknown>): string {
		switch (type) {
			case 'checkin':
				return `${t.common.mood} ${data.mood ?? '?'}/10 · ${t.common.energy} ${data.energy ?? '?'}/10`;
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
				return `${t.timeline.sleep} ${data.hours ?? '?'}h Q${data.quality ?? '?'}/10`;
			case 'signal.skin':
				return `${t.timeline.skin} O${data.oiliness ?? '?'} E${data.elasticity ?? '?'}`;
			case 'signal.hair':
				return `${t.timeline.hair} D${data.density ?? '?'} S${data.shedding ?? '?'}`;
			case 'signal.genital':
				return `${t.genital.libido} ${data.libido ?? '?'}/10`;
			case 'experiment':
				return `${data.hypothesis ?? '?'} [${data.status ?? '?'}]`;
			case 'journal': {
				const text = typeof data.text === 'string' ? data.text : '';
				return text.length > 0 ? text.slice(0, 60) : t.timeline.entry;
			}
			case 'hydration':
				return `${data.amount ?? '?'}ml ${data.source ?? ''}`.trim();
			case 'weight': {
				let w = `${data.weight ?? '?'}kg`;
				if (data.bodyFat != null) w += ` ${data.bodyFat}% BF`;
				return w;
			}
			case 'measurement': {
				const parts: string[] = [];
				if (data.waist != null) parts.push(`${t.profile.waist} ${data.waist}cm`);
				if (data.chest != null) parts.push(`${t.profile.chest} ${data.chest}cm`);
				return parts.length > 0 ? parts.join(' / ') : t.timeline.measurement;
			}
			case 'bloodwork': {
				if (data.marker != null && data.value != null) return `${data.marker}: ${data.value}`;
				return t.timeline.bloodWork;
			}
			case 'medication':
				return `${data.medication ?? '?'} ${data.dose ?? ''}`.trim();
			case 'symptom':
				return `${data.symptom ?? '?'} ${t.medications.severity} ${data.severity ?? '?'}/10`;
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
		'experiment',
		'journal', 'hydration', 'weight', 'measurement', 'bloodwork', 'medication', 'symptom'
	];

	interface TypeGroup {
		label: string;
		types: string[];
	}

	const typeGroups = $derived.by((): TypeGroup[] => [
		{ label: t.more.healthTracking, types: ['checkin', 'intake', 'hydration', 'habit', 'supplement', 'medication', 'symptom', 'journal'] },
		{ label: t.more.training, types: ['training.strength', 'training.cardio', 'training.hiit', 'training.rings', 'training.mobility'] },
		{ label: t.more.signals, types: ['signal.sleep', 'signal.skin', 'signal.hair', 'signal.genital'] },
		{ label: t.timeline.bodyLabs, types: ['weight', 'measurement', 'bloodwork', 'experiment'] }
	]);

	function toggleGroup(types: string[]): void {
		const allOn = types.every(t => enabledTypes.has(t));
		const next = new Set(enabledTypes);
		for (const t of types) {
			if (allOn) next.delete(t);
			else next.add(t);
		}
		enabledTypes = next;
		visibleCount = 50;
	}

	/* --- Filter state --- */
	const today = new Date();
	const weekAgo = new Date(today.getTime() - 6 * 86400000);

	let dateFrom = $state(weekAgo.toISOString().slice(0, 10));
	let dateTo = $state(today.toISOString().slice(0, 10));
	let enabledTypes = $state<Set<string>>(new Set(allTypes));
	let filtersOpen = $state(false);
	let visibleCount = $state(50);
	let searchQuery = $state('');

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

	function matchesSearch(data: Record<string, unknown>, query: string): boolean {
		for (const val of Object.values(data)) {
			if (typeof val === 'string' && val.toLowerCase().includes(query)) return true;
			if (typeof val === 'number' && String(val).includes(query)) return true;
		}
		return false;
	}

	/* --- Filtered + sorted entries --- */
	const filtered = $derived.by(() => {
		const from = dateFrom + 'T00:00:00';
		const to = dateTo + 'T23:59:59';
		const hasTags = selectedTags.length > 0;
		const q = searchQuery.trim().toLowerCase();
		return store.items
			.filter((e) => {
				if (!enabledTypes.has(e.type)) return false;
				if (e.createdAt < from || e.createdAt > to) return false;
				if (hasTags) {
					const tags = e.data.tags;
					if (!Array.isArray(tags)) return false;
					if (!selectedTags.every((st) => tags.includes(st))) return false;
				}
				if (q && !matchesSearch(e.data, q) && !e.type.toLowerCase().includes(q)) return false;
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
		const yesterdayStr = new Date(Date.now() - 86400000).toISOString().slice(0, 10);

		if (dateStr === todayStr) return t.timeline.today;
		if (dateStr === yesterdayStr) return t.timeline.yesterday;

		return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
	}

	/* --- Daily density data (max 60 days) --- */
	interface DayDensity {
		date: string;
		count: number;
		dominantType: string;
	}

	const dailyDensity = $derived.by((): DayDensity[] => {
		if (filtered.length === 0) return [];

		const dayCounts: Record<string, Record<string, number>> = {};

		for (const e of filtered) {
			const d = e.createdAt.slice(0, 10);
			if (!dayCounts[d]) dayCounts[d] = {};
			dayCounts[d][e.type] = (dayCounts[d][e.type] || 0) + 1;
		}

		const fromDate = new Date(dateFrom + 'T00:00:00');
		const toDate = new Date(dateTo + 'T00:00:00');
		const dayMs = 86400000;
		const totalDays = Math.min(60, Math.round((toDate.getTime() - fromDate.getTime()) / dayMs) + 1);

		const result: DayDensity[] = [];
		for (let i = 0; i < totalDays; i++) {
			const d = new Date(fromDate.getTime() + i * dayMs);
			const ds = d.toISOString().slice(0, 10);
			const types = dayCounts[ds] ?? {};
			const count = Object.values(types).reduce((s, v) => s + v, 0);
			let dominant = '';
			let maxC = 0;
			for (const [tp, c] of Object.entries(types)) {
				if (c > maxC) { maxC = c; dominant = tp; }
			}
			result.push({ date: ds, count, dominantType: dominant });
		}
		return result;
	});

	const densityMax = $derived(Math.max(1, ...dailyDensity.map((d) => d.count)));

	/* --- Summary stats --- */
	interface TypeCount {
		type: string;
		count: number;
	}

	const summaryStats = $derived.by(() => {
		const total = filtered.length;
		if (total === 0) return null;

		// Most active day
		const dayCounts: Record<string, number> = {};
		const typeCounts: Record<string, number> = {};
		for (const e of filtered) {
			const d = e.createdAt.slice(0, 10);
			dayCounts[d] = (dayCounts[d] || 0) + 1;
			typeCounts[e.type] = (typeCounts[e.type] || 0) + 1;
		}

		let bestDay = '';
		let bestDayCount = 0;
		for (const [d, c] of Object.entries(dayCounts)) {
			if (c > bestDayCount) { bestDayCount = c; bestDay = d; }
		}

		const uniqueDays = Object.keys(dayCounts).length;
		const avg = uniqueDays > 0 ? (total / uniqueDays) : 0;

		const topTypes: TypeCount[] = Object.entries(typeCounts)
			.map(([type, count]) => ({ type, count }))
			.sort((a, b) => b.count - a.count)
			.slice(0, 3);

		return {
			total,
			bestDay,
			bestDayCount,
			avg: avg.toFixed(1),
			topTypes
		};
	});

	function formatDayName(dateStr: string): string {
		const d = new Date(dateStr + 'T12:00:00');
		return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
	}

	let confirmDeleteId = $state<string | null>(null);

	function deleteEntry(entry: Entry) {
		if (confirmDeleteId !== entry.id) {
			confirmDeleteId = entry.id;
			return;
		}
		entries.remove(entry.id);
		confirmDeleteId = null;
		toast.show(`${getTypeLabel(entry.type)} ${t.common.deleted}`, {
			label: t.common.undo,
			fn: () => entries.restore(entry)
		});
	}

	function formatTime(iso: string): string {
		return iso.slice(11, 16);
	}
</script>

<svelte:head>
  <title>{t.timeline.title} | Darink</title>
</svelte:head>

<PageHeader title={t.timeline.title} back="/more" />

<!-- Filter bar -->
<section class="filter-bar">
	<div class="search-bar">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
		<input type="text" class="search-input" bind:value={searchQuery} placeholder={t.timeline.searchEntries} />
		{#if searchQuery}
			<button class="search-clear" onclick={() => { searchQuery = ''; }} aria-label={t.common.close}>
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
			</button>
		{/if}
	</div>
	<div class="date-filters">
		<label class="date-label">
			{t.timeline.from}
			<input type="date" bind:value={dateFrom} />
		</label>
		<label class="date-label">
			{t.timeline.to}
			<input type="date" bind:value={dateTo} />
		</label>
	</div>

	<button class="toggle-filters" onclick={() => filtersOpen = !filtersOpen}>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
		{t.timeline.types} ({enabledTypes.size}/{allTypes.length})
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="chevron" class:open={filtersOpen}><path d="m6 9 6 6 6-6"/></svg>
	</button>

	{#if filtersOpen}
	<div class="type-filters">
		<div class="type-actions">
			<button class="sm" onclick={selectAllTypes}>{t.timeline.all}</button>
			<button class="sm" onclick={deselectAllTypes}>{t.timeline.none}</button>
		</div>
		{#each typeGroups as group}
			{@const allOn = group.types.every(t => enabledTypes.has(t))}
			<div class="type-group">
				<button class="type-group-header" onclick={() => toggleGroup(group.types)}>
					<span class="type-group-label">{group.label}</span>
					<span class="type-group-toggle" class:all-on={allOn}>{allOn ? '−' : '+'}</span>
				</button>
				<div class="type-chips">
					{#each group.types as type}
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
		{/each}
	</div>
	{/if}

	{#if allTags.length > 0}
	<button class="toggle-filters tag-toggle" onclick={() => tagFilterOpen = !tagFilterOpen}>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
		{t.timeline.tags} ({selectedTags.length}/{allTags.length})
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="chevron" class:open={tagFilterOpen}><path d="m6 9 6 6 6-6"/></svg>
	</button>

	{#if tagFilterOpen}
	<div class="type-filters">
		{#if selectedTags.length > 0}
		<div class="type-actions">
			<button class="sm" onclick={clearTagFilter}>{t.timeline.clearTags}</button>
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

<!-- Daily activity density -->
{#if dailyDensity.length > 0 && filtered.length > 0}
<section class="density-section">
	<div class="density-label">{t.timeline.dailyActivity}</div>
	<div class="density-chart-wrap">
		<svg class="density-chart" viewBox="0 0 {dailyDensity.length * 10} 40" preserveAspectRatio="none">
			{#each dailyDensity as day, i}
				{@const barH = day.count > 0 ? Math.max(2, (day.count / densityMax) * 36) : 0}
				{@const barColor = day.count > 0 ? getTypeColor(day.dominantType) : 'transparent'}
				<rect
					x={i * 10 + 1}
					y={40 - barH}
					width="8"
					height={barH}
					rx="1.5"
					fill={barColor}
					opacity="0.85"
				>
					<title>{day.date}: {day.count} {day.count === 1 ? t.timeline.entry : t.timeline.entries}</title>
				</rect>
			{/each}
		</svg>
	</div>
</section>
{/if}

<!-- Timeline feed -->
{#if filtered.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
	<p>{t.timeline.noEntries}</p>
	<p class="empty-hint">{t.timeline.noEntriesHint}</p>
</div>
{:else}
<div class="feed">
	{#if summaryStats}
	<div class="summary-stats">
		<div class="stat-row">
			<div class="stat-item">
				<span class="stat-value">{summaryStats.total}</span>
				<span class="stat-label">{t.timeline.entries}</span>
			</div>
			<div class="stat-item">
				<span class="stat-value">{summaryStats.avg}</span>
				<span class="stat-label">{t.timeline.perDay}</span>
			</div>
			<div class="stat-item">
				<span class="stat-value">{summaryStats.bestDayCount}</span>
				<span class="stat-label">{formatDayName(summaryStats.bestDay)}</span>
			</div>
		</div>
		{#if summaryStats.topTypes.length > 0}
		<div class="top-types">
			{#each summaryStats.topTypes as tp}
				<span class="top-type-chip" style="--chip-color: {getTypeColor(tp.type)}">
					{getTypeLabel(tp.type)} <span class="top-type-count">{tp.count}</span>
				</span>
			{/each}
		</div>
		{/if}
	</div>
	{/if}

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
					<div class="entry-top-right">
						<span class="entry-time">{formatTime(entry.createdAt)}</span>
						<button
							class="entry-delete"
							class:confirm={confirmDeleteId === entry.id}
							onclick={() => deleteEntry(entry)}
							aria-label={t.common.delete}
							title={confirmDeleteId === entry.id ? t.common.confirmDelete : t.common.delete}
						>
							{#if confirmDeleteId === entry.id}
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--c-cancel)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
							{:else}
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
							{/if}
						</button>
					</div>
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
			{t.timeline.loadMore} ({filtered.length - visibleCount} {t.timeline.remaining})
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

	.search-bar {
		display: flex;
		align-items: center;
		gap: 0.4rem;
		padding: 0.4rem 0.6rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		margin-bottom: 0.5rem;
		color: var(--c-text-muted);
	}

	.search-bar:focus-within {
		border-color: var(--c-accent);
	}

	.search-input {
		flex: 1;
		border: none;
		background: transparent;
		font-size: 0.85rem;
		color: var(--c-text);
		padding: 0.15rem 0;
		outline: none;
	}

	.search-clear {
		background: none;
		border: none;
		padding: 0.15rem;
		cursor: pointer;
		color: var(--c-text-muted);
		display: flex;
		align-items: center;
	}

	.search-clear:hover {
		color: var(--c-text);
		background: none;
		transform: none;
		box-shadow: none;
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

	.type-group {
		margin-top: 0.4rem;
	}

	.type-group-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		width: 100%;
		background: none;
		border: none;
		padding: 0.2rem 0;
		cursor: pointer;
		margin-bottom: 0.25rem;
	}

	.type-group-header:hover {
		background: none;
		transform: none;
		box-shadow: none;
	}

	.type-group-label {
		font-size: 0.68rem;
		font-weight: 600;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.04em;
	}

	.type-group-toggle {
		font-size: 0.75rem;
		font-weight: 700;
		color: var(--c-text-muted);
		width: 18px;
		height: 18px;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: 50%;
		background: var(--c-border);
	}

	.type-group-toggle.all-on {
		background: var(--c-accent);
		color: #fff;
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

	.entry-top-right {
		display: flex;
		align-items: center;
		gap: 0.4rem;
	}

	.entry-time {
		font-size: 0.7rem;
		color: var(--c-text-muted);
		font-variant-numeric: tabular-nums;
	}

	.entry-delete {
		display: flex;
		align-items: center;
		justify-content: center;
		background: none;
		border: none;
		padding: 0.2rem;
		cursor: pointer;
		color: var(--c-text-muted);
		opacity: 0;
		transition: opacity 0.15s, color 0.15s;
		border-radius: var(--radius);
	}

	.entry-card:hover .entry-delete {
		opacity: 1;
	}

	.entry-delete.confirm {
		opacity: 1;
		background: color-mix(in srgb, var(--c-cancel) 10%, transparent);
	}

	.entry-delete:hover {
		color: var(--c-cancel);
		background: none;
		transform: none;
		box-shadow: none;
	}

	@media (max-width: 599px) {
		.entry-delete {
			opacity: 0.5;
		}
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

	/* --- Density chart --- */
	.density-section {
		padding: 0 1rem 0.25rem;
	}

	.density-label {
		font-size: 0.7rem;
		font-weight: 600;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
		margin-bottom: 0.25rem;
	}

	.density-chart-wrap {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.35rem 0.5rem;
		overflow-x: auto;
	}

	.density-chart {
		display: block;
		width: 100%;
		height: 40px;
		min-width: 200px;
	}

	/* --- Summary stats --- */
	.summary-stats {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.6rem 0.75rem;
		margin-bottom: 0.6rem;
	}

	.stat-row {
		display: flex;
		gap: 0.75rem;
		justify-content: space-around;
	}

	.stat-item {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 0.05rem;
	}

	.stat-value {
		font-size: 1.1rem;
		font-weight: 700;
		color: var(--c-text);
		font-variant-numeric: tabular-nums;
		line-height: 1.2;
	}

	.stat-label {
		font-size: 0.65rem;
		color: var(--c-text-muted);
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		max-width: 7rem;
	}

	.top-types {
		display: flex;
		flex-wrap: wrap;
		gap: 0.3rem;
		margin-top: 0.45rem;
		justify-content: center;
	}

	.top-type-chip {
		display: inline-flex;
		align-items: center;
		gap: 0.2rem;
		font-size: 0.68rem;
		font-weight: 600;
		padding: 0.12rem 0.5rem;
		border-radius: 20px;
		background: color-mix(in srgb, var(--chip-color) 15%, transparent);
		color: var(--chip-color);
		line-height: 1.4;
	}

	.top-type-count {
		font-size: 0.62rem;
		opacity: 0.75;
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
