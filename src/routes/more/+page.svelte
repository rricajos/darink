<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';

	const sections = [
		{ href: '/signals', label: 'Body Signals', desc: 'Sleep, skin, hair, genital' },
		{ href: '/habits', label: 'Habits', desc: 'Cold, sun, fasting, meditation' },
		{ href: '/supplements', label: 'Supplements', desc: 'Stack, doses, timing' },
		{ href: '/experiments', label: 'Experiments', desc: 'n=1 diary' },
		{ href: '/ref', label: 'Reference', desc: 'Batmanvault knowledge base' },
		{ href: '/profile', label: 'Profile', desc: 'Height, weight, targets' },
		{ href: '/data', label: 'Data', desc: 'Export, import, delete' }
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
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
		padding: 0 1rem;
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
