<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';

	const sections = [
		{ href: '/timeline', label: 'Timeline', desc: 'Activity feed across all data' },
		{ href: '/journal', label: 'Journal', desc: 'Freeform notes and reflections' },
		{ href: '/goals', label: 'Goals', desc: 'Set targets and track progress' },
		{ href: '/signals', label: 'Body Signals', desc: 'Sleep, skin, hair, genital' },
		{ href: '/habits', label: 'Habits', desc: 'Cold, sun, fasting, meditation' },
		{ href: '/supplements', label: 'Supplements', desc: 'Stack, doses, timing' },
		{ href: '/experiments', label: 'Experiments', desc: 'n=1 diary' },
		{ href: '/ref', label: 'Reference', desc: 'Batmanvault knowledge base' },
		{ href: '/profile', label: 'Profile', desc: 'Height, weight, targets' },
		{ href: '/data', label: 'Data', desc: 'Search, export, import, stats' }
	];
</script>

<PageHeader title="More" />

<section class="grid">
	{#each sections as s}
		<a href={s.href} class="card">
			<strong>{s.label}</strong>
			<span>{s.desc}</span>
		</a>
	{/each}
</section>

<style>
	.grid {
		display: grid;
		grid-template-columns: 1fr;
		gap: 0.5rem;
		padding: 0 1rem;
	}

	@media (min-width: 600px) {
		.grid { grid-template-columns: repeat(2, 1fr); }
	}

	@media (min-width: 900px) {
		.grid { grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
	}

	.card {
		display: flex;
		flex-direction: column;
		padding: 1rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		text-decoration: none;
		color: var(--c-text);
		transition: border-color 0.15s;
	}

	.card:hover {
		border-color: var(--c-accent);
	}

	.card span {
		font-size: 0.85rem;
		color: var(--c-text-muted);
	}
</style>
