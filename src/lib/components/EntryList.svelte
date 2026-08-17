<script lang="ts">
	import type { Entry } from '$lib/db';
	import type { Snippet } from 'svelte';
	import { entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';

	let { items, row, limit = 50 }: {
		items: Entry[];
		row: Snippet<[Entry]>;
		limit?: number;
	} = $props();

	const sorted = $derived(
		items.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt)).slice(0, limit)
	);

	function remove(id: string) {
		entries.remove(id);
		toast.show('Deleted');
	}
</script>

{#if sorted.length > 0}
	<ul class="entry-list">
		{#each sorted as item (item.id)}
			<li>
				<div class="content">
					{@render row(item)}
				</div>
				<button class="del" onclick={() => remove(item.id)} aria-label="Delete">×</button>
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
		align-items: center;
		gap: 0.5rem;
		padding: 0.5rem 0;
		border-bottom: 1px solid var(--c-border);
	}

	.content {
		flex: 1;
		min-width: 0;
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
</style>
