<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { db } from '$lib/db';
	import { toast } from '$lib/stores/toast.svelte';

	let fileInput: HTMLInputElement;

	function exportData() {
		const json = db.exportJSON();
		const blob = new Blob([json], { type: 'application/json' });
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = `darink-${new Date().toISOString().slice(0, 10)}.json`;
		a.click();
		URL.revokeObjectURL(url);
		toast.show('Data exported');
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
			} catch {
				toast.show('Invalid file format');
			}
		};
		reader.readAsText(file);
	}

	function clearAll() {
		if (!confirm('Delete ALL data? This cannot be undone.')) return;
		db.clear();
		toast.show('All data deleted');
	}
</script>

<PageHeader title="Data" />

<section class="actions">
	<button onclick={exportData}>Export JSON</button>
	<button onclick={importData}>Import JSON</button>
	<input type="file" accept=".json" bind:this={fileInput} onchange={handleFile} hidden />
	<button class="danger" onclick={clearAll}>Delete all data</button>
</section>

<style>
	.actions {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
		padding: 0 1rem;
	}

	@media (min-width: 600px) {
		.actions {
			flex-direction: row;
			flex-wrap: wrap;
		}
		.actions button { flex: 1; min-width: 150px; }
	}
</style>
