<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';

	const groups = [
		{
			title: 'Health Tracking',
			items: [
				{ href: '/signals', label: 'Signals', desc: 'Body signal monitoring' },
				{ href: '/habits', label: 'Habits', desc: 'Daily habits and streaks' },
				{ href: '/supplements', label: 'Supplements', desc: 'Stack and adherence' },
				{ href: '/journal', label: 'Journal', desc: 'Freeform notes' }
			]
		},
		{
			title: 'Insights',
			items: [
				{ href: '/timeline', label: 'Timeline', desc: 'Activity feed' },
				{ href: '/goals', label: 'Goals', desc: 'Targets and progress' },
				{ href: '/experiments', label: 'Experiments', desc: 'n=1 testing' }
			]
		},
		{
			title: 'Settings',
			items: [
				{ href: '/profile', label: 'Profile', desc: 'Body metrics and targets' },
				{ href: '/data', label: 'Data', desc: 'Search, export, import' },
				{ href: '/ref', label: 'Reference', desc: 'Health knowledge base' }
			]
		}
	];
</script>

<PageHeader title="More" />

{#each groups as group}
	<h2>{group.title}</h2>
	<section class="grid">
		{#each group.items as s}
			<a href={s.href} class="card">
				<strong>{s.label}</strong>
				<span>{s.desc}</span>
			</a>
		{/each}
	</section>
{/each}

<style>
	h2 {
		font-size: 0.9rem;
		font-weight: 600;
		margin-bottom: 0.5rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
		padding: 0 1rem;
		margin-top: 1.5rem;
	}

	h2:first-of-type {
		margin-top: 0.5rem;
	}

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
