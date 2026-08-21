<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { ui } from '$lib/db';
	import { toast } from '$lib/stores/toast.svelte';
	import { useLocale } from '$lib/stores/locale.svelte';
	import { onMount } from 'svelte';

	const { t } = useLocale();

	interface Reminder {
		id: string;
		type: string;
		time: string;
		days: number[];
		label: string;
		enabled: boolean;
	}

	const TYPES = ['checkin', 'habit', 'supplement', 'training', 'journal', 'custom'] as const;
	const DAY_LABELS = $derived([t.days.mon, t.days.tue, t.days.wed, t.days.thu, t.days.fri, t.days.sat, t.days.sun] as const);

	let reminders = $state<Reminder[]>([]);
	let permission = $state<NotificationPermission>('default');
	let lastFired = $state<Record<string, string>>({});

	// Form state
	let formType = $state<string>('checkin');
	let formTime = $state('08:00');
	let formDays = $state<boolean[]>([true, true, true, true, true, true, true]);
	let formLabel = $state('');

	let intervalId: ReturnType<typeof setInterval> | undefined;

	function generateId(): string {
		return Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
	}

	function saveReminders(): void {
		ui.patch({ reminders });
	}

	function addReminder(): void {
		const selectedDays = formDays
			.map((checked, i) => (checked ? i : -1))
			.filter((d) => d >= 0);

		if (selectedDays.length === 0) {
			toast.show(t.reminders.selectAtLeastOneDay);
			return;
		}

		const reminder: Reminder = {
			id: generateId(),
			type: formType,
			time: formTime,
			days: selectedDays,
			label: formLabel.trim(),
			enabled: true
		};

		reminders = [...reminders, reminder];
		saveReminders();
		toast.show(t.reminders.reminderAdded);

		// Reset form
		formLabel = '';
		formDays = [true, true, true, true, true, true, true];
	}

	function toggleReminder(id: string): void {
		reminders = reminders.map((r) =>
			r.id === id ? { ...r, enabled: !r.enabled } : r
		);
		saveReminders();
	}

	function deleteReminder(id: string): void {
		reminders = reminders.filter((r) => r.id !== id);
		saveReminders();
		toast.show(t.reminders.reminderDeleted);
	}

	async function requestPermission(): Promise<void> {
		if (!('Notification' in window)) {
			toast.show(t.reminders.notificationsNotSupported);
			return;
		}
		const result = await Notification.requestPermission();
		permission = result;
		if (result === 'granted') {
			toast.show(t.reminders.notificationsEnabled);
		} else if (result === 'denied') {
			toast.show(t.reminders.notificationsBlocked);
		}
	}

	function checkReminders(): void {
		if (permission !== 'granted') return;
		const now = new Date();
		const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
		// JS getDay(): 0=Sun, but our days array: 0=Mon..6=Sun
		const jsDay = now.getDay();
		const currentDay = jsDay === 0 ? 6 : jsDay - 1;
		const minuteKey = `${now.getFullYear()}-${now.getMonth()}-${now.getDate()}-${currentTime}`;

		for (const r of reminders) {
			if (!r.enabled) continue;
			if (r.time !== currentTime) continue;
			if (!r.days.includes(currentDay)) continue;

			const fireKey = `${r.id}-${minuteKey}`;
			if (lastFired[fireKey]) continue;

			lastFired = { ...lastFired, [fireKey]: minuteKey };

			const title = r.label || `${r.type.charAt(0).toUpperCase() + r.type.slice(1)} reminder`;
			new Notification(title, {
				body: `Time for your ${r.type} at ${r.time}`,
				icon: '/favicon.png'
			});
		}
	}

	function daysDisplay(days: number[]): string {
		if (days.length === 7) return t.reminders.everyDay;
		if (days.length === 5 && !days.includes(5) && !days.includes(6)) return t.reminders.weekdays;
		if (days.length === 2 && days.includes(5) && days.includes(6)) return t.reminders.weekends;
		return days.map((d) => DAY_LABELS[d]).join(', ');
	}

	onMount(() => {
		const stored = ui.get().reminders as Reminder[] | undefined;
		if (stored && Array.isArray(stored)) {
			reminders = stored;
		}

		if ('Notification' in window) {
			permission = Notification.permission;
		}

		checkReminders();
		intervalId = setInterval(checkReminders, 60_000);

		return () => {
			if (intervalId) clearInterval(intervalId);
		};
	});
</script>

<svelte:head>
	<title>{t.reminders.title} | Darink</title>
</svelte:head>

<PageHeader title={t.reminders.title} />

