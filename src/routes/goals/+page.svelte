<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { useEntries } from '$lib/stores/entries.svelte';
	import { ui } from '$lib/db';
	import { useLocale } from '$lib/stores/locale.svelte';
	import { onMount } from 'svelte';

	const { t } = useLocale();

	interface Goal {
		id: string;
		label: string;
		type: 'daily' | 'weekly';
		metric: string;
		target: number;
		unit: string;
	}

	const TEMPLATES = $derived.by(() => [
		{ id: '', label: t.goals.sleep8, type: 'daily' as const, metric: 'signal.sleep.hours', target: 8, unit: 'h' },
		{ id: '', label: t.goals.drink3l, type: 'daily' as const, metric: 'intake.water', target: 3, unit: 'L' },
		{ id: '', label: t.goals.workout4x, type: 'weekly' as const, metric: 'training.count', target: 4, unit: 'sessions' },
		{ id: '', label: t.goals.meditatDaily, type: 'daily' as const, metric: 'habit.meditation', target: 1, unit: 'sessions' },
		{ id: '', label: t.goals.checkinDaily, type: 'daily' as const, metric: 'checkin.count', target: 1, unit: '' },
	]);

	const store = useEntries();

	let goals: Goal[] = $state([]);
	let showForm = $state(false);
	let selectedTemplate = $state('custom');
	let customLabel = $state('');
	let customType: 'daily' | 'weekly' = $state('daily');
	let customMetric = $state('');
	let customTarget = $state(1);
	let customUnit = $state('');

	function generateId(): string {
		return Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
	}

	onMount(() => {
		const saved = ui.get();
		if (Array.isArray(saved.goals)) {
			goals = saved.goals as Goal[];
		}
	});

	function saveGoals(): void {
		ui.patch({ goals });
	}

	function addGoal(): void {
		let goal: Goal;
		if (selectedTemplate === 'custom') {
			if (!customLabel.trim() || !customMetric.trim() || customTarget <= 0) return;
			goal = {
				id: generateId(),
				label: customLabel.trim(),
				type: customType,
				metric: customMetric.trim(),
				target: customTarget,
				unit: customUnit.trim()
			};
		} else {
			const tpl = TEMPLATES[Number(selectedTemplate)];
			goal = { ...tpl, id: generateId() };
		}
		goals = [...goals, goal];
		saveGoals();
		resetForm();
	}

	function removeGoal(id: string): void {
		goals = goals.filter(g => g.id !== id);
		saveGoals();
	}

	function resetForm(): void {
		showForm = false;
		selectedTemplate = 'custom';
		customLabel = '';
		customType = 'daily';
		customMetric = '';
		customTarget = 1;
		customUnit = '';
	}

	function onTemplateChange(): void {
		if (selectedTemplate !== 'custom') {
			const tpl = TEMPLATES[Number(selectedTemplate)];
			customLabel = tpl.label;
			customType = tpl.type;
			customMetric = tpl.metric;
			customTarget = tpl.target;
			customUnit = tpl.unit;
		} else {
			customLabel = '';
			customType = 'daily';
			customMetric = '';
			customTarget = 1;
			customUnit = '';
		}
	}

	/* --- Date helpers --- */
	function todayStr(): string {
		return new Date().toISOString().slice(0, 10);
	}

	function weekStart(): string {
		const d = new Date();
		const day = d.getDay();
		const diff = day === 0 ? 6 : day - 1;
		d.setDate(d.getDate() - diff);
		return d.toISOString().slice(0, 10);
	}

	/* --- Compute progress for a goal --- */
	function getProgress(goal: Goal, allItems: typeof store.items): number {
		const today = todayStr();
		const wStart = weekStart();
		const isDaily = goal.type === 'daily';
		const startDate = isDaily ? today : wStart;

		const filtered = allItems.filter(e => {
			const eDate = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			return eDate >= startDate;
		});

		switch (goal.metric) {
			case 'signal.sleep.hours': {
				const sleeps = filtered.filter(e => e.type === 'signal.sleep');
				if (sleeps.length === 0) return 0;
				const total = sleeps.reduce((s, e) => s + Number(e.data.hours || 0), 0);
				return +(total / sleeps.length).toFixed(1);
			}
			case 'intake.water': {
				const waters = filtered.filter(e =>
					e.type === 'intake' &&
					typeof e.data.what === 'string' &&
					(e.data.what.toLowerCase().includes('water') || e.data.what.toLowerCase().includes('agua'))
				);
				return waters.length;
			}
			case 'training.count': {
				return filtered.filter(e => e.type.startsWith('training.')).length;
			}
			case 'habit.meditation': {
				return filtered.filter(e => e.type === 'habit' && e.data.habit === 'meditation').length;
			}
			case 'checkin.count': {
				return filtered.filter(e => e.type === 'checkin').length;
			}
			default: {
				return filtered.filter(e => e.type === goal.metric).length;
			}
		}
	}

	/* --- Derived goal progress --- */
	const goalProgress = $derived.by(() => {
		const items = store.items;
		return goals.map(g => {
			const current = getProgress(g, items);
			const pct = g.target > 0 ? Math.min(Math.round((current / g.target) * 100), 100) : 0;
			return { goal: g, current, pct, done: current >= g.target };
		});
	});

	const dailyGoals = $derived(goalProgress.filter(gp => gp.goal.type === 'daily'));
	const weeklyGoals = $derived(goalProgress.filter(gp => gp.goal.type === 'weekly'));
	const dailyHit = $derived(dailyGoals.filter(gp => gp.done).length);
	const weeklyHit = $derived(weeklyGoals.filter(gp => gp.done).length);

	/* --- Helper: check if all daily goals met for a given date string --- */
	function allDailyGoalsMet(dayStr: string, dailyG: Goal[], items: typeof store.items): boolean {
		for (const g of dailyG) {
			const dayItems = items.filter(e => {
				const eDate = (e.data.date as string) ?? e.createdAt.slice(0, 10);
				return eDate === dayStr;
			});
			if (getProgressForDay(g, dayItems) < g.target) return false;
		}
		return true;
	}

	/* --- Streak: consecutive days with all daily goals met --- */
	const streak = $derived.by(() => {
		if (goals.filter(g => g.type === 'daily').length === 0) return 0;
		const items = store.items;
		const dailyG = goals.filter(g => g.type === 'daily');
		let count = 0;
		const now = new Date();

		for (let i = 0; i < 365; i++) {
			const d = new Date(now);
			d.setDate(d.getDate() - i);
			const dayStr = d.toISOString().slice(0, 10);
			if (allDailyGoalsMet(dayStr, dailyG, items)) {
				count++;
			} else {
				break;
			}
		}
		return count;
	});

	/* --- Best streak: longest consecutive run with all daily goals met --- */
	const bestStreak = $derived.by(() => {
		const dailyG = goals.filter(g => g.type === 'daily');
		if (dailyG.length === 0) return { days: 0, from: '', to: '' };
		const items = store.items;
		const now = new Date();
		let best = 0;
		let bestFrom = '';
		let bestTo = '';
		let current = 0;
		let currentFrom = '';

		for (let i = 364; i >= 0; i--) {
			const d = new Date(now);
			d.setDate(d.getDate() - i);
			const dayStr = d.toISOString().slice(0, 10);
			if (allDailyGoalsMet(dayStr, dailyG, items)) {
				if (current === 0) currentFrom = dayStr;
				current++;
				if (current > best) {
					best = current;
					bestFrom = currentFrom;
					bestTo = dayStr;
				}
			} else {
				current = 0;
			}
		}
		return { days: best, from: bestFrom, to: bestTo };
	});

	/* --- 30-day calendar: which days all daily goals were met --- */
	const calendarDays = $derived.by(() => {
		const dailyG = goals.filter(g => g.type === 'daily');
		if (dailyG.length === 0) return [];
		const items = store.items;
		const today = todayStr();
		const result: { date: string; day: number; met: boolean; isToday: boolean }[] = [];
		const now = new Date();
		for (let i = 29; i >= 0; i--) {
			const d = new Date(now);
			d.setDate(d.getDate() - i);
			const dayStr = d.toISOString().slice(0, 10);
			result.push({
				date: dayStr,
				day: d.getDate(),
				met: allDailyGoalsMet(dayStr, dailyG, items),
				isToday: dayStr === today
			});
		}
		return result;
	});

	/* --- Weekly completion rates: last 4 weeks --- */
	const weeklyRates = $derived.by(() => {
		const dailyG = goals.filter(g => g.type === 'daily');
		if (dailyG.length === 0) return [];
		const items = store.items;
		const now = new Date();
		const dayOfWeek = now.getDay();
		const mondayOffset = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
		const labels = [t.goals.thisWeek, t.goals.lastWeek, t.goals.weeksAgo2, t.goals.weeksAgo3];
		const weeks: { label: string; pct: number; met: number; total: number }[] = [];

		for (let w = 0; w < 4; w++) {
			let met = 0;
			const weekStartOffset = mondayOffset + w * 7;
			const daysInWeek = w === 0 ? mondayOffset + 1 : 7;
			for (let d = 0; d < daysInWeek; d++) {
				const dt = new Date(now);
				dt.setDate(dt.getDate() - weekStartOffset + d);
				const dayStr = dt.toISOString().slice(0, 10);
				if (allDailyGoalsMet(dayStr, dailyG, items)) met++;
			}
			weeks.push({
				label: labels[w],
				pct: daysInWeek > 0 ? Math.round((met / 7) * 100) : 0,
				met,
				total: 7
			});
		}
		return weeks;
	});

	/* --- Sparkline: last 14 days of individual goal completion --- */
	function getGoalSparkline(goal: Goal, items: typeof store.items): { date: string; met: boolean }[] {
		const now = new Date();
		const result: { date: string; met: boolean }[] = [];
		for (let i = 13; i >= 0; i--) {
			const d = new Date(now);
			d.setDate(d.getDate() - i);
			const dayStr = d.toISOString().slice(0, 10);
			const dayItems = items.filter(e => {
				const eDate = (e.data.date as string) ?? e.createdAt.slice(0, 10);
				return eDate === dayStr;
			});
			result.push({ date: dayStr, met: getProgressForDay(goal, dayItems) >= goal.target });
		}
		return result;
	}

	function getProgressForDay(goal: Goal, dayItems: typeof store.items): number {
		switch (goal.metric) {
			case 'signal.sleep.hours': {
				const sleeps = dayItems.filter(e => e.type === 'signal.sleep');
				if (sleeps.length === 0) return 0;
				const total = sleeps.reduce((s, e) => s + Number(e.data.hours || 0), 0);
				return +(total / sleeps.length).toFixed(1);
			}
			case 'intake.water': {
				return dayItems.filter(e =>
					e.type === 'intake' &&
					typeof e.data.what === 'string' &&
					(e.data.what.toLowerCase().includes('water') || e.data.what.toLowerCase().includes('agua'))
				).length;
			}
			case 'training.count': {
				return dayItems.filter(e => e.type.startsWith('training.')).length;
			}
			case 'habit.meditation': {
				return dayItems.filter(e => e.type === 'habit' && e.data.habit === 'meditation').length;
			}
			case 'checkin.count': {
				return dayItems.filter(e => e.type === 'checkin').length;
			}
			default: {
				return dayItems.filter(e => e.type === goal.metric).length;
			}
		}
	}
