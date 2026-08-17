<script lang="ts">
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';

	onMount(() => {
		goto('/checkin');
	});
</script>

<svelte:head>
  <title>Darink</title>
</svelte:head>
