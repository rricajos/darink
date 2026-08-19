<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { useEntries } from '$lib/stores/entries.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries();

	interface Record {
		title: string;
		value: string;
		date: string;
	}

	function entryDate(e: Entry): string {
		return (e.data.date as string) ?? e.createdAt.slice(0, 10);
	}

	function formatDate(d: string): string {
		if (!d) return '';
		const dt = new Date(d + 'T00:00:00');
		return dt.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
	}

	/* --- Training PRs --- */
	const trainingRecords = $derived.by(() => {
		const items = store.items;
		const records: Record[] = [];

		/* Heaviest weight per exercise */
		const strengthEntries = items.filter(e => e.type === 'training.strength');
		const byExercise = new Map<string, { weight: number; date: string }>();
		for (const e of strengthEntries) {
			const ex = (e.data.exercise as string) ?? '';
			const w = Number(e.data.weight) || 0;
			if (!ex || w <= 0) continue;
			const prev = byExercise.get(ex);
			if (!prev || w > prev.weight) {
				byExercise.set(ex, { weight: w, date: entryDate(e) });
			}
		}
		for (const [ex, info] of byExercise) {
			records.push({ title: ex, value: `${info.weight} kg`, date: info.date });
		}

		/* Fastest pace per activity */
		const cardioEntries = items.filter(e => e.type === 'training.cardio');
		const byActivity = new Map<string, { pace: number; date: string }>();
		for (const e of cardioEntries) {
			const act = (e.data.activity as string) ?? '';
			const dist = Number(e.data.distanceKm) || 0;
			const dur = Number(e.data.durationMin) || 0;
			if (!act || dist <= 0 || dur <= 0) continue;
			const pace = dur / dist;
			const prev = byActivity.get(act);
			if (!prev || pace < prev.pace) {
				byActivity.set(act, { pace, date: entryDate(e) });
			}
		}
		for (const [act, info] of byActivity) {
			const mins = Math.floor(info.pace);
			const secs = Math.round((info.pace - mins) * 60);
			records.push({ title: `${act} pace`, value: `${mins}:${secs.toString().padStart(2, '0')} /km`, date: info.date });
		}

		/* Longest session across all training types */
		const trainingEntries = items.filter(e => e.type.startsWith('training.'));
		let longestDur = 0;
		let longestDate = '';
		let longestType = '';
		for (const e of trainingEntries) {
			let dur = 0;
			if (e.data.durationMin != null) {
				dur = Number(e.data.durationMin) || 0;
			} else if (e.data.duration != null) {
				dur = Number(e.data.duration) || 0;
			} else if (e.type === 'training.hiit') {
				const rounds = Number(e.data.rounds) || 0;
				const work = Number(e.data.workSec) || 0;
				const rest = Number(e.data.restSec) || 0;
				dur = (rounds * (work + rest)) / 60;
			}
			if (dur > longestDur) {
				longestDur = dur;
				longestDate = entryDate(e);
				longestType = e.type.replace('training.', '');
			}
		}
		if (longestDur > 0) {
			records.push({ title: `Longest session (${longestType})`, value: `${Math.round(longestDur)} min`, date: longestDate });
		}

		return records;
	});

	/* --- Habit Milestones --- */
	const habitRecords = $derived.by(() => {
		const items = store.items;
		const records: Record[] = [];
		const habitEntries = items.filter(e => e.type === 'habit');

		const byHabit = new Map<string, string[]>();
		for (const e of habitEntries) {
			const h = (e.data.habit as string) ?? '';
			if (!h) continue;
			const d = entryDate(e);
			if (!byHabit.has(h)) byHabit.set(h, []);
			byHabit.get(h)!.push(d);
		}

		for (const [habit, dates] of byHabit) {
			const unique = [...new Set(dates)].sort();
			/* Best streak */
			let bestStreak = 1;
			let currentStreak = 1;
			let bestEnd = unique[0];
			for (let i = 1; i < unique.length; i++) {
				const prev = new Date(unique[i - 1] + 'T00:00:00');
				const curr = new Date(unique[i] + 'T00:00:00');
				const diff = (curr.getTime() - prev.getTime()) / (1000 * 60 * 60 * 24);
				if (diff === 1) {
					currentStreak++;
					if (currentStreak > bestStreak) {
						bestStreak = currentStreak;
						bestEnd = unique[i];
					}
				} else {
					currentStreak = 1;
				}
			}
			records.push({ title: `${habit} streak`, value: `${bestStreak} day${bestStreak !== 1 ? 's' : ''}`, date: bestEnd });
			records.push({ title: `${habit} total`, value: `${unique.length} day${unique.length !== 1 ? 's' : ''}`, date: '' });
		}

		return records;
	});

	/* --- Consistency Records --- */
	const consistencyRecords = $derived.by(() => {
		const items = store.items;
		const records: Record[] = [];
		if (items.length === 0) return records;

		/* Most entries in a single day */
		const byDay = new Map<string, number>();
		for (const e of items) {
			const d = e.createdAt.slice(0, 10);
			byDay.set(d, (byDay.get(d) || 0) + 1);
		}
		let maxDay = '';
		let maxCount = 0;
		for (const [day, count] of byDay) {
			if (count > maxCount) {
				maxCount = count;
				maxDay = day;
			}
		}
		if (maxCount > 0) {
			records.push({ title: 'Most entries in a day', value: `${maxCount}`, date: maxDay });
		}

		/* Most consecutive days with any entry */
		const uniqueDays = [...byDay.keys()].sort();
		let bestRun = 1;
		let currentRun = 1;
		let bestRunEnd = uniqueDays[0];
		for (let i = 1; i < uniqueDays.length; i++) {
			const prev = new Date(uniqueDays[i - 1] + 'T00:00:00');
			const curr = new Date(uniqueDays[i] + 'T00:00:00');
			const diff = (curr.getTime() - prev.getTime()) / (1000 * 60 * 60 * 24);
			if (diff === 1) {
				currentRun++;
				if (currentRun > bestRun) {
					bestRun = currentRun;
					bestRunEnd = uniqueDays[i];
				}
			} else {
				currentRun = 1;
			}
		}
		records.push({ title: 'Longest daily streak', value: `${bestRun} day${bestRun !== 1 ? 's' : ''}`, date: bestRunEnd });

		/* Total entries all time */
		records.push({ title: 'Total entries', value: `${items.length}`, date: '' });

		return records;
	});

	/* --- Check-in Records --- */
	const checkinRecords = $derived.by(() => {
		const items = store.items;
		const records: Record[] = [];
		const checkins = items.filter(e => e.type === 'checkin');
		if (checkins.length === 0) return records;

		let bestMood = -Infinity, bestMoodDate = '';
		let bestEnergy = -Infinity, bestEnergyDate = '';
		let bestSleep = -Infinity, bestSleepDate = '';
		let lowestStress = Infinity, lowestStressDate = '';

		for (const e of checkins) {
			const d = entryDate(e);
			const mood = Number(e.data.mood);
			const energy = Number(e.data.energy);
			const sleep = Number(e.data.sleep);
			const stress = Number(e.data.stress);

			if (!isNaN(mood) && mood > bestMood) { bestMood = mood; bestMoodDate = d; }
			if (!isNaN(energy) && energy > bestEnergy) { bestEnergy = energy; bestEnergyDate = d; }
			if (!isNaN(sleep) && sleep > bestSleep) { bestSleep = sleep; bestSleepDate = d; }
			if (!isNaN(stress) && stress < lowestStress) { lowestStress = stress; lowestStressDate = d; }
		}

		if (bestMood > -Infinity) records.push({ title: 'Best mood', value: `${bestMood}/10`, date: bestMoodDate });
		if (bestEnergy > -Infinity) records.push({ title: 'Best energy', value: `${bestEnergy}/10`, date: bestEnergyDate });
		if (bestSleep > -Infinity) records.push({ title: 'Best sleep', value: `${bestSleep} h`, date: bestSleepDate });
		if (lowestStress < Infinity) records.push({ title: 'Lowest stress', value: `${lowestStress}/10`, date: lowestStressDate });

		return records;
	});

	/* --- Weight Records --- */
	const weightRecords = $derived.by(() => {
		const items = store.items;
		const records: Record[] = [];
		const weights = items.filter(e => e.type === 'weight');
		if (weights.length === 0) return records;

		let minW = Infinity, minWDate = '';
		let maxW = -Infinity, maxWDate = '';
		let minBF = Infinity, minBFDate = '';

		for (const e of weights) {
			const d = entryDate(e);
			const w = Number(e.data.weight);
			const bf = e.data.bodyFat != null ? Number(e.data.bodyFat) : NaN;

			if (!isNaN(w) && w > 0) {
				if (w < minW) { minW = w; minWDate = d; }
				if (w > maxW) { maxW = w; maxWDate = d; }
			}
			if (!isNaN(bf) && bf > 0 && bf < minBF) { minBF = bf; minBFDate = d; }
		}

		if (minW < Infinity) records.push({ title: 'Lowest weight', value: `${minW} kg`, date: minWDate });
		if (maxW > -Infinity) records.push({ title: 'Highest weight', value: `${maxW} kg`, date: maxWDate });
		if (minBF < Infinity) records.push({ title: 'Lowest body fat', value: `${minBF}%`, date: minBFDate });

		return records;
	});

	const hasAnyRecords = $derived(
		trainingRecords.length > 0 ||
		habitRecords.length > 0 ||
		consistencyRecords.length > 0 ||
		checkinRecords.length > 0 ||
		weightRecords.length > 0
	);

	interface Section {
		title: string;
		icon: string;
		records: Record[];
	}

	const sections = $derived.by((): Section[] => {
		const result: Section[] = [];
		if (trainingRecords.length > 0) result.push({ title: 'Training PRs', icon: 'trophy', records: trainingRecords });
		if (habitRecords.length > 0) result.push({ title: 'Habit Milestones', icon: 'flame', records: habitRecords });
		if (consistencyRecords.length > 0) result.push({ title: 'Consistency', icon: 'calendar', records: consistencyRecords });
		if (checkinRecords.length > 0) result.push({ title: 'Check-in Records', icon: 'star', records: checkinRecords });
		if (weightRecords.length > 0) result.push({ title: 'Weight Records', icon: 'scale', records: weightRecords });
		return result;
	});

	/* --- PR Timeline (scatter plot data) --- */
	interface TimelinePoint {
		date: string;
		category: 'Training' | 'Habits' | 'Check-in' | 'Weight';
		label: string;
	}

	const categoryColors: { [key: string]: string } = {
		Training: 'var(--c-accent)',
		Habits: 'var(--c-done)',
		'Check-in': '#f59e0b',
		Weight: '#8b5cf6'
	};

	const categoryYPositions: { [key: string]: number } = {
		Training: 0,
		Habits: 1,
		'Check-in': 2,
		Weight: 3
	};

	const timelinePoints = $derived.by((): TimelinePoint[] => {
		const points: TimelinePoint[] = [];
		for (const r of trainingRecords) {
			if (r.date) points.push({ date: r.date, category: 'Training', label: r.title });
		}
		for (const r of habitRecords) {
			if (r.date) points.push({ date: r.date, category: 'Habits', label: r.title });
		}
		for (const r of checkinRecords) {
			if (r.date) points.push({ date: r.date, category: 'Check-in', label: r.title });
		}
		for (const r of weightRecords) {
			if (r.date) points.push({ date: r.date, category: 'Weight', label: r.title });
		}
		return points;
	});

	const timelineBounds = $derived.by(() => {
		const dates = timelinePoints.filter(p => p.date).map(p => new Date(p.date + 'T00:00:00').getTime());
		if (dates.length === 0) return { min: Date.now(), max: Date.now(), range: 1, minLabel: '', maxLabel: '' };
		const min = Math.min(...dates);
		const max = Math.max(...dates);
		const range = max - min || 1;
		const fmt = (ms: number) => new Date(ms).toLocaleDateString('en-GB', { month: 'short', year: '2-digit' });
		return { min, max, range, minLabel: fmt(min), maxLabel: fmt(max) };
	});

	/* --- Records by Category --- */
	interface CategoryBreakdown {
		name: string;
		count: number;
		color: string;
	}

	const categoryBreakdown = $derived.by((): CategoryBreakdown[] => {
		const cats: CategoryBreakdown[] = [];
		const strengthCount = store.items.filter(e => e.type === 'training.strength').length ? trainingRecords.filter(r => !r.title.includes('pace') && !r.title.includes('session')).length : 0;
		const cardioCount = trainingRecords.filter(r => r.title.includes('pace')).length;
		const habitCount = habitRecords.length;
		const checkinCount = checkinRecords.length;
		const bodyCount = weightRecords.length;

		if (strengthCount > 0) cats.push({ name: 'Strength PRs', count: strengthCount, color: 'var(--c-accent)' });
		if (cardioCount > 0) cats.push({ name: 'Cardio PRs', count: cardioCount, color: '#3b82f6' });
		if (habitCount > 0) cats.push({ name: 'Habit streaks', count: habitCount, color: 'var(--c-done)' });
		if (checkinCount > 0) cats.push({ name: 'Check-in peaks', count: checkinCount, color: '#f59e0b' });
		if (bodyCount > 0) cats.push({ name: 'Body composition', count: bodyCount, color: '#8b5cf6' });
		return cats;
	});

	const totalCategoryRecords = $derived(categoryBreakdown.reduce((s, c) => s + c.count, 0));

	/* --- Recent Achievements --- */
	interface Achievement {
		icon: string;
		description: string;
		date: string;
		category: string;
	}

	const recentAchievements = $derived.by((): Achievement[] => {
		const all: Achievement[] = [];

		for (const r of trainingRecords) {
			if (r.date) all.push({ icon: 'trophy', description: `${r.title}: ${r.value}`, date: r.date, category: 'Training' });
		}
		for (const r of habitRecords) {
			if (r.date) all.push({ icon: 'flame', description: `${r.title}: ${r.value}`, date: r.date, category: 'Habits' });
		}
		for (const r of checkinRecords) {
			if (r.date) all.push({ icon: 'star', description: `${r.title}: ${r.value}`, date: r.date, category: 'Check-in' });
		}
		for (const r of weightRecords) {
			if (r.date) all.push({ icon: 'scale', description: `${r.title}: ${r.value}`, date: r.date, category: 'Weight' });
		}

		return all.toSorted((a, b) => b.date.localeCompare(a.date)).slice(0, 5);
	});

	/* --- Days Since Last PR --- */
	const daysSinceLastPR = $derived.by((): number => {
		const allDates: string[] = [];
		for (const r of trainingRecords) { if (r.date) allDates.push(r.date); }
		for (const r of habitRecords) { if (r.date) allDates.push(r.date); }
		for (const r of checkinRecords) { if (r.date) allDates.push(r.date); }
		for (const r of weightRecords) { if (r.date) allDates.push(r.date); }
		if (allDates.length === 0) return -1;
		const latest = allDates.toSorted((a, b) => b.localeCompare(a))[0];
		const latestMs = new Date(latest + 'T00:00:00').getTime();
		const nowMs = new Date(new Date().toISOString().slice(0, 10) + 'T00:00:00').getTime();
		return Math.floor((nowMs - latestMs) / (1000 * 60 * 60 * 24));
	});

	const daysSinceColor = $derived(
		daysSinceLastPR < 0 ? 'var(--c-text-muted)' :
		daysSinceLastPR < 7 ? '#22c55e' :
		daysSinceLastPR <= 30 ? '#eab308' :
		'#ef4444'
	);

	const daysSinceMessage = $derived(
		daysSinceLastPR < 0 ? 'No records yet' :
		daysSinceLastPR === 0 ? 'You set a PR today!' :
		daysSinceLastPR < 7 ? 'On fire! Keep pushing!' :
		daysSinceLastPR <= 30 ? 'Time to chase a new record' :
		'Go break some records!'
	);
