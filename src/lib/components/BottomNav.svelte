<script lang="ts">
	import { page } from '$app/state';
	import { theme } from '$lib/db';
	import { onMount } from 'svelte';
	import { useLocale, setLocale, initLocale } from '$lib/stores/locale.svelte';
	import { useFavorites } from '$lib/stores/favorites.svelte';
	import type { Locale } from '$lib/stores/locale.svelte';

	const { t, locale } = useLocale();
	const favs = useFavorites();

	const tabKeys: Array<{ href: string; key: keyof typeof t.nav; icon: string }> = [
		{ href: '/', key: 'today', icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' },
		{ href: '/intake', key: 'intake', icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>' },
		{ href: '/training', key: 'train', icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6.5 6.5 11 11"/><path d="m21 21-1-1"/><path d="m3 3 1 1"/><path d="m18 22 4-4"/><path d="m2 6 4-4"/><path d="m3 10 7-7"/><path d="m14 21 7-7"/></svg>' },
		{ href: '/dashboard', key: 'dashboard', icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>' },
		{ href: '/more', key: 'more', icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>' }
	];

	const morePaths = ['/checkin', '/signals', '/habits', '/supplements', '/experiments', '/profile', '/data', '/ref', '/timeline', '/goals', '/journal', '/report', '/reminders', '/records', '/hydration', '/measurements', '/bloodwork', '/medications', '/symptoms', '/insights'];

	const favLabelMap = $derived.by(() => ({
		'/signals': t.more.signals,
		'/habits': t.more.habits,
		'/supplements': t.more.supplements,
		'/hydration': t.more.hydration,
		'/measurements': t.more.measurements,
		'/bloodwork': t.more.bloodwork,
		'/medications': t.more.medications,
		'/symptoms': t.more.symptoms,
		'/journal': t.more.journal,
		'/timeline': t.more.timeline,
		'/goals': t.more.goals,
		'/experiments': t.more.experiments,
		'/records': t.more.records,
		'/report': t.more.report,
		'/insights': t.more.insights,
		'/profile': t.more.profile,
		'/reminders': t.more.reminders,
		'/data': t.more.data,
		'/ref': t.more.ref,
		'/checkin': t.nav.today
	}) as Record<string, string>);

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

	function toggleLocale(): void {
		setLocale(locale === 'en' ? 'es' : 'en');
	}

	onMount(() => {
		initLocale();
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
	{#each tabKeys as tab}
		<a href={tab.href} class:active={isActive(tab.href)} aria-label={t.nav[tab.key]}>
			<span class="icon">{@html tab.icon}</span>
			<span class="label">{t.nav[tab.key]}</span>
		</a>
	{/each}
	{#if favs.items.length > 0}
		<div class="fav-divider"></div>
		{#each favs.items as href}
			<a href={href} class="fav-link" class:active={page.url.pathname === href || page.url.pathname.startsWith(href + '/')}>
				<span class="icon">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="var(--c-accent)" stroke="var(--c-accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
				</span>
				<span class="label">{favLabelMap[href] ?? href.slice(1)}</span>
			</a>
		{/each}
	{/if}
	<div class="nav-controls">
		<button class="locale-toggle" onclick={toggleLocale} aria-label={t.nav.language}>
			{locale === 'en' ? 'ES' : 'EN'}
		</button>
		<button class="theme-toggle" onclick={toggleTheme} aria-label={t.nav.toggleTheme}>
		{#if isDark}
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
		{:else}
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
		{/if}
	</button>
	</div>
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

	.nav-controls {
		position: absolute;
		top: 0.4rem;
		right: 0.4rem;
		display: flex;
		gap: 0.3rem;
		z-index: 101;
	}

	.theme-toggle, .locale-toggle {
		background: none;
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.25rem 0.35rem;
		cursor: pointer;
		color: var(--c-text-muted);
		transition: color 0.2s;
		line-height: 1;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.theme-toggle:hover, .locale-toggle:hover {
		color: var(--c-accent);
		background: none;
	}

	.locale-toggle {
		font-size: 0.65rem;
		font-weight: 700;
		letter-spacing: 0.03em;
	}

	.fav-divider {
		display: none;
	}

	.fav-link {
		display: none;
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

		.nav-controls {
			position: static;
			margin-top: auto;
			margin-bottom: 1rem;
			margin-left: 1rem;
			margin-right: 1rem;
			display: flex;
			gap: 0.5rem;
		}

		.theme-toggle, .locale-toggle {
			padding: 0.5rem;
		}

		.fav-divider {
			display: block;
			height: 1px;
			background: var(--c-border);
			margin: 0.35rem 1rem;
		}

		.fav-link {
			display: flex;
			flex-direction: row;
			align-items: center;
			justify-content: flex-start;
			gap: 0.75rem;
			padding: 0.5rem 1rem;
			font-size: 0.8rem;
			text-decoration: none;
			color: var(--c-text-muted);
			border-radius: 0;
			transition: color 0.15s, background 0.15s;
		}

		.fav-link:hover {
			background: var(--c-accent-bg);
			color: var(--c-text);
		}

		.fav-link.active {
			color: var(--c-accent);
			font-weight: 600;
			background: var(--c-accent-bg);
			border-right: 3px solid var(--c-accent);
		}

		.fav-link .icon {
			display: flex;
			align-items: center;
			justify-content: center;
			width: 1.5rem;
		}

		.fav-link .icon svg {
			width: 14px;
			height: 14px;
		}
	}
</style>
