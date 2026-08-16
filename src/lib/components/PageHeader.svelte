<script lang="ts">
	let { title, back = '' }: { title: string; back?: string } = $props();
</script>

<header class="page-header">
	{#if back}
		<a href={back} class="back">←</a>
	{/if}
	<h1>{title}</h1>
</header>

<style>
	.page-header {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 1rem;
	}

	h1 {
		font-size: 1.25rem;
		font-weight: 700;
	}

	.back {
		font-size: 1.25rem;
		text-decoration: none;
		color: var(--c-accent);
	}
</style>
