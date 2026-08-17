<script lang="ts">
	import { page } from '$app/state';
	import { theme } from '$lib/db';
	import { onMount } from 'svelte';

	const tabs = [
		{ href: '/checkin', label: 'Check-in', icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/></svg>' },
		{ href: '/intake', label: 'Intake', icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>' },
		{ href: '/training', label: 'Train', icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6.5 6.5 11 11"/><path d="m21 21-1-1"/><path d="m3 3 1 1"/><path d="m18 22 4-4"/><path d="m2 6 4-4"/><path d="m3 10 7-7"/><path d="m14 21 7-7"/></svg>' },
		{ href: '/dashboard', label: 'Dashboard', icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>' },
		{ href: '/more', label: 'More', icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>' }
	];

	const morePaths = ['/signals', '/habits', '/supplements', '/experiments', '/profile', '/data', '/ref'];

	let isDark = $state(false);

	function isActive(href: string): boolean {
		if (href === '/more') {
			return morePaths.some((p) => page.url.pathname === p || page.url.pathname.startsWith(p + '/'));
		}
		return page.url.pathname === href || page.url.pathname.startsWith(href + '/');
	}

	function applyTheme(dark: boolean): void {
		const root = document.documentElement;
		if (dark) {
			root.classList.add('dark');
			root.classList.remove('light');
		} else {
			root.classList.add('light');
			root.classList.remove('dark');
		}
	}

	function toggleTheme(): void {
		isDark = !isDark;
		theme.set(isDark ? 'dark' : 'light');
		applyTheme(isDark);
	}

	onMount(() => {
		const saved = theme.get();
		if (saved === 'dark') {
			isDark = true;
		} else if (saved === 'light') {
			isDark = false;
		} else {
			isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
		}
		applyTheme(isDark);
	});
</script>

<nav>
	<div class="brand">Darink</div>
	{#each tabs as tab}
		<a href={tab.href} class:active={isActive(tab.href)}>
			<span class="icon">{@html tab.icon}</span>
			<span class="label">{tab.label}</span>
		</a>
	{/each}
	<button class="theme-toggle" onclick={toggleTheme} aria-label="Toggle theme">
		{#if isDark}
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
		{:else}
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
		{/if}
	</button>
</nav>

<style>
	.brand {
		display: none;
	}

	nav {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		height: calc(var(--nav-height) + env(safe-area-inset-bottom, 0px));
		padding-bottom: env(safe-area-inset-bottom, 0px);
		background: var(--c-bg-card);
		border-top: 1px solid var(--c-border);
		display: flex;
		z-index: 100;
	}

	a {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 2px;
		text-decoration: none;
		color: var(--c-text-muted);
		font-size: 0.7rem;
		transition: color 0.15s, background 0.15s;
	}

	a.active {
		color: var(--c-accent);
		font-weight: 600;
	}

	.icon {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 20px;
	}

	.theme-toggle {
		position: absolute;
		top: 0.4rem;
		right: 0.4rem;
		background: none;
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.25rem 0.35rem;
		cursor: pointer;
		color: var(--c-text-muted);
		transition: color 0.2s;
		line-height: 1;
		z-index: 101;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.theme-toggle:hover {
		color: var(--c-accent);
		background: none;
	}

	@media (min-width: 900px) {
		.brand {
			display: block;
			font-size: 1.25rem;
			font-weight: 700;
			color: var(--c-accent);
			padding: 1.5rem 1rem 1rem;
		}

		nav {
			position: fixed;
			top: 0;
			left: 0;
			bottom: 0;
			right: auto;
			width: var(--sidebar-width, 200px);
			height: 100dvh;
			flex-direction: column;
			border-top: none;
			border-right: 1px solid var(--c-border);
			padding: 0;
			gap: 0;
		}

		a {
			flex: 0;
			flex-direction: row;
			justify-content: flex-start;
			gap: 0.75rem;
			padding: 0.75rem 1rem;
			font-size: 0.9rem;
			border-radius: 0;
		}

		a:hover {
			background: var(--c-accent-bg);
		}

		a.active {
			background: var(--c-accent-bg);
			border-right: 3px solid var(--c-accent);
		}

		.icon {
			display: flex;
			align-items: center;
			justify-content: center;
			width: 1.5rem;
		}

		.theme-toggle {
			position: static;
			margin-top: auto;
			margin-bottom: 1rem;
			margin-left: 1rem;
			margin-right: 1rem;
			padding: 0.5rem;
			display: flex;
			align-items: center;
			justify-content: center;
		}
	}
</style>
