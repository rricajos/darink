<script lang="ts">
	import type { Entry } from '$lib/db';
	import type { Snippet } from 'svelte';
	import { entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';

	let { items, row, editForm, limit = 50 }: {
		items: Entry[];
		row: Snippet<[Entry]>;
		editForm?: Snippet<[Entry, () => void]>;
		limit?: number;
	} = $props();

	let editingId = $state<string | null>(null);

	const sorted = $derived(
		items.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt)).slice(0, limit)
	);

	function remove(id: string) {
		entries.remove(id);
		toast.show('Deleted');
	}

	function startEdit(id: string) {
		editingId = id;
	}

	function stopEdit() {
		editingId = null;
	}
</script>

{#if sorted.length > 0}
	<ul class="entry-list">
		{#each sorted as item (item.id)}
			<li class:editing={editingId === item.id}>
				<div class="row">
					<div class="content">
						{@render row(item)}
					</div>
					<div class="actions">
						{#if editForm}
							{#if editingId === item.id}
								<button class="edit-btn" onclick={stopEdit} aria-label="Cancel edit">✕</button>
							{:else}
								<button class="edit-btn" onclick={() => startEdit(item.id)} aria-label="Edit">✎</button>
							{/if}
						{/if}
						<button class="del" onclick={() => remove(item.id)} aria-label="Delete">×</button>
					</div>
				</div>
				{#if editForm && editingId === item.id}
					<div class="edit-form">
						{@render editForm(item, stopEdit)}
					</div>
				{/if}
			</li>
		{/each}
	</ul>
{/if}

<style>
	.entry-list {
		list-style: none;
		padding: 1rem;
	}

	li {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
		padding: 0.5rem 0;
		border-bottom: 1px solid var(--c-border);
	}

	.row {
		display: flex;
		align-items: center;
		gap: 0.5rem;
	}

	.content {
		flex: 1;
		min-width: 0;
	}

	.actions {
		display: flex;
		align-items: center;
		gap: 0.25rem;
		flex-shrink: 0;
	}

	.edit-btn {
		border: none;
		background: none;
		color: var(--c-text-muted);
		font-size: 1.2rem;
		padding: 0 0.25rem;
		flex-shrink: 0;
	}

	.edit-btn:hover {
		background: none;
		color: var(--c-accent);
	}

	.del {
		border: none;
		background: none;
		color: var(--c-cancel);
		font-size: 1.2rem;
		padding: 0 0.25rem;
		flex-shrink: 0;
	}

	.del:hover {
		background: none;
		color: #d00;
	}

	.edit-form {
		padding: 0.5rem 0;
	}
</style>
