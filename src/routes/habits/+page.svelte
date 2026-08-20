<script lang="ts">
	import { onMount } from 'svelte';
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { ui } from '$lib/db';
	import type { Entry } from '$lib/db';
	import { useLocale } from '$lib/stores/locale.svelte';

	const { t } = useLocale();
	const store = useEntries('habit');
	const allStore = useEntries();

	const defaultHabits = $derived.by(() => [
		{ id: 'cold', label: t.habits.cold, unit: 'min' },
		{ id: 'sun', label: t.habits.sun, unit: 'min' },
		{ id: 'fasting', label: t.habits.fasting, unit: 'hours' },
		{ id: 'meditation', label: t.habits.meditation, unit: 'min' },
		{ id: 'wimhof', label: t.habits.wimhof, unit: 'rounds' },
		{ id: 'ejaculation', label: t.habits.ejaculation, unit: 'days' }
	]);

	let customHabits = $state<Array<{id: string, label: string, unit: string}>>([]);
	const allHabitTypes = $derived([...defaultHabits, ...customHabits]);

	onMount(() => {
		const stored = ui.get().customHabits;
		if (Array.isArray(stored)) {
			customHabits = stored as Array<{id: string, label: string, unit: string}>;
		}
	});

	let date = $state(new Date().toISOString().slice(0, 10));
	let selectedHabit = $state('cold');
	let duration = $state(0);
	let notes = $state('');

	let manageOpen = $state(false);
	let newLabel = $state('');
	let newUnit = $state('');

	const todayItems = $derived.by(() => {
		const today = new Date().toISOString().slice(0, 10);
		return store.items.filter((e) => e.createdAt.startsWith(today));
	});

	const selectedUnit = $derived(allHabitTypes.find((h) => h.id === selectedHabit)?.unit ?? '');

	const streaks = $derived.by(() => {
		const result: Record<string, { current: number; best: number; total: number }> = {};
		for (const h of allHabitTypes) {
			const dates = new Set(
				store.items
					.filter((e) => e.data.habit === h.id)
					.map((e) => (e.data.date as string) ?? e.createdAt.slice(0, 10))
			);
			const total = dates.size;
			let current = 0;
			let best = 0;
			let streak = 0;
			const today = new Date();
			for (let i = 0; i < 365; i++) {
				const d = new Date(today);
				d.setDate(d.getDate() - i);
				const key = d.toISOString().slice(0, 10);
				if (dates.has(key)) {
					streak++;
					if (i === current) current = streak;
					if (streak > best) best = streak;
				} else {
					streak = 0;
					if (i === 0) current = 0;
				}
			}
			if (total > 0) result[h.id] = { current, best, total };
		}
		return result;
	});

	// --- Analytics: 30-Day Heatmap ---
	const heatmapData = $derived.by(() => {
		const today = new Date();
		const days: Array<{ date: string; dayNum: number; month: string; count: number }> = [];
		for (let i = 29; i >= 0; i--) {
			const d = new Date(today);
			d.setDate(d.getDate() - i);
			const key = d.toISOString().slice(0, 10);
			const monthLabel = d.toLocaleString('en', { month: 'short' });
			const uniqueHabits = new Set(
				store.items
					.filter((e) => ((e.data.date as string) ?? e.createdAt.slice(0, 10)) === key)
					.map((e) => e.data.habit as string)
			);
			days.push({ date: key, dayNum: d.getDate(), month: monthLabel, count: uniqueHabits.size });
		}
		return days;
	});

	// --- Analytics: Weekly Completion Rates ---
	const weeklyRates = $derived.by(() => {
		const today = new Date();
		const habitCount = allHabitTypes.length || 1;
		const weeks: Array<{ label: string; completions: number; possible: number; rate: number }> = [];
		for (let w = 3; w >= 0; w--) {
			const weekStart = new Date(today);
			weekStart.setDate(weekStart.getDate() - ((w + 1) * 7 - 1));
			const weekEnd = new Date(today);
			weekEnd.setDate(weekEnd.getDate() - (w * 7));
			const label = weekStart.toLocaleString('en', { month: 'short' }) + ' ' + weekStart.getDate();
			let completions = 0;
			for (let d = 0; d < 7; d++) {
				const day = new Date(weekStart);
				day.setDate(day.getDate() + d);
				const key = day.toISOString().slice(0, 10);
				const unique = new Set(
					store.items
						.filter((e) => ((e.data.date as string) ?? e.createdAt.slice(0, 10)) === key)
						.map((e) => e.data.habit as string)
				);
				completions += unique.size;
			}
			const possible = 7 * habitCount;
			weeks.push({ label, completions, possible, rate: possible > 0 ? completions / possible : 0 });
		}
		return weeks;
	});

	// --- Analytics: Habit-Mood Correlation ---
	const habitMoodCorrelation = $derived.by(() => {
		const checkins = allStore.items.filter((e) => e.type === 'checkin');
		if (checkins.length === 0) return [];

		const moodByDate: Record<string, number> = {};
		for (const c of checkins) {
			const d = (c.data.date as string) ?? c.createdAt.slice(0, 10);
			const mood = Number(c.data.mood ?? c.data.value ?? 0);
			if (mood > 0) moodByDate[d] = mood;
		}

		const allDatesWithMood = Object.keys(moodByDate);
		if (allDatesWithMood.length === 0) return [];

		const result: Array<{ id: string; label: string; doneMood: number; skipMood: number; doneCount: number; skipCount: number }> = [];
		for (const h of allHabitTypes) {
			const habitDates = new Set(
				store.items
					.filter((e) => e.data.habit === h.id)
					.map((e) => (e.data.date as string) ?? e.createdAt.slice(0, 10))
			);

			let doneMoodSum = 0, doneMoodCount = 0;
			let skipMoodSum = 0, skipMoodCount = 0;

			for (const d of allDatesWithMood) {
				if (habitDates.has(d)) {
					doneMoodSum += moodByDate[d];
					doneMoodCount++;
				} else {
					skipMoodSum += moodByDate[d];
					skipMoodCount++;
				}
			}

			if (doneMoodCount > 0 || skipMoodCount > 0) {
				result.push({
					id: h.id,
					label: h.label,
					doneMood: doneMoodCount > 0 ? doneMoodSum / doneMoodCount : 0,
					skipMood: skipMoodCount > 0 ? skipMoodSum / skipMoodCount : 0,
					doneCount: doneMoodCount,
					skipCount: skipMoodCount
				});
			}
		}
		return result;
	});

	// --- Analytics: Habit Frequency Distribution (donut) ---
	const frequencyData = $derived.by(() => {
		const counts: Record<string, number> = {};
		let total = 0;
		for (const e of store.items) {
			const hid = e.data.habit as string;
			counts[hid] = (counts[hid] ?? 0) + 1;
			total++;
		}
		const donutColors = [
			'var(--c-accent)', '#e07c4f', '#50b896', '#c25ddb', '#e0c24f',
			'#5d8edb', '#db5d6f', '#5ddbb8', '#9b5ddb', '#dba55d'
		];
		const segments: Array<{ id: string; label: string; count: number; fraction: number; color: string }> = [];
		let idx = 0;
		for (const h of allHabitTypes) {
			const c = counts[h.id] ?? 0;
			if (c > 0) {
				segments.push({
					id: h.id,
					label: h.label,
					count: c,
					fraction: total > 0 ? c / total : 0,
					color: donutColors[idx % donutColors.length]
				});
				idx++;
			}
		}
		return { segments, total };
	});

	const defaultIds = $derived(new Set(defaultHabits.map((h) => h.id)));

	function submit() {
		entries.add('habit', { date, habit: selectedHabit, duration, notes });
		date = new Date().toISOString().slice(0, 10);
		duration = 0; notes = '';
		toast.show(t.habits.habitLogged);
	}

	function addCustomHabit() {
		const label = newLabel.trim();
		const unit = newUnit.trim();
		if (!label || !unit) {
			toast.show(t.habits.labelUnitRequired);
			return;
		}
		const id = label.toLowerCase().replace(/\s+/g, '');
		if (allHabitTypes.some((h) => h.id === id)) {
			toast.show(t.habits.nameExists);
			return;
		}
		customHabits = [...customHabits, { id, label, unit }];
		ui.patch({ customHabits });
		newLabel = '';
		newUnit = '';
		toast.show(t.habits.typeAdded);
	}

	function removeCustomHabit(id: string) {
		customHabits = customHabits.filter((h) => h.id !== id);
		ui.patch({ customHabits });
		if (selectedHabit === id) selectedHabit = 'cold';
		toast.show(t.habits.typeRemoved);
	}

	function getLabel(id: string): string {
		return allHabitTypes.find((h) => h.id === id)?.label ?? id;
	}

	function getUnit(id: string): string {
		return allHabitTypes.find((h) => h.id === id)?.unit ?? '';
	}
