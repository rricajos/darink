<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { useLocale } from '$lib/stores/locale.svelte';
	const { t } = useLocale();

	let search = $state('');

	const allItems = $derived.by(() => [
		{ href: '/signals', label: t.more.signals, desc: t.more.signalsDesc, group: t.more.healthTracking },
		{ href: '/habits', label: t.more.habits, desc: t.more.habitsDesc, group: t.more.healthTracking },
		{ href: '/supplements', label: t.more.supplements, desc: t.more.supplementsDesc, group: t.more.healthTracking },
		{ href: '/hydration', label: t.more.hydration, desc: t.more.hydrationDesc, group: t.more.healthTracking },
		{ href: '/measurements', label: t.more.measurements, desc: t.more.measurementsDesc, group: t.more.healthTracking },
		{ href: '/bloodwork', label: t.more.bloodwork, desc: t.more.bloodworkDesc, group: t.more.healthTracking },
		{ href: '/medications', label: t.more.medications, desc: t.more.medicationsDesc, group: t.more.healthTracking },
		{ href: '/symptoms', label: t.more.symptoms, desc: t.more.symptomsDesc, group: t.more.healthTracking },
		{ href: '/journal', label: t.more.journal, desc: t.more.journalDesc, group: t.more.healthTracking },
		{ href: '/timeline', label: t.more.timeline, desc: t.more.timelineDesc, group: t.more.insightsGroup },
		{ href: '/goals', label: t.more.goals, desc: t.more.goalsDesc, group: t.more.insightsGroup },
		{ href: '/experiments', label: t.more.experiments, desc: t.more.experimentsDesc, group: t.more.insightsGroup },
		{ href: '/records', label: t.more.records, desc: t.more.recordsDesc, group: t.more.insightsGroup },
		{ href: '/report', label: t.more.report, desc: t.more.reportDesc, group: t.more.insightsGroup },
		{ href: '/insights', label: t.more.insights, desc: t.more.insightsDesc, group: t.more.insightsGroup },
		{ href: '/profile', label: t.more.profile, desc: t.more.profileDesc, group: t.more.settings },
		{ href: '/reminders', label: t.more.reminders, desc: t.more.remindersDesc, group: t.more.settings },
		{ href: '/data', label: t.more.data, desc: t.more.dataDesc, group: t.more.settings },
		{ href: '/ref', label: t.more.ref, desc: t.more.refDesc, group: t.more.settings }
	]);

	const filtered = $derived.by(() => {
		const q = search.trim().toLowerCase();
		if (!q) return null;
		return allItems.filter(i => i.label.toLowerCase().includes(q) || i.desc.toLowerCase().includes(q));
	});

	const groups = $derived.by(() => [
		{
			title: t.more.healthTracking,
			items: [
				{ href: '/signals', label: t.more.signals, desc: t.more.signalsDesc },
				{ href: '/habits', label: t.more.habits, desc: t.more.habitsDesc },
				{ href: '/supplements', label: t.more.supplements, desc: t.more.supplementsDesc },
				{ href: '/hydration', label: t.more.hydration, desc: t.more.hydrationDesc },
				{ href: '/measurements', label: t.more.measurements, desc: t.more.measurementsDesc },
				{ href: '/bloodwork', label: t.more.bloodwork, desc: t.more.bloodworkDesc },
				{ href: '/medications', label: t.more.medications, desc: t.more.medicationsDesc },
				{ href: '/symptoms', label: t.more.symptoms, desc: t.more.symptomsDesc },
				{ href: '/journal', label: t.more.journal, desc: t.more.journalDesc }
			]
		},
		{
			title: t.more.insightsGroup,
			items: [
				{ href: '/timeline', label: t.more.timeline, desc: t.more.timelineDesc },
				{ href: '/goals', label: t.more.goals, desc: t.more.goalsDesc },
				{ href: '/experiments', label: t.more.experiments, desc: t.more.experimentsDesc },
				{ href: '/records', label: t.more.records, desc: t.more.recordsDesc },
				{ href: '/report', label: t.more.report, desc: t.more.reportDesc },
				{ href: '/insights', label: t.more.insights, desc: t.more.insightsDesc }
			]
		},
		{
			title: t.more.settings,
			items: [
				{ href: '/profile', label: t.more.profile, desc: t.more.profileDesc },
				{ href: '/reminders', label: t.more.reminders, desc: t.more.remindersDesc },
				{ href: '/data', label: t.more.data, desc: t.more.dataDesc },
				{ href: '/ref', label: t.more.ref, desc: t.more.refDesc }
			]
		}
	]);
</script>

<svelte:head>
  <title>{t.more.title} | Darink</title>
</svelte:head>

<PageHeader title={t.more.title} />

<div class="search-wrap">
	<input type="search" class="search-input" placeholder={t.more.searchPages} bind:value={search} />
</div>

{#if filtered}
	{#if filtered.length === 0}
		<p class="no-results">{t.more.noMatch} "{search}"</p>
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
	{t.more.version}
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
