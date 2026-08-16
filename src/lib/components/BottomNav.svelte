<script lang="ts">
	import { page } from '$app/state';

	const tabs = [
		{ href: '/checkin', label: 'Check-in', icon: '✓' },
		{ href: '/intake', label: 'Intake', icon: '🍽' },
		{ href: '/training', label: 'Train', icon: '💪' },
		{ href: '/dashboard', label: 'Dashboard', icon: '📈' },
		{ href: '/more', label: 'More', icon: '⋯' }
	] as const;

	const morePaths = ['/signals', '/habits', '/supplements', '/experiments', '/profile', '/data', '/ref'];

	function isActive(href: string): boolean {
		if (href === '/more') {
			return morePaths.some((p) => page.url.pathname === p || page.url.pathname.startsWith(p + '/'));
		}
		return page.url.pathname === href || page.url.pathname.startsWith(href + '/');
	}
</script>

<nav class="bottom-nav">
	{#each tabs as tab}
		<a href={tab.href} class:active={isActive(tab.href)}>
			<span class="icon">{tab.icon}</span>
			<span class="label">{tab.label}</span>
		</a>
	{/each}
</nav>

<style>
	.bottom-nav {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		height: var(--nav-height);
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
		transition: color 0.15s;
	}

	a.active {
		color: var(--c-accent);
		font-weight: 600;
	}

	.icon {
		font-size: 1.25rem;
	}
</style>
