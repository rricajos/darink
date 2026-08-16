<script lang="ts">
	import '$lib/styles/global.css';
	import BottomNav from '$lib/components/BottomNav.svelte';
	import Toast from '$lib/components/Toast.svelte';
	import { onMount } from 'svelte';

	let { children } = $props();

	onMount(() => {
		if ('serviceWorker' in navigator) {
			navigator.serviceWorker.register('/service-worker.js');
		}
	});
</script>

<main>
	{@render children()}
</main>

<Toast />
<BottomNav />

<style>
	main {
		max-width: 720px;
		margin: 0 auto;
		min-height: 100dvh;
	}
</style>
