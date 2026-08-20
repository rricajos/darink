<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { useLocale } from '$lib/stores/locale.svelte';
	import { useFavorites } from '$lib/stores/favorites.svelte';
	import { useEntries } from '$lib/stores/entries.svelte';
	const { t } = useLocale();
	const favs = useFavorites();
	const allEntries = useEntries();

	let search = $state('');

	const hrefTypeMap: Record<string, string> = {
		'/checkin': 'checkin', '/intake': 'intake', '/training': '', '/dashboard': '',
		'/signals': 'checkin', '/habits': 'habit', '/supplements': 'supplement',
		'/hydration': 'hydration', '/measurements': 'measurement', '/bloodwork': 'bloodwork',
		'/medications': 'medication', '/symptoms': 'symptom', '/journal': 'journal',
		'/timeline': '', '/goals': 'goal', '/experiments': 'experiment',
		'/records': '', '/report': '', '/insights': '',
		'/profile': 'weight', '/reminders': '', '/data': '',
		'/ref': ''
	};

	const lastLoggedMap = $derived.by(() => {
		const map = new Map<string, string>();
		const now = Date.now();
		for (const [href, type] of Object.entries(hrefTypeMap)) {
			if (!type) continue;
			const latest = allEntries.items
				.filter(e => e.type === type)
				.reduce((best, e) => e.createdAt > best ? e.createdAt : best, '');
			if (!latest) continue;
			const diff = now - new Date(latest).getTime();
			const hours = Math.floor(diff / 3600000);
			const days = Math.floor(diff / 86400000);
			if (hours < 1) map.set(href, t.common.justNow);
			else if (hours < 24) map.set(href, `${hours}${t.common.hoursAgo}`);
			else if (days < 30) map.set(href, `${days}${t.common.daysAgo}`);
			else map.set(href, `${Math.floor(days / 7)}${t.common.weeksAgo}`);
		}
		return map;
	});

	const allItems = $derived.by(() => [
		{ href: '/checkin', label: t.more.checkin, desc: t.more.checkinDesc, group: t.more.healthTracking },
		{ href: '/intake', label: t.more.intake, desc: t.more.intakeDesc, group: t.more.healthTracking },
		{ href: '/training', label: t.more.training, desc: t.more.trainingDesc, group: t.more.healthTracking },
		{ href: '/signals', label: t.more.signals, desc: t.more.signalsDesc, group: t.more.healthTracking },
		{ href: '/habits', label: t.more.habits, desc: t.more.habitsDesc, group: t.more.healthTracking },
		{ href: '/supplements', label: t.more.supplements, desc: t.more.supplementsDesc, group: t.more.healthTracking },
		{ href: '/hydration', label: t.more.hydration, desc: t.more.hydrationDesc, group: t.more.healthTracking },
		{ href: '/measurements', label: t.more.measurements, desc: t.more.measurementsDesc, group: t.more.healthTracking },
		{ href: '/bloodwork', label: t.more.bloodwork, desc: t.more.bloodworkDesc, group: t.more.healthTracking },
		{ href: '/medications', label: t.more.medications, desc: t.more.medicationsDesc, group: t.more.healthTracking },
		{ href: '/symptoms', label: t.more.symptoms, desc: t.more.symptomsDesc, group: t.more.healthTracking },
		{ href: '/journal', label: t.more.journal, desc: t.more.journalDesc, group: t.more.healthTracking },
		{ href: '/dashboard', label: t.more.dashboard, desc: t.more.dashboardDesc, group: t.more.insightsGroup },
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
				{ href: '/checkin', label: t.more.checkin, desc: t.more.checkinDesc },
				{ href: '/intake', label: t.more.intake, desc: t.more.intakeDesc },
				{ href: '/training', label: t.more.training, desc: t.more.trainingDesc },
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
				{ href: '/dashboard', label: t.more.dashboard, desc: t.more.dashboardDesc },
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
				<div class="card-wrap">
					<a href={s.href} class="card">
						<strong>{s.label}</strong>
						<span>{s.desc}</span>
						<span class="card-group">{s.group}</span>
					</a>
					<button class="star-btn" class:starred={favs.has(s.href)} onclick={() => favs.toggle(s.href)} aria-label="Pin">
						<svg width="16" height="16" viewBox="0 0 24 24" fill={favs.has(s.href) ? 'var(--c-accent)' : 'none'} stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
					</button>
				</div>
			{/each}
		</section>
	{/if}
{:else}

{#each groups as group}
	<h2>{group.title}</h2>
	<section class="grid">
		{#each group.items as s}
			<div class="card-wrap">
				<a href={s.href} class="card">
					<strong>{s.label}</strong>
					<span>{s.desc}</span>
					{#if lastLoggedMap.has(s.href)}
						<span class="last-logged">{t.more.lastLogged}: {lastLoggedMap.get(s.href)}</span>
					{/if}
				</a>
				<button class="star-btn" class:starred={favs.has(s.href)} onclick={() => favs.toggle(s.href)} aria-label="Pin">
					<svg width="16" height="16" viewBox="0 0 24 24" fill={favs.has(s.href) ? 'var(--c-accent)' : 'none'} stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
				</button>
			</div>
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

	.card-wrap {
		position: relative;
		display: flex;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		transition: border-color 0.15s;
	}

	.card-wrap:hover {
		border-color: var(--c-accent);
	}

	.card {
		display: flex;
		flex-direction: column;
		padding: 1rem;
		padding-right: 2.5rem;
		flex: 1;
		text-decoration: none;
		color: var(--c-text);
		min-width: 0;
	}

	.card span {
		font-size: 0.85rem;
		color: var(--c-text-muted);
	}

	.star-btn {
		position: absolute;
		top: 0.6rem;
		right: 0.5rem;
		background: none;
		border: none;
		cursor: pointer;
		color: var(--c-border);
		padding: 0.25rem;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: color 0.15s, transform 0.15s;
	}

	.star-btn:hover {
		color: var(--c-accent);
		transform: scale(1.15);
	}

	.star-btn.starred {
		color: var(--c-accent);
	}

	.last-logged {
		font-size: 0.7rem !important;
		color: var(--c-accent) !important;
		font-weight: 500;
		margin-top: 0.15rem;
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