<!-- Notification permission -->
<section class="perm">
	{#if permission === 'granted'}
		<div class="perm-status granted">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
			{t.reminders.notificationsEnabled}
		</div>
	{:else if permission === 'denied'}
		<div class="perm-status denied">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
			{t.reminders.notificationsBlocked} ({t.reminders.notificationsBlockedHint})
		</div>
	{:else}
		<button class="perm-btn" onclick={requestPermission}>
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
			{t.reminders.enableNotifications}
		</button>
	{/if}
</section>

<!-- Add reminder form -->
<section class="form">
	<h2>{t.reminders.newReminder}</h2>
	<div class="row">
		<label>{t.reminders.typeLabel}
			<select bind:value={formType}>
				{#each TYPES as tp}
					<option value={tp}>{tp.charAt(0).toUpperCase() + tp.slice(1)}</option>
				{/each}
			</select>
		</label>
		<label>{t.reminders.time}
			<input type="time" bind:value={formTime} />
		</label>
	</div>
	<div class="days-row">
		<div class="days-presets">
			<span class="days-label">{t.reminders.days}</span>
			<div class="preset-btns">
				<button type="button" class="preset" onclick={() => { formDays = [true,true,true,true,true,true,true]; }}>{t.reminders.everyDay}</button>
				<button type="button" class="preset" onclick={() => { formDays = [true,true,true,true,true,false,false]; }}>{t.reminders.weekdays}</button>
				<button type="button" class="preset" onclick={() => { formDays = [false,false,false,false,false,true,true]; }}>{t.reminders.weekends}</button>
				<button type="button" class="preset" onclick={() => { formDays = [true,false,true,false,true,false,false]; }}>{t.reminders.mwf}</button>
			</div>
		</div>
		<div class="days-grid">
			{#each DAY_LABELS as day, i}
				<label class="day-check" class:checked={formDays[i]}>
					<input type="checkbox" bind:checked={formDays[i]} />
					{day}
				</label>
			{/each}
		</div>
	</div>
	<label>{t.reminders.labelOptional}
		<input type="text" bind:value={formLabel} placeholder={t.reminders.labelPlaceholder} />
	</label>
	<button class="primary" onclick={addReminder}>{t.reminders.addReminder}</button>
</section>

<!-- Week overview -->
{#if reminders.length > 0}
<section class="week-overview">
	<h2>{t.reminders.weekOverview}</h2>
	<div class="wo-grid">
		{#each DAY_LABELS as dayLabel, dayIdx}
			{@const dayReminders = reminders.filter(r => r.enabled && r.days.includes(dayIdx))}
			<div class="wo-col">
				<span class="wo-day">{dayLabel}</span>
				{#if dayReminders.length > 0}
					{#each dayReminders as r}
						<div class="wo-pill" title={r.label || r.type}>
							<span class="wo-time">{r.time}</span>
							<span class="wo-type">{r.type.slice(0, 3)}</span>
						</div>
					{/each}
				{:else}
					<span class="wo-empty">-</span>
				{/if}
			</div>
		{/each}
	</div>
</section>
{/if}

<!-- Reminder list -->
<section class="list">
	<h2>{t.reminders.activeReminders}</h2>
	{#if reminders.length === 0}
		<div class="empty">
			<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
			<p>{t.reminders.noReminders}</p>
			<span>{t.reminders.noRemindersHint}</span>
		</div>
	{:else}
		{#each reminders as r (r.id)}
			<div class="reminder-card" class:disabled={!r.enabled}>
				<button class="toggle" class:on={r.enabled} onclick={() => toggleReminder(r.id)} aria-label={r.enabled ? 'Disable' : 'Enable'}>
					<span class="toggle-knob"></span>
				</button>
				<div class="reminder-info">
					<div class="reminder-top">
						<span class="badge">{r.type}</span>
						<span class="time">{r.time}</span>
					</div>
					{#if r.label}
						<span class="label">{r.label}</span>
					{/if}
					<span class="days">{daysDisplay(r.days)}</span>
				</div>
				<button class="delete" onclick={() => deleteReminder(r.id)} aria-label={t.common.delete}>
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
				</button>
			</div>
		{/each}
	{/if}
</section>

<style>
	h2 {
		font-size: 0.9rem;
		font-weight: 600;
		margin-bottom: 0.5rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}

	/* Permission section */
	.perm {
		padding: 0 1rem 1rem;
	}

	.perm-status {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.75rem 1rem;
		border-radius: var(--radius);
		font-size: 0.85rem;
		font-weight: 500;
	}

	.perm-status.granted {
		background: color-mix(in srgb, var(--c-done) 15%, transparent);
		color: var(--c-done);
	}

	.perm-status.denied {
		background: color-mix(in srgb, var(--c-cancel) 15%, transparent);
		color: var(--c-cancel);
	}

	.perm-btn {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.75rem 1rem;
		background: var(--c-accent-bg);
		color: var(--c-accent);
		border: 1px solid var(--c-accent);
		border-radius: var(--radius);
		font-size: 0.85rem;
		font-weight: 500;
		cursor: pointer;
		width: 100%;
		justify-content: center;
	}

	.perm-btn:hover {
		background: var(--c-accent);
		color: var(--c-bg);
	}

	/* Form */
	.form {
		display: flex;
		flex-direction: column;
		gap: 1rem;
		padding: 0 1rem 1.5rem;
	}

	.row {
		display: flex;
		gap: 0.5rem;
		flex-wrap: wrap;
	}

	.row label {
		flex: 1;
		min-width: 120px;
	}

	.days-row {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}

	.days-presets {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		flex-wrap: wrap;
	}

	.preset-btns {
		display: flex;
		gap: 0.25rem;
		flex-wrap: wrap;
	}

	.preset {
		font-size: 0.7rem;
		padding: 0.2rem 0.5rem;
		border-radius: 12px;
		border: 1px solid var(--c-border);
		background: var(--c-bg-card);
		color: var(--c-text-muted);
		cursor: pointer;
		font-weight: 500;
		white-space: nowrap;
	}

	.preset:hover {
		border-color: var(--c-accent);
		color: var(--c-accent);
		background: var(--c-bg-card);
		transform: none;
		box-shadow: none;
	}

	.days-label {
		font-size: 0.85rem;
		font-weight: 500;
	}

	.days-grid {
		display: flex;
		gap: 0.25rem;
		flex-wrap: wrap;
	}

	.day-check {
		display: flex;
		align-items: center;
		justify-content: center;
		min-width: 2.75rem;
		padding: 0.4rem 0.5rem;
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		font-size: 0.8rem;
		cursor: pointer;
		user-select: none;
		transition: background 0.15s, border-color 0.15s, color 0.15s;
		background: var(--c-bg-card);
		color: var(--c-text-muted);
	}

	.day-check input {
		position: absolute;
		opacity: 0;
		pointer-events: none;
		width: 0;
		height: 0;
	}

	.day-check.checked {
		background: var(--c-accent);
		border-color: var(--c-accent);
		color: var(--c-bg);
		font-weight: 600;
	}

	/* Reminder list */
	.list {
		padding: 0 1rem 2rem;
	}

	.empty {
		text-align: center;
		padding: 2.5rem 1rem;
		color: var(--c-text-muted);
	}

	.empty svg {
		opacity: 0.3;
		margin-bottom: 0.75rem;
	}

	.empty p {
		font-weight: 600;
		margin-bottom: 0.25rem;
	}

	.empty span {
		font-size: 0.85rem;
	}

	.reminder-card {
		display: flex;
		align-items: center;
		gap: 0.75rem;
		padding: 0.75rem 1rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		margin-bottom: 0.5rem;
		transition: opacity 0.15s;
	}

	.reminder-card.disabled {
		opacity: 0.5;
	}

	.reminder-info {
		flex: 1;
		display: flex;
		flex-direction: column;
		gap: 0.15rem;
		min-width: 0;
	}

	.reminder-top {
		display: flex;
		align-items: center;
		gap: 0.5rem;
	}

	.badge {
		font-size: 0.7rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		padding: 0.15rem 0.5rem;
		border-radius: var(--radius);
		background: var(--c-accent-bg);
		color: var(--c-accent);
	}

	.time {
		font-size: 1rem;
		font-weight: 700;
		font-variant-numeric: tabular-nums;
	}

	.label {
		font-size: 0.85rem;
		color: var(--c-text);
	}

	.days {
		font-size: 0.75rem;
		color: var(--c-text-muted);
	}

	/* Toggle switch */
	.toggle {
		position: relative;
		width: 40px;
		height: 22px;
		border-radius: 11px;
		background: var(--c-border);
		border: none;
		cursor: pointer;
		flex-shrink: 0;
		transition: background 0.2s;
		padding: 0;
	}

	.toggle.on {
		background: var(--c-accent);
	}

	.toggle-knob {
		position: absolute;
		top: 2px;
		left: 2px;
		width: 18px;
		height: 18px;
		border-radius: 50%;
		background: white;
		transition: transform 0.2s;
	}

	.toggle.on .toggle-knob {
		transform: translateX(18px);
	}

	/* Week overview */
	.week-overview { padding: 0 1rem 1.5rem; }
	.wo-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; }
	.wo-col { display: flex; flex-direction: column; align-items: center; gap: 3px; min-width: 0; }
	.wo-day { font-size: 0.7rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; margin-bottom: 2px; }
	.wo-pill { display: flex; flex-direction: column; align-items: center; background: var(--c-accent-bg); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 2px 4px; width: 100%; }
	.wo-time { font-size: 0.65rem; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--c-text); }
	.wo-type { font-size: 0.6rem; color: var(--c-accent); text-transform: uppercase; font-weight: 600; }
	.wo-empty { font-size: 0.7rem; color: var(--c-text-muted); opacity: 0.4; }

	/* Delete button */
	.delete {
		background: none;
		border: none;
		cursor: pointer;
		color: var(--c-text-muted);
		padding: 0.25rem;
		border-radius: var(--radius);
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
		transition: color 0.15s;
	}

	.delete:hover {
		color: var(--c-cancel);
	}
</style>
