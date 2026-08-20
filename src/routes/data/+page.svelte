<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import TagInput from '$lib/components/TagInput.svelte';
	import { db, ui, type Entry } from '$lib/db';
	import { useEntries, bumpEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { onMount } from 'svelte';
	import { useLocale } from '$lib/stores/locale.svelte';

	const { t } = useLocale();
	const store = useEntries();

	let search = $state('');
	let fileInput: HTMLInputElement;
	let confirmClear = $state(false);

	let exportType = $state('');
	let exportFrom = $state('');
	let exportTo = $state('');

	/* --- Auto-backup tracking --- */
	let lastExportDate = $state<string | null>(null);

	onMount(() => {
		const saved = ui.get().lastExportDate;
		if (typeof saved === 'string') lastExportDate = saved;
	});

	const backupStatus = $derived.by(() => {
		if (!lastExportDate) return { label: t.data.neverBackedUp, daysAgo: Infinity, warn: true };
		const diff = Math.floor((Date.now() - new Date(lastExportDate).getTime()) / 86400000);
		return { label: t.data.lastBackup.replace('{n}', String(diff)), daysAgo: diff, warn: diff > 7 };
	});

	function markExported() {
		const now = new Date().toISOString();
		lastExportDate = now;
		ui.patch({ lastExportDate: now });
	}

	/* --- Donut chart colors --- */
	const TYPE_COLORS: Record<string, string> = {
		checkin: '#4aa3ff',
		intake: '#22c55e',
		habit: '#a855f7',
		supplement: '#14b8a6',
		journal: '#6366f1',
		hydration: '#06b6d4',
		experiment: '#f59e0b',
		weight: '#8b5cf6',
		measurement: '#d946ef',
		bloodwork: '#ef4444',
		medication: '#f97316',
		symptom: '#dc2626'
	};

	function getTypeColor(type: string): string {
		if (TYPE_COLORS[type]) return TYPE_COLORS[type];
		if (type.startsWith('training')) return '#f97316';
		if (type.startsWith('signal')) return '#ec4899';
		return '#94a3b8';
	}

	const donutData = $derived.by(() => {
		const items = stats.byType;
		const total = stats.total;
		if (total === 0) return [];
		let offset = 0;
		return items.map(([type, count]) => {
			const pct = count / total;
			const dash = pct * 100;
			const entry = { type, count, color: getTypeColor(type), dash, offset };
			offset += dash;
			return entry;
		});
	});

	/* --- Entry count stats --- */
	const entryStats = $derived.by(() => {
		const all = store.items;
		const total = all.length;
		const now = Date.now();
		const weekAgo = now - 7 * 86400000;
		const monthAgo = now - 30 * 86400000;
		let thisWeek = 0;
		let last30 = 0;
		let oldest: string | null = null;
		for (const e of all) {
			const ts = new Date(e.createdAt).getTime();
			if (ts >= weekAgo) thisWeek++;
			if (ts >= monthAgo) last30++;
			if (!oldest || e.createdAt < oldest) oldest = e.createdAt;
		}
		const avgPerDay = last30 > 0 ? (last30 / 30) : 0;
		return {
			total,
			thisWeek,
			avgPerDay: avgPerDay.toFixed(1),
			oldest: oldest ? oldest.slice(0, 10) : null
		};
	});

	/* --- Storage usage (full localStorage) --- */
	const storageUsage = $derived.by(() => {
		const _ = store.items;
		if (typeof localStorage === 'undefined') return { bytes: 0, pct: 0, label: '0 B', maxLabel: '5 MB', color: '#38a169' };
		let totalBytes = 0;
		for (let i = 0; i < localStorage.length; i++) {
			const key = localStorage.key(i);
			if (key) {
				totalBytes += key.length + (localStorage.getItem(key)?.length ?? 0);
			}
		}
		totalBytes *= 2; // UTF-16
		const maxBytes = 5 * 1024 * 1024;
		const pct = (totalBytes / maxBytes) * 100;
		let label: string;
		if (totalBytes < 1024) label = `${totalBytes} B`;
		else if (totalBytes < 1024 * 1024) label = `${(totalBytes / 1024).toFixed(1)} KB`;
		else label = `${(totalBytes / (1024 * 1024)).toFixed(2)} MB`;
		const color = pct >= 80 ? '#e53e3e' : pct >= 50 ? '#d69e2e' : '#38a169';
		return { bytes: totalBytes, pct, label, maxLabel: '5 MB', color };
	});

	let selectedTags = $state<string[]>([]);
	let tagCloudOpen = $state(false);

	/* --- All tags across all entries --- */
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
	}

	function clearTagFilter(): void {
		selectedTags = [];
	}

	const filtered = $derived.by(() => {
		const all = store.items;
		const q = search.trim().toLowerCase();
		const hasTags = selectedTags.length > 0;

		let results = all;

		if (q) {
			results = results.filter((e) => {
				if (e.type.toLowerCase().includes(q)) return true;
				for (const val of Object.values(e.data)) {
					if (typeof val === 'string' && val.toLowerCase().includes(q)) return true;
				}
				const tags = e.data.tags;
				if (Array.isArray(tags)) {
					for (const tag of tags) {
						if (typeof tag === 'string' && tag.toLowerCase().includes(q)) return true;
					}
				}
				return false;
			});
		}

		if (hasTags) {
			results = results.filter((e) => {
				const tags = e.data.tags;
				if (!Array.isArray(tags)) return false;
				return selectedTags.every((st) => tags.includes(st));
			});
		}

		return results
			.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt))
			.slice(0, 50);
	});

	const stats = $derived.by(() => {
		const all = store.items;
		const byType: Record<string, number> = {};
		for (const e of all) {
			byType[e.type] = (byType[e.type] || 0) + 1;
		}
		const sorted = all.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt));
		const jsonSize = JSON.stringify(all).length;
		return {
			total: all.length,
			byType: Object.entries(byType).sort((a, b) => b[1] - a[1]),
			firstDate: sorted.length > 0 ? sorted[0].createdAt.slice(0, 10) : null,
			lastDate: sorted.length > 0 ? sorted[sorted.length - 1].createdAt.slice(0, 10) : null,
			sizeKB: (jsonSize / 1024).toFixed(1)
		};
	});

	function summarize(entry: Entry): string {
		const parts: string[] = [];
		for (const [key, val] of Object.entries(entry.data)) {
			if (typeof val === 'string' && val) {
				parts.push(`${key}: ${val.length > 30 ? val.slice(0, 30) + '...' : val}`);
			} else if (typeof val === 'number') {
				parts.push(`${key}: ${val}`);
			} else if (typeof val === 'boolean' && val) {
				parts.push(key);
			}
		}
		return parts.slice(0, 4).join(' · ') || '—';
	}

	function formatDate(iso: string): string {
		return iso.slice(0, 10);
	}

	const exportTypes = ['checkin', 'intake', 'training', 'habit', 'supplement', 'signal', 'experiment', 'journal', 'weight'] as const;

	function getFilteredEntries(): Entry[] {
		let all = db.getAll();
		if (exportType) {
			if (exportType === 'training' || exportType === 'signal') {
				all = all.filter((e) => e.type.startsWith(exportType));
			} else {
				all = all.filter((e) => e.type === exportType);
			}
		}
		if (exportFrom) {
			all = all.filter((e) => e.createdAt.slice(0, 10) >= exportFrom);
		}
		if (exportTo) {
			all = all.filter((e) => e.createdAt.slice(0, 10) <= exportTo);
		}
		return all;
	}

	function exportJSON() {
		const data = getFilteredEntries();
		if (data.length === 0) {
			toast.show(t.data.noDataToExport);
			return;
		}
		const json = JSON.stringify(data, null, 2);
		const blob = new Blob([json], { type: 'application/json' });
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		const date = new Date().toISOString().slice(0, 10);
		a.download = `darink-export-${date}.json`;
		a.click();
		URL.revokeObjectURL(url);
		toast.show(t.data.entriesExportedJSON.replace('{n}', String(data.length)));
		markExported();
	}

	function exportCSV() {
		const all = getFilteredEntries();
		if (all.length === 0) {
			toast.show(t.data.noDataToExport);
			return;
		}
		const dataKeys = new Set<string>();
		for (const e of all) {
			for (const key of Object.keys(e.data)) {
				dataKeys.add(key);
			}
		}
		const sortedKeys = [...dataKeys].sort();
		const headers = ['id', 'type', 'createdAt', 'updatedAt', ...sortedKeys];
		const csvEscape = (val: unknown): string => {
			const s = val == null ? '' : String(val);
			if (s.includes(',') || s.includes('"') || s.includes('\n')) {
				return '"' + s.replace(/"/g, '""') + '"';
			}
			return s;
		};
		const rows = [headers.join(',')];
		for (const e of all) {
			const row = [
				csvEscape(e.id),
				csvEscape(e.type),
				csvEscape(e.createdAt),
				csvEscape(e.updatedAt),
				...sortedKeys.map((k) => csvEscape(e.data[k]))
			];
			rows.push(row.join(','));
		}
		const csv = rows.join('\n');
		const blob = new Blob([csv], { type: 'text/csv' });
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		const date = new Date().toISOString().slice(0, 10);
		a.download = `darink-export-${date}.csv`;
		a.click();
		URL.revokeObjectURL(url);
		toast.show(t.data.entriesExportedCSV.replace('{n}', String(all.length)));
		markExported();
	}

	function importData() {
		fileInput.click();
	}

	function handleFile(e: Event) {
		const file = (e.target as HTMLInputElement).files?.[0];
		if (!file) return;
		const reader = new FileReader();
		reader.onload = () => {
			try {
				const count = db.importJSON(reader.result as string);
				toast.show(t.data.entriesImported.replace('{n}', String(count)));
				bumpEntries();
			} catch {
				toast.show(t.data.invalidFileFormat);
			}
		};
		reader.readAsText(file);
	}

	function clearAll() {
		if (!confirmClear) {
			confirmClear = true;
			setTimeout(() => { confirmClear = false; }, 3000);
			return;
		}
		db.clear();
		toast.show(t.data.allDataCleared);
		bumpEntries();
	}

	/* --- Storage Health --- */
	const MAX_BYTES = 5 * 1024 * 1024;

	const storageHealth = $derived.by(() => {
		const _ = store.items; // reactivity trigger
		const raw = typeof localStorage !== 'undefined' ? localStorage.getItem('darinkDB') ?? '' : '';
		const usedBytes = new Blob([raw]).size;
		const pct = (usedBytes / MAX_BYTES) * 100;
		const usedMB = (usedBytes / (1024 * 1024)).toFixed(2);
		const maxMB = (MAX_BYTES / (1024 * 1024)).toFixed(0);
		const level: 'ok' | 'warn' | 'critical' = pct > 80 ? 'critical' : pct > 50 ? 'warn' : 'ok';
		return { usedBytes, pct, usedMB, maxMB, level };
	});

	const duplicates = $derived.by(() => {
		const all = store.items;
		const seen = new Map<string, Entry>();
		const dupeIds: string[] = [];
		const sorted = all.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt) || a.id.localeCompare(b.id));
		for (const e of sorted) {
			const key = e.type + '|' + e.createdAt + '|' + JSON.stringify(e.data);
			if (seen.has(key)) {
				dupeIds.push(e.id);
			} else {
				seen.set(key, e);
			}
		}
		return dupeIds;
	});

	function cleanDuplicates() {
		if (duplicates.length === 0) return;
		const count = duplicates.length;
		entries.removeMany(duplicates);
		toast.show(t.data.removedDuplicates.replace('{n}', String(count)));
	}

	const dataQuality = $derived.by(() => {
		const all = store.items;
		let missing = 0;
		for (const e of all) {
			if (e.type === 'checkin') {
				if (e.data.mood == null) { missing++; continue; }
			} else if (e.type === 'intake') {
				if (!e.data.what) { missing++; continue; }
			} else if (e.type === 'training.strength') {
				if (!e.data.exercise) { missing++; continue; }
			} else if (e.type === 'training.cardio') {
				if (!e.data.activity) { missing++; continue; }
			} else if (e.type === 'training.hiit') {
				if (!e.data.name) { missing++; continue; }
			} else if (e.type === 'training.mobility') {
				if (!e.data.routine) { missing++; continue; }
			} else if (e.type === 'training.rings') {
				if (!e.data.progression) { missing++; continue; }
			}
		}
		return missing;
	});
