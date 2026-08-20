<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import HeatmapCalendar from '$lib/components/HeatmapCalendar.svelte';
	import { useEntries } from '$lib/stores/entries.svelte';
	import { useLocale } from '$lib/stores/locale.svelte';

	const { t, locale } = useLocale();
	const store = useEntries();
	const hydrationStore = useEntries('hydration');

	let period = $state<'week' | 'month' | '3month'>('week');
	let heatmapFilter = $state<string | null>(null);

	const heatmapTypes = ['checkin', 'intake', 'training', 'habit', 'supplement', 'journal', 'hydration'] as const;

	const filteredHeatmapItems = $derived(
		heatmapFilter ? store.items.filter((e) => e.type === heatmapFilter || e.type.startsWith(heatmapFilter + '.')) : store.items
	);

	const periodDays = $derived(period === 'week' ? 7 : period === 'month' ? 30 : 90);

	const periodComparison = $derived.by(() => {
		const all = store.items;
		const now = Date.now();
		const msPerDay = 86400000;
		const currentStart = new Date(now - periodDays * msPerDay).toISOString();
		const prevStart = new Date(now - periodDays * 2 * msPerDay).toISOString();
		const prevEnd = currentStart;

		const current = all.filter((e) => e.createdAt >= currentStart);
		const previous = all.filter((e) => e.createdAt >= prevStart && e.createdAt < prevEnd);

		const avg = (arr: number[]) => arr.length ? +(arr.reduce((a, b) => a + b, 0) / arr.length).toFixed(1) : null;

		const currentCheckins = current.filter((e) => e.type === 'checkin');
		const previousCheckins = previous.filter((e) => e.type === 'checkin');

		const currentTrainings = current.filter((e) => e.type.startsWith('training.')).length;
		const previousTrainings = previous.filter((e) => e.type.startsWith('training.')).length;

		// Habit completion: count unique days with at least one habit entry
		const habitDays = (entries: typeof all) => {
			const days = new Set(
				entries
					.filter((e) => e.type === 'habit')
					.map((e) => (e.data.date as string) ?? e.createdAt.slice(0, 10))
			);
			return days.size;
		};

		return {
			entries: { current: current.length, previous: previous.length },
			mood: {
				current: avg(currentCheckins.map((e) => e.data.mood as number)),
				previous: avg(previousCheckins.map((e) => e.data.mood as number))
			},
			energy: {
				current: avg(currentCheckins.map((e) => e.data.energy as number)),
				previous: avg(previousCheckins.map((e) => e.data.energy as number))
			},
			training: { current: currentTrainings, previous: previousTrainings },
			habitDays: { current: habitDays(current), previous: habitDays(previous) }
		};
	});

	const periodLabels = $derived({
		current: period === 'week' ? t.common.thisWeek : period === 'month' ? t.common.thisMonth : t.common.thisQuarter,
		previous: period === 'week' ? t.common.lastWeek : period === 'month' ? t.common.lastMonth : t.common.lastQuarter
	});

	const stats = $derived.by(() => {
		const all = store.items;
		const today = new Date().toISOString().slice(0, 10);
		return {
			total: all.length,
			today: all.filter((e) => e.createdAt.startsWith(today)).length,
			checkins: all.filter((e) => e.type === 'checkin').length,
			intakes: all.filter((e) => e.type === 'intake').length,
			trainings: all.filter((e) => e.type.startsWith('training.')).length,
			habits: all.filter((e) => e.type === 'habit').length,
			supplements: all.filter((e) => e.type === 'supplement').length,
			experiments: all.filter((e) => e.type === 'experiment').length
		};
	});

	const yearAgoComparison = $derived.by(() => {
		const all = store.items;
		const now = new Date();
		const days = period === 'week' ? 7 : period === 'month' ? 30 : 90;

		// Current period boundaries
		const currentStart = new Date(now);
		currentStart.setDate(currentStart.getDate() - days);
		const currentStartStr = currentStart.toISOString();

		// Same period one year ago
		const yearAgoEnd = new Date(now);
		yearAgoEnd.setFullYear(yearAgoEnd.getFullYear() - 1);
		const yearAgoStart = new Date(yearAgoEnd);
		yearAgoStart.setDate(yearAgoStart.getDate() - days);
		const yearAgoStartStr = yearAgoStart.toISOString();
		const yearAgoEndStr = yearAgoEnd.toISOString();

		const currentItems = all.filter((e) => e.createdAt >= currentStartStr);
		const yearAgoItems = all.filter((e) => e.createdAt >= yearAgoStartStr && e.createdAt <= yearAgoEndStr);

		const avg = (arr: number[]) => arr.length ? +(arr.reduce((a, b) => a + b, 0) / arr.length).toFixed(1) : null;

		const currentCheckins = currentItems.filter((e) => e.type === 'checkin');
		const yearAgoCheckins = yearAgoItems.filter((e) => e.type === 'checkin');

		const currentMood = avg(currentCheckins.map((e) => e.data.mood as number));
		const yearAgoMood = avg(yearAgoCheckins.map((e) => e.data.mood as number));

		const currentEnergy = avg(currentCheckins.map((e) => e.data.energy as number));
		const yearAgoEnergy = avg(yearAgoCheckins.map((e) => e.data.energy as number));

		// Weight: find the most recent weight entry in each period
		const currentWeights = all
			.filter((e) => e.type === 'weight' && e.createdAt >= currentStartStr)
			.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt));
		const yearAgoWeights = all
			.filter((e) => e.type === 'weight' && e.createdAt >= yearAgoStartStr && e.createdAt <= yearAgoEndStr)
			.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt));

		const currentWeight = currentWeights.length > 0 ? (currentWeights[0].data.weight as number) : null;
		const yearAgoWeight = yearAgoWeights.length > 0 ? (yearAgoWeights[0].data.weight as number) : null;

		const currentBodyFat = currentWeights.length > 0 ? (currentWeights[0].data.bodyFat as number | null) ?? null : null;
		const yearAgoBodyFat = yearAgoWeights.length > 0 ? (yearAgoWeights[0].data.bodyFat as number | null) ?? null : null;

		const yearLabel = yearAgoEnd.getFullYear();

		return {
			hasData: yearAgoItems.length > 0,
			entries: { current: currentItems.length, yearAgo: yearAgoItems.length },
			mood: { current: currentMood, yearAgo: yearAgoMood },
			energy: { current: currentEnergy, yearAgo: yearAgoEnergy },
			weight: { current: currentWeight, yearAgo: yearAgoWeight },
			bodyFat: { current: currentBodyFat, yearAgo: yearAgoBodyFat },
			yearLabel
		};
	});

	const weeklyActivity = $derived.by(() => {
		const all = store.items;
		const days: { label: string; count: number }[] = [];
		for (let i = 6; i >= 0; i--) {
			const d = new Date();
			d.setDate(d.getDate() - i);
			const key = d.toISOString().slice(0, 10);
			const weekday = d.toLocaleDateString(locale, { weekday: 'short' });
			days.push({ label: weekday, count: all.filter((e) => e.createdAt.startsWith(key)).length });
		}
		return days;
	});

	const moodTrend = $derived.by(() => {
		const checkins = store.items
			.filter((e) => e.type === 'checkin')
			.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt))
			.slice(-14);
		return checkins.map((e) => ({
			date: e.createdAt.slice(5, 10),
			mood: (e.data.mood as number) ?? 5,
			energy: (e.data.energy as number) ?? 5
		}));
	});

	const weekSummary = $derived.by(() => {
		const now = new Date();
		const weekAgo = new Date(now.getTime() - 7 * 86400000).toISOString();
		const week = store.items.filter((e) => e.createdAt >= weekAgo);
		const checkins = week.filter((e) => e.type === 'checkin');
		const sleeps = week.filter((e) => e.type === 'signal.sleep');

		const avg = (arr: number[]) => arr.length ? +(arr.reduce((a, b) => a + b, 0) / arr.length).toFixed(1) : null;

		return {
			entries: week.length,
			avgMood: avg(checkins.map((e) => e.data.mood as number)),
			avgEnergy: avg(checkins.map((e) => e.data.energy as number)),
			avgSleep: avg(sleeps.map((e) => e.data.hours as number))
		};
	});

	/* --- Today's Log Overview --- */
	const todayLog = $derived.by(() => {
		const today = new Date().toISOString().slice(0, 10);
		const todayEntries = store.items.filter((e) => e.createdAt.startsWith(today));
		const groups: { key: string; label: string; items: string[] }[] = [];

		const checkins = todayEntries.filter((e) => e.type === 'checkin');
		if (checkins.length > 0) {
			groups.push({
				key: 'checkins',
				label: t.dashboard.checkins,
				items: checkins.map((e) => `${t.common.mood} ${e.data.mood} · ${t.common.energy} ${e.data.energy}`)
			});
		}

		const intakes = todayEntries.filter((e) => e.type === 'intake');
		if (intakes.length > 0) {
			groups.push({
				key: 'intakes',
				label: t.dashboard.intakes,
				items: intakes.map((e) => e.data.what as string)
			});
		}

		const trainings = todayEntries.filter((e) => e.type.startsWith('training.'));
		if (trainings.length > 0) {
			groups.push({
				key: 'training',
				label: t.dashboard.training,
				items: trainings.map((e) => {
					const sub = e.type.replace('training.', '');
					const name = (e.data.exercise ?? e.data.activity ?? e.data.routine ?? e.data.progression ?? e.data.name ?? sub) as string;
					return `${sub}: ${name}`;
				})
			});
		}

		const habits = todayEntries.filter((e) => e.type === 'habit');
		if (habits.length > 0) {
			const habitLabels: Record<string, string> = {
				cold: t.dashboard.cold, sun: t.dashboard.sun, fasting: t.dashboard.fasting,
				meditation: t.dashboard.meditation, wimhof: t.dashboard.wimhof, ejaculation: t.dashboard.ejacControl
			};
			groups.push({
				key: 'habits',
				label: t.dashboard.habits,
				items: habits.map((e) => habitLabels[e.data.habit as string] ?? (e.data.habit as string))
			});
		}

		const supps = todayEntries.filter((e) => e.type === 'supplement');
		if (supps.length > 0) {
			groups.push({
				key: 'supplements',
				label: t.dashboard.supplements,
				items: supps.map((e) => `${e.data.name}${e.data.dose ? ' ' + e.data.dose : ''}`)
			});
		}

		const signals = todayEntries.filter((e) => e.type.startsWith('signal.'));
		if (signals.length > 0) {
			groups.push({
				key: 'signals',
				label: t.dashboard.signals,
				items: signals.map((e) => e.type.replace('signal.', ''))
			});
		}

		const todayHydration = hydrationStore.items.filter((e) => {
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			return d === today;
		});
		if (todayHydration.length > 0) {
			const total = todayHydration.reduce((sum, e) => sum + (e.data.amount as number), 0);
			groups.push({
				key: 'hydration',
				label: t.dashboard.hydration,
				items: [`${t.hydration.water} ${total}ml`]
			});
		}

		return groups;
	});

	/* --- Habit Streaks Summary --- */
	const habitStreaks = $derived.by(() => {
		const habitEntries = store.items.filter((e) => e.type === 'habit');
		if (habitEntries.length === 0) return [];

		const habitTypes: { id: string; label: string }[] = [
			{ id: 'cold', label: t.dashboard.cold },
			{ id: 'sun', label: t.dashboard.sun },
			{ id: 'fasting', label: t.dashboard.fasting },
			{ id: 'meditation', label: t.dashboard.meditation },
			{ id: 'wimhof', label: t.dashboard.wimhof },
			{ id: 'ejaculation', label: t.dashboard.ejacControl }
		];

		const result: { label: string; streak: number }[] = [];
		const today = new Date();

		for (const h of habitTypes) {
			const dates = new Set(
				habitEntries
					.filter((e) => e.data.habit === h.id)
					.map((e) => (e.data.date as string) ?? e.createdAt.slice(0, 10))
			);
			if (dates.size === 0) continue;

			let current = 0;
			for (let i = 0; i < 365; i++) {
				const d = new Date(today);
				d.setDate(d.getDate() - i);
				const key = d.toISOString().slice(0, 10);
				if (dates.has(key)) {
					current++;
				} else {
					break;
				}
			}
			if (current > 0) result.push({ label: h.label, streak: current });
		}

		return result;
	});

	/* --- Sleep Quality Card --- */
	const sleepCard = $derived.by(() => {
		const weekAgo = new Date(Date.now() - 7 * 86400000).toISOString();
		const sleeps = store.items
			.filter((e) => e.type === 'signal.sleep' && e.createdAt >= weekAgo)
			.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt));
		if (sleeps.length === 0) return null;

		const hours = sleeps.map((e) => Number(e.data.hours));
		const quals = sleeps.map((e) => Number(e.data.quality));
		const avg = (arr: number[]) => +(arr.reduce((a, b) => a + b, 0) / arr.length).toFixed(1);

		return {
			avgHours: avg(hours),
			avgQuality: avg(quals),
			lastHours: hours[0],
			lastQuality: quals[0]
		};
	});

	/* --- Multi-Correlation System --- */
	const corrMetrics = $derived.by(() => [
		{ id: 'sleep_hours', label: t.dashboard.sleepHours, type: 'signal.sleep', field: 'hours' },
		{ id: 'sleep_quality', label: t.dashboard.sleepQuality, type: 'signal.sleep', field: 'quality' },
		{ id: 'mood', label: t.common.mood, type: 'checkin', field: 'mood' },
		{ id: 'energy', label: t.common.energy, type: 'checkin', field: 'energy' },
		{ id: 'stress', label: t.common.stress, type: 'checkin', field: 'stress' },
		{ id: 'training_vol', label: t.dashboard.trainingSessions, type: 'training', field: '_count' },
		{ id: 'supplement_count', label: t.dashboard.supplementsTaken, type: 'supplement', field: '_count' },
		{ id: 'habit_count', label: t.dashboard.habitsDone, type: 'habit', field: '_count' },
	] as const);

	let metricA = $state('sleep_hours');
	let metricB = $state('mood');

	function extractMetricByDate(metricId: string): Map<string, number> {
		const def = corrMetrics.find((m) => m.id === metricId);
		if (!def) return new Map();

		const result = new Map<string, number>();
		const all = store.items;

		if (def.field === '_count') {
			const counts = new Map<string, number>();
			for (const e of all) {
				const matchType = def.type === 'training' ? e.type.startsWith('training.') : e.type === def.type;
				if (!matchType) continue;
				const date = e.createdAt.slice(0, 10);
				counts.set(date, (counts.get(date) ?? 0) + 1);
			}
			return counts;
		}

		for (const e of all) {
			if (e.type !== def.type) continue;
			const date = e.createdAt.slice(0, 10);
			const val = Number(e.data[def.field]);
			if (!isNaN(val)) result.set(date, val);
		}
		return result;
	}

	const correlation = $derived.by(() => {
		const mapA = extractMetricByDate(metricA);
		const mapB = extractMetricByDate(metricB);

		const pairs: { x: number; y: number }[] = [];
		for (const [date, valA] of mapA) {
			const valB = mapB.get(date);
			if (valB !== undefined) pairs.push({ x: valA, y: valB });
		}

		if (pairs.length < 3) return { r: null, label: t.dashboard.notEnoughData, color: 'gray', pairs, n: pairs.length };

		const n = pairs.length;
		let sumX = 0, sumY = 0, sumXY = 0, sumX2 = 0, sumY2 = 0;
		for (const p of pairs) {
			sumX += p.x; sumY += p.y;
			sumXY += p.x * p.y;
			sumX2 += p.x * p.x;
			sumY2 += p.y * p.y;
		}
		const denom = Math.sqrt((n * sumX2 - sumX * sumX) * (n * sumY2 - sumY * sumY));
		if (denom === 0) return { r: 0, label: t.dashboard.noVariance, color: 'gray', pairs, n };

		const r = +((n * sumXY - sumX * sumY) / denom).toFixed(2);

		let label: string;
		let color: string;
		const absR = Math.abs(r);
		if (absR > 0.5) {
			color = 'green';
			label = r > 0 ? `${t.common.strong} ${t.common.positive}` : `${t.common.strong} ${t.common.negative}`;
		} else if (absR > 0.3) {
			color = 'amber';
			label = r > 0 ? `${t.common.moderate} ${t.common.positive}` : `${t.common.moderate} ${t.common.negative}`;
		} else {
			color = 'gray';
			label = t.common.weak;
		}

		return { r, label, color, pairs, n };
	});

	const scatterData = $derived.by(() => {
		const pairs = correlation?.pairs ?? [];
		if (pairs.length === 0) return { dots: [], trendline: null };

		const xs = pairs.map((p) => p.x);
		const ys = pairs.map((p) => p.y);
		const minX = Math.min(...xs), maxX = Math.max(...xs);
		const minY = Math.min(...ys), maxY = Math.max(...ys);
		const rangeX = maxX - minX || 1;
		const rangeY = maxY - minY || 1;

		const dots = pairs.map((p) => ({
			cx: 15 + ((p.x - minX) / rangeX) * 170,
			cy: 185 - ((p.y - minY) / rangeY) * 170
		}));

		// Linear regression for trendline
		const n = pairs.length;
		let sumX = 0, sumY = 0, sumXY = 0, sumX2 = 0;
		for (const p of pairs) {
			sumX += p.x; sumY += p.y;
			sumXY += p.x * p.y;
			sumX2 += p.x * p.x;
		}
		const denomSlope = n * sumX2 - sumX * sumX;
		if (denomSlope === 0) return { dots, trendline: null };

		const slope = (n * sumXY - sumX * sumY) / denomSlope;
		const intercept = (sumY - slope * sumX) / n;

		const y1 = slope * minX + intercept;
		const y2 = slope * maxX + intercept;

		const trendline = {
			x1: 15 + 0,
			y1: 185 - ((y1 - minY) / rangeY) * 170,
			x2: 15 + 170,
			y2: 185 - ((y2 - minY) / rangeY) * 170
		};

		return { dots, trendline };
	});

	const weightMoodLink = $derived.by(() => {
		const weights = store.items.filter(e => e.type === 'weight').toSorted((a, b) => a.createdAt.localeCompare(b.createdAt));
		const checkins = store.items.filter(e => e.type === 'checkin');
		if (weights.length < 5 || checkins.length < 5) return null;
		const moodByDate: Record<string, number> = {};
		for (const c of checkins) {
			const d = (c.data.date as string) ?? c.createdAt.slice(0, 10);
			moodByDate[d] = Number(c.data.mood);
		}
		const pairs: { w: number; m: number }[] = [];
		for (const e of weights) {
			const d = e.createdAt.slice(0, 10);
			if (moodByDate[d] !== undefined) pairs.push({ w: Number(e.data.weight), m: moodByDate[d] });
		}
		if (pairs.length < 3) return null;
		const last5 = weights.slice(-5);
		const first5 = weights.slice(0, 5);
		const avgLast = +(last5.reduce((s, e) => s + Number(e.data.weight), 0) / last5.length).toFixed(1);
		const avgFirst = +(first5.reduce((s, e) => s + Number(e.data.weight), 0) / first5.length).toFixed(1);
		const trend = avgLast > avgFirst ? t.dashboard.gaining : avgLast < avgFirst ? t.dashboard.losing : t.dashboard.stable;
		return { pairs: pairs.length, trend, avgFirst, avgLast, change: +(avgLast - avgFirst).toFixed(1) };
	});

	const seasonalPattern = $derived.by(() => {
		const checkins = store.items.filter(e => e.type === 'checkin');
		if (checkins.length < 30) return null;
		const months: Record<string, { moods: number[]; energies: number[] }> = {};
		for (const c of checkins) {
			const d = (c.data.date as string) ?? c.createdAt.slice(0, 10);
			const m = d.slice(0, 7);
			if (!months[m]) months[m] = { moods: [], energies: [] };
			months[m].moods.push(Number(c.data.mood));
			months[m].energies.push(Number(c.data.energy));
		}
		const sorted = Object.keys(months).toSorted().slice(-6);
		if (sorted.length < 3) return null;
		const avg = (a: number[]) => +(a.reduce((s, v) => s + v, 0) / a.length).toFixed(1);
		const data = sorted.map(m => ({
			month: new Date(m + '-15').toLocaleDateString(locale, { month: 'short' }),
			mood: avg(months[m].moods),
			energy: avg(months[m].energies),
			n: months[m].moods.length
		}));
		const maxVal = Math.max(...data.flatMap(d => [d.mood, d.energy]), 1);
		return { data, maxVal };
	});

	const healthIndex = $derived.by(() => {
		const now = new Date();
		const weekAgo = new Date(now.getTime() - 7 * 86400000).toISOString();
		const recent = store.items.filter(e => e.createdAt >= weekAgo);
		const checkins = recent.filter(e => e.type === 'checkin');
		const habits = recent.filter(e => e.type === 'habit');
		const trainings = recent.filter(e => e.type.startsWith('training.'));
		const supplements = recent.filter(e => e.type === 'supplement');
		if (checkins.length === 0) return null;
		const avg = (arr: number[]) => arr.length ? arr.reduce((s, v) => s + v, 0) / arr.length : 0;
		const moodScore = avg(checkins.map(e => Number(e.data.mood))) / 10;
		const energyScore = avg(checkins.map(e => Number(e.data.energy))) / 10;
		const stressScore = 1 - avg(checkins.map(e => Number(e.data.stress))) / 10;
		const habitDays = new Set(habits.map(e => (e.data.date as string) ?? e.createdAt.slice(0, 10))).size;
		const habitScore = Math.min(habitDays / 7, 1);
		const trainingDays = new Set(trainings.map(e => e.createdAt.slice(0, 10))).size;
		const trainingScore = Math.min(trainingDays / 4, 1);
		const suppDays = new Set(supplements.map(e => (e.data.date as string) ?? e.createdAt.slice(0, 10))).size;
		const suppScore = Math.min(suppDays / 7, 1);
		const composite = +((moodScore * 0.25 + energyScore * 0.2 + stressScore * 0.15 + habitScore * 0.15 + trainingScore * 0.15 + suppScore * 0.1) * 100).toFixed(0);
		return { composite, moodScore: +(moodScore * 100).toFixed(0), energyScore: +(energyScore * 100).toFixed(0), stressScore: +(stressScore * 100).toFixed(0), habitScore: +(habitScore * 100).toFixed(0), trainingScore: +(trainingScore * 100).toFixed(0) };
	});

	const radarData = $derived.by(() => {
		if (!healthIndex) return null;
		const CX = 100, CY = 100, R = 75;
		const axes = [
			{ label: t.common.mood, val: healthIndex.moodScore },
			{ label: t.common.energy, val: healthIndex.energyScore },
			{ label: t.common.stress, val: healthIndex.stressScore },
			{ label: t.dashboard.habitsScore, val: healthIndex.habitScore },
			{ label: t.dashboard.trainingScore, val: healthIndex.trainingScore }
		];
		const N = axes.length;
		const step = (2 * Math.PI) / N;
		const start = -Math.PI / 2;
		const pt = (i: number, r: number) => `${CX + r * Math.cos(start + i * step)},${CY + r * Math.sin(start + i * step)}`;
		const grids = [25, 50, 75, 100].map(lv => axes.map((_, i) => pt(i, (lv / 100) * R)).join(' '));
		const axisEnds = axes.map((_, i) => ({ x2: CX + R * Math.cos(start + i * step), y2: CY + R * Math.sin(start + i * step) }));
		const dataPoints = axes.map((a, i) => pt(i, (a.val / 100) * R)).join(' ');
		const labels = axes.map((a, i) => {
			const lr = R + 18;
			return { x: CX + lr * Math.cos(start + i * step), y: CY + lr * Math.sin(start + i * step), text: a.label, val: a.val };
		});
		return { grids, axisEnds, dataPoints, labels, CX, CY };
	});
