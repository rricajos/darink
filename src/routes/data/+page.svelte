<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import TagInput from '$lib/components/TagInput.svelte';
	import { db, type Entry } from '$lib/db';
	import { useEntries, bumpEntries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';

	const store = useEntries();

	let search = $state('');
	let fileInput: HTMLInputElement;
	let confirmClear = $state(false);

	let exportType = $state('');
	let exportFrom = $state('');
	let exportTo = $state('');

	let selectedTags = $state<string[]>([]);
	let tagCloudOpen = $state(false);

	/* --- All tags across all entries --- */
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
					for (const t of tags) {
						if (typeof t === 'string' && t.toLowerCase().includes(q)) return true;
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
			toast.show('No data to export');
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
		toast.show(`${data.length} entries exported as JSON`);
	}

	function exportCSV() {
		const all = getFilteredEntries();
		if (all.length === 0) {
			toast.show('No data to export');
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
		toast.show(`${all.length} entries exported as CSV`);
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
				toast.show(`${count} entries imported`);
				bumpEntries();
			} catch {
				toast.show('Invalid file format');
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
		toast.show('All data cleared');
		bumpEntries();
	}
</script>

<svelte:head>
  <title>Data | Darink</title>
</svelte:head>

<PageHeader title="Data" />

<!-- Search -->
<section class="search-section">
	<input
		type="search"
		class="search-input"
		placeholder="Search all entries..."
		bind:value={search}
	/>
	{#if search.trim() || selectedTags.length > 0}
		<p class="search-hint">{filtered.length} result{filtered.length !== 1 ? 's' : ''}</p>
	{/if}
</section>

<!-- Tag filter -->
{#if allTags.length > 0}
<section class="tag-filter-section">
	<div class="tag-filter-header">
		<h2>
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
			Filter by tags
		</h2>
		{#if selectedTags.length > 0}
			<button class="clear-tags-btn" onclick={clearTagFilter}>Clear</button>
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
		Tag cloud
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
			<p class="empty">No entries match your search.</p>
		{/each}
	</section>
{/if}

<!-- Stats -->
<section class="stats-section">
	<h2>Statistics</h2>
	<div class="stats-grid">
		<div class="stat-card">
			<span class="stat-value">{stats.total}</span>
			<span class="stat-label">Total entries</span>
		</div>
		<div class="stat-card">
			<span class="stat-value">{stats.sizeKB} KB</span>
			<span class="stat-label">Storage used</span>
		</div>
		<div class="stat-card">
			<span class="stat-value">{stats.firstDate ?? '—'}</span>
			<span class="stat-label">First entry</span>
		</div>
		<div class="stat-card">
			<span class="stat-value">{stats.lastDate ?? '—'}</span>
			<span class="stat-label">Last entry</span>
		</div>
	</div>
	{#if stats.byType.length > 0}
		<h2>By type</h2>
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

<!-- Export / Import -->
<section class="io-section">
	<h2>Export & Import</h2>
	<div class="export-filters">
		<label class="filter-field">
			<span class="filter-label">Type</span>
			<select bind:value={exportType}>
				<option value="">All types</option>
				{#each exportTypes as t}
					<option value={t}>{t}{t === 'training' || t === 'signal' ? '.*' : ''}</option>
				{/each}
			</select>
		</label>
		<label class="filter-field">
			<span class="filter-label">From</span>
			<input type="date" bind:value={exportFrom} />
		</label>
		<label class="filter-field">
			<span class="filter-label">To</span>
			<input type="date" bind:value={exportTo} />
		</label>
	</div>
	<div class="io-buttons">
		<button onclick={exportJSON}>Export JSON</button>
		<button onclick={exportCSV}>Export CSV</button>
		<button onclick={importData}>Import JSON</button>
	</div>
	<input type="file" accept=".json" bind:this={fileInput} onchange={handleFile} hidden />
</section>

<!-- Danger Zone -->
<section class="danger-section">
	<h2>Danger zone</h2>
	<button class="danger-btn" onclick={clearAll}>
		{confirmClear ? 'Are you sure? Click again to confirm' : 'Clear all data'}
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

	@media (min-width: 600px) {
		.stats-grid {
			grid-template-columns: repeat(4, 1fr);
		}
	}
</style>
