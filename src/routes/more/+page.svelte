<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';

	let search = $state('');

	const allItems = [
		{ href: '/signals', label: 'Signals', desc: 'Body signal monitoring', group: 'Health Tracking' },
		{ href: '/habits', label: 'Habits', desc: 'Daily habits and streaks', group: 'Health Tracking' },
		{ href: '/supplements', label: 'Supplements', desc: 'Stack and adherence', group: 'Health Tracking' },
		{ href: '/hydration', label: 'Hydration', desc: 'Water and fluid tracking', group: 'Health Tracking' },
		{ href: '/measurements', label: 'Measurements', desc: 'Body measurements over time', group: 'Health Tracking' },
		{ href: '/bloodwork', label: 'Blood Work', desc: 'Lab results and reference ranges', group: 'Health Tracking' },
		{ href: '/medications', label: 'Medications', desc: 'Prescriptions, doses, side effects', group: 'Health Tracking' },
		{ href: '/symptoms', label: 'Symptoms', desc: 'Pain and symptom tracking', group: 'Health Tracking' },
		{ href: '/journal', label: 'Journal', desc: 'Freeform notes', group: 'Health Tracking' },
		{ href: '/timeline', label: 'Timeline', desc: 'Activity feed', group: 'Insights' },
		{ href: '/goals', label: 'Goals', desc: 'Targets and progress', group: 'Insights' },
		{ href: '/experiments', label: 'Experiments', desc: 'n=1 testing', group: 'Insights' },
		{ href: '/records', label: 'Records', desc: 'Personal bests and milestones', group: 'Insights' },
		{ href: '/report', label: 'Report', desc: 'Weekly summary for review', group: 'Insights' },
		{ href: '/insights', label: 'Insights', desc: 'Deep dive analytics and patterns', group: 'Insights' },
		{ href: '/profile', label: 'Profile', desc: 'Body metrics and targets', group: 'Settings' },
		{ href: '/reminders', label: 'Reminders', desc: 'Notification reminders', group: 'Settings' },
		{ href: '/data', label: 'Data', desc: 'Search, export, import', group: 'Settings' },
		{ href: '/ref', label: 'Reference', desc: 'Health knowledge base', group: 'Settings' }
	];

	const filtered = $derived.by(() => {
		const q = search.trim().toLowerCase();
		if (!q) return null;
		return allItems.filter(i => i.label.toLowerCase().includes(q) || i.desc.toLowerCase().includes(q));
	});

	const groups = [
		{
			title: 'Health Tracking',
			items: [
				{ href: '/signals', label: 'Signals', desc: 'Body signal monitoring' },
				{ href: '/habits', label: 'Habits', desc: 'Daily habits and streaks' },
				{ href: '/supplements', label: 'Supplements', desc: 'Stack and adherence' },
				{ href: '/hydration', label: 'Hydration', desc: 'Water and fluid tracking' },
				{ href: '/measurements', label: 'Measurements', desc: 'Body measurements over time' },
				{ href: '/bloodwork', label: 'Blood Work', desc: 'Lab results and reference ranges' },
				{ href: '/medications', label: 'Medications', desc: 'Prescriptions, doses, side effects' },
				{ href: '/symptoms', label: 'Symptoms', desc: 'Pain and symptom tracking' },
				{ href: '/journal', label: 'Journal', desc: 'Freeform notes' }
			]
		},
		{
			title: 'Insights',
			items: [
				{ href: '/timeline', label: 'Timeline', desc: 'Activity feed' },
				{ href: '/goals', label: 'Goals', desc: 'Targets and progress' },
				{ href: '/experiments', label: 'Experiments', desc: 'n=1 testing' },
				{ href: '/records', label: 'Records', desc: 'Personal bests and milestones' },
				{ href: '/report', label: 'Report', desc: 'Weekly summary for review' },
				{ href: '/insights', label: 'Insights', desc: 'Deep dive analytics and patterns' }
			]
		},
		{
			title: 'Settings',
			items: [
				{ href: '/profile', label: 'Profile', desc: 'Body metrics and targets' },
				{ href: '/reminders', label: 'Reminders', desc: 'Notification reminders' },
				{ href: '/data', label: 'Data', desc: 'Search, export, import' },
				{ href: '/ref', label: 'Reference', desc: 'Health knowledge base' }
			]
		}
	];
</script>

<svelte:head>
  <title>More | Darink</title>
</svelte:head>

<PageHeader title="More" />

<div class="search-wrap">
	<input type="search" class="search-input" placeholder="Search pages..." bind:value={search} />
</div>

{#if filtered}
	{#if filtered.length === 0}
		<p class="no-results">No pages matching "{search}"</p>
	{:else}
		<section class="grid" style="padding:0 1rem">
			{#each filtered as s}
				<a href={s.href} class="card">
					<strong>{s.label}</strong>
					<span>{s.desc}</span>
					<span class="card-group">{s.group}</span>
				</a>
			{/each}
		</section>
	{/if}
{:else}

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

<footer class="version">
	Darink v1.0.0
</footer>

{/if}

<style>
	.version {
		text-align: center;
		padding: 2rem 1rem 1rem;
		font-size: 0.75rem;
		color: var(--c-text-muted);
	}

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

	.search-wrap {
		padding: 0 1rem 0.5rem;
	}

	.search-input {
		width: 100%;
		padding: 0.6rem 0.75rem;
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		background: var(--c-bg-card);
		color: var(--c-text);
		font-size: 0.9rem;
	}

	.search-input:focus {
		outline: 2px solid var(--c-accent);
		outline-offset: -1px;
	}

	.no-results {
		padding: 2rem 1rem;
		text-align: center;
		color: var(--c-text-muted);
		font-size: 0.9rem;
	}

	.card-group {
		font-size: 0.7rem !important;
		text-transform: uppercase;
		letter-spacing: 0.05em;
		margin-top: 0.25rem;
	}
</style>
