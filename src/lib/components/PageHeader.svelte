<script lang="ts">
	let { title, back = '', subtitle = '', breadcrumbs = [] }: { title: string; back?: string; subtitle?: string; breadcrumbs?: Array<{ href: string; label: string }> } = $props();
</script>

<header class="page-header">
	{#if back}
		<a href={back} class="back" aria-label="Go back"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg></a>
	{/if}
	<div class="title-group">
		{#if breadcrumbs.length > 0}
			<nav class="breadcrumbs" aria-label="Breadcrumb">
				{#each breadcrumbs as crumb, i}
					<a href={crumb.href}>{crumb.label}</a>
					<span class="crumb-sep">/</span>
				{/each}
				<span class="crumb-current">{title}</span>
			</nav>
		{/if}
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

	.breadcrumbs {
		display: flex;
		align-items: center;
		gap: 0.3rem;
		font-size: 0.75rem;
		margin-bottom: 0.1rem;
	}
	.breadcrumbs a {
		color: var(--c-accent);
		text-decoration: none;
	}
	.breadcrumbs a:hover { text-decoration: underline; }
	.crumb-sep { color: var(--c-text-muted); font-size: 0.65rem; }
	.crumb-current { color: var(--c-text-muted); }
</style>
