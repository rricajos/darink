<script lang="ts">
	import '$lib/styles/global.css';
	import BottomNav from '$lib/components/BottomNav.svelte';
	import QuickLog from '$lib/components/QuickLog.svelte';
	import Toast from '$lib/components/Toast.svelte';
	import { onMount } from 'svelte';
	import { onNavigate } from '$app/navigation';
	import { goto } from '$app/navigation';
	import { theme, ui } from '$lib/db';

	let { children } = $props();

	let installEvent = $state<Event | null>(null);
	let showInstallBanner = $state(false);

	onNavigate((navigation) => {
		if (!document.startViewTransition) return;
		return new Promise((resolve) => {
			document.startViewTransition(async () => {
				resolve();
				await navigation.complete;
			});
		});
	});

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

		const dismissed = ui.get().installDismissed;
		if (!dismissed) {
			window.addEventListener('beforeinstallprompt', (e) => {
				e.preventDefault();
				installEvent = e;
				showInstallBanner = true;
			});
		}

		function handleKeydown(e: KeyboardEvent) {
			if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement || e.target instanceof HTMLSelectElement) return;
			switch(e.key) {
				case '1': goto('/checkin'); break;
				case '2': goto('/intake'); break;
				case '3': goto('/training'); break;
				case '4': goto('/dashboard'); break;
				case '5': goto('/more'); break;
			}
		}
		window.addEventListener('keydown', handleKeydown);
		return () => window.removeEventListener('keydown', handleKeydown);
	});

	function installApp() {
		if (installEvent && 'prompt' in installEvent) {
			(installEvent as any).prompt();
		}
		showInstallBanner = false;
	}

	function dismissInstall() {
		showInstallBanner = false;
		ui.patch({ installDismissed: true });
	}
</script>

<a href="#main-content" class="skip-link">Skip to content</a>

{#if showInstallBanner}
	<div class="install-banner">
		<span style="flex:1">Install Darink for offline access</span>
		<button onclick={installApp}>Install</button>
		<button class="dismiss" onclick={dismissInstall} aria-label="Dismiss">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
		</button>
	</div>
{/if}

<div class="shell">
	<BottomNav />
	<main id="main-content">
		{@render children()}
	</main>
</div>

<QuickLog />
<Toast />

<style>
	.install-banner {
		display: flex;
		align-items: center;
		gap: 0.75rem;
		padding: 0.75rem 1rem;
		background: var(--c-accent-bg);
		border-bottom: 1px solid var(--c-accent);
		font-size: 0.85rem;
	}
	.install-banner button { flex-shrink: 0; }
	.install-banner .dismiss { background: none; border: none; color: var(--c-text-muted); padding: 0.25rem; }

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
