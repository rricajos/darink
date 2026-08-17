<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { useEntries } from '$lib/stores/entries.svelte';
	import { ui } from '$lib/db';
	import { onMount } from 'svelte';

	interface Goal {
		id: string;
		label: string;
		type: 'daily' | 'weekly';
		metric: string;
		target: number;
		unit: string;
	}

	const TEMPLATES: Goal[] = [
		{ id: '', label: 'Sleep 8 hours', type: 'daily', metric: 'signal.sleep.hours', target: 8, unit: 'h' },
		{ id: '', label: 'Drink 3L water', type: 'daily', metric: 'intake.water', target: 3, unit: 'L' },
		{ id: '', label: 'Workout 4x/week', type: 'weekly', metric: 'training.count', target: 4, unit: 'sessions' },
		{ id: '', label: 'Meditate daily', type: 'daily', metric: 'habit.meditation', target: 1, unit: 'sessions' },
		{ id: '', label: 'Check-in daily', type: 'daily', metric: 'checkin.count', target: 1, unit: '' },
	];

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

			let allMet = true;
			for (const g of dailyG) {
				const dayItems = items.filter(e => {
					const eDate = (e.data.date as string) ?? e.createdAt.slice(0, 10);
					return eDate === dayStr;
				});
				const val = getProgressForDay(g, dayItems);
				if (val < g.target) {
					allMet = false;
					break;
				}
			}
			if (allMet) {
				count++;
			} else {
				break;
			}
		}
		return count;
	});

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

<PageHeader title="Goals" />

<!-- Summary cards -->
{#if goals.length > 0}
<section class="summary">
	<div class="metrics-row">
		{#if dailyGoals.length > 0}
		<div class="metric-card">
			<span class="metric-value">{dailyHit}/{dailyGoals.length}</span>
			<span class="metric-label">Daily goals</span>
		</div>
		{/if}
		{#if weeklyGoals.length > 0}
		<div class="metric-card">
			<span class="metric-value">{weeklyHit}/{weeklyGoals.length}</span>
			<span class="metric-label">Weekly goals</span>
		</div>
		{/if}
		{#if dailyGoals.length > 0}
		<div class="metric-card">
			<span class="metric-value">{streak}</span>
			<span class="metric-label">Day streak</span>
		</div>
		{/if}
	</div>
</section>
{/if}

<!-- Goal cards -->
{#if goalProgress.length > 0}
<section class="goal-list">
	{#each goalProgress as gp}
		{@const barWidth = `${gp.pct}%`}
		<div class="goal-card" class:done={gp.done}>
			<div class="goal-header">
				<div class="goal-info">
					<span class="goal-label">{gp.goal.label}</span>
					<span class="goal-type-badge">{gp.goal.type}</span>
				</div>
				<button class="remove-btn" onclick={() => removeGoal(gp.goal.id)} aria-label="Remove goal">
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
			{#if gp.done}
			<div class="done-badge">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
				<span>Completed</span>
			</div>
			{/if}
		</div>
	{/each}
</section>
{:else}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
	<p>No goals set yet</p>
	<p class="empty-hint">Set daily or weekly targets to stay on track with your health habits.</p>
</div>
{/if}

<!-- Add goal button / form -->
{#if !showForm}
<section class="add-section">
	<button class="primary" onclick={() => { showForm = true; }}>Add goal</button>
</section>
{:else}
<section class="form">
	<h2>New goal</h2>
	<label>
		Template
		<select bind:value={selectedTemplate} onchange={onTemplateChange}>
			{#each TEMPLATES as tpl, i}
				<option value={String(i)}>{tpl.label}</option>
			{/each}
			<option value="custom">Custom</option>
		</select>
	</label>

	{#if selectedTemplate === 'custom'}
	<label>Label <input type="text" bind:value={customLabel} placeholder="Goal name" /></label>
	<div class="row">
		<label>
			Type
			<select bind:value={customType}>
				<option value="daily">Daily</option>
				<option value="weekly">Weekly</option>
			</select>
		</label>
		<label>Metric <input type="text" bind:value={customMetric} placeholder="entry type" /></label>
	</div>
	<div class="row">
		<label>Target <input type="number" min="1" step="1" bind:value={customTarget} /></label>
		<label>Unit <input type="text" bind:value={customUnit} placeholder="h, L, sessions..." /></label>
	</div>
	{/if}

	<div class="form-actions">
		<button class="primary" onclick={addGoal}>Add</button>
		<button onclick={resetForm}>Cancel</button>
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
