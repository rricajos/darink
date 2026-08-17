<script lang="ts">
	import '$lib/styles/global.css';
	import BottomNav from '$lib/components/BottomNav.svelte';
	import Toast from '$lib/components/Toast.svelte';
	import { onMount } from 'svelte';

	let { children } = $props();

	onMount(() => {
		if ('serviceWorker' in navigator) {
			navigator.serviceWorker.register('/service-worker.js');
			navigator.serviceWorker.addEventListener('controllerchange', () => {
				location.reload();
			});
		}
	});
</script>

<div class="shell">
	<BottomNav />
	<main>
		{@render children()}
	</main>
</div>

<Toast />

<style>
	.shell {
		display: flex;
		min-height: 100dvh;
	}

	main {
		flex: 1;
		max-width: 720px;
		margin: 0 auto;
		min-height: 100dvh;
		width: 100%;
	}

	@media (min-width: 900px) {
		main {
			max-width: 960px;
			margin-left: var(--sidebar-width, 200px);
			padding: 0 2rem;
		}
	}

	@media (min-width: 1400px) {
		main {
			max-width: 1100px;
		}
	}
</style>