</script>

<svelte:head>
  <title>{t.dashboard.title} | Darink</title>
</svelte:head>

<PageHeader title={t.dashboard.title} />

<section class="period-selector">
	<button class="period-chip" class:active={period === 'week'} onclick={() => period = 'week'}>{t.dashboard.week}</button>
	<button class="period-chip" class:active={period === 'month'} onclick={() => period = 'month'}>{t.dashboard.month}</button>
	<button class="period-chip" class:active={period === '3month'} onclick={() => period = '3month'}>{t.dashboard.threeMonths}</button>
</section>

{#if stats.total === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
	<p>{t.dashboard.welcome}</p>
	<p class="empty-hint">{t.dashboard.welcomeHint}</p>
</div>
{:else}

{#if weekSummary.entries > 0}
<section class="summary">
	<h2>{t.common.thisWeek}</h2>
	<div class="summary-row">
		<div class="chip">{weekSummary.entries} {t.common.entries}</div>
		{#if weekSummary.avgMood}<div class="chip">{t.common.mood} {weekSummary.avgMood}</div>{/if}
		{#if weekSummary.avgEnergy}<div class="chip">{t.common.energy} {weekSummary.avgEnergy}</div>{/if}
		{#if weekSummary.avgSleep}<div class="chip">{t.common.sleep} {weekSummary.avgSleep}h</div>{/if}
	</div>
</section>
{/if}

{#if periodComparison.entries.current > 0 || periodComparison.entries.previous > 0}
<section class="comparison-section">
	<h2>{t.dashboard.periodComparison}</h2>
	<div class="comparison-grid">
		<div class="cmp-header">{t.dashboard.metric}</div>
		<div class="cmp-header">{periodLabels.current}</div>
		<div class="cmp-header">{periodLabels.previous}</div>
		<div class="cmp-header">{t.dashboard.delta}</div>

		{#if true}
			{@const eDelta = periodComparison.entries.current - periodComparison.entries.previous}
			<div class="cmp-metric">{t.dashboard.entries}</div>
			<div class="cmp-val">{periodComparison.entries.current}</div>
			<div class="cmp-val">{periodComparison.entries.previous}</div>
			<div class="cmp-delta {eDelta > 0 ? 'positive' : eDelta < 0 ? 'negative' : ''}">
				{#if eDelta !== 0}
					{eDelta > 0 ? '+' : ''}{eDelta}
					{#if eDelta > 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 2L10 7H2Z" fill="currentColor"/></svg>{:else}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 10L2 5H10Z" fill="currentColor"/></svg>{/if}
				{:else}
					--
				{/if}
			</div>
		{/if}

		{#if periodComparison.mood.current != null || periodComparison.mood.previous != null}
			{@const mC = periodComparison.mood.current ?? 0}
			{@const mP = periodComparison.mood.previous ?? 0}
			{@const mDelta = +(mC - mP).toFixed(1)}
			<div class="cmp-metric">{t.common.mood}</div>
			<div class="cmp-val">{periodComparison.mood.current ?? '--'}</div>
			<div class="cmp-val">{periodComparison.mood.previous ?? '--'}</div>
			<div class="cmp-delta {mDelta > 0 ? 'positive' : mDelta < 0 ? 'negative' : ''}">
				{#if periodComparison.mood.current != null && periodComparison.mood.previous != null}
					{mDelta > 0 ? '+' : ''}{mDelta}
					{#if mDelta > 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 2L10 7H2Z" fill="currentColor"/></svg>{:else if mDelta < 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 10L2 5H10Z" fill="currentColor"/></svg>{/if}
				{:else}
					--
				{/if}
			</div>
		{/if}

		{#if periodComparison.energy.current != null || periodComparison.energy.previous != null}
			{@const eC = periodComparison.energy.current ?? 0}
			{@const eP = periodComparison.energy.previous ?? 0}
			{@const enDelta = +(eC - eP).toFixed(1)}
			<div class="cmp-metric">{t.common.energy}</div>
			<div class="cmp-val">{periodComparison.energy.current ?? '--'}</div>
			<div class="cmp-val">{periodComparison.energy.previous ?? '--'}</div>
			<div class="cmp-delta {enDelta > 0 ? 'positive' : enDelta < 0 ? 'negative' : ''}">
				{#if periodComparison.energy.current != null && periodComparison.energy.previous != null}
					{enDelta > 0 ? '+' : ''}{enDelta}
					{#if enDelta > 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 2L10 7H2Z" fill="currentColor"/></svg>{:else if enDelta < 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 10L2 5H10Z" fill="currentColor"/></svg>{/if}
				{:else}
					--
				{/if}
			</div>
		{/if}

		{#if true}
			{@const tDelta = periodComparison.training.current - periodComparison.training.previous}
			<div class="cmp-metric">{t.dashboard.training}</div>
			<div class="cmp-val">{periodComparison.training.current}</div>
			<div class="cmp-val">{periodComparison.training.previous}</div>
			<div class="cmp-delta {tDelta > 0 ? 'positive' : tDelta < 0 ? 'negative' : ''}">
				{#if tDelta !== 0}
					{tDelta > 0 ? '+' : ''}{tDelta}
					{#if tDelta > 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 2L10 7H2Z" fill="currentColor"/></svg>{:else}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 10L2 5H10Z" fill="currentColor"/></svg>{/if}
				{:else}
					--
				{/if}
			</div>
		{/if}

		{#if true}
			{@const hDelta = periodComparison.habitDays.current - periodComparison.habitDays.previous}
			<div class="cmp-metric">{t.dashboard.habitDays}</div>
			<div class="cmp-val">{periodComparison.habitDays.current}</div>
			<div class="cmp-val">{periodComparison.habitDays.previous}</div>
			<div class="cmp-delta {hDelta > 0 ? 'positive' : hDelta < 0 ? 'negative' : ''}">
				{#if hDelta !== 0}
					{hDelta > 0 ? '+' : ''}{hDelta}
					{#if hDelta > 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 2L10 7H2Z" fill="currentColor"/></svg>{:else}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 10L2 5H10Z" fill="currentColor"/></svg>{/if}
				{:else}
					--
				{/if}
			</div>
		{/if}
	</div>
</section>
{/if}

<section class="year-ago-section">
	<h2>{t.dashboard.vsLastYear}</h2>
	{#if !yearAgoComparison.hasData}
	<div class="year-ago-card year-ago-empty">
		<p>{t.dashboard.noDataFrom} {yearAgoComparison.yearLabel}</p>
	</div>
	{:else}
	<div class="year-ago-card">
		<div class="year-ago-grid">
			{#if true}
				{@const eCur = yearAgoComparison.entries.current}
				{@const eYa = yearAgoComparison.entries.yearAgo}
				{@const ePct = eYa > 0 ? Math.round(((eCur - eYa) / eYa) * 100) : null}
				<div class="ya-metric">
					<span class="ya-label">{t.dashboard.entries}</span>
					<span class="ya-values">
						{eYa}
						<svg class="ya-arrow-sep" viewBox="0 0 16 12" width="16" height="12"><path d="M2 6H14M10 2L14 6L10 10" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
						{eCur}
					</span>
					<span class="ya-delta {eCur > eYa ? 'positive' : eCur < eYa ? 'negative' : ''}">
						{#if ePct !== null}
							{ePct > 0 ? '+' : ''}{ePct}%
							{#if eCur > eYa}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 2L10 7H2Z" fill="currentColor"/></svg>{:else if eCur < eYa}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 10L2 5H10Z" fill="currentColor"/></svg>{/if}
						{:else}
							--
						{/if}
					</span>
				</div>
			{/if}

			{#if yearAgoComparison.mood.current != null || yearAgoComparison.mood.yearAgo != null}
				{@const mCur = yearAgoComparison.mood.current ?? 0}
				{@const mYa = yearAgoComparison.mood.yearAgo ?? 0}
				{@const mDelta = +(mCur - mYa).toFixed(1)}
				<div class="ya-metric">
					<span class="ya-label">{t.common.mood}</span>
					<span class="ya-values">
						{yearAgoComparison.mood.yearAgo ?? '--'}
						<svg class="ya-arrow-sep" viewBox="0 0 16 12" width="16" height="12"><path d="M2 6H14M10 2L14 6L10 10" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
						{yearAgoComparison.mood.current ?? '--'}
					</span>
					<span class="ya-delta {mDelta > 0 ? 'positive' : mDelta < 0 ? 'negative' : ''}">
						{#if yearAgoComparison.mood.current != null && yearAgoComparison.mood.yearAgo != null}
							{mDelta > 0 ? '+' : ''}{mDelta}
							{#if mDelta > 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 2L10 7H2Z" fill="currentColor"/></svg>{:else if mDelta < 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 10L2 5H10Z" fill="currentColor"/></svg>{/if}
						{:else}
							--
						{/if}
					</span>
				</div>
			{/if}

			{#if yearAgoComparison.energy.current != null || yearAgoComparison.energy.yearAgo != null}
				{@const eCur = yearAgoComparison.energy.current ?? 0}
				{@const eYa = yearAgoComparison.energy.yearAgo ?? 0}
				{@const enDelta = +(eCur - eYa).toFixed(1)}
				<div class="ya-metric">
					<span class="ya-label">{t.common.energy}</span>
					<span class="ya-values">
						{yearAgoComparison.energy.yearAgo ?? '--'}
						<svg class="ya-arrow-sep" viewBox="0 0 16 12" width="16" height="12"><path d="M2 6H14M10 2L14 6L10 10" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
						{yearAgoComparison.energy.current ?? '--'}
					</span>
					<span class="ya-delta {enDelta > 0 ? 'positive' : enDelta < 0 ? 'negative' : ''}">
						{#if yearAgoComparison.energy.current != null && yearAgoComparison.energy.yearAgo != null}
							{enDelta > 0 ? '+' : ''}{enDelta}
							{#if enDelta > 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 2L10 7H2Z" fill="currentColor"/></svg>{:else if enDelta < 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 10L2 5H10Z" fill="currentColor"/></svg>{/if}
						{:else}
							--
						{/if}
					</span>
				</div>
			{/if}

			{#if yearAgoComparison.weight.current != null && yearAgoComparison.weight.yearAgo != null}
				{@const wDelta = +(yearAgoComparison.weight.current - yearAgoComparison.weight.yearAgo).toFixed(1)}
				<div class="ya-metric">
					<span class="ya-label">{t.dashboard.weight}</span>
					<span class="ya-values">
						{yearAgoComparison.weight.yearAgo}kg
						<svg class="ya-arrow-sep" viewBox="0 0 16 12" width="16" height="12"><path d="M2 6H14M10 2L14 6L10 10" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
						{yearAgoComparison.weight.current}kg
					</span>
					<span class="ya-delta {wDelta < 0 ? 'positive' : wDelta > 0 ? 'negative' : ''}">
						{wDelta > 0 ? '+' : ''}{wDelta}kg
						{#if wDelta < 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 2L10 7H2Z" fill="currentColor"/></svg>{:else if wDelta > 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 10L2 5H10Z" fill="currentColor"/></svg>{/if}
					</span>
				</div>
			{/if}

			{#if yearAgoComparison.bodyFat.current != null && yearAgoComparison.bodyFat.yearAgo != null}
				{@const bfDelta = +(yearAgoComparison.bodyFat.current - yearAgoComparison.bodyFat.yearAgo).toFixed(1)}
				<div class="ya-metric">
					<span class="ya-label">{t.dashboard.bodyFat}</span>
					<span class="ya-values">
						{yearAgoComparison.bodyFat.yearAgo}%
						<svg class="ya-arrow-sep" viewBox="0 0 16 12" width="16" height="12"><path d="M2 6H14M10 2L14 6L10 10" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
						{yearAgoComparison.bodyFat.current}%
					</span>
					<span class="ya-delta {bfDelta < 0 ? 'positive' : bfDelta > 0 ? 'negative' : ''}">
						{bfDelta > 0 ? '+' : ''}{bfDelta}%
						{#if bfDelta < 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 2L10 7H2Z" fill="currentColor"/></svg>{:else if bfDelta > 0}<svg class="arrow-icon" viewBox="0 0 12 12"><path d="M6 10L2 5H10Z" fill="currentColor"/></svg>{/if}
					</span>
				</div>
			{/if}
		</div>
	</div>
	{/if}
</section>

<section class="chart-section">
	<h2>{t.dashboard.activity7d}</h2>
	<div class="bar-chart">
		{#each weeklyActivity as day}
			{@const maxAct = Math.max(...weeklyActivity.map((d) => d.count), 1)}
			<div class="bar-col">
				<div class="bar" style="height: {(day.count / maxAct) * 100}%">
					{#if day.count > 0}<span class="bar-val">{day.count}</span>{/if}
				</div>
				<span class="bar-label">{day.label}</span>
			</div>
		{/each}
	</div>
</section>

{#if moodTrend.length > 1}
{@const chartW = 280 / Math.max(moodTrend.length - 1, 1)}
<section class="chart-section">
	<h2>{t.dashboard.moodEnergy14}</h2>
	<svg class="line-chart" viewBox="0 0 280 100" preserveAspectRatio="none">
		<polyline
			fill="none" stroke="var(--c-accent)" stroke-width="2" stroke-linejoin="round"
			points={moodTrend.map((p, i) => `${i * chartW},${100 - p.mood * 10}`).join(' ')}
		/>
		<polyline
			fill="none" stroke="var(--c-done)" stroke-width="2" stroke-linejoin="round" stroke-dasharray="4 2"
			points={moodTrend.map((p, i) => `${i * chartW},${100 - p.energy * 10}`).join(' ')}
		/>
	</svg>
	<div class="legend">
		<span class="dot mood"></span> {t.common.mood}
		<span class="dot energy"></span> {t.common.energy}
	</div>
</section>
{/if}

{#if todayLog.length > 0}
<section class="today-log">
	<h2>{t.dashboard.todayLog}</h2>
	{#each todayLog as group}
	<div class="log-group">
		<h3>{group.label}</h3>
		<div class="log-chips">
			{#each group.items as item}
			<span class="log-chip">{item}</span>
			{/each}
		</div>
	</div>
	{/each}
</section>
{/if}

{#if habitStreaks.length > 0}
<section class="streaks-summary">
	<h2>{t.dashboard.habitStreaks}</h2>
	<div class="streaks-row">
		{#each habitStreaks as hs}
		<span class="streak-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; display: inline-block; margin-right: 2px;"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg> {hs.label} {hs.streak}d</span>
		{/each}
	</div>
</section>
{/if}

{#if sleepCard}
<section class="sleep-card-section">
	<h2>{t.dashboard.sleep7d}</h2>
	<div class="sleep-card">
		<div class="sleep-stat">
			<span class="sleep-val">{sleepCard.avgHours}h</span>
			<span class="sleep-lbl">{t.dashboard.avgHours}</span>
		</div>
		<div class="sleep-stat">
			<span class="sleep-val">{sleepCard.avgQuality}/10</span>
			<span class="sleep-lbl">{t.dashboard.avgQuality}</span>
		</div>
		<div class="sleep-stat">
			<span class="sleep-val">{sleepCard.lastHours}h</span>
			<span class="sleep-lbl">{t.dashboard.lastNight}</span>
		</div>
		<div class="sleep-stat">
			<span class="sleep-val">{sleepCard.lastQuality}/10</span>
			<span class="sleep-lbl">{t.dashboard.lastQuality}</span>
		</div>
	</div>
</section>
{/if}

<section class="correlation-section">
	<h2>{t.dashboard.correlationExplorer}</h2>
	<div class="corr-selects">
		<select class="corr-select" bind:value={metricA}>
			{#each corrMetrics as m}
			<option value={m.id}>{m.label}</option>
			{/each}
		</select>
		<span class="corr-vs">vs</span>
		<select class="corr-select" bind:value={metricB}>
			{#each corrMetrics as m}
			<option value={m.id}>{m.label}</option>
			{/each}
		</select>
	</div>
	{#if correlation}
		{#if correlation.r !== null}
		<div class="corr-card corr-{correlation.color}">
			<span class="corr-indicator"></span>
			<span class="corr-text">
				r = {correlation.r > 0 ? '+' : ''}{correlation.r}
				<span class="corr-label">({correlation.label})</span>
			</span>
			<span class="corr-n">N = {correlation.n} {t.dashboard.matchingDays}</span>
		</div>
		{:else}
		<div class="corr-card corr-gray">
			<span class="corr-indicator"></span>
			<span>{correlation.label} ({t.dashboard.needDays})</span>
		</div>
		{/if}
		{#if scatterData.dots.length > 0}
			{@const labelA = corrMetrics.find((m) => m.id === metricA)?.label ?? ''}
			{@const labelB = corrMetrics.find((m) => m.id === metricB)?.label ?? ''}
		<div class="scatter-wrap">
			<svg class="scatter-plot" viewBox="0 0 200 200">
				<!-- Axes -->
				<line x1="15" y1="185" x2="185" y2="185" stroke="var(--c-border)" stroke-width="1" />
				<line x1="15" y1="15" x2="15" y2="185" stroke="var(--c-border)" stroke-width="1" />
				<!-- Axis labels -->
				<text x="100" y="198" text-anchor="middle" font-size="8" fill="var(--c-text-muted)">{labelA}</text>
				<text x="6" y="100" text-anchor="middle" font-size="8" fill="var(--c-text-muted)" transform="rotate(-90, 6, 100)">{labelB}</text>
				<!-- Data points -->
				{#each scatterData.dots as dot}
				<circle cx={dot.cx} cy={dot.cy} r="3" fill="var(--c-accent)" opacity="0.7" />
				{/each}
				<!-- Trendline -->
				{#if scatterData.trendline}
				<line
					x1={scatterData.trendline.x1} y1={scatterData.trendline.y1}
					x2={scatterData.trendline.x2} y2={scatterData.trendline.y2}
					stroke="var(--c-accent)" stroke-width="1.5" stroke-dasharray="4 2" opacity="0.6"
				/>
				{/if}
			</svg>
		</div>
		{/if}
	{/if}
</section>

<section class="heatmap-section">
	<h2>{t.dashboard.activity}</h2>
	<div class="heatmap-filter">
		<select class="heatmap-select" bind:value={heatmapFilter}>
			<option value={null}>{t.dashboard.all}</option>
			{#each heatmapTypes as ht}
				<option value={ht}>{ht.charAt(0).toUpperCase() + ht.slice(1)}</option>
			{/each}
		</select>
	</div>
	<HeatmapCalendar items={filteredHeatmapItems} typeFilter={heatmapFilter} />
</section>

<section class="stats">
	<div class="stat highlight">
		<span class="value">{stats.today}</span>
		<span class="label">{t.common.today}</span>
	</div>
	<div class="stat"><span class="value">{stats.checkins}</span><span class="label">{t.dashboard.checkins}</span></div>
	<div class="stat"><span class="value">{stats.intakes}</span><span class="label">{t.dashboard.intakes}</span></div>
	<div class="stat"><span class="value">{stats.trainings}</span><span class="label">{t.dashboard.training}</span></div>
	<div class="stat"><span class="value">{stats.habits}</span><span class="label">{t.dashboard.habits}</span></div>
	<div class="stat"><span class="value">{stats.supplements}</span><span class="label">{t.dashboard.supplements}</span></div>
	<div class="stat"><span class="value">{stats.experiments}</span><span class="label">{t.dashboard.experiments}</span></div>
	<div class="stat total"><span class="value">{stats.total}</span><span class="label">{t.dashboard.totalEntries}</span></div>
</section>

{#if healthIndex}
<section class="hi-section">
	<h2>{t.dashboard.healthIndex} (7d)</h2>
	<div class="hi-gauge">
		<span class="hi-value" style="color:{healthIndex.composite >= 70 ? '#38a169' : healthIndex.composite >= 40 ? '#e8a735' : '#e53e3e'}">{healthIndex.composite}</span>
		<span class="hi-max">/100</span>
	</div>
	{#if radarData}
	<div class="hi-radar">
		<svg viewBox="0 0 200 200" class="radar-svg">
			{#each radarData.grids as poly}
				<polygon points={poly} fill="none" stroke="var(--c-border)" stroke-width="0.5" />
			{/each}
			{#each radarData.axisEnds as line}
				<line x1={radarData.CX} y1={radarData.CY} x2={line.x2} y2={line.y2} stroke="var(--c-border)" stroke-width="0.5" />
			{/each}
			<polygon points={radarData.dataPoints} fill="var(--c-accent)" fill-opacity="0.2" stroke="var(--c-accent)" stroke-width="1.5" />
			{#each radarData.labels as label}
				<text x={label.x} y={label.y} text-anchor="middle" dominant-baseline="middle" font-size="8" fill="var(--c-text-muted)">{label.text} {label.val}</text>
			{/each}
		</svg>
	</div>
	{/if}
	<div class="hi-breakdown">
		{#each [
			{ label: t.common.mood, val: healthIndex.moodScore },
			{ label: t.common.energy, val: healthIndex.energyScore },
			{ label: t.common.stress, val: healthIndex.stressScore },
			{ label: t.dashboard.habitsScore, val: healthIndex.habitScore },
			{ label: t.dashboard.trainingScore, val: healthIndex.trainingScore }
		] as metric}
			<div class="hi-row">
				<span class="hi-label">{metric.label}</span>
				<div class="hi-bar-bg"><div class="hi-bar-fill" style="width:{metric.val}%;background:{metric.val >= 70 ? '#38a169' : metric.val >= 40 ? '#e8a735' : '#e53e3e'}"></div></div>
				<span class="hi-val">{metric.val}</span>
			</div>
		{/each}
	</div>
</section>
{/if}

{#if seasonalPattern}
<section class="seasonal-section">
	<h2>{t.dashboard.seasonalLast6}</h2>
	<div class="seasonal-chart">
		{#each seasonalPattern.data as m}
			<div class="seasonal-col">
				<div class="seasonal-bars">
					<div class="seasonal-bar mood" style="height:{(m.mood / seasonalPattern.maxVal) * 100}%"></div>
					<div class="seasonal-bar energy" style="height:{(m.energy / seasonalPattern.maxVal) * 100}%"></div>
				</div>
				<span class="seasonal-label">{m.month}</span>
			</div>
		{/each}
	</div>
	<div class="seasonal-legend">
		<span><span class="dot" style="background:#4aa3ff"></span> {t.common.mood}</span>
		<span><span class="dot" style="background:#2e8b57"></span> {t.common.energy}</span>
	</div>
</section>
{/if}

{#if weightMoodLink}
<section class="wm-section">
	<h2>{t.dashboard.weightTrajectory}</h2>
	<div class="wm-cards">
		<div class="wm-card">
			<span class="wm-value">{weightMoodLink.avgFirst}kg</span>
			<span class="wm-label">{t.dashboard.first5avg}</span>
		</div>
		<div class="wm-card">
			<span class="wm-value">{weightMoodLink.avgLast}kg</span>
			<span class="wm-label">{t.dashboard.last5avg}</span>
		</div>
		<div class="wm-card">
			<span class="wm-value" style="color:{weightMoodLink.change === 0 ? 'var(--c-text-muted)' : weightMoodLink.change > 0 ? '#e8a735' : '#38a169'}">{weightMoodLink.change > 0 ? '+' : ''}{weightMoodLink.change}kg</span>
			<span class="wm-label">{weightMoodLink.trend}</span>
		</div>
	</div>
</section>
{/if}

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

	/* Period Selector */
	.period-selector {
		display: flex;
		gap: 0.5rem;
		padding: 0 1rem 1rem;
	}
	.period-chip {
		flex: 1;
		padding: 0.4rem 0.75rem;
		border: 1px solid var(--c-border);
		border-radius: 20px;
		background: var(--c-bg-card);
		color: var(--c-text-muted);
		font-size: 0.85rem;
		font-weight: 500;
		cursor: pointer;
		text-align: center;
		transition: all 0.15s;
	}
	.period-chip.active {
		background: var(--c-accent);
		color: #fff;
		border-color: var(--c-accent);
	}

	/* Comparison Grid */
	.comparison-section {
		padding: 0 1rem 1.5rem;
	}
	.comparison-grid {
		display: grid;
		grid-template-columns: 1.2fr 1fr 1fr 1fr;
		gap: 0;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		overflow: hidden;
	}
	.comparison-grid > div {
		padding: 0.5rem 0.6rem;
		font-size: 0.8rem;
		border-bottom: 1px solid var(--c-border);
	}
	.comparison-grid > div:nth-last-child(-n+4) {
		border-bottom: none;
	}
	.cmp-header {
		font-weight: 600;
		font-size: 0.7rem;
		text-transform: uppercase;
		color: var(--c-text-muted);
		letter-spacing: 0.03em;
		background: var(--c-bg);
	}
	.cmp-metric {
		font-weight: 500;
	}
	.cmp-val {
		text-align: center;
		font-variant-numeric: tabular-nums;
	}
	.cmp-delta {
		text-align: center;
		font-weight: 600;
		font-variant-numeric: tabular-nums;
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 0.2rem;
	}
	.cmp-delta.positive {
		color: #22c55e;
	}
	.cmp-delta.negative {
		color: #ef4444;
	}
	.arrow-icon {
		width: 10px;
		height: 10px;
		flex-shrink: 0;
	}

	/* Year Ago Comparison */
	.year-ago-section {
		padding: 0 1rem 1.5rem;
	}
	.year-ago-card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-left: 3px solid #d4a017;
		border-radius: var(--radius);
		padding: 0.75rem 1rem;
	}
	.year-ago-empty {
		color: var(--c-text-muted);
		font-size: 0.85rem;
	}
	.year-ago-empty p {
		margin: 0;
	}
	.year-ago-grid {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 0.75rem 1.5rem;
	}
	.ya-metric {
		display: flex;
		flex-direction: column;
		gap: 0.15rem;
	}
	.ya-label {
		font-size: 0.7rem;
		font-weight: 600;
		text-transform: uppercase;
		color: var(--c-text-muted);
		letter-spacing: 0.03em;
	}
	.ya-values {
		font-size: 0.9rem;
		font-weight: 500;
		font-variant-numeric: tabular-nums;
		display: flex;
		align-items: center;
		gap: 0.3rem;
	}
	.ya-arrow-sep {
		color: var(--c-text-muted);
		flex-shrink: 0;
	}
	.ya-delta {
		font-size: 0.75rem;
		font-weight: 600;
		font-variant-numeric: tabular-nums;
		display: flex;
		align-items: center;
		gap: 0.2rem;
	}
	.ya-delta.positive {
		color: #22c55e;
	}
	.ya-delta.negative {
		color: #ef4444;
	}

	.summary {
		padding: 0 1rem 1rem;
	}
	.summary-row {
		display: flex;
		gap: 0.5rem;
		flex-wrap: wrap;
	}
	.chip {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: 20px;
		padding: 0.35rem 0.75rem;
		font-size: 0.85rem;
		font-weight: 500;
	}

	.chart-section {
		padding: 0 1rem 1.5rem;
	}

	.bar-chart {
		display: flex;
		align-items: flex-end;
		gap: 0.25rem;
		height: 120px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem 0.5rem 0;
	}
	.bar-col {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		height: 100%;
		justify-content: flex-end;
	}
	.bar {
		width: 100%;
		max-width: 32px;
		background: var(--c-accent);
		border-radius: 3px 3px 0 0;
		min-height: 2px;
		display: flex;
		align-items: flex-start;
		justify-content: center;
		transition: height 0.3s;
	}
	.bar-val {
		font-size: 0.65rem;
		font-weight: 600;
		color: #fff;
		margin-top: 2px;
	}
	.bar-label {
		font-size: 0.7rem;
		color: var(--c-text-muted);
		margin-top: 0.25rem;
	}

	.line-chart {
		width: 100%;
		height: 100px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.5rem;
	}
	.legend {
		display: flex;
		gap: 1rem;
		font-size: 0.75rem;
		color: var(--c-text-muted);
		margin-top: 0.25rem;
		align-items: center;
	}
	.dot {
		display: inline-block;
		width: 8px;
		height: 8px;
		border-radius: 50%;
	}
	.dot.mood { background: var(--c-accent); }
	.dot.energy { background: var(--c-done); }

	/* Today's Log */
	.today-log {
		padding: 0 1rem 1rem;
	}
	.log-group {
		margin-bottom: 0.75rem;
	}
	.log-group h3 {
		font-size: 0.8rem;
		color: var(--c-text-muted);
		margin-bottom: 0.25rem;
		font-weight: 500;
	}
	.log-chips {
		display: flex;
		flex-wrap: wrap;
		gap: 0.25rem;
	}
	.log-chip {
		display: inline-block;
		padding: 0.25rem 0.6rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: 16px;
		font-size: 0.8rem;
	}

	/* Habit Streaks */
	.streaks-summary {
		padding: 0 1rem 1.5rem;
	}
	.streaks-row {
		display: flex;
		flex-wrap: wrap;
		gap: 0.5rem;
		align-items: center;
	}
	.streak-item {
		font-size: 0.85rem;
		font-weight: 500;
		white-space: nowrap;
	}

	/* Sleep Card */
	.sleep-card-section {
		padding: 0 1rem 1.5rem;
	}
	.sleep-card {
		display: grid;
		grid-template-columns: repeat(4, 1fr);
		gap: 0.5rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem;
	}
	.sleep-stat {
		text-align: center;
	}
	.sleep-val {
		display: block;
		font-size: 1.15rem;
		font-weight: 700;
	}
	.sleep-lbl {
		font-size: 0.7rem;
		color: var(--c-text-muted);
	}

	/* Correlation Explorer */
	.correlation-section {
		padding: 0 1rem 1.5rem;
	}
	.corr-selects {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		margin-bottom: 0.75rem;
	}
	.corr-select {
		flex: 1;
		padding: 0.4rem 0.5rem;
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		background: var(--c-bg-card);
		color: var(--c-text);
		font-size: 0.8rem;
		font-family: inherit;
	}
	.corr-vs {
		font-size: 0.75rem;
		color: var(--c-text-muted);
		font-weight: 600;
		text-transform: uppercase;
		flex-shrink: 0;
	}
	.corr-card {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		flex-wrap: wrap;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem 1rem;
		font-size: 0.85rem;
	}
	.corr-text {
		font-weight: 600;
		font-variant-numeric: tabular-nums;
	}
	.corr-label {
		font-weight: 400;
	}
	.corr-n {
		margin-left: auto;
		font-size: 0.75rem;
		color: var(--c-text-muted);
	}
	.corr-indicator {
		display: inline-block;
		width: 10px;
		height: 10px;
		border-radius: 50%;
		flex-shrink: 0;
	}
	.corr-green .corr-indicator { background: #22c55e; }
	.corr-amber .corr-indicator { background: #f59e0b; }
	.corr-gray .corr-indicator { background: var(--c-text-muted); }
	.scatter-wrap {
		margin-top: 0.75rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.5rem;
	}
	.scatter-plot {
		width: 100%;
		max-width: 300px;
		display: block;
		margin: 0 auto;
	}

	/* Heatmap Section */
	.heatmap-section {
		padding: 0 1rem 1.5rem;
	}
	.heatmap-filter {
		margin-bottom: 0.5rem;
	}
	.heatmap-select {
		padding: 0.4rem 0.5rem;
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		background: var(--c-bg-card);
		color: var(--c-text);
		font-size: 0.8rem;
		font-family: inherit;
	}

	.stats {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 0.5rem;
		padding: 0 1rem;
	}
	.stat {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 1rem;
		text-align: center;
	}
	.stat.highlight {
		grid-column: 1 / -1;
		background: var(--c-accent-bg);
		border-color: var(--c-accent);
	}
	.stat.total { grid-column: 1 / -1; }
	.value { display: block; font-size: 1.5rem; font-weight: 700; }
	.label { font-size: 0.8rem; color: var(--c-text-muted); }

	@media (min-width: 600px) {
		.stats { grid-template-columns: repeat(3, 1fr); }
		.stat.highlight, .stat.total { grid-column: 1 / -1; }
		.value { font-size: 2rem; }
	}
	@media (min-width: 900px) {
		.stats { grid-template-columns: repeat(4, 1fr); gap: 0.75rem; }
		.stat { padding: 1.25rem; }
		.value { font-size: 2.25rem; }
		.bar-chart { height: 160px; }
	}
	@media (max-width: 359px) {
		.stats { grid-template-columns: 1fr; }
		.stat.highlight, .stat.total { grid-column: auto; }
	}

	/* Health Index */
	.hi-section { padding: 1.5rem 1rem 0; }
	.hi-gauge { text-align: center; margin-bottom: 0.75rem; }
	.hi-value { font-size: 3rem; font-weight: 700; }
	.hi-max { font-size: 1rem; color: var(--c-text-muted); }
	.hi-breakdown { display: flex; flex-direction: column; gap: 0.4rem; }
	.hi-row { display: flex; align-items: center; gap: 0.5rem; }
	.hi-label { width: 4rem; font-size: 0.75rem; color: var(--c-text-muted); text-align: right; }
	.hi-bar-bg { flex: 1; height: 10px; background: var(--c-border); border-radius: 5px; overflow: hidden; }
	.hi-bar-fill { height: 100%; border-radius: 5px; transition: width 0.3s; }
	.hi-val { width: 2rem; font-size: 0.8rem; font-weight: 600; }
	.hi-radar { display: flex; justify-content: center; margin: 0.5rem 0; }
	.radar-svg { width: 220px; height: 220px; }

	/* Seasonal */
	.seasonal-section { padding: 1.5rem 1rem 0; }
	.seasonal-chart { display: flex; align-items: flex-end; gap: 0.5rem; height: 120px; padding: 0.5rem; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); }
	.seasonal-col { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; }
	.seasonal-bars { display: flex; gap: 2px; align-items: flex-end; width: 100%; height: 100%; }
	.seasonal-bar { flex: 1; border-radius: 3px 3px 0 0; min-height: 4px; transition: height 0.3s; }
	.seasonal-bar.mood { background: #4aa3ff; }
	.seasonal-bar.energy { background: #2e8b57; }
	.seasonal-label { font-size: 0.65rem; color: var(--c-text-muted); margin-top: 4px; }
	.seasonal-legend { display: flex; gap: 1rem; font-size: 0.75rem; color: var(--c-text-muted); margin-top: 0.25rem; }
	.dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }

	/* Weight-mood */
	.wm-section { padding: 1.5rem 1rem 0; }
	.wm-cards { display: flex; gap: 0.5rem; }
	.wm-card { flex: 1; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; text-align: center; display: flex; flex-direction: column; gap: 0.15rem; }
	.wm-value { font-size: 1.3rem; font-weight: 700; }
	.wm-label { font-size: 0.7rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; }
</style>
