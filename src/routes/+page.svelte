<script lang="ts">
	import { useEntries } from '$lib/stores/entries.svelte';
	import { ui } from '$lib/db';
	import type { Entry } from '$lib/db';

	const store = useEntries();

	const today = $derived(new Date().toISOString().slice(0, 10));

	const greeting = $derived.by(() => {
		const h = new Date().getHours();
		if (h < 12) return 'Good morning';
		if (h < 18) return 'Good afternoon';
		return 'Good evening';
	});

	const dateLabel = $derived(
		new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })
	);

	/* --- Helpers --- */
	function dateOf(e: Entry): string {
		return (e.data.date as string) ?? e.createdAt.slice(0, 10);
	}

	function isoForDaysAgo(n: number): string {
		const d = new Date();
		d.setDate(d.getDate() - n);
		return d.toISOString().slice(0, 10);
	}

	/* --- Today's entries by type --- */
	const todayCheckins = $derived(
		store.items.filter((e) => e.type === 'checkin' && dateOf(e) === today)
	);

	const yesterdayCheckins = $derived.by(() => {
		const yesterday = isoForDaysAgo(1);
		return store.items.filter((e) => e.type === 'checkin' && dateOf(e) === yesterday);
	});

	const todayHabits = $derived(
		store.items.filter((e) => e.type === 'habit' && dateOf(e) === today)
	);

	const todaySupplements = $derived(
		store.items.filter((e) => e.type === 'supplement' && dateOf(e) === today)
	);

	const TRAINING_TYPES = ['training.strength', 'training.rings', 'training.hiit', 'training.cardio', 'training.mobility'];

	const todayTraining = $derived(
		store.items.filter((e) => TRAINING_TYPES.includes(e.type) && e.createdAt.startsWith(today))
	);

	const todayJournal = $derived(
		store.items.filter((e) => e.type === 'journal' && dateOf(e) === today)
	);

	/* --- Habit & supplement config --- */
	const defaultHabits = [
		{ id: 'cold', label: 'Cold exposure' },
		{ id: 'sun', label: 'Sun exposure' },
		{ id: 'fasting', label: 'Fasting' },
		{ id: 'meditation', label: 'Meditation' },
		{ id: 'wimhof', label: 'Wim Hof' },
		{ id: 'ejaculation', label: 'Ejaculation control' }
	];

	const allHabitTypes = $derived.by(() => {
		const custom = ui.get().customHabits;
		const extra = Array.isArray(custom) ? (custom as Array<{id: string}>).map(h => h.id) : [];
		return [...defaultHabits.map(h => h.id), ...extra];
	});

	const supplementStack = $derived.by(() => {
		const stack = ui.get().supplementStack;
		return Array.isArray(stack) ? stack as Array<{name: string}> : [];
	});

	/* --- Compute daily score for a given date --- */
	function computeScore(dateStr: string): { score: number; hasData: boolean } {
		const yesterday = (() => {
			const d = new Date(dateStr + 'T12:00:00');
			d.setDate(d.getDate() - 1);
			return d.toISOString().slice(0, 10);
		})();

		const allItems = store.items;

		const dayCheckins = allItems.filter((e) => e.type === 'checkin' && dateOf(e) === dateStr);
		const yesterdayC = allItems.filter((e) => e.type === 'checkin' && dateOf(e) === yesterday);
		const dayHabits = allItems.filter((e) => e.type === 'habit' && dateOf(e) === dateStr);
		const daySupps = allItems.filter((e) => e.type === 'supplement' && dateOf(e) === dateStr);
		const dayTraining = allItems.filter((e) => TRAINING_TYPES.includes(e.type) && e.createdAt.startsWith(dateStr));

		type Component = { weight: number; value: number };
		const components: Component[] = [];

		// Sleep quality (25%): from yesterday's or today's checkin data.sleep
		const sleepCheckin = dayCheckins[0] ?? yesterdayC[yesterdayC.length - 1];
		if (sleepCheckin) {
			const sleepVal = Math.min(Number(sleepCheckin.data.sleep) || 0, 10);
			components.push({ weight: 25, value: sleepVal * 10 });
		}

		// Mood + Energy (25%): average of today's checkin mood and energy
		if (dayCheckins.length > 0) {
			const latest = dayCheckins[dayCheckins.length - 1];
			const mood = Math.min(Number(latest.data.mood) || 0, 10);
			const energy = Math.min(Number(latest.data.energy) || 0, 10);
			const avg = ((mood + energy) / 2) * 10;
			components.push({ weight: 25, value: avg });
		}

		// Habit completion (20%)
		const uiData = ui.get();
		const customH = Array.isArray(uiData.customHabits) ? (uiData.customHabits as Array<{id: string}>) : [];
		const totalHabitTypes = 6 + customH.length;
		if (totalHabitTypes > 0) {
			const loggedHabitIds = new Set(dayHabits.map(e => e.data.habit as string));
			const pct = Math.min((loggedHabitIds.size / totalHabitTypes) * 100, 100);
			components.push({ weight: 20, value: pct });
		}

		// Supplement adherence (15%)
		const stack = Array.isArray(uiData.supplementStack) ? uiData.supplementStack as Array<{name: string}> : [];
		if (stack.length > 0) {
			const takenNames = new Set(daySupps.map(e => (e.data.name as string ?? '').toLowerCase()));
			const matched = stack.filter(s => takenNames.has(s.name.toLowerCase())).length;
			const pct = Math.min((matched / stack.length) * 100, 100);
			components.push({ weight: 15, value: pct });
		}

		// Training (15%)
		if (dayTraining.length > 0) {
			components.push({ weight: 15, value: 100 });
		} else {
			components.push({ weight: 15, value: 0 });
		}

		if (components.length === 0) return { score: 0, hasData: false };

		const totalWeight = components.reduce((s, c) => s + c.weight, 0);
		const weighted = components.reduce((s, c) => s + (c.value * c.weight / totalWeight), 0);
		return { score: Math.round(weighted), hasData: true };
	}

	/* --- Today's score --- */
	const todayScore = $derived(computeScore(today));

	/* --- 7-day trend --- */
	const weekScores = $derived.by(() => {
		const scores: { date: string; score: number; hasData: boolean }[] = [];
		for (let i = 6; i >= 0; i--) {
			const d = isoForDaysAgo(i);
			const result = computeScore(d);
			scores.push({ date: d, ...result });
		}
		return scores;
	});

	const weekWithData = $derived(weekScores.filter(s => s.hasData));

	/* --- Score color --- */
	function scoreColor(score: number): string {
		if (score < 40) return '#e53e3e';
		if (score <= 70) return '#e8a735';
		return 'var(--c-done)';
	}

	/* --- SVG arc for gauge --- */
	function describeArc(cx: number, cy: number, r: number, startAngle: number, endAngle: number): string {
		const rad = (a: number) => (a * Math.PI) / 180;
		const x1 = cx + r * Math.cos(rad(startAngle));
		const y1 = cy + r * Math.sin(rad(startAngle));
		const x2 = cx + r * Math.cos(rad(endAngle));
		const y2 = cy + r * Math.sin(rad(endAngle));
		const largeArc = endAngle - startAngle > 180 ? 1 : 0;
		return `M ${x1} ${y1} A ${r} ${r} 0 ${largeArc} 1 ${x2} ${y2}`;
	}

	// Gauge: 270-degree arc, starting from 135deg (bottom-left) going to 405deg (bottom-right)
	const gaugeStartAngle = 135;
	const gaugeTotalAngle = 270;
	const gaugeR = 80;
	const gaugeCx = 100;
	const gaugeCy = 100;

	const gaugeBackgroundArc = $derived(describeArc(gaugeCx, gaugeCy, gaugeR, gaugeStartAngle, gaugeStartAngle + gaugeTotalAngle));
	const gaugeValueArc = $derived.by(() => {
		if (!todayScore.hasData || todayScore.score === 0) return '';
		const angle = (todayScore.score / 100) * gaugeTotalAngle;
		return describeArc(gaugeCx, gaugeCy, gaugeR, gaugeStartAngle, gaugeStartAngle + Math.max(angle, 1));
	});

	/* --- Sparkline --- */
	const sparklinePoints = $derived.by(() => {
		if (weekWithData.length < 2) return '';
		const stepX = 190 / Math.max(weekWithData.length - 1, 1);
		return weekWithData.map((s, i) => {
			const x = 5 + i * stepX;
			const y = 35 - (s.score / 100) * 30;
			return `${x},${y}`;
		}).join(' ');
	});

	/* --- What's missing --- */
	const missingItems = $derived.by(() => {
		const items: Array<{ label: string; hint: string; href: string; icon: string }> = [];

		if (todayCheckins.length === 0) {
			items.push({
				label: 'Check-in',
				hint: 'Log mood, energy, and sleep',
				href: '/checkin',
				icon: 'checkin'
			});
		}

		if (todayTraining.length === 0) {
			items.push({
				label: 'Training',
				hint: 'Log a workout',
				href: '/training',
				icon: 'training'
			});
		}

		const loggedHabitIds = new Set(todayHabits.map(e => e.data.habit as string));
		const remainingHabits = allHabitTypes.filter(id => !loggedHabitIds.has(id)).length;
		if (remainingHabits > 0) {
			items.push({
				label: `Habits (${remainingHabits} remaining)`,
				hint: 'Track your daily habits',
				href: '/habits',
				icon: 'habit'
			});
		}

		const takenNames = new Set(todaySupplements.map(e => (e.data.name as string ?? '').toLowerCase()));
		const remainingSupps = supplementStack.filter(s => !takenNames.has(s.name.toLowerCase())).length;
		if (remainingSupps > 0) {
			items.push({
				label: `Supplements (${remainingSupps} remaining)`,
				hint: 'Log your supplement intake',
				href: '/supplements',
				icon: 'supplement'
			});
		}

		if (todayJournal.length === 0) {
			items.push({
				label: 'Journal',
				hint: 'Write your thoughts',
				href: '/journal',
				icon: 'journal'
			});
		}

		return items;
	});