</script>

<svelte:head>
	<title>Records | Darink</title>
</svelte:head>

<PageHeader title="Records" />

{#if !hasAnyRecords}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5C7 4 7 7 7 7"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5C17 4 17 7 17 7"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
	<p>No records yet</p>
	<p class="empty-hint">Start logging entries to see your personal bests and milestones here.</p>
</div>
{:else}
{#each sections as section}
	<section class="section">
		<div class="section-header">
			{#if section.icon === 'trophy'}
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5C7 4 7 7 7 7"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5C17 4 17 7 17 7"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
			{:else if section.icon === 'flame'}
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14 0-5.5 3-7 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.5-2.26 1.4-3.2l1.1 1.2"/></svg>
			{:else if section.icon === 'calendar'}
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
			{:else if section.icon === 'star'}
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
			{:else if section.icon === 'scale'}
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg>
			{/if}
			<h2>{section.title}</h2>
		</div>
		<div class="records-grid">
			{#each section.records as record}
				<div class="record-card">
					<span class="record-title">{record.title}</span>
					<span class="record-value">{record.value}</span>
					{#if record.date}
						<span class="record-date">{formatDate(record.date)}</span>
					{/if}
				</div>
			{/each}
		</div>
	</section>
{/each}
{/if}

<!-- Analytics Sections -->
{#if hasAnyRecords}

<!-- Days Since Last PR -->
{#if daysSinceLastPR >= 0}
<section class="section">
	<h3>Days Since Last PR</h3>
	<div class="days-since-card" style="border-color: {daysSinceColor}">
		<span class="days-since-number" style="color: {daysSinceColor}">
			{daysSinceLastPR}
		</span>
		<span class="days-since-label">day{daysSinceLastPR !== 1 ? 's' : ''} ago</span>
		<span class="days-since-message" style="color: {daysSinceColor}">{daysSinceMessage}</span>
	</div>
</section>
{/if}

<!-- PR Timeline -->
{#if timelinePoints.length > 1}
<section class="section">
	<h3>PR Timeline</h3>
	<div class="timeline-chart-container">
		<svg class="timeline-svg" viewBox="0 0 800 180" preserveAspectRatio="xMidYMid meet">
			<!-- Category labels -->
			{#each ['Training', 'Habits', 'Check-in', 'Weight'] as cat}
				{@const yPos = 25 + categoryYPositions[cat] * 38}
				<text x="2" y={yPos + 4} fill="var(--c-text-muted)" font-size="11" font-family="inherit">{cat}</text>
				<line x1="80" y1={yPos} x2="790" y2={yPos} stroke="var(--c-border)" stroke-width="1" stroke-dasharray="4,4" />
			{/each}
			<!-- Data points -->
			{#each timelinePoints as point}
				{@const xPct = (new Date(point.date + 'T00:00:00').getTime() - timelineBounds.min) / timelineBounds.range}
				{@const cx = 80 + xPct * 710}
				{@const cy = 25 + categoryYPositions[point.category] * 38}
				<circle
					{cx}
					{cy}
					r="5"
					fill={categoryColors[point.category]}
					opacity="0.85"
				>
					<title>{point.label} — {formatDate(point.date)}</title>
				</circle>
			{/each}
			<text x="80" y="175" fill="var(--c-text-muted)" font-size="10" font-family="inherit" text-anchor="start">
				{new Date(timelineBounds.min).toLocaleDateString('en-GB', { month: 'short', year: '2-digit' })}
			</text>
			<text x="790" y="175" fill="var(--c-text-muted)" font-size="10" font-family="inherit" text-anchor="end">
				{new Date(timelineBounds.max).toLocaleDateString('en-GB', { month: 'short', year: '2-digit' })}
			</text>
		</svg>
	</div>
</section>
{/if}

<!-- Records by Category -->
{#if categoryBreakdown.length > 0}
<section class="section">
	<h3>Records by Category</h3>
	<div class="category-breakdown">
		{#each categoryBreakdown as cat}
			{@const pct = totalCategoryRecords > 0 ? Math.round((cat.count / totalCategoryRecords) * 100) : 0}
			<div class="category-row">
				<div class="category-info">
					<span class="category-dot" style="background: {cat.color}"></span>
					<span class="category-name">{cat.name}</span>
					<span class="category-count">{cat.count}</span>
					<span class="category-pct">{pct}%</span>
				</div>
				<div class="category-bar-bg">
					<div class="category-bar-fill" style="width: {pct}%; background: {cat.color}"></div>
				</div>
			</div>
		{/each}
	</div>
</section>
{/if}

<!-- Recent Achievements -->
{#if recentAchievements.length > 0}
<section class="section">
	<h3>Recent Achievements</h3>
	<div class="achievements-list">
		{#each recentAchievements as ach}
			<div class="achievement-card">
				<div class="achievement-icon">
					{#if ach.icon === 'trophy'}
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5C7 4 7 7 7 7"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5C17 4 17 7 17 7"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
					{:else if ach.icon === 'flame'}
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14 0-5.5 3-7 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.5-2.26 1.4-3.2l1.1 1.2"/></svg>
					{:else if ach.icon === 'star'}
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
					{:else if ach.icon === 'scale'}
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg>
					{/if}
				</div>
				<div class="achievement-body">
					<span class="achievement-desc">{ach.description}</span>
					<span class="achievement-meta">{ach.category} &middot; {formatDate(ach.date)}</span>
				</div>
			</div>
		{/each}
	</div>
</section>
{/if}

{/if}

<style>
	.section {
		padding: 0 1rem;
		margin-bottom: 1.5rem;
	}

	.section-header {
		display: flex;
		align-items: center;
		gap: 0.4rem;
		margin-bottom: 0.5rem;
		color: var(--c-text-muted);
	}

	h2 {
		font-size: 0.9rem;
		font-weight: 600;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
		margin: 0;
	}

	.records-grid {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 0.5rem;
	}

	@media (min-width: 600px) {
		.records-grid {
			grid-template-columns: repeat(3, 1fr);
		}
	}

	.record-card {
		display: flex;
		flex-direction: column;
		gap: 0.15rem;
		padding: 0.75rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}

	.record-title {
		font-size: 0.75rem;
		font-weight: 600;
		color: var(--c-text-muted);
		text-transform: capitalize;
	}

	.record-value {
		font-size: 1.25rem;
		font-weight: 700;
		color: var(--c-text);
	}

	.record-date {
		font-size: 0.7rem;
		color: var(--c-text-muted);
	}

	/* Empty state */
	.empty-state {
		text-align: center;
		padding: 3rem 1rem;
		color: var(--c-text-muted);
	}

	.empty-state p {
		margin-top: 0.5rem;
	}

	.empty-hint {
		font-size: 0.85rem;
	}

	/* Analytics section headers */
	h3 {
		font-size: 0.9rem;
		font-weight: 600;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
		margin: 0 0 0.5rem 0;
	}

	/* Days Since Last PR */
	.days-since-card {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 0.25rem;
		padding: 1.5rem 1rem;
		background: var(--c-bg-card);
		border: 2px solid var(--c-border);
		border-radius: var(--radius);
		text-align: center;
	}

	.days-since-number {
		font-size: 3rem;
		font-weight: 800;
		line-height: 1;
	}

	.days-since-label {
		font-size: 0.9rem;
		color: var(--c-text-muted);
		font-weight: 500;
	}

	.days-since-message {
		font-size: 0.8rem;
		font-weight: 600;
		margin-top: 0.25rem;
	}

	/* PR Timeline */
	.timeline-chart-container {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem;
		overflow: hidden;
	}

	.timeline-svg {
		width: 100%;
		height: auto;
		display: block;
	}

	/* Records by Category */
	.category-breakdown {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}

	.category-row {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem 1rem;
	}

	.category-info {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		margin-bottom: 0.4rem;
	}

	.category-dot {
		width: 10px;
		height: 10px;
		border-radius: 50%;
		flex-shrink: 0;
	}

	.category-name {
		font-size: 0.85rem;
		font-weight: 600;
		color: var(--c-text);
		flex: 1;
	}

	.category-count {
		font-size: 0.85rem;
		font-weight: 700;
		color: var(--c-text);
	}

	.category-pct {
		font-size: 0.75rem;
		color: var(--c-text-muted);
		min-width: 2.5rem;
		text-align: right;
	}

	.category-bar-bg {
		height: 6px;
		background: var(--c-border);
		border-radius: 3px;
		overflow: hidden;
	}

	.category-bar-fill {
		height: 100%;
		border-radius: 3px;
		transition: width 0.3s ease;
		min-width: 2px;
	}

	/* Recent Achievements */
	.achievements-list {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}

	.achievement-card {
		display: flex;
		align-items: flex-start;
		gap: 0.75rem;
		padding: 1rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}

	.achievement-icon {
		flex-shrink: 0;
		color: var(--c-accent);
		margin-top: 0.1rem;
	}

	.achievement-body {
		display: flex;
		flex-direction: column;
		gap: 0.2rem;
		min-width: 0;
	}

	.achievement-desc {
		font-size: 0.9rem;
		font-weight: 600;
		color: var(--c-text);
	}

	.achievement-meta {
		font-size: 0.75rem;
		color: var(--c-text-muted);
	}
</style>