</script>

<svelte:head>
  <title>{t.goals.title} | Darink</title>
</svelte:head>

<PageHeader title={t.goals.title} />

<!-- Summary cards -->
{#if goals.length > 0}
<section class="summary">
	<div class="metrics-row">
		{#if dailyGoals.length > 0}
		<div class="metric-card">
			<span class="metric-value">{dailyHit}/{dailyGoals.length}</span>
			<span class="metric-label">{t.goals.dailyGoals}</span>
		</div>
		{/if}
		{#if weeklyGoals.length > 0}
		<div class="metric-card">
			<span class="metric-value">{weeklyHit}/{weeklyGoals.length}</span>
			<span class="metric-label">{t.goals.weeklyGoals}</span>
		</div>
		{/if}
		{#if dailyGoals.length > 0}
		<div class="metric-card">
			<span class="metric-value">{streak}</span>
			<span class="metric-label">{t.goals.dayStreak}</span>
		</div>
		{/if}
	</div>
</section>
{/if}

<!-- 30-Day Completion Calendar -->
{#if calendarDays.length > 0}
<section class="calendar-section">
	<h2>{t.goals.last30days}</h2>
	<div class="calendar-grid">
		{#each calendarDays as cell}
			<div
				class="calendar-cell"
				class:met={cell.met}
				class:today={cell.isToday}
				title={cell.date}
			>
				{cell.day}
			</div>
		{/each}
	</div>
</section>
{/if}

<!-- Weekly Completion Rate -->
{#if weeklyRates.length > 0}
<section class="weekly-section">
	<h2>{t.goals.weeklyCompletion}</h2>
	<div class="weekly-bars">
		{#each weeklyRates as week}
			{@const color = week.pct >= 80 ? '#22c55e' : week.pct >= 50 ? '#eab308' : '#ef4444'}
			<div class="weekly-row">
				<span class="weekly-label">{week.label}</span>
				<div class="weekly-bar-track">
					<div class="weekly-bar-fill" style="width: {week.pct}%; background: {color}"></div>
				</div>
				<span class="weekly-pct" style="color: {color}">{week.pct}%</span>
			</div>
		{/each}
	</div>
</section>
{/if}

<!-- Best Streak -->
{#if bestStreak.days > 0}
<section class="best-streak-section">
	<div class="best-streak-card">
		<div class="best-streak-icon">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
		</div>
		<div class="best-streak-info">
			<span class="best-streak-value">{t.goals.bestStreak}: {bestStreak.days} {t.common.days}</span>
			<span class="best-streak-range">{bestStreak.from} &ndash; {bestStreak.to}</span>
		</div>
	</div>
</section>
{/if}

<!-- Goal cards -->
{#if goalProgress.length > 0}
<section class="goal-list">
	{#each goalProgress as gp}
		{@const barWidth = `${gp.pct}%`}
		{@const sparkline = gp.goal.type === 'daily' ? getGoalSparkline(gp.goal, store.items) : null}
		<div class="goal-card" class:done={gp.done}>
			<div class="goal-header">
				<div class="goal-info">
					<span class="goal-label">{gp.goal.label}</span>
					<span class="goal-type-badge">{gp.goal.type}</span>
				</div>
				<button class="remove-btn" onclick={() => removeGoal(gp.goal.id)} aria-label={t.goals.removeGoal}>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
				</button>
			</div>
			<div class="goal-progress-row">
				<span class="goal-progress-text">
					{gp.current}{gp.goal.unit ? ' ' + gp.goal.unit : ''} / {gp.goal.target}{gp.goal.unit ? ' ' + gp.goal.unit : ''}
				</span>
				<span class="goal-pct">{gp.pct}%</span>
			</div>
			<div class="progress-bar-track">
				<div class="progress-bar-fill" class:complete={gp.done} style="width: {barWidth}"></div>
			</div>
			{#if sparkline}
			<div class="sparkline-row">
				{#each sparkline as dot}
					<span class="spark-dot" class:spark-met={dot.met} title={dot.date}></span>
				{/each}
			</div>
			{/if}
			{#if gp.done}
			<div class="done-badge">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
				<span>{t.goals.completed}</span>
			</div>
			{/if}
		</div>
	{/each}
</section>
{:else}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
	<p>{t.goals.noGoalsYet}</p>
	<p class="empty-hint">{t.goals.noGoalsHint}</p>
</div>
{/if}

<!-- Add goal button / form -->
{#if !showForm}
<section class="add-section">
	<button class="primary" onclick={() => { showForm = true; }}>{t.goals.addGoal}</button>
</section>
{:else}
<section class="form">
	<h2>{t.goals.newGoal}</h2>
	<label>
		{t.goals.template}
		<select bind:value={selectedTemplate} onchange={onTemplateChange}>
			{#each TEMPLATES as tpl, i}
				<option value={String(i)}>{tpl.label}</option>
			{/each}
			<option value="custom">{t.goals.customGoal}</option>
		</select>
	</label>

	{#if selectedTemplate === 'custom'}
	<label>{t.goals.label} <input type="text" bind:value={customLabel} placeholder={t.goals.goalName} /></label>
	<div class="row">
		<label>
			{t.goals.type}
			<select bind:value={customType}>
				<option value="daily">{t.common.daily}</option>
				<option value="weekly">{t.common.weekly}</option>
			</select>
		</label>
		<label>{t.goals.metric} <input type="text" bind:value={customMetric} placeholder={t.goals.entryType} /></label>
	</div>
	<div class="row">
		<label>{t.goals.targetLabel} <input type="number" min="1" step="1" bind:value={customTarget} /></label>
		<label>{t.goals.unitLabel} <input type="text" bind:value={customUnit} placeholder="h, L, sessions..." /></label>
	</div>
	{/if}

	<div class="form-actions">
		<button class="primary" onclick={addGoal}>{t.common.add}</button>
		<button onclick={resetForm}>{t.common.cancel}</button>
	</div>
</section>
{/if}

<style>
	h2 {
		font-size: 0.9rem;
		font-weight: 600;
		margin-bottom: 0.5rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}

	.summary {
		padding: 0 1rem 1rem;
	}

	.metrics-row {
		display: flex;
		gap: 0.5rem;
		flex-wrap: wrap;
	}

	.metric-card {
		flex: 1;
		min-width: 80px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem;
		text-align: center;
		display: flex;
		flex-direction: column;
		gap: 0.15rem;
	}

	.metric-value {
		font-size: 1.4rem;
		font-weight: 700;
	}

	.metric-label {
		font-size: 0.75rem;
		font-weight: 600;
		color: var(--c-text-muted);
		text-transform: uppercase;
	}

	/* 30-day calendar */
	.calendar-section {
		padding: 0 1rem 1rem;
	}

	.calendar-grid {
		display: grid;
		grid-template-columns: repeat(6, 1fr);
		gap: 4px;
	}

	.calendar-cell {
		aspect-ratio: 1;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 0.72rem;
		font-weight: 600;
		border-radius: 6px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		color: var(--c-text-muted);
	}

	.calendar-cell.met {
		background: #22c55e;
		border-color: #22c55e;
		color: #fff;
	}

	.calendar-cell.today {
		box-shadow: inset 0 0 0 2px var(--c-accent);
	}

	/* Weekly completion */
	.weekly-section {
		padding: 0 1rem 1rem;
	}

	.weekly-bars {
		display: flex;
		flex-direction: column;
		gap: 0.4rem;
	}

	.weekly-row {
		display: flex;
		align-items: center;
		gap: 0.5rem;
	}

	.weekly-label {
		font-size: 0.75rem;
		font-weight: 500;
		color: var(--c-text-muted);
		min-width: 80px;
		flex-shrink: 0;
	}

	.weekly-bar-track {
		flex: 1;
		height: 8px;
		background: var(--c-border);
		border-radius: 4px;
		overflow: hidden;
	}

	.weekly-bar-fill {
		height: 100%;
		border-radius: 4px;
		transition: width 0.3s ease;
	}

	.weekly-pct {
		font-size: 0.75rem;
		font-weight: 700;
		min-width: 32px;
		text-align: right;
	}

	/* Best streak */
	.best-streak-section {
		padding: 0 1rem 1rem;
	}

	.best-streak-card {
		display: flex;
		align-items: center;
		gap: 0.75rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem 1rem;
	}

	.best-streak-icon {
		color: #f97316;
		display: flex;
		flex-shrink: 0;
	}

	.best-streak-info {
		display: flex;
		flex-direction: column;
		gap: 0.1rem;
	}

	.best-streak-value {
		font-size: 0.9rem;
		font-weight: 700;
	}

	.best-streak-range {
		font-size: 0.75rem;
		color: var(--c-text-muted);
	}

	/* Sparkline dots */
	.sparkline-row {
		display: flex;
		align-items: center;
		gap: 3px;
		margin-top: 0.5rem;
		padding-top: 0.4rem;
		border-top: 1px solid var(--c-border);
	}

	.spark-dot {
		width: 8px;
		height: 8px;
		border-radius: 50%;
		background: transparent;
		border: 1.5px solid var(--c-text-muted);
		flex-shrink: 0;
	}

	.spark-dot.spark-met {
		background: #22c55e;
		border-color: #22c55e;
	}

	/* Goal list */
	.goal-list {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
		padding: 0 1rem;
	}

	.goal-card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem 1rem;
		transition: border-color 0.15s;
	}

	.goal-card.done {
		border-color: #22c55e;
		background: color-mix(in srgb, #22c55e 5%, var(--c-bg-card));
	}

	.goal-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 0.5rem;
		margin-bottom: 0.4rem;
	}

	.goal-info {
		display: flex;
		align-items: center;
		gap: 0.5rem;
	}

	.goal-label {
		font-weight: 600;
		font-size: 0.95rem;
	}

	.goal-type-badge {
		font-size: 0.65rem;
		font-weight: 600;
		text-transform: uppercase;
		color: var(--c-accent);
		background: var(--c-accent-bg);
		padding: 0.1rem 0.4rem;
		border-radius: 8px;
		letter-spacing: 0.03em;
	}

	.remove-btn {
		background: none;
		border: none;
		padding: 0.2rem;
		cursor: pointer;
		color: var(--c-text-muted);
		display: flex;
		align-items: center;
		transition: color 0.15s;
	}

	.remove-btn:hover {
		color: var(--c-cancel, #ef4444);
		background: none;
	}

	.goal-progress-row {
		display: flex;
		justify-content: space-between;
		align-items: center;
		font-size: 0.8rem;
		color: var(--c-text-muted);
		margin-bottom: 0.35rem;
	}

	.goal-progress-text {
		font-weight: 500;
	}

	.goal-pct {
		font-weight: 600;
	}

	/* Progress bar */
	.progress-bar-track {
		width: 100%;
		height: 6px;
		background: var(--c-border);
		border-radius: 3px;
		overflow: hidden;
	}

	.progress-bar-fill {
		height: 100%;
		background: var(--c-accent);
		border-radius: 3px;
		transition: width 0.3s ease;
	}

	.progress-bar-fill.complete {
		background: #22c55e;
	}

	.done-badge {
		display: flex;
		align-items: center;
		gap: 0.3rem;
		margin-top: 0.4rem;
		font-size: 0.75rem;
		font-weight: 600;
		color: #22c55e;
	}

	/* Form */
	.form {
		display: flex;
		flex-direction: column;
		gap: 1rem;
		padding: 1rem 1rem 0;
	}

	.row {
		display: flex;
		gap: 0.75rem;
		flex-wrap: wrap;
	}

	.row label {
		flex: 1;
		min-width: 120px;
	}

	.form-actions {
		display: flex;
		gap: 0.5rem;
	}

	.form-actions button {
		flex: 1;
	}

	.add-section {
		padding: 1rem;
	}

	/* Empty state */
	.empty-state {
		text-align: center;
		padding: 2rem 1rem;
		color: var(--c-text-muted);
	}

	.empty-hint {
		font-size: 0.85rem;
		margin-top: 0.25rem;
	}
</style>
