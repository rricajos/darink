<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';

	const sections = [
		{ id: 'hormonal', label: 'Hormonal System', desc: 'Glands, hormones, axes, cascades' },
		{ id: 'neuro', label: 'Neurotransmitters', desc: 'Dopamine, serotonin, GABA, NO...' },
		{ id: 'protocols', label: 'Protocols', desc: 'Nocturnal, anti-cortisol, androgenic...' },
		{ id: 'nutrition', label: 'Nutrition', desc: 'Meal plan, shopping list, supplements' },
		{ id: 'toxins', label: 'Toxins', desc: 'Xenoestrogens, DHT blockers, alcohol' },
		{ id: 'tissues', label: 'Tissues & Receptors', desc: 'AR, IGF-1R, GHR, cavernous bodies' }
	];
</script>

<PageHeader title="Reference" />

<section class="grid">
	{#each sections as s}
		<div class="card">
			<strong>{s.label}</strong>
			<span>{s.desc}</span>
		</div>
	{/each}
</section>

<p class="hint">Full reference content from batmanvault. Coming soon.</p>

<style>
	.grid { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.card { display: flex; flex-direction: column; padding: 1rem; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); }
	.card span { font-size: 0.85rem; color: var(--c-text-muted); }
	.hint { text-align: center; padding: 1rem; font-size: 0.85rem; color: var(--c-text-muted); }
</style>
