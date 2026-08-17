<script lang="ts">
	import '$lib/styles/global.css';
	import BottomNav from '$lib/components/BottomNav.svelte';
	import Toast from '$lib/components/Toast.svelte';
	import { onMount } from 'svelte';
	import { theme } from '$lib/db';

	let { children } = $props();

	onMount(() => {
		const saved = theme.get();
		const root = document.documentElement;
		if (saved === 'dark') {
			root.classList.add('dark');
			root.classList.remove('light');
		} else if (saved === 'light') {
			root.classList.add('light');
			root.classList.remove('dark');
		} else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
			root.classList.add('dark');
		}

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
