<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';

	const types = [
		{ href: '/training/strength', label: 'Strength', desc: 'Weights, sets, reps, RIR' },
		{ href: '/training/rings', label: 'Rings', desc: 'Progressions, holds, levels' },
		{ href: '/training/hiit', label: 'HIIT', desc: 'Intervals, work/rest, HR' },
		{ href: '/training/cardio', label: 'Cardio', desc: 'Distance, time, zone' },
		{ href: '/training/mobility', label: 'Mobility', desc: 'Routine, duration, notes' }
	];
</script>

<PageHeader title="Training" />

<section class="grid">
	{#each types as t}
		<a href={t.href} class="card">
			<strong>{t.label}</strong>
			<span>{t.desc}</span>
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
