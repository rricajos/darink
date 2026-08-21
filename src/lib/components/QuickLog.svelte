<script lang="ts">
	import { entries, useEntries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { ui } from '$lib/db';
	import { page } from '$app/state';
	import { onMount } from 'svelte';
	import { useLocale } from '$lib/stores/locale.svelte';

	const { t } = useLocale();
	const allEntries = useEntries();

	const defaultHabits = $derived.by(() => [
		{ id: 'cold', label: t.habits.cold },
		{ id: 'sun', label: t.habits.sun },
		{ id: 'fasting', label: t.habits.fasting },
		{ id: 'meditation', label: t.habits.meditation },
		{ id: 'wimhof', label: t.habits.wimhof },
		{ id: 'ejaculation', label: t.habits.ejaculation }
	]);

	let open = $state(false);
	let panel = $state<'habits' | 'supplements' | 'journal' | 'water' | null>(null);
	let journalText = $state('');
	let customWaterMl = $state(300);
	let mounted = $state(false);

	const isHome = $derived(page.url.pathname === '/');

	const todayEntryCount = $derived.by(() => {
		const todayStr = today();
		return allEntries.items.filter(e => e.createdAt.startsWith(todayStr)).length;
	});

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

	function undoAction(id: string) {
		entries.remove(id);
	}

	function quickCheckin() {
		const e = entries.add('checkin', { mood: 5, energy: 5, stress: 5, sleep: 7, date: today() });
		toast.show(t.quickLog.quickCheckinLogged, { label: t.common.undo, fn: () => undoAction(e.id) });
		close();
	}

	function quickWater(ml: number) {
		const e = entries.add('hydration', { date: today(), amount: ml, unit: 'ml' });
		toast.show(`${ml}ml ${t.quickLog.waterLoggedShort}`, { label: t.common.undo, fn: () => undoAction(e.id) });
		close();
	}

	function logHabit(id: string, label: string) {
		const e = entries.add('habit', { date: today(), habit: id, duration: 0, notes: '' });
		toast.show(`${label} ${t.quickLog.logged}`, { label: t.common.undo, fn: () => undoAction(e.id) });
		close();
	}

	function logSupplement(item: { name: string; dose: string; timing: string }) {
		const e = entries.add('supplement', { date: today(), name: item.name, dose: item.dose, timing: item.timing, notes: '' });
		toast.show(`${item.name} ${t.quickLog.logged}`, { label: t.common.undo, fn: () => undoAction(e.id) });
		close();
	}

	const repeatableTypes = new Set(['intake', 'habit', 'supplement', 'hydration']);

	function repeatLabel(e: { type: string; data: Record<string, unknown> }): string {
		if (e.type === 'intake') return `${e.data.what ?? '?'}`;
		if (e.type === 'habit') return `${e.data.habit ?? '?'}`;
		if (e.type === 'supplement') return `${e.data.name ?? '?'}`;
		if (e.type === 'hydration') return `${e.data.amount ?? '?'}ml`;
		return e.type;
	}

	const recentEntries = $derived.by(() => {
		const seen = new Set<string>();
		const result: Array<{ type: string; data: Record<string, unknown>; label: string }> = [];
		const sorted = allEntries.items.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt));
		for (const e of sorted) {
			if (!repeatableTypes.has(e.type)) continue;
			const key = `${e.type}:${repeatLabel(e)}`;
			if (seen.has(key)) continue;
			seen.add(key);
			result.push({ type: e.type, data: e.data, label: repeatLabel(e) });
			if (result.length >= 3) break;
		}
		return result;
	});

	function repeatEntry(item: { type: string; data: Record<string, unknown>; label: string }) {
		const data = { ...item.data, date: today() };
		const e = entries.add(item.type, data);
		toast.show(`${item.label} ${t.quickLog.logged}`, { label: t.common.undo, fn: () => undoAction(e.id) });
		close();
	}

	function saveJournal() {
		const text = journalText.trim();
		if (!text) return;
		const e = entries.add('journal', { date: today(), text, mood: 5 });
		toast.show(t.quickLog.journalSaved, { label: t.common.undo, fn: () => undoAction(e.id) });
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
					<!-- Repeat last -->
					{#if recentEntries.length > 0}
						<div class="ql-repeat">
							<span class="ql-repeat-title">{t.common.repeatLast}</span>
							<div class="ql-repeat-items">
								{#each recentEntries as item}
									<button class="ql-repeat-btn" onclick={() => repeatEntry(item)}>
										<span class="ql-repeat-label">{item.label}</span>
									</button>
								{/each}
							</div>
						</div>
					{/if}
					<!-- Action buttons -->
					<button class="ql-action" onclick={quickCheckin}>
						<span class="ql-action-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/></svg>
						</span>
						<span class="ql-action-label">{t.quickLog.quickCheckin}</span>
					</button>

					<button class="ql-action" onclick={() => panel = 'water'}>
						<span class="ql-action-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/></svg>
						</span>
						<span class="ql-action-label">{t.quickLog.quickWater}</span>
					</button>

					<button class="ql-action" onclick={() => panel = 'habits'}>
						<span class="ql-action-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
						</span>
						<span class="ql-action-label">{t.quickLog.logHabit}</span>
					</button>

					<button class="ql-action" onclick={() => panel = 'supplements'}>
						<span class="ql-action-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="8" rx="4"/><path d="M12 8v8"/></svg>
						</span>
						<span class="ql-action-label">{t.quickLog.takeSupplement}</span>
					</button>

					<button class="ql-action" onclick={() => panel = 'journal'}>
						<span class="ql-action-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
						</span>
						<span class="ql-action-label">{t.quickLog.quickJournal}</span>
					</button>
				{:else if panel === 'habits'}
					<div class="ql-panel">
						<div class="ql-panel-header">
							<button class="ql-back" onclick={() => panel = null} aria-label={t.common.back}>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
							</button>
							<span class="ql-panel-title">{t.quickLog.logHabit}</span>
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
							<button class="ql-back" onclick={() => panel = null} aria-label={t.common.back}>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
							</button>
							<span class="ql-panel-title">{t.quickLog.takeSupplement}</span>
						</div>
						<div class="ql-chips">
							{#if supplementStack.length > 0}
								{#each supplementStack as item}
									<button class="ql-chip" onclick={() => logSupplement(item)}>
										{item.name}{#if item.dose} <span class="ql-chip-meta">{item.dose}</span>{/if}
									</button>
								{/each}
							{:else}
								<p class="ql-empty">{t.quickLog.noSupplementsInStack}</p>
							{/if}
						</div>
					</div>
				{:else if panel === 'water'}
					<div class="ql-panel">
						<div class="ql-panel-header">
							<button class="ql-back" onclick={() => panel = null} aria-label={t.common.back}>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
							</button>
							<span class="ql-panel-title">{t.quickLog.quickWater}</span>
						</div>
						<div class="ql-water-btns">
							<button class="ql-water-btn" onclick={() => quickWater(250)}>
								<span class="ql-water-amount">250</span><span class="ql-water-unit">ml</span>
							</button>
							<button class="ql-water-btn" onclick={() => quickWater(500)}>
								<span class="ql-water-amount">500</span><span class="ql-water-unit">ml</span>
							</button>
						</div>
						<div class="ql-water-custom">
							<input type="number" class="ql-water-input" min="50" step="50" bind:value={customWaterMl} />
							<button class="ql-water-go" onclick={() => quickWater(customWaterMl)}>ml</button>
						</div>
					</div>
				{:else if panel === 'journal'}
					<div class="ql-panel">
						<div class="ql-panel-header">
							<button class="ql-back" onclick={() => panel = null} aria-label={t.common.back}>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
							</button>
							<span class="ql-panel-title">{t.quickLog.quickJournal}</span>
						</div>
						<textarea
							class="ql-textarea"
							rows="3"
							placeholder={t.quickLog.whatsOnYourMind}
							bind:value={journalText}
						></textarea>
						<button class="ql-save primary" onclick={saveJournal} disabled={!journalText.trim()}>{t.common.save}</button>
					</div>
				{/if}
			</div>
		{/if}

		<!-- FAB button -->
		<button class="ql-fab" class:open onclick={toggle} aria-label={open ? t.quickLog.closeQuickLog : t.quickLog.openQuickLog}>
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
			{#if todayEntryCount > 0 && !open}
				<span class="ql-badge">{todayEntryCount}</span>
			{/if}
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

	.ql-badge {
		position: absolute;
		top: -4px;
		right: -4px;
		background: var(--c-done);
		color: #fff;
		font-size: 0.6rem;
		font-weight: 700;
		min-width: 18px;
		height: 18px;
		border-radius: 9px;
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 0 4px;
		pointer-events: none;
	}

	/* Menu */
	.ql-menu {
		display: flex;
		flex-direction: column;
		gap: 8px;
		animation: ql-slide-up 0.2s ease;
	}

	/* Repeat last */
	.ql-repeat {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.5rem 0.6rem;
		box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
		animation: ql-pop-in 0.15s ease;
	}

	.ql-repeat-title {
		font-size: 0.65rem;
		font-weight: 600;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.04em;
		display: block;
		margin-bottom: 0.3rem;
	}

	.ql-repeat-items {
		display: flex;
		flex-wrap: wrap;
		gap: 0.25rem;
	}

	.ql-repeat-btn {
		display: inline-flex;
		align-items: center;
		padding: 0.25rem 0.5rem;
		font-size: 0.75rem;
		font-weight: 500;
		background: var(--c-accent-bg);
		border: 1px solid var(--c-border);
		border-radius: 14px;
		color: var(--c-text);
		cursor: pointer;
		transition: border-color 0.15s, background 0.15s;
	}

	.ql-repeat-btn:hover {
		border-color: var(--c-accent);
		background: var(--c-accent);
		color: #fff;
		transform: none;
		box-shadow: none;
	}

	.ql-repeat-label {
		max-width: 120px;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
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
	.ql-action:nth-child(2) { animation-delay: 0.05s; }
	.ql-action:nth-child(3) { animation-delay: 0.08s; }
	.ql-action:nth-child(4) { animation-delay: 0.11s; }
	.ql-action:nth-child(5) { animation-delay: 0.14s; }

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

	/* Water panel */
	.ql-water-btns {
		display: flex;
		gap: 0.5rem;
		margin-bottom: 0.5rem;
	}

	.ql-water-btn {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 0.05rem;
		padding: 0.6rem 0.5rem;
		background: var(--c-accent-bg);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		cursor: pointer;
		transition: border-color 0.15s, background 0.15s;
	}

	.ql-water-btn:hover {
		border-color: var(--c-accent);
		background: var(--c-accent);
		color: #fff;
		transform: none;
		box-shadow: none;
	}

	.ql-water-amount {
		font-size: 1.1rem;
		font-weight: 700;
	}

	.ql-water-unit {
		font-size: 0.65rem;
		color: var(--c-text-muted);
	}

	.ql-water-btn:hover .ql-water-unit {
		color: rgba(255,255,255,0.8);
	}

	.ql-water-custom {
		display: flex;
		gap: 0.35rem;
	}

	.ql-water-input {
		flex: 1;
		font-size: 0.85rem;
		padding: 0.35rem 0.5rem;
		text-align: center;
	}

	.ql-water-go {
		padding: 0.35rem 0.65rem;
		font-size: 0.8rem;
		font-weight: 600;
		background: var(--c-accent);
		color: #fff;
		border: none;
		border-radius: var(--radius);
		cursor: pointer;
	}

	.ql-water-go:hover {
		opacity: 0.9;
		background: var(--c-accent);
		color: #fff;
		transform: none;
		box-shadow: none;
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
