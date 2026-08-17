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

<nav>
	<div class="brand">Darink</div>
	{#each tabs as tab}
		<a href={tab.href} class:active={isActive(tab.href)}>
			<span class="icon">{tab.icon}</span>
			<span class="label">{tab.label}</span>
		</a>
	{/each}
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
		font-size: 1.25rem;
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
			font-size: 1.1rem;
			width: 1.5rem;
			text-align: center;
		}
	}
</style>
