<script lang="ts">
	import { entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { ui } from '$lib/db';
	import { page } from '$app/state';
	import { onMount } from 'svelte';

	const defaultHabits = [
		{ id: 'cold', label: 'Cold exposure' },
		{ id: 'sun', label: 'Sun exposure' },
		{ id: 'fasting', label: 'Fasting' },
		{ id: 'meditation', label: 'Meditation' },
		{ id: 'wimhof', label: 'Wim Hof' },
		{ id: 'ejaculation', label: 'Ejaculation control' }
	];

	let open = $state(false);
	let panel = $state<'habits' | 'supplements' | 'journal' | null>(null);
	let journalText = $state('');
	let mounted = $state(false);

	const isHome = $derived(page.url.pathname === '/');

	const allHabits = $derived.by(() => {
		const stored = ui.get().customHabits;
		const custom = Array.isArray(stored) ? (stored as Array<{ id: string; label: string }>) : [];
		return [...defaultHabits, ...custom];
	});

	const supplementStack = $derived.by(() => {
		const stored = ui.get().supplementStack;
		return Array.isArray(stored) ? (stored as Array<{ name: string; dose: string; timing: string }>) : [];
	});

	onMount(() => {
		mounted = true;

		function handleKeydown(e: KeyboardEvent) {
			if (e.key === 'Escape' && open) {
				close();
			}
		}
		window.addEventListener('keydown', handleKeydown);
		return () => window.removeEventListener('keydown', handleKeydown);
	});

	function toggle() {
		if (open) {
			close();
		} else {
			open = true;
			panel = null;
		}
	}

	function close() {
		open = false;
		panel = null;
		journalText = '';
	}

	function today(): string {
		return new Date().toISOString().slice(0, 10);
	}

	function quickCheckin() {
		entries.add('checkin', { mood: 5, energy: 5, stress: 5, sleep: 7, date: today() });
		toast.show('Quick check-in logged');
		close();
	}

	function logHabit(id: string, label: string) {
		entries.add('habit', { date: today(), habit: id, duration: 0, notes: '' });
		toast.show(`${label} logged`);
		close();
	}

	function logSupplement(item: { name: string; dose: string; timing: string }) {
		entries.add('supplement', { date: today(), name: item.name, dose: item.dose, timing: item.timing, notes: '' });
		toast.show(`${item.name} logged`);
		close();
	}

	function saveJournal() {
		const text = journalText.trim();
		if (!text) return;
		entries.add('journal', { date: today(), text, mood: 5 });
		toast.show('Journal entry saved');
		close();
	}
</script>

{#if mounted && !isHome}
	<!-- Backdrop -->
	{#if open}
		<div class="ql-backdrop" onclick={close} role="presentation"></div>
	{/if}

	<div class="ql-container" class:open>
		<!-- Expanded menu -->
		{#if open}
			<div class="ql-menu">
				{#if panel === null}
					<!-- Action buttons -->
					<button class="ql-action" onclick={quickCheckin}>
						<span class="ql-action-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/></svg>
						</span>
						<span class="ql-action-label">Quick Check-in</span>
					</button>

					<button class="ql-action" onclick={() => panel = 'habits'}>
						<span class="ql-action-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
						</span>
						<span class="ql-action-label">Log Habit</span>
					</button>

					<button class="ql-action" onclick={() => panel = 'supplements'}>
						<span class="ql-action-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="8" rx="4"/><path d="M12 8v8"/></svg>
						</span>
						<span class="ql-action-label">Take Supplement</span>
					</button>

					<button class="ql-action" onclick={() => panel = 'journal'}>
						<span class="ql-action-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
						</span>
						<span class="ql-action-label">Quick Journal</span>
					</button>
				{:else if panel === 'habits'}
					<div class="ql-panel">
						<div class="ql-panel-header">
							<button class="ql-back" onclick={() => panel = null} aria-label="Back">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
							</button>
							<span class="ql-panel-title">Log Habit</span>
						</div>
						<div class="ql-chips">
							{#each allHabits as h}
								<button class="ql-chip" onclick={() => logHabit(h.id, h.label)}>{h.label}</button>
							{/each}
						</div>
					</div>
				{:else if panel === 'supplements'}
					<div class="ql-panel">
						<div class="ql-panel-header">
							<button class="ql-back" onclick={() => panel = null} aria-label="Back">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
							</button>
							<span class="ql-panel-title">Take Supplement</span>
						</div>
						<div class="ql-chips">
							{#if supplementStack.length > 0}
								{#each supplementStack as item}
									<button class="ql-chip" onclick={() => logSupplement(item)}>
										{item.name}{#if item.dose} <span class="ql-chip-meta">{item.dose}</span>{/if}
									</button>
								{/each}
							{:else}
								<p class="ql-empty">No supplements in your stack. Add them in Supplements page.</p>
							{/if}
						</div>
					</div>
				{:else if panel === 'journal'}
					<div class="ql-panel">
						<div class="ql-panel-header">
							<button class="ql-back" onclick={() => panel = null} aria-label="Back">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
							</button>
							<span class="ql-panel-title">Quick Journal</span>
						</div>
						<textarea
							class="ql-textarea"
							rows="3"
							placeholder="What's on your mind?"
							bind:value={journalText}
						></textarea>
						<button class="ql-save primary" onclick={saveJournal} disabled={!journalText.trim()}>Save</button>
					</div>
				{/if}
			</div>
		{/if}

		<!-- FAB button -->
		<button class="ql-fab" class:open onclick={toggle} aria-label={open ? 'Close quick log' : 'Open quick log'}>
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
		</button>
	</div>
{/if}

<style>
	.ql-backdrop {
		position: fixed;
		inset: 0;
		background: rgba(0, 0, 0, 0.4);
		z-index: 199;
		animation: ql-fade-in 0.2s ease;
	}

	.ql-container {
		position: fixed;
		bottom: calc(var(--nav-height) + env(safe-area-inset-bottom, 0px) + 14px);
		right: 16px;
		z-index: 200;
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		gap: 12px;
	}

	/* FAB */
	.ql-fab {
		width: 48px;
		height: 48px;
		border-radius: 50%;
		background: var(--c-accent);
		color: #fff;
		border: none;
		display: flex;
		align-items: center;
		justify-content: center;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
		transition: transform 0.25s ease, box-shadow 0.2s;
		padding: 0;
		cursor: pointer;
		flex-shrink: 0;
	}

	.ql-fab:hover {
		transform: scale(1.08);
		box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25);
		background: var(--c-accent);
		color: #fff;
	}

	.ql-fab.open {
		transform: rotate(45deg);
	}

	.ql-fab.open:hover {
		transform: rotate(45deg) scale(1.08);
	}

	/* Menu */
	.ql-menu {
		display: flex;
		flex-direction: column;
		gap: 8px;
		animation: ql-slide-up 0.2s ease;
	}

	/* Action buttons */
	.ql-action {
		display: flex;
		align-items: center;
		gap: 10px;
		flex-direction: row-reverse;
		background: none;
		border: none;
		padding: 0;
		cursor: pointer;
		animation: ql-pop-in 0.2s ease backwards;
	}

	.ql-action:nth-child(1) { animation-delay: 0.02s; }
	.ql-action:nth-child(2) { animation-delay: 0.06s; }
	.ql-action:nth-child(3) { animation-delay: 0.1s; }
	.ql-action:nth-child(4) { animation-delay: 0.14s; }

	.ql-action-icon {
		width: 40px;
		height: 40px;
		border-radius: 50%;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		display: flex;
		align-items: center;
		justify-content: center;
		color: var(--c-accent);
		flex-shrink: 0;
		transition: background 0.15s, border-color 0.15s;
		box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
	}

	.ql-action:hover .ql-action-icon {
		background: var(--c-accent-bg);
		border-color: var(--c-accent);
	}

	.ql-action:hover {
		transform: none;
		box-shadow: none;
		background: none;
		color: var(--c-text);
	}

	.ql-action-label {
		font-size: 0.8rem;
		font-weight: 600;
		color: var(--c-text);
		background: var(--c-bg-card);
		padding: 0.3rem 0.6rem;
		border-radius: var(--radius);
		box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
		white-space: nowrap;
	}

	/* Panel */
	.ql-panel {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem;
		min-width: 220px;
		max-width: 280px;
		box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
		animation: ql-pop-in 0.2s ease;
	}

	.ql-panel-header {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		margin-bottom: 0.6rem;
	}

	.ql-panel-title {
		font-size: 0.85rem;
		font-weight: 600;
		color: var(--c-text);
	}

	.ql-back {
		width: 28px;
		height: 28px;
		border-radius: 50%;
		background: var(--c-accent-bg);
		border: 1px solid var(--c-border);
		display: flex;
		align-items: center;
		justify-content: center;
		color: var(--c-text-muted);
		cursor: pointer;
		padding: 0;
		flex-shrink: 0;
	}

	.ql-back:hover {
		color: var(--c-accent);
		background: var(--c-accent-bg);
		transform: none;
		box-shadow: none;
	}

	/* Chips */
	.ql-chips {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
	}

	.ql-chip {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		padding: 0.35rem 0.65rem;
		background: var(--c-accent-bg);
		border: 1px solid var(--c-border);
		border-radius: 20px;
		font-size: 0.8rem;
		color: var(--c-text);
		cursor: pointer;
		transition: background 0.15s, border-color 0.15s;
	}

	.ql-chip:hover {
		border-color: var(--c-accent);
		background: var(--c-accent);
		color: #fff;
		transform: none;
		box-shadow: none;
	}

	.ql-chip-meta {
		font-size: 0.7rem;
		color: var(--c-text-muted);
	}

	.ql-chip:hover .ql-chip-meta {
		color: rgba(255, 255, 255, 0.8);
	}

	.ql-empty {
		font-size: 0.8rem;
		color: var(--c-text-muted);
		margin: 0;
	}

	/* Journal textarea & save */
	.ql-textarea {
		width: 100%;
		min-height: 64px;
		resize: vertical;
		font-size: 0.85rem;
		margin-bottom: 0.5rem;
	}

	.ql-save {
		width: 100%;
	}

	/* Desktop positioning */
	@media (min-width: 900px) {
		.ql-container {
			bottom: 24px;
			right: 32px;
		}
	}

	/* Animations */
	@keyframes ql-fade-in {
		from { opacity: 0; }
		to { opacity: 1; }
	}

	@keyframes ql-slide-up {
		from {
			opacity: 0;
			transform: translateY(8px);
		}
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}

	@keyframes ql-pop-in {
		from {
			opacity: 0;
			transform: scale(0.8);
		}
		to {
			opacity: 1;
			transform: scale(1);
		}
	}

	/* Print: hide the FAB */
	@media print {
		.ql-container,
		.ql-backdrop {
			display: none !important;
		}
	}
</style>
