<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { ui } from '$lib/db';
	import { onMount } from 'svelte';

	let height = $state(175);
	let weight = $state(88);
	let age = $state(25);
	let bodyFat = $state(14);
	let stressBaseline = $state(7);
	let sleepTarget = $state('22:00');
	let wakeTarget = $state('06:00');
	let notes = $state('');

	onMount(() => {
		const profile = ui.get().profile as Record<string, any> | undefined;
		if (profile) {
			height = profile.height ?? height;
			weight = profile.weight ?? weight;
			age = profile.age ?? age;
			bodyFat = profile.bodyFat ?? bodyFat;
			stressBaseline = profile.stressBaseline ?? stressBaseline;
			sleepTarget = profile.sleepTarget ?? sleepTarget;
			wakeTarget = profile.wakeTarget ?? wakeTarget;
			notes = profile.notes ?? notes;
		}
	});

	function save() {
		ui.patch({
			profile: { height, weight, age, bodyFat, stressBaseline, sleepTarget, wakeTarget, notes }
		});
	}
</script>

<PageHeader title="Profile" />

<section class="form">
	<div class="row">
		<label>Height (cm) <input type="number" bind:value={height} /></label>
		<label>Weight (kg) <input type="number" step="0.1" bind:value={weight} /></label>
	</div>
	<div class="row">
		<label>Age <input type="number" bind:value={age} /></label>
		<label>Body fat (%) <input type="number" step="0.5" bind:value={bodyFat} /></label>
	</div>
	<label>Stress baseline ({stressBaseline}/10) <input type="range" min="1" max="10" bind:value={stressBaseline} /></label>
	<div class="row">
		<label>Sleep target <input type="time" bind:value={sleepTarget} /></label>
		<label>Wake target <input type="time" bind:value={wakeTarget} /></label>
	</div>
	<label>Notes <textarea bind:value={notes} rows="3" placeholder="Conditions, medications, genetic notes..."></textarea></label>
	<button class="primary" onclick={save}>Save profile</button>
</section>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.5rem; }
	.row label { flex: 1; }
	input[type="range"] { padding: 0; }
</style>
