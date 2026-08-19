<script lang="ts">
	let { title, back = '', subtitle = '' }: { title: string; back?: string; subtitle?: string } = $props();
</script>

<header class="page-header">
	{#if back}
		<a href={back} class="back" aria-label="Go back"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg></a>
	{/if}
	<div class="title-group">
		<h1 id="page-title">{title}</h1>
		{#if subtitle}
			<p class="subtitle">{subtitle}</p>
		{/if}
	</div>
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

	@media (min-width: 900px) {
		.page-header { padding: 1.5rem 1rem; }
		h1 { font-size: 1.5rem; }
	}

	.title-group {
		display: flex;
		flex-direction: column;
	}

	.subtitle {
		font-size: 0.85rem;
		color: var(--c-text-muted);
		margin: 0;
	}

	.back {
		text-decoration: none;
		color: var(--c-accent);
		display: flex;
		align-items: center;
	}
</style>
