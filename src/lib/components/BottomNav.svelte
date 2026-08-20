<script lang="ts">
	import { page } from '$app/state';
	import { theme } from '$lib/db';
	import { onMount } from 'svelte';
	import { useLocale, setLocale, initLocale } from '$lib/stores/locale.svelte';
	import { useFavorites } from '$lib/stores/favorites.svelte';
	import type { Locale } from '$lib/stores/locale.svelte';

	const { t, locale } = useLocale();
	const favs = useFavorites();

	const PAGE_ICONS: Record<string, string> = {
		'/intake': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>',
		'/training': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6.5 6.5 11 11"/><path d="m21 21-1-1"/><path d="m3 3 1 1"/><path d="m18 22 4-4"/><path d="m2 6 4-4"/><path d="m3 10 7-7"/><path d="m14 21 7-7"/></svg>',
		'/dashboard': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>',
		'/checkin': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg>',
		'/signals': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
		'/habits': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
		'/supplements': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>',
		'/hydration': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/></svg>',
		'/measurements': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.41 2.41 0 0 1 0-3.4l2.6-2.6a2.41 2.41 0 0 1 3.4 0Z"/><path d="m14.5 12.5 2-2"/><path d="m11.5 9.5 2-2"/><path d="m8.5 6.5 2-2"/><path d="m17.5 15.5 2-2"/></svg>',
		'/bloodwork': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5s-2.5-1.1-2.5-2.5V2"/><path d="M8.5 2h7"/><path d="M14.5 16h-5"/></svg>',
		'/medications': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 11 3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
		'/symptoms': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4v10.54a4 4 0 1 1-4 0V4a2 2 0 0 1 4 0Z"/></svg>',
		'/journal': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
		'/timeline': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
		'/goals': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
		'/experiments': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v7.527a2 2 0 0 1-.211.896L4.72 20.55a1 1 0 0 0 .9 1.45h12.76a1 1 0 0 0 .9-1.45l-5.069-10.127A2 2 0 0 1 14 9.527V2"/><path d="M8.5 2h7"/><path d="M7 16.5h10"/></svg>',
		'/records': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5C7 4 9 7 12 7s5-3 7.5-3a2.5 2.5 0 0 1 0 5H18"/><path d="M12 7v14"/><path d="M8 21h8"/></svg>',
		'/report': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>',
		'/insights': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>',
		'/profile': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
		'/reminders': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>',
		'/data': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/></svg>',
		'/ref': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>'
	};

	const FALLBACK_ICON = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>';

	const TODAY_ICON = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';

	const MORE_ICON = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>';

	const favLabelMap = $derived.by(() => ({
		'/checkin': t.more.checkin,
		'/intake': t.more.intake,
		'/training': t.more.training,
		'/dashboard': t.more.dashboard,
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
		'/ref': t.more.ref
	}) as Record<string, string>);

	let isDark = $state(false);

	function isActive(href: string): boolean {
		const path = page.url.pathname;
		if (href === '/more') {
			if (path === '/more' || path.startsWith('/more/')) return true;
			if (path === '/') return false;
			return !favs.items.some(f => path === f || path.startsWith(f + '/'));
		}
		if (href === '/') return path === '/';
		return path === href || path.startsWith(href + '/');
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

	<a href="/" class:active={isActive('/')} aria-label={t.nav.today}>
		<span class="icon">{@html TODAY_ICON}</span>
		<span class="label">{t.nav.today}</span>
	</a>

	{#each favs.items as href, i}
		<div class="fav-item" class:fav-overflow={i >= 3}>
			<a href={href} class:active={isActive(href)} aria-label={favLabelMap[href] ?? href.slice(1)}>
				<span class="icon">{@html PAGE_ICONS[href] ?? FALLBACK_ICON}</span>
				<span class="label">{favLabelMap[href] ?? href.slice(1)}</span>
			</a>
			<div class="reorder-btns">
				<button onclick={() => favs.move(href, -1)} disabled={i === 0} aria-label="Move up">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
				</button>
				<button onclick={() => favs.move(href, 1)} disabled={i === favs.items.length - 1} aria-label="Move down">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
				</button>
			</div>
		</div>
	{/each}

	<a href="/more" class:active={isActive('/more')} aria-label={t.nav.more}>
		<span class="icon">{@html MORE_ICON}</span>
		<span class="label">{t.nav.more}</span>
	</a>

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

	nav > a {
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

	nav > a.active {
		color: var(--c-accent);
		font-weight: 600;
	}

	.fav-item {
		flex: 1;
		display: flex;
		position: relative;
	}

	.fav-item > a {
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

	.fav-item > a.active {
		color: var(--c-accent);
		font-weight: 600;
	}

	.fav-overflow {
		display: none;
	}

	.reorder-btns {
		display: none;
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

		nav > a {
			flex: 0;
			flex-direction: row;
			justify-content: flex-start;
			gap: 0.75rem;
			padding: 0.75rem 1rem;
			font-size: 0.9rem;
			border-radius: 0;
		}

		nav > a:hover {
			background: var(--c-accent-bg);
		}

		nav > a.active {
			background: var(--c-accent-bg);
			border-right: 3px solid var(--c-accent);
		}

		.fav-item {
			flex: 0;
		}

		.fav-item > a {
			flex-direction: row;
			justify-content: flex-start;
			gap: 0.75rem;
			padding: 0.75rem 1rem;
			padding-right: 3rem;
			font-size: 0.9rem;
			border-radius: 0;
		}

		.fav-item > a:hover {
			background: var(--c-accent-bg);
		}

		.fav-item > a.active {
			background: var(--c-accent-bg);
			border-right: 3px solid var(--c-accent);
			color: var(--c-accent);
		}

		.fav-overflow {
			display: flex;
		}

		.reorder-btns {
			display: none;
			position: absolute;
			right: 0.4rem;
			top: 50%;
			transform: translateY(-50%);
			flex-direction: column;
			gap: 1px;
		}

		.fav-item:hover .reorder-btns {
			display: flex;
		}

		.reorder-btns button {
			background: none;
			border: none;
			cursor: pointer;
			color: var(--c-text-muted);
			padding: 1px;
			display: flex;
			align-items: center;
			justify-content: center;
			border-radius: 2px;
			transition: color 0.15s, background 0.15s;
		}

		.reorder-btns button:hover:not(:disabled) {
			color: var(--c-accent);
			background: var(--c-accent-bg);
		}

		.reorder-btns button:disabled {
			opacity: 0.25;
			cursor: default;
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
	}
</style>