</script>

<svelte:head>
  <title>{t.data.title} | Darink</title>
</svelte:head>

<PageHeader title={t.data.title} />

<!-- Entry Count Stats -->
<section class="metrics-section">
	<div class="metrics-grid">
		<div class="metric-card">
			<span class="metric-value">{entryStats.total}</span>
			<span class="metric-label">{t.data.totalEntries}</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{entryStats.thisWeek}</span>
			<span class="metric-label">{t.data.thisWeek}</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{entryStats.avgPerDay}</span>
			<span class="metric-label">{t.data.perDay30d}</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{entryStats.oldest ?? '--'}</span>
			<span class="metric-label">{t.data.oldestEntry}</span>
		</div>
	</div>
</section>

<!-- Entry Distribution Chart -->
{#if donutData.length > 0}
<section class="donut-section">
	<h2>
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
		{t.data.entryDistribution}
	</h2>
	<div class="donut-wrapper">
		<svg class="donut-chart" viewBox="0 0 42 42" role="img" aria-label="Entry distribution donut chart">
			{#each donutData as seg}
				<circle
					cx="21" cy="21" r="15.915"
					fill="none"
					stroke={seg.color}
					stroke-width="5"
					stroke-dasharray="{seg.dash} {100 - seg.dash}"
					stroke-dashoffset="{-seg.offset}"
					transform="rotate(-90 21 21)"
				/>
			{/each}
		</svg>
		<div class="donut-legend">
			{#each donutData as seg}
				<div class="legend-item">
					<span class="legend-swatch" style="background: {seg.color}"></span>
					<span class="legend-type">{seg.type}</span>
					<span class="legend-count">{seg.count}</span>
				</div>
			{/each}
		</div>
	</div>
</section>
{/if}

<!-- Search -->
<section class="search-section">
	<input
		type="search"
		class="search-input"
		placeholder={t.data.searchAllEntries}
		bind:value={search}
	/>
	{#if search.trim() || selectedTags.length > 0}
		<p class="search-hint">{filtered.length} {t.data.results}</p>
	{/if}
</section>

<!-- Tag filter -->
{#if allTags.length > 0}
<section class="tag-filter-section">
	<div class="tag-filter-header">
		<h2>
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
			{t.data.filterByTags}
		</h2>
		{#if selectedTags.length > 0}
			<button class="clear-tags-btn" onclick={clearTagFilter}>{t.data.clear}</button>
		{/if}
	</div>
	<div class="tag-filter-chips">
		{#each allTags as tag}
			<button
				class="filter-tag-chip"
				class:active={selectedTags.includes(tag)}
				onclick={() => toggleTagFilter(tag)}
			>
				{tag}
				<span class="tag-count">{allTagsData[tag]}</span>
			</button>
		{/each}
	</div>
</section>
{/if}

<!-- Tag Cloud -->
{#if allTags.length > 0}
<section class="tag-cloud-section">
	<button class="toggle-cloud" onclick={() => tagCloudOpen = !tagCloudOpen}>
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>
		{t.data.tagCloud}
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="chevron" class:open={tagCloudOpen}><path d="m6 9 6 6 6-6"/></svg>
	</button>
	{#if tagCloudOpen}
		{@const maxCount = Math.max(...Object.values(allTagsData), 1)}
		<div class="tag-cloud">
			{#each allTags as tag}
				{@const count = allTagsData[tag]}
				{@const size = 0.75 + (count / maxCount) * 0.75}
				<button
					class="cloud-tag"
					style="font-size: {size}rem"
					class:active={selectedTags.includes(tag)}
					onclick={() => toggleTagFilter(tag)}
				>
					{tag}
				</button>
			{/each}
		</div>
	{/if}
</section>
{/if}

<!-- Search Results -->
{#if search.trim() || selectedTags.length > 0}
	<section class="results">
		{#each filtered as entry (entry.id)}
			<div class="result-item">
				<div class="result-top">
					<span class="type-badge">{entry.type}</span>
					<span class="result-date">{formatDate(entry.createdAt)}</span>
				</div>
				<p class="result-summary">{summarize(entry)}</p>
				{#if Array.isArray(entry.data.tags) && entry.data.tags.length > 0}
					<div class="entry-tags">
						{#each entry.data.tags as tag}
							<span class="entry-tag">{tag}</span>
						{/each}
					</div>
				{/if}
			</div>
		{:else}
			<p class="empty">{t.data.noMatchSearch}</p>
		{/each}
	</section>
{/if}

<!-- Stats -->
<section class="stats-section">
	<h2>{t.data.statistics}</h2>
	<div class="stats-grid">
		<div class="stat-card">
			<span class="stat-value">{stats.total}</span>
			<span class="stat-label">{t.data.totalEntries}</span>
		</div>
		<div class="stat-card">
			<span class="stat-value">{stats.sizeKB} KB</span>
			<span class="stat-label">{t.data.storageUsed}</span>
		</div>
		<div class="stat-card">
			<span class="stat-value">{stats.firstDate ?? '—'}</span>
			<span class="stat-label">{t.data.firstEntry}</span>
		</div>
		<div class="stat-card">
			<span class="stat-value">{stats.lastDate ?? '—'}</span>
			<span class="stat-label">{t.data.lastEntry}</span>
		</div>
	</div>
	{#if stats.byType.length > 0}
		<h2>{t.data.byType}</h2>
		<div class="type-list">
			{#each stats.byType as [type, count]}
				<div class="type-row">
					<span class="type-badge">{type}</span>
					<span class="type-count">{count}</span>
				</div>
			{/each}
		</div>
	{/if}
</section>

<!-- Storage Health -->
<section class="health-section">
	<h2>
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
		{t.data.storageHealth}
	</h2>

	<!-- Usage bar -->
	<div class="health-card">
		<div class="health-row">
			<span class="health-label">{t.data.localStorageUsage}</span>
			<span class="health-value">{storageHealth.usedMB} MB / {storageHealth.maxMB} MB ({storageHealth.pct.toFixed(1)}%)</span>
		</div>
		<div class="progress-track">
			<div
				class="progress-fill"
				class:fill-ok={storageHealth.level === 'ok'}
				class:fill-warn={storageHealth.level === 'warn'}
				class:fill-critical={storageHealth.level === 'critical'}
				style="width: {Math.min(storageHealth.pct, 100)}%"
			></div>
		</div>
		{#if storageHealth.level === 'critical'}
			<div class="health-warning">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
				{t.data.storageRunningLow}
			</div>
		{/if}
	</div>

	<!-- Full localStorage usage -->
	<div class="health-card">
		<div class="health-row">
			<span class="health-label">{t.data.fullLocalStorage}</span>
			<span class="health-value">{storageUsage.label} / {storageUsage.maxLabel} ({storageUsage.pct.toFixed(1)}%)</span>
		</div>
		<div class="progress-track">
			<div
				class="progress-fill"
				style="width: {Math.min(storageUsage.pct, 100)}%; background: {storageUsage.color}"
			></div>
		</div>
	</div>

	<!-- Entry stats -->
	<div class="health-card">
		<div class="health-row">
			<span class="health-label">{t.data.totalEntries}</span>
			<span class="health-value">{stats.total}</span>
		</div>
		<div class="health-row">
			<span class="health-label">{t.data.oldestEntry}</span>
			<span class="health-value">{stats.firstDate ?? '—'}</span>
		</div>
		<div class="health-row">
			<span class="health-label">{t.data.newestEntry}</span>
			<span class="health-value">{stats.lastDate ?? '—'}</span>
		</div>
	</div>

	<!-- Duplicates -->
	<div class="health-card">
		<div class="health-row">
			<span class="health-label">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
				{t.data.duplicates}
			</span>
			{#if duplicates.length > 0}
				<span class="health-value health-warn-text">{t.data.duplicatesFound.replace('{n}', String(duplicates.length))}</span>
			{:else}
				<span class="health-value health-ok-text">{t.data.noDuplicates}</span>
			{/if}
		</div>
		{#if duplicates.length > 0}
			<button class="cleanup-btn" onclick={cleanDuplicates}>
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
				{t.data.cleanUp}
			</button>
		{/if}
	</div>

	<!-- Data quality -->
	<div class="health-card">
		<div class="health-row">
			<span class="health-label">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
				{t.data.dataQuality}
			</span>
			{#if dataQuality > 0}
				<span class="health-value health-warn-text">{t.data.entriesWithMissingData.replace('{n}', String(dataQuality))}</span>
			{:else}
				<span class="health-value health-ok-text">{t.data.allEntriesValid}</span>
			{/if}
		</div>
	</div>
</section>

<!-- Auto-Backup Status -->
<section class="backup-section">
	{#if backupStatus.warn}
		<div class="backup-card backup-warn">
			<div class="backup-icon">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
			</div>
			<div class="backup-text">
				<strong>{backupStatus.label}</strong>
				<span>{t.data.backupHint}</span>
			</div>
		</div>
	{:else}
		<div class="backup-card backup-ok">
			<div class="backup-icon">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
			</div>
			<div class="backup-text">
				<strong>{backupStatus.label}</strong>
			</div>
		</div>
	{/if}
</section>

<!-- Export / Import -->
<section class="io-section">
	<h2>{t.data.exportImport}</h2>
	<div class="export-filters">
		<label class="filter-field">
			<span class="filter-label">{t.data.type}</span>
			<select bind:value={exportType}>
				<option value="">{t.data.allTypes}</option>
				{#each exportTypes as tp}
					<option value={tp}>{tp}{tp === 'training' || tp === 'signal' ? '.*' : ''}</option>
				{/each}
			</select>
		</label>
		<label class="filter-field">
			<span class="filter-label">{t.data.from}</span>
			<input type="date" bind:value={exportFrom} />
		</label>
		<label class="filter-field">
			<span class="filter-label">{t.data.to}</span>
			<input type="date" bind:value={exportTo} />
		</label>
	</div>
	<div class="io-buttons">
		<button onclick={exportJSON}>{t.data.exportJSON}</button>
		<button onclick={exportCSV}>{t.data.exportCSV}</button>
		<button onclick={importData}>{t.data.importJSON}</button>
	</div>
	<input type="file" accept=".json" bind:this={fileInput} onchange={handleFile} hidden />
</section>

<!-- Danger Zone -->
<section class="danger-section">
	<h2>{t.data.dangerZone}</h2>
	<button class="danger-btn" onclick={clearAll}>
		{confirmClear ? t.data.confirmClear : t.data.clearAll}
	</button>
</section>

<style>
	h2 {
		font-size: 0.9rem;
		font-weight: 600;
		margin-bottom: 0.5rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}

	/* Search */
	.search-section {
		padding: 0 1rem 1rem;
	}

	.search-input {
		width: 100%;
		padding: 0.75rem 1rem;
		font-size: 1rem;
		border: 2px solid var(--c-border);
		border-radius: var(--radius);
		background: var(--c-bg-card);
		color: var(--c-text);
		transition: border-color 0.15s;
	}

	.search-input:focus {
		outline: none;
		border-color: var(--c-accent);
	}

	.search-hint {
		font-size: 0.8rem;
		color: var(--c-text-muted);
		margin-top: 0.35rem;
	}

	/* Results */
	.results {
		padding: 0 1rem 1rem;
	}

	.result-item {
		padding: 0.6rem 0.75rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		margin-bottom: 0.4rem;
	}

	.result-top {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 0.5rem;
		margin-bottom: 0.25rem;
	}

	.type-badge {
		display: inline-block;
		font-size: 0.7rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.03em;
		padding: 0.15rem 0.5rem;
		border-radius: 20px;
		background: var(--c-accent);
		color: #fff;
	}

	.result-date {
		font-size: 0.75rem;
		color: var(--c-text-muted);
	}

	.result-summary {
		font-size: 0.85rem;
		color: var(--c-text-muted);
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.empty {
		color: var(--c-text-muted);
		font-size: 0.85rem;
		text-align: center;
		padding: 1rem 0;
	}

	/* Stats */
	.stats-section {
		padding: 0 1rem 1rem;
	}

	.stats-grid {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 0.5rem;
		margin-bottom: 1rem;
	}

	.stat-card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem;
		text-align: center;
	}

	.stat-value {
		display: block;
		font-size: 1.25rem;
		font-weight: 700;
	}

	.stat-label {
		font-size: 0.75rem;
		color: var(--c-text-muted);
	}

	.type-list {
		display: flex;
		flex-direction: column;
		gap: 0.35rem;
	}

	.type-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0.4rem 0.75rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}

	.type-count {
		font-weight: 600;
		font-size: 0.9rem;
	}

	/* Export / Import */
	.io-section {
		padding: 0 1rem 1rem;
	}

	.export-filters {
		display: flex;
		gap: 0.5rem;
		flex-wrap: wrap;
		margin-bottom: 0.75rem;
	}

	.filter-field {
		display: flex;
		flex-direction: column;
		gap: 0.2rem;
		flex: 1;
		min-width: 100px;
	}

	.filter-label {
		font-size: 0.7rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.03em;
	}

	.filter-field select,
	.filter-field input {
		padding: 0.45rem 0.5rem;
		font-size: 0.85rem;
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		background: var(--c-bg-card);
		color: var(--c-text);
	}

	.io-buttons {
		display: flex;
		gap: 0.5rem;
		flex-wrap: wrap;
	}

	.io-buttons button {
		flex: 1;
		min-width: 100px;
	}

	/* Danger Zone */
	.danger-section {
		padding: 0 1rem 2rem;
		border-top: 1px solid var(--c-border);
		margin-top: 0.5rem;
		padding-top: 1rem;
	}

	.danger-section h2 {
		color: var(--c-danger, #e53e3e);
	}

	.danger-btn {
		width: 100%;
		background: var(--c-danger, #e53e3e);
		color: #fff;
		border: none;
		padding: 0.75rem 1rem;
		border-radius: var(--radius);
		font-weight: 600;
		cursor: pointer;
	}

	.danger-btn:hover {
		opacity: 0.9;
	}

	/* Tag filter */
	.tag-filter-section {
		padding: 0 1rem 0.75rem;
	}

	.tag-filter-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 0.35rem;
	}

	.tag-filter-header h2 {
		display: flex;
		align-items: center;
		gap: 0.35rem;
		margin-bottom: 0;
	}

	.clear-tags-btn {
		font-size: 0.7rem;
		padding: 0.15rem 0.5rem;
		border-radius: 4px;
		border: 1px solid var(--c-border);
		background: var(--c-bg-card);
		color: var(--c-text-muted);
		cursor: pointer;
	}

	.clear-tags-btn:hover {
		color: var(--c-accent);
		border-color: var(--c-accent);
		transform: none;
		box-shadow: none;
	}

	.tag-filter-chips {
		display: flex;
		flex-wrap: wrap;
		gap: 0.3rem;
	}

	.filter-tag-chip {
		display: inline-flex;
		align-items: center;
		gap: 0.25rem;
		font-size: 0.72rem;
		font-weight: 500;
		padding: 0.2rem 0.5rem;
		border-radius: 20px;
		border: 1px solid var(--c-accent);
		background: transparent;
		color: var(--c-accent);
		cursor: pointer;
		transition: background 0.15s, color 0.15s;
	}

	.filter-tag-chip:hover {
		transform: none;
		box-shadow: none;
	}

	.filter-tag-chip.active {
		background: var(--c-accent);
		color: #fff;
	}

	.tag-count {
		font-size: 0.6rem;
		opacity: 0.7;
	}

	/* Tag cloud */
	.tag-cloud-section {
		padding: 0 1rem 0.75rem;
	}

	.toggle-cloud {
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

	.toggle-cloud:hover {
		border-color: var(--c-accent);
		color: var(--c-text);
		background: var(--c-bg-card);
		transform: none;
		box-shadow: none;
	}

	.toggle-cloud .chevron {
		margin-left: auto;
		transition: transform 0.2s;
	}

	.toggle-cloud .chevron.open {
		transform: rotate(180deg);
	}

	.tag-cloud {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 0.4rem;
		padding: 0.75rem;
		margin-top: 0.4rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}

	.cloud-tag {
		border: none;
		background: none;
		color: var(--c-accent);
		cursor: pointer;
		padding: 0.1rem 0.3rem;
		border-radius: 4px;
		font-weight: 500;
		line-height: 1.3;
		transition: background 0.15s;
	}

	.cloud-tag:hover {
		background: var(--c-accent-bg);
		transform: none;
		box-shadow: none;
	}

	.cloud-tag.active {
		background: var(--c-accent);
		color: #fff;
	}

	/* Entry tags on result items */
	.entry-tags {
		display: flex;
		flex-wrap: wrap;
		gap: 0.2rem;
		margin-top: 0.3rem;
	}

	.entry-tag {
		font-size: 0.65rem;
		font-weight: 500;
		padding: 0.1rem 0.35rem;
		border-radius: 8px;
		background: var(--c-accent-bg);
		color: var(--c-text-muted);
	}

	/* Storage Health */
	.health-section {
		padding: 0 1rem 1rem;
	}

	.health-section > h2 {
		display: flex;
		align-items: center;
		gap: 0.35rem;
	}

	.health-card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem;
		margin-bottom: 0.5rem;
	}

	.health-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 0.5rem;
	}

	.health-row + .health-row {
		margin-top: 0.4rem;
	}

	.health-label {
		font-size: 0.8rem;
		color: var(--c-text-muted);
		display: flex;
		align-items: center;
		gap: 0.3rem;
	}

	.health-value {
		font-size: 0.8rem;
		font-weight: 600;
	}

	.health-ok-text {
		color: var(--c-done, #38a169);
	}

	.health-warn-text {
		color: var(--c-cancel, #e53e3e);
	}

	/* Progress bar */
	.progress-track {
		height: 8px;
		background: var(--c-border);
		border-radius: 4px;
		margin-top: 0.5rem;
		overflow: hidden;
	}

	.progress-fill {
		height: 100%;
		border-radius: 4px;
		transition: width 0.3s ease;
	}

	.fill-ok {
		background: var(--c-done, #38a169);
	}

	.fill-warn {
		background: #d69e2e;
	}

	.fill-critical {
		background: var(--c-cancel, #e53e3e);
	}

	/* Warning banner */
	.health-warning {
		display: flex;
		align-items: flex-start;
		gap: 0.4rem;
		margin-top: 0.5rem;
		padding: 0.5rem 0.6rem;
		font-size: 0.78rem;
		color: var(--c-cancel, #e53e3e);
		background: color-mix(in srgb, var(--c-cancel, #e53e3e) 8%, transparent);
		border: 1px solid color-mix(in srgb, var(--c-cancel, #e53e3e) 25%, transparent);
		border-radius: var(--radius);
		line-height: 1.3;
	}

	.health-warning svg {
		flex-shrink: 0;
		margin-top: 1px;
	}

	/* Cleanup button */
	.cleanup-btn {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 0.35rem;
		width: 100%;
		margin-top: 0.5rem;
		padding: 0.5rem 0.75rem;
		font-size: 0.8rem;
		font-weight: 600;
		border: 1px solid var(--c-accent);
		border-radius: var(--radius);
		background: var(--c-accent-bg);
		color: var(--c-accent);
		cursor: pointer;
	}

	.cleanup-btn:hover {
		background: var(--c-accent);
		color: #fff;
	}

	/* Metrics cards */
	.metrics-section {
		padding: 0 1rem 1rem;
	}

	.metrics-grid {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 0.5rem;
	}

	.metric-card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.6rem 0.75rem;
		text-align: center;
	}

	.metric-value {
		display: block;
		font-size: 1.1rem;
		font-weight: 700;
	}

	.metric-label {
		font-size: 0.7rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.03em;
	}

	/* Donut chart */
	.donut-section {
		padding: 0 1rem 1rem;
	}

	.donut-section > h2 {
		display: flex;
		align-items: center;
		gap: 0.35rem;
	}

	.donut-wrapper {
		display: flex;
		align-items: center;
		gap: 1rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 1rem;
	}

	.donut-chart {
		width: 120px;
		height: 120px;
		flex-shrink: 0;
	}

	.donut-legend {
		flex: 1;
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
		overflow-y: auto;
		max-height: 140px;
	}

	.legend-item {
		display: flex;
		align-items: center;
		gap: 0.35rem;
		font-size: 0.75rem;
	}

	.legend-swatch {
		width: 10px;
		height: 10px;
		border-radius: 2px;
		flex-shrink: 0;
	}

	.legend-type {
		flex: 1;
		color: var(--c-text-muted);
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.legend-count {
		font-weight: 600;
		font-variant-numeric: tabular-nums;
	}

	/* Auto-backup */
	.backup-section {
		padding: 0 1rem 0.75rem;
	}

	.backup-card {
		display: flex;
		align-items: flex-start;
		gap: 0.6rem;
		padding: 0.75rem;
		border-radius: var(--radius);
		font-size: 0.82rem;
		line-height: 1.4;
	}

	.backup-warn {
		background: color-mix(in srgb, #d69e2e 10%, transparent);
		border: 1px solid color-mix(in srgb, #d69e2e 30%, transparent);
		color: #d69e2e;
	}

	.backup-ok {
		background: color-mix(in srgb, var(--c-done, #38a169) 10%, transparent);
		border: 1px solid color-mix(in srgb, var(--c-done, #38a169) 30%, transparent);
		color: var(--c-done, #38a169);
	}

	.backup-icon {
		flex-shrink: 0;
		margin-top: 1px;
	}

	.backup-text {
		display: flex;
		flex-direction: column;
		gap: 0.15rem;
	}

	.backup-text strong {
		font-weight: 600;
	}

	.backup-text span {
		font-size: 0.76rem;
		opacity: 0.85;
	}

	@media (min-width: 600px) {
		.stats-grid {
			grid-template-columns: repeat(4, 1fr);
		}

		.metrics-grid {
			grid-template-columns: repeat(4, 1fr);
		}
	}
</style>