</script>

<svelte:head>
  <title>{t.habits.title} | Darink</title>
</svelte:head>

<PageHeader title={t.habits.title} />

<section class="form">
	<label>{t.common.date} <input type="date" bind:value={date} /></label>
	<label>
		{t.habits.habit}
		<select bind:value={selectedHabit}>
			{#each allHabitTypes as h}
				<option value={h.id}>{h.label}</option>
			{/each}
		</select>
	</label>
	<label>{t.habits.durationLabel} ({selectedUnit}) <input type="number" min="0" step="1" bind:value={duration} /></label>
	<label>{t.common.notes} <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>{t.habits.logHabit}</button>
</section>

<section class="manage-section">
	<button class="manage-toggle" onclick={() => manageOpen = !manageOpen}>
		<span>{t.habits.manageTypes}</span>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class:rotate={manageOpen}><path d="m6 9 6 6 6-6"/></svg>
	</button>
	{#if manageOpen}
		<div class="manage-body">
			<div class="manage-add">
				<label>{t.habits.labelField} <input type="text" bind:value={newLabel} placeholder="e.g. Stretching" /></label>
				<label>{t.habits.unitField} <input type="text" bind:value={newUnit} placeholder="e.g. min" /></label>
				<button class="primary" onclick={addCustomHabit}>{t.habits.addType}</button>
			</div>
			<ul class="habit-type-list">
				{#each allHabitTypes as h}
					<li class="habit-type-item">
						<span><strong>{h.label}</strong> <span class="unit-tag">({h.unit})</span></span>
						{#if !defaultIds.has(h.id)}
							<button class="remove-btn" onclick={() => removeCustomHabit(h.id)} title={t.common.remove}>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
							</button>
						{/if}
					</li>
				{/each}
			</ul>
		</div>
	{/if}
</section>

{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
	<p>{t.habits.noHabits}</p>
	<p class="empty-hint">{t.habits.noHabitsHint}</p>
</div>
{/if}

{#if todayItems.length > 0}
	<section class="today">
		<h2>{t.habits.today}</h2>
		<div class="chips">
			{#each todayItems as item}
				<span class="chip">{getLabel(item.data.habit as string)} · {item.data.duration}{getUnit(item.data.habit as string)}</span>
			{/each}
		</div>
	</section>
{/if}

{#if Object.keys(streaks).length > 0}
	<section class="streaks">
		<h2>{t.habits.streaks}</h2>
		<div class="streak-grid">
			{#each Object.entries(streaks) as [id, s]}
				<div class="streak-card" class:active={s.current > 0}>
					<strong>{getLabel(id)}</strong>
					<div class="streak-nums">
						<span class="streak-current">{s.current}d</span>
						<span class="streak-meta">{t.habits.best} {s.best}d · {s.total} {t.common.total.toLowerCase()}</span>
					</div>
				</div>
			{/each}
		</div>
	</section>
{/if}

<!-- Analytics: 30-Day Completion Heatmap -->
{#if store.items.length > 0}
	<section class="analytics-section">
		<h3>{t.habits.heatmap30}</h3>
		<div class="heatmap-grid">
			{#each heatmapData as cell}
				<div
					class="heatmap-cell"
					class:heatmap-0={cell.count === 0}
					class:heatmap-low={cell.count >= 1 && cell.count <= 2}
					class:heatmap-mid={cell.count >= 3 && cell.count <= 4}
					class:heatmap-high={cell.count >= 5}
					title="{cell.date}: {cell.count} {t.habits.nHabits}"
				>
					<span class="heatmap-day">{cell.dayNum}</span>
				</div>
			{/each}
		</div>
	</section>
{/if}

<!-- Analytics: Weekly Completion Rates -->
{#if store.items.length > 0}
	<section class="analytics-section">
		<h3>{t.habits.weeklyCompletion}</h3>
		<svg class="weekly-chart" width="100%" height="140" viewBox="0 0 400 140">
			{#each weeklyRates as week, i}
				{@const barMaxWidth = 260}
				{@const maxCompletions = Math.max(...weeklyRates.map(w => w.completions), 1)}
				{@const barWidth = (week.completions / maxCompletions) * barMaxWidth}
				{@const barColor = week.rate > 0.7 ? 'var(--c-done, #48bb78)' : week.rate >= 0.4 ? '#e0c24f' : '#e53e3e'}
				{@const y = i * 34 + 4}
				<text x="0" y={y + 18} fill="var(--c-text-muted)" font-size="12" font-family="inherit">{week.label}</text>
				<rect x="70" y={y + 4} width={barWidth} height="20" rx="4" fill={barColor} opacity="0.85" />
				<text x={74 + barWidth} y={y + 18} fill="var(--c-text)" font-size="11" font-family="inherit">{week.completions}</text>
			{/each}
		</svg>
	</section>
{/if}

<!-- Analytics: Habit-Mood Correlation -->
{#if store.items.length > 0 && habitMoodCorrelation.length > 0}
	<section class="analytics-section">
		<h3>{t.habits.habitMoodCorrelation}</h3>
		<div class="mood-correlation-list">
			{#each habitMoodCorrelation as hm}
				<div class="mood-corr-row">
					<span class="mood-corr-label">{hm.label}</span>
					<div class="mood-corr-bars">
						<div class="mood-bar-track">
							<div class="mood-bar done" style="width: {(hm.doneMood / 10) * 100}%"></div>
							<span class="mood-bar-value">{hm.doneMood.toFixed(1)}</span>
						</div>
						<div class="mood-bar-track">
							<div class="mood-bar skip" style="width: {(hm.skipMood / 10) * 100}%"></div>
							<span class="mood-bar-value">{hm.skipMood.toFixed(1)}</span>
						</div>
					</div>
					<div class="mood-legend-inline">
						<span class="legend-done">{t.habits.done} ({hm.doneCount}d)</span>
						<span class="legend-skip">{t.habits.skipped} ({hm.skipCount}d)</span>
					</div>
				</div>
			{/each}
		</div>
	</section>
{/if}

<!-- Analytics: Habit Frequency Distribution -->
{#if store.items.length > 0 && frequencyData.segments.length > 0}
	<section class="analytics-section analytics-donut-section">
		<h3>{t.habits.frequencyDistribution}</h3>
		<div class="donut-container">
			<svg width="160" height="160" viewBox="0 0 160 160">
				{#each frequencyData.segments as seg, i}
					{@const startAngle = frequencyData.segments.slice(0, i).reduce((a, s) => a + s.fraction * 360, 0)}
					{@const endAngle = startAngle + seg.fraction * 360}
					{@const largeArc = seg.fraction > 0.5 ? 1 : 0}
					{@const startRad = ((startAngle - 90) * Math.PI) / 180}
					{@const endRad = ((endAngle - 90) * Math.PI) / 180}
					{@const x1 = 80 + 60 * Math.cos(startRad)}
					{@const y1 = 80 + 60 * Math.sin(startRad)}
					{@const x2 = 80 + 60 * Math.cos(endRad)}
					{@const y2 = 80 + 60 * Math.sin(endRad)}
					{#if seg.fraction >= 1}
						<circle cx="80" cy="80" r="60" fill="none" stroke={seg.color} stroke-width="24" />
					{:else}
						<path
							d="M {x1} {y1} A 60 60 0 {largeArc} 1 {x2} {y2}"
							fill="none"
							stroke={seg.color}
							stroke-width="24"
						/>
					{/if}
				{/each}
				<circle cx="80" cy="80" r="46" fill="var(--c-bg-card)" />
				<text x="80" y="76" text-anchor="middle" fill="var(--c-text)" font-size="22" font-weight="700" font-family="inherit">{frequencyData.total}</text>
				<text x="80" y="94" text-anchor="middle" fill="var(--c-text-muted)" font-size="10" font-family="inherit">{t.common.entries}</text>
			</svg>
			<div class="donut-legend">
				{#each frequencyData.segments as seg}
					<div class="donut-legend-item">
						<span class="donut-swatch" style="background: {seg.color}"></span>
						<span class="donut-legend-label">{seg.label}</span>
						<span class="donut-legend-count">{seg.count}</span>
					</div>
				{/each}
			</div>
		</div>
	</section>
{/if}

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			date: (fd.get('date') as string),
			habit: fd.get('habit') as string,
			duration: Number(fd.get('duration')),
			notes: (fd.get('notes') as string).trim()
		});
		toast.show(t.common.updated);
		done();
	}}>
		<label>{t.common.date} <input type="date" name="date" value={data.date as string ?? ''} /></label>
		<label>
			{t.habits.habit}
			<select name="habit">
				{#each allHabitTypes as h}
					<option value={h.id} selected={data.habit === h.id}>{h.label}</option>
				{/each}
			</select>
		</label>
		<label>{t.habits.durationLabel} <input type="number" name="duration" min="0" step="1" value={data.duration} /></label>
		<label>{t.common.notes} <textarea name="notes" rows="2">{data.notes ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">{t.common.save}</button>
			<button type="button" onclick={done}>{t.common.cancel}</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm} limit={20}>
	{#snippet row(item)}
		<span>
			<strong>{getLabel(item.data.habit as string)}</strong>
			{item.data.duration}{getUnit(item.data.habit as string)}
			<span class="date">{(item.data.date as string) ?? item.createdAt.slice(0, 10)}</span>
		</span>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.today { padding: 1rem; }
	h2 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
	.chips { display: flex; flex-wrap: wrap; gap: 0.25rem; }
	.chip {
		display: inline-block;
		padding: 0.35rem 0.75rem;
		background: var(--c-accent-bg);
		border: 1px solid var(--c-accent);
		border-radius: 20px;
		font-size: 0.85rem;
	}

	.streaks { padding: 1rem; }
	.streak-grid {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 0.5rem;
	}
	.streak-card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem;
	}
	.streak-card.active {
		border-color: var(--c-accent);
		background: var(--c-accent-bg);
	}
	.streak-card strong {
		display: block;
		font-size: 0.8rem;
		margin-bottom: 0.25rem;
	}
	.streak-nums { display: flex; align-items: baseline; gap: 0.5rem; }
	.streak-current { font-size: 1.5rem; font-weight: 700; color: var(--c-accent); }
	.streak-meta { font-size: 0.75rem; color: var(--c-text-muted); }

	.date { font-size: 0.8rem; color: var(--c-text-muted); margin-left: 0.25rem; }

	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }

	.manage-section { padding: 0 1rem; margin-top: 0.5rem; }
	.manage-toggle {
		display: flex;
		align-items: center;
		justify-content: space-between;
		width: 100%;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.6rem 0.75rem;
		font-size: 0.85rem;
		font-weight: 600;
		color: var(--c-text-muted);
		cursor: pointer;
	}
	.manage-toggle svg { transition: transform 0.2s; }
	.manage-toggle svg.rotate { transform: rotate(180deg); }
	.manage-body {
		border: 1px solid var(--c-border);
		border-top: none;
		border-radius: 0 0 var(--radius) var(--radius);
		padding: 0.75rem;
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}
	.manage-add {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}
	.habit-type-list {
		list-style: none;
		margin: 0;
		padding: 0;
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
	}
	.habit-type-item {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0.4rem 0.5rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		font-size: 0.85rem;
	}
	.unit-tag { color: var(--c-text-muted); font-size: 0.8rem; }
	.remove-btn {
		display: flex;
		align-items: center;
		justify-content: center;
		background: none;
		border: none;
		color: var(--c-text-muted);
		cursor: pointer;
		padding: 0.15rem;
		border-radius: 4px;
	}
	.remove-btn:hover { color: var(--c-danger, #e53e3e); background: var(--c-accent-bg); }

	@media (min-width: 600px) {
		.streak-grid { grid-template-columns: repeat(3, 1fr); }
	}

	/* Analytics sections */
	.analytics-section { padding: 1rem; }
	.analytics-section h3 { padding: 0 1rem; font-size: 0.9rem; font-weight: 600; margin: 0 0 0.75rem 0; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

	/* Heatmap */
	.heatmap-grid {
		display: grid;
		grid-template-columns: repeat(6, 1fr);
		gap: 3px;
		max-width: 300px;
	}
	.heatmap-cell {
		aspect-ratio: 1;
		border-radius: 4px;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 0.65rem;
		font-weight: 600;
		position: relative;
	}
	.heatmap-day { position: relative; z-index: 1; }
	.heatmap-0 { background: var(--c-bg-card); color: var(--c-text-muted); border: 1px solid var(--c-border); }
	.heatmap-low { background: color-mix(in srgb, var(--c-accent) 25%, var(--c-bg-card)); color: var(--c-text); border: 1px solid var(--c-accent); }
	.heatmap-mid { background: color-mix(in srgb, var(--c-accent) 55%, var(--c-bg-card)); color: var(--c-text); border: 1px solid var(--c-accent); }
	.heatmap-high { background: var(--c-accent); color: #fff; border: 1px solid var(--c-accent); }

	/* Weekly chart */
	.weekly-chart { display: block; max-width: 100%; }

	/* Mood correlation */
	.mood-correlation-list { display: flex; flex-direction: column; gap: 0.75rem; }
	.mood-corr-row { display: flex; flex-direction: column; gap: 0.25rem; }
	.mood-corr-label { font-size: 0.8rem; font-weight: 600; }
	.mood-corr-bars { display: flex; flex-direction: column; gap: 0.2rem; }
	.mood-bar-track {
		display: flex;
		align-items: center;
		gap: 0.4rem;
		height: 16px;
		background: var(--c-bg-card);
		border-radius: 4px;
		overflow: visible;
		border: 1px solid var(--c-border);
	}
	.mood-bar {
		height: 100%;
		border-radius: 4px 0 0 4px;
		min-width: 2px;
		transition: width 0.3s ease;
	}
	.mood-bar.done { background: var(--c-done, #48bb78); }
	.mood-bar.skip { background: var(--c-text-muted); opacity: 0.5; }
	.mood-bar-value { font-size: 0.7rem; color: var(--c-text-muted); white-space: nowrap; flex-shrink: 0; padding-right: 0.25rem; }
	.mood-legend-inline { display: flex; gap: 0.75rem; font-size: 0.7rem; color: var(--c-text-muted); }
	.legend-done::before { content: ''; display: inline-block; width: 8px; height: 8px; border-radius: 2px; background: var(--c-done, #48bb78); margin-right: 0.2rem; vertical-align: middle; }
	.legend-skip::before { content: ''; display: inline-block; width: 8px; height: 8px; border-radius: 2px; background: var(--c-text-muted); opacity: 0.5; margin-right: 0.2rem; vertical-align: middle; }

	/* Donut chart */
	.analytics-donut-section { padding: 1rem; }
	.donut-container { display: flex; align-items: flex-start; gap: 1.5rem; flex-wrap: wrap; }
	.donut-legend { display: flex; flex-direction: column; gap: 0.3rem; }
	.donut-legend-item { display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; }
	.donut-swatch { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }
	.donut-legend-label { color: var(--c-text); }
	.donut-legend-count { color: var(--c-text-muted); font-size: 0.75rem; }
</style>