</script>

<svelte:head>
	<title>Today | Darink</title>
</svelte:head>

<section class="today-page">
	<!-- Greeting -->
	<header class="greeting">
		<h1>{greeting}</h1>
		<p class="date-label">{dateLabel}</p>
	</header>

	<!-- Score gauge -->
	<div class="gauge-container">
		{#if todayScore.hasData}
			<svg viewBox="0 0 200 175" class="gauge-svg">
				<path
					d={gaugeBackgroundArc}
					fill="none"
					stroke="var(--c-border)"
					stroke-width="14"
					stroke-linecap="round"
				/>
				{#if gaugeValueArc}
					<path
						d={gaugeValueArc}
						fill="none"
						stroke={scoreColor(todayScore.score)}
						stroke-width="14"
						stroke-linecap="round"
					/>
				{/if}
				<text
					x={gaugeCx}
					y={gaugeCy - 4}
					text-anchor="middle"
					dominant-baseline="central"
					class="score-number"
					fill={scoreColor(todayScore.score)}
				>{todayScore.score}</text>
				<text
					x={gaugeCx}
					y={gaugeCy + 24}
					text-anchor="middle"
					class="score-label"
					fill="var(--c-text-muted)"
				>Your daily score</text>
			</svg>
		{:else}
			<svg viewBox="0 0 200 175" class="gauge-svg">
				<path
					d={gaugeBackgroundArc}
					fill="none"
					stroke="var(--c-border)"
					stroke-width="14"
					stroke-linecap="round"
				/>
				<text
					x={gaugeCx}
					y={gaugeCy - 4}
					text-anchor="middle"
					dominant-baseline="central"
					class="score-number score-empty"
					fill="var(--c-text-muted)"
				>--</text>
				<text
					x={gaugeCx}
					y={gaugeCy + 24}
					text-anchor="middle"
					class="score-label"
					fill="var(--c-text-muted)"
				>No data yet today</text>
			</svg>
		{/if}
	</div>

	<!-- 7-day trend sparkline -->
	{#if weekWithData.length >= 2}
		<div class="trend-section">
			<h2>7-day trend</h2>
			<div class="sparkline-wrap">
				<svg viewBox="0 0 200 40" class="sparkline-svg" preserveAspectRatio="none">
					<polyline
						points={sparklinePoints}
						fill="none"
						stroke="var(--c-accent)"
						stroke-width="2"
						stroke-linejoin="round"
						stroke-linecap="round"
					/>
					{#each weekWithData as s, i}
						{@const stepX = 190 / Math.max(weekWithData.length - 1, 1)}
						{@const x = 5 + i * stepX}
						{@const y = 35 - (s.score / 100) * 30}
						<circle cx={x} cy={y} r="3" fill="var(--c-accent)" />
					{/each}
				</svg>
				<div class="sparkline-labels">
					{#each weekScores as s}
						<span class="sparkline-day" class:has-data={s.hasData}>
							{new Date(s.date + 'T12:00:00').toLocaleDateString('en', { weekday: 'narrow' })}
						</span>
					{/each}
				</div>
			</div>
		</div>
	{/if}

	<!-- What's missing today -->
	<div class="missing-section">
		<h2>Today's progress</h2>
		{#if missingItems.length === 0}
			<div class="all-done">
				<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--c-done)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
					<path d="m9 11 3 3L22 4"/>
				</svg>
				<p>All caught up!</p>
				<p class="all-done-hint">You've logged everything for today.</p>
			</div>
		{:else}
			<div class="missing-list">
				{#each missingItems as item}
					<a href={item.href} class="missing-card">
						<span class="missing-icon">
							{#if item.icon === 'checkin'}
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/></svg>
							{:else if item.icon === 'training'}
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6.5 6.5 11 11"/><path d="m21 21-1-1"/><path d="m3 3 1 1"/><path d="m18 22 4-4"/><path d="m2 6 4-4"/><path d="m3 10 7-7"/><path d="m14 21 7-7"/></svg>
							{:else if item.icon === 'habit'}
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
							{:else if item.icon === 'supplement'}
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>
							{:else if item.icon === 'journal'}
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/><path d="M8 7h6"/><path d="M8 11h8"/></svg>
							{/if}
						</span>
						<span class="missing-info">
							<span class="missing-label">{item.label}</span>
							<span class="missing-hint">{item.hint}</span>
						</span>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--c-text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
					</a>
				{/each}
			</div>
		{/if}
	</div>
</section>

<style>
	.today-page {
		padding: 0 1rem 2rem;
	}

	/* Greeting */
	.greeting {
		text-align: center;
		padding: 1.5rem 0 0.5rem;
	}

	.greeting h1 {
		font-size: 1.5rem;
		font-weight: 700;
		margin: 0;
		color: var(--c-text);
	}

	.date-label {
		font-size: 0.9rem;
		color: var(--c-text-muted);
		margin: 0.25rem 0 0;
	}

	/* Gauge */
	.gauge-container {
		display: flex;
		justify-content: center;
		padding: 0.5rem 0;
	}

	.gauge-svg {
		width: 220px;
		height: auto;
	}

	.score-number {
		font-size: 40px;
		font-weight: 800;
	}

	.score-empty {
		font-size: 32px;
		font-weight: 600;
	}

	.score-label {
		font-size: 11px;
		font-weight: 500;
	}

	/* Section headings */
	h2 {
		font-size: 0.8rem;
		font-weight: 600;
		margin: 0 0 0.5rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}

	/* 7-day trend */
	.trend-section {
		padding: 0.75rem 0;
	}

	.sparkline-wrap {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem;
	}

	.sparkline-svg {
		width: 100%;
		height: 40px;
		display: block;
	}

	.sparkline-labels {
		display: flex;
		justify-content: space-between;
		padding-top: 0.35rem;
	}

	.sparkline-day {
		font-size: 0.7rem;
		color: var(--c-text-muted);
		text-align: center;
		flex: 1;
	}

	.sparkline-day.has-data {
		color: var(--c-accent);
		font-weight: 600;
	}

	/* Missing section */
	.missing-section {
		padding: 0.75rem 0 0;
	}

	.missing-list {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}

	.missing-card {
		display: flex;
		align-items: center;
		gap: 0.75rem;
		padding: 0.75rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		text-decoration: none;
		color: var(--c-text);
		transition: border-color 0.15s;
	}

	.missing-card:hover {
		border-color: var(--c-accent);
	}

	.missing-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 36px;
		height: 36px;
		flex-shrink: 0;
		background: var(--c-accent-bg);
		border-radius: var(--radius);
		color: var(--c-accent);
	}

	.missing-info {
		flex: 1;
		display: flex;
		flex-direction: column;
		gap: 0.1rem;
	}

	.missing-label {
		font-weight: 600;
		font-size: 0.9rem;
	}

	.missing-hint {
		font-size: 0.75rem;
		color: var(--c-text-muted);
	}

	/* All done */
	.all-done {
		text-align: center;
		padding: 1.5rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-done);
		border-radius: var(--radius);
	}

	.all-done p {
		margin: 0.5rem 0 0;
		font-weight: 600;
		color: var(--c-done);
	}

	.all-done-hint {
		font-size: 0.85rem;
		color: var(--c-text-muted) !important;
		font-weight: 400 !important;
	}
</style>
