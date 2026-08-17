<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { useEntries } from '$lib/stores/entries.svelte';

	const store = useEntries();

	const stats = $derived.by(() => {
		const all = store.items;
		const today = new Date().toISOString().slice(0, 10);
		return {
			total: all.length,
			today: all.filter((e) => e.createdAt.startsWith(today)).length,
			checkins: all.filter((e) => e.type === 'checkin').length,
			intakes: all.filter((e) => e.type === 'intake').length,
			trainings: all.filter((e) => e.type.startsWith('training.')).length,
			habits: all.filter((e) => e.type === 'habit').length,
			supplements: all.filter((e) => e.type === 'supplement').length,
			experiments: all.filter((e) => e.type === 'experiment').length
		};
	});
</script>

<PageHeader title="Dashboard" />

<section class="stats">
	<div class="stat highlight">
		<span class="value">{stats.today}</span>
		<span class="label">Today</span>
	</div>
	<div class="stat"><span class="value">{stats.checkins}</span><span class="label">Check-ins</span></div>
	<div class="stat"><span class="value">{stats.intakes}</span><span class="label">Intakes</span></div>
	<div class="stat"><span class="value">{stats.trainings}</span><span class="label">Training</span></div>
	<div class="stat"><span class="value">{stats.habits}</span><span class="label">Habits</span></div>
	<div class="stat"><span class="value">{stats.supplements}</span><span class="label">Supplements</span></div>
	<div class="stat"><span class="value">{stats.experiments}</span><span class="label">Experiments</span></div>
	<div class="stat total"><span class="value">{stats.total}</span><span class="label">Total entries</span></div>
</section>

<style>
	.stats {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 0.5rem;
		padding: 0 1rem;
	}

	.stat {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 1rem;
		text-align: center;
	}

	.stat.highlight {
		grid-column: 1 / -1;
		background: var(--c-accent-bg);
		border-color: var(--c-accent);
	}

	.stat.total { grid-column: 1 / -1; }
	.value { display: block; font-size: 1.5rem; font-weight: 700; }
	.label { font-size: 0.8rem; color: var(--c-text-muted); }
</style>
