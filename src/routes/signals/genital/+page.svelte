<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { entries } from '$lib/stores/entries';
	import { toast } from '$lib/stores/toast';

	let morningErection = $state(0);
	let libido = $state(5);
	let sensitivity = $state(5);
	let notes = $state('');

	function submit() {
		entries.add('signal.genital', { morningErection, libido, sensitivity, notes });
		notes = '';
		toast.show('Signal logged');
	}
</script>

<PageHeader title="Genital Signals" back="/signals" />

<section class="form">
	<label>Morning erection ({morningErection}/3) <input type="range" min="0" max="3" bind:value={morningErection} /></label>
	<label>Libido ({libido}/10) <input type="range" min="1" max="10" bind:value={libido} /></label>
	<label>Sensitivity ({sensitivity}/10) <input type="range" min="1" max="10" bind:value={sensitivity} /></label>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log signals</button>
</section>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	input[type="range"] { padding: 0; }
</style>
