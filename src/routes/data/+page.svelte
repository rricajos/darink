<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
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

	const filtered = $derived.by(() => {
		const all = store.items;
		const q = search.trim().toLowerCase();
		if (!q) return all.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt)).slice(0, 50);
		return all
			.filter((e) => {
				if (e.type.toLowerCase().includes(q)) return true;
				for (const val of Object.values(e.data)) {
					if (typeof val === 'string' && val.toLowerCase().includes(q)) return true;
				}
				return false;
			})
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
	{#if search.trim()}
		<p class="search-hint">{filtered.length} result{filtered.length !== 1 ? 's' : ''}</p>
	{/if}
</section>

<!-- Search Results -->
{#if search.trim()}
	<section class="results">
		{#each filtered as entry (entry.id)}
			<div class="result-item">
				<div class="result-top">
					<span class="type-badge">{entry.type}</span>
					<span class="result-date">{formatDate(entry.createdAt)}</span>
				</div>
				<p class="result-summary">{summarize(entry)}</p>
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

	@media (min-width: 600px) {
		.stats-grid {
			grid-template-columns: repeat(4, 1fr);
		}
	}
</style>
