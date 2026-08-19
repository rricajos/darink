<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import { useEntries } from '$lib/stores/entries.svelte';
	import { ui } from '$lib/db';
	import type { Entry } from '$lib/db';

	const store = useEntries();

	/* ---------- Period selector ---------- */
	let period = $state<30 | 60 | 90>(30);

	/* ---------- Correlation matrix state ---------- */
	let selectedCell = $state<{ row: number; col: number } | null>(null);

	/* ---------- Rolling averages toggles ---------- */
	let showScore = $state(true);
	let showMood = $state(true);
	let showEnergy = $state(true);
	let showSleep = $state(true);

	/* ---------- Helpers ---------- */
	const defaultHabits = ['cold', 'sun', 'fasting', 'meditation', 'wimhof', 'ejaculation'];

	function allHabitIds(): string[] {
		const custom = ui.get().customHabits;
		const ids = [...defaultHabits];
		if (Array.isArray(custom)) {
			for (const h of custom as Array<{ id: string }>) {
				if (h.id && !ids.includes(h.id)) ids.push(h.id);
			}
		}
		return ids;
	}

	function supplementStackSize(): number {
		const stack = ui.get().supplementStack;
		return Array.isArray(stack) ? stack.length : 0;
	}

	function dateKey(iso: string): string {
		return iso.slice(0, 10);
	}

	function daysAgo(n: number): string {
		const d = new Date();
		d.setDate(d.getDate() - n);
		return d.toISOString().slice(0, 10);
	}

	function dayOfWeek(dateStr: string): number {
		const d = new Date(dateStr + 'T12:00:00');
		return (d.getDay() + 6) % 7; // 0=Mon, 6=Sun
	}

	function formatDate(dateStr: string): string {
		const d = new Date(dateStr + 'T12:00:00');
		return d.toLocaleDateString('en', { month: 'short', day: 'numeric' });
	}

	/* ---------- Sufficient data check ---------- */
	const uniqueDays = $derived.by(() => {
		const days = new Set<string>();
		for (const e of store.items) days.add(dateKey(e.createdAt));
		return days.size;
	});

	/* ---------- 1. Score Breakdown Timeline ---------- */

	interface DayScore {
		date: string;
		score: number;
		sleep: number | null;
		moodEnergy: number | null;
		habits: number | null;
		supplements: number | null;
		training: number | null;
	}

	const dailyScores = $derived.by((): DayScore[] => {
		const all = store.items;
		const habitIds = allHabitIds();
		const totalHabitTypes = habitIds.length;
		const stackSize = supplementStackSize();
		const scores: DayScore[] = [];

		for (let i = period - 1; i >= 0; i--) {
			const d = daysAgo(i);
			const dayEntries = all.filter((e) => dateKey(e.createdAt) === d);
			if (dayEntries.length === 0) continue;

			const checkins = dayEntries.filter((e) => e.type === 'checkin');
			const habits = dayEntries.filter((e) => e.type === 'habit');
			const supplements = dayEntries.filter((e) => e.type === 'supplement');
			const trainings = dayEntries.filter((e) => e.type.startsWith('training.'));

			// Components
			let sleepScore: number | null = null;
			let moodEnergyScore: number | null = null;
			let habitsScore: number | null = null;
			let supplementsScore: number | null = null;
			let trainingScore: number | null = null;

			// Sleep from checkin data.sleep
			if (checkins.length > 0) {
				const sleepVals = checkins.map((e) => Number(e.data.sleep)).filter((v) => !isNaN(v) && v >= 0);
				if (sleepVals.length > 0) {
					const avg = sleepVals.reduce((a, b) => a + b, 0) / sleepVals.length;
					sleepScore = Math.min((avg / 10) * 100, 100);
				}
			}

			// Mood + Energy
			if (checkins.length > 0) {
				const moodVals = checkins.map((e) => Number(e.data.mood)).filter((v) => !isNaN(v));
				const energyVals = checkins.map((e) => Number(e.data.energy)).filter((v) => !isNaN(v));
				const vals = [...moodVals, ...energyVals];
				if (vals.length > 0) {
					const avg = vals.reduce((a, b) => a + b, 0) / vals.length;
					moodEnergyScore = Math.min((avg / 10) * 100, 100);
				}
			}

			// Habits
			if (totalHabitTypes > 0 && habits.length > 0) {
				const uniqueHabits = new Set(habits.map((e) => e.data.habit as string));
				habitsScore = Math.min((uniqueHabits.size / totalHabitTypes) * 100, 100);
			}

			// Supplements
			if (stackSize > 0 && supplements.length > 0) {
				supplementsScore = Math.min((supplements.length / stackSize) * 100, 100);
			}

			// Training
			if (trainings.length > 0) {
				trainingScore = 100;
			}

			// Weighted composite
			const components: { val: number; weight: number }[] = [];
			if (sleepScore !== null) components.push({ val: sleepScore, weight: 25 });
			if (moodEnergyScore !== null) components.push({ val: moodEnergyScore, weight: 25 });
			if (habitsScore !== null) components.push({ val: habitsScore, weight: 20 });
			if (supplementsScore !== null) components.push({ val: supplementsScore, weight: 15 });
			if (trainingScore !== null) components.push({ val: trainingScore, weight: 15 });

			let score = 0;
			if (components.length > 0) {
				const totalWeight = components.reduce((a, c) => a + c.weight, 0);
				score = Math.round(components.reduce((a, c) => a + (c.val * c.weight) / totalWeight, 0));
			}

			scores.push({
				date: d,
				score,
				sleep: sleepScore,
				moodEnergy: moodEnergyScore,
				habits: habitsScore,
				supplements: supplementsScore,
				training: trainingScore
			});
		}
		return scores;
	});

	const avgScore = $derived(
		dailyScores.length > 0
			? Math.round(dailyScores.reduce((a, d) => a + d.score, 0) / dailyScores.length)
			: 0
	);

	function scoreColor(s: number): string {
		if (s < 40) return '#ef4444';
		if (s <= 70) return '#f59e0b';
		return '#22c55e';
	}

	const scoreChartPoints = $derived.by(() => {
		if (dailyScores.length < 2) return '';
		const w = 560;
		const h = 160;
		const step = w / (dailyScores.length - 1);
		return dailyScores.map((d, i) => `${(i * step).toFixed(1)},${(h - (d.score / 100) * h).toFixed(1)}`).join(' ');
	});

	const scoreChartSegments = $derived.by(() => {
		if (dailyScores.length < 2) return [];
		const w = 560;
		const h = 160;
		const step = w / (dailyScores.length - 1);
		const segs: { x1: number; y1: number; x2: number; y2: number; color: string }[] = [];
		for (let i = 0; i < dailyScores.length - 1; i++) {
			const s1 = dailyScores[i].score;
			const s2 = dailyScores[i + 1].score;
			const avgSeg = (s1 + s2) / 2;
			segs.push({
				x1: i * step,
				y1: h - (s1 / 100) * h,
				x2: (i + 1) * step,
				y2: h - (s2 / 100) * h,
				color: scoreColor(avgSeg)
			});
		}
		return segs;
	});

	/* ---------- 2. Multi-Correlation Matrix ---------- */

	const matrixMetrics = [
		{ id: 'sleep', label: 'Sleep' },
		{ id: 'mood', label: 'Mood' },
		{ id: 'energy', label: 'Energy' },
		{ id: 'stress', label: 'Stress' },
		{ id: 'training', label: 'Training' },
		{ id: 'habits', label: 'Habits' },
		{ id: 'supplements', label: 'Suppl.' },
		{ id: 'hydration', label: 'Hydration' }
	];

	function extractMetric(metricId: string): Map<string, number> {
		const all = store.items;
		const result = new Map<string, number>();

		if (metricId === 'sleep' || metricId === 'mood' || metricId === 'energy' || metricId === 'stress') {
			for (const e of all) {
				if (e.type !== 'checkin') continue;
				const d = dateKey(e.createdAt);
				const val = Number(e.data[metricId]);
				if (!isNaN(val)) result.set(d, val);
			}
		} else if (metricId === 'training') {
			const counts = new Map<string, number>();
			for (const e of all) {
				if (!e.type.startsWith('training.')) continue;
				const d = dateKey(e.createdAt);
				counts.set(d, 1);
			}
			return counts;
		} else if (metricId === 'habits') {
			const dayCounts = new Map<string, Set<string>>();
			for (const e of all) {
				if (e.type !== 'habit') continue;
				const d = (e.data.date as string) ?? dateKey(e.createdAt);
				if (!dayCounts.has(d)) dayCounts.set(d, new Set());
				dayCounts.get(d)!.add(e.data.habit as string);
			}
			for (const [d, s] of dayCounts) result.set(d, s.size);
		} else if (metricId === 'supplements') {
			const dayCounts = new Map<string, number>();
			for (const e of all) {
				if (e.type !== 'supplement') continue;
				const d = (e.data.date as string) ?? dateKey(e.createdAt);
				dayCounts.set(d, (dayCounts.get(d) ?? 0) + 1);
			}
			return dayCounts;
		} else if (metricId === 'hydration') {
			const dayCounts = new Map<string, number>();
			for (const e of all) {
				if (e.type !== 'hydration') continue;
				const d = (e.data.date as string) ?? dateKey(e.createdAt);
				dayCounts.set(d, (dayCounts.get(d) ?? 0) + Number(e.data.amount ?? 0));
			}
			return dayCounts;
		}
		return result;
	}

	function pearson(mapA: Map<string, number>, mapB: Map<string, number>): { r: number | null; pairs: { x: number; y: number }[] } {
		const pairs: { x: number; y: number }[] = [];
		for (const [date, valA] of mapA) {
			const valB = mapB.get(date);
			if (valB !== undefined) pairs.push({ x: valA, y: valB });
		}
		if (pairs.length < 3) return { r: null, pairs };
		const n = pairs.length;
		let sumX = 0, sumY = 0, sumXY = 0, sumX2 = 0, sumY2 = 0;
		for (const p of pairs) {
			sumX += p.x; sumY += p.y;
			sumXY += p.x * p.y;
			sumX2 += p.x * p.x;
			sumY2 += p.y * p.y;
		}
		const denom = Math.sqrt((n * sumX2 - sumX * sumX) * (n * sumY2 - sumY * sumY));
		if (denom === 0) return { r: 0, pairs };
		return { r: +((n * sumXY - sumX * sumY) / denom).toFixed(2), pairs };
	}

	const correlationMatrix = $derived.by(() => {
		const maps = matrixMetrics.map((m) => extractMetric(m.id));
		const matrix: (number | null)[][] = [];
		for (let i = 0; i < matrixMetrics.length; i++) {
			const row: (number | null)[] = [];
			for (let j = 0; j < matrixMetrics.length; j++) {
				if (i === j) { row.push(1); continue; }
				row.push(pearson(maps[i], maps[j]).r);
			}
			matrix.push(row);
		}
		return matrix;
	});

	function corrCellColor(r: number | null): string {
		if (r === null) return 'var(--c-bg)';
		if (r === 1) return 'var(--c-accent-bg)';
		if (r > 0.3) return 'rgba(34,197,94,0.2)';
		if (r < -0.3) return 'rgba(239,68,68,0.2)';
		return 'rgba(128,128,128,0.1)';
	}

	function corrTextColor(r: number | null): string {
		if (r === null) return 'var(--c-text-muted)';
		if (r > 0.3) return '#22c55e';
		if (r < -0.3) return '#ef4444';
		return 'var(--c-text-muted)';
	}

	const selectedScatter = $derived.by(() => {
		if (!selectedCell) return null;
		const { row, col } = selectedCell;
		if (row === col) return null;
		const mapA = extractMetric(matrixMetrics[row].id);
		const mapB = extractMetric(matrixMetrics[col].id);
		const { r, pairs } = pearson(mapA, mapB);
		if (pairs.length === 0) return null;

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

		// Trendline
		const n = pairs.length;
		let sumX = 0, sumY = 0, sumXY = 0, sumX2 = 0;
		for (const p of pairs) {
			sumX += p.x; sumY += p.y;
			sumXY += p.x * p.y;
			sumX2 += p.x * p.x;
		}
		const denomSlope = n * sumX2 - sumX * sumX;
		let trendline: { x1: number; y1: number; x2: number; y2: number } | null = null;
		if (denomSlope !== 0) {
			const slope = (n * sumXY - sumX * sumY) / denomSlope;
			const intercept = (sumY - slope * sumX) / n;
			const y1 = slope * minX + intercept;
			const y2 = slope * maxX + intercept;
			trendline = {
				x1: 15,
				y1: 185 - ((y1 - minY) / rangeY) * 170,
				x2: 185,
				y2: 185 - ((y2 - minY) / rangeY) * 170
			};
		}

		return {
			labelA: matrixMetrics[row].label,
			labelB: matrixMetrics[col].label,
			r,
			n: pairs.length,
			dots,
			trendline
		};
	});

	/* ---------- 3. Day-of-Week Patterns ---------- */

	const dowPatterns = $derived.by(() => {
		const labels = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
		const scoreSums = [0, 0, 0, 0, 0, 0, 0];
		const scoreCounts = [0, 0, 0, 0, 0, 0, 0];
		const moodSums = [0, 0, 0, 0, 0, 0, 0];
		const moodCounts = [0, 0, 0, 0, 0, 0, 0];
		const energySums = [0, 0, 0, 0, 0, 0, 0];
		const energyCounts = [0, 0, 0, 0, 0, 0, 0];

		for (const ds of dailyScores) {
			const dow = dayOfWeek(ds.date);
			scoreSums[dow] += ds.score;
			scoreCounts[dow]++;
		}

		const checkins = store.items.filter((e) => e.type === 'checkin');
		for (const e of checkins) {
			const dow = dayOfWeek(dateKey(e.createdAt));
			const m = Number(e.data.mood);
			const en = Number(e.data.energy);
			if (!isNaN(m)) { moodSums[dow] += m; moodCounts[dow]++; }
			if (!isNaN(en)) { energySums[dow] += en; energyCounts[dow]++; }
		}

		const days = labels.map((label, i) => ({
			label,
			avgScore: scoreCounts[i] > 0 ? Math.round(scoreSums[i] / scoreCounts[i]) : 0,
			avgMood: moodCounts[i] > 0 ? +(moodSums[i] / moodCounts[i]).toFixed(1) : 0,
			avgEnergy: energyCounts[i] > 0 ? +(energySums[i] / energyCounts[i]).toFixed(1) : 0,
			hasData: scoreCounts[i] > 0
		}));

		const withData = days.filter((d) => d.hasData);
		let bestIdx = -1;
		let worstIdx = -1;
		if (withData.length > 0) {
			let bestScore = -1;
			let worstScore = 101;
			for (let i = 0; i < days.length; i++) {
				if (!days[i].hasData) continue;
				if (days[i].avgScore > bestScore) { bestScore = days[i].avgScore; bestIdx = i; }
				if (days[i].avgScore < worstScore) { worstScore = days[i].avgScore; worstIdx = i; }
			}
		}

		return { days, bestIdx, worstIdx };
	});

	/* ---------- 4. Best & Worst Days ---------- */

	const bestDays = $derived(
		dailyScores.toSorted((a, b) => b.score - a.score).slice(0, 5)
	);

	const worstDays = $derived(
		dailyScores.toSorted((a, b) => a.score - b.score).slice(0, 5)
	);

	function dayDetails(dateStr: string): { habits: string[]; trainingType: string; sleepHours: number | null; mood: number | null } {
		const dayEntries = store.items.filter((e) => dateKey(e.createdAt) === dateStr);
		const habits = dayEntries.filter((e) => e.type === 'habit').map((e) => e.data.habit as string);
		const trainings = dayEntries.filter((e) => e.type.startsWith('training.'));
		const trainingType = trainings.length > 0 ? trainings.map((e) => e.type.replace('training.', '')).join(', ') : '';
		const checkins = dayEntries.filter((e) => e.type === 'checkin');
		const sleepHours = checkins.length > 0 ? Number(checkins[0].data.sleep) || null : null;
		const mood = checkins.length > 0 ? Number(checkins[0].data.mood) || null : null;
		return { habits, trainingType, sleepHours, mood };
	}

	/* ---------- 5. Rolling Averages ---------- */

	const rollingData = $derived.by(() => {
		const all = store.items;
		const start = daysAgo(period - 1);
		const daysList: string[] = [];
		for (let i = period - 1; i >= 0; i--) daysList.push(daysAgo(i));

		// Collect raw daily values
		const rawScore = new Map<string, number>();
		for (const ds of dailyScores) rawScore.set(ds.date, ds.score);

		const rawMood = new Map<string, number>();
		const rawEnergy = new Map<string, number>();
		const rawSleep = new Map<string, number>();

		for (const e of all) {
			if (e.type !== 'checkin') continue;
			const d = dateKey(e.createdAt);
			const m = Number(e.data.mood);
			const en = Number(e.data.energy);
			const sl = Number(e.data.sleep);
			if (!isNaN(m)) rawMood.set(d, m);
			if (!isNaN(en)) rawEnergy.set(d, en);
			if (!isNaN(sl)) rawSleep.set(d, sl);
		}

		function rollingAvg(raw: Map<string, number>, idx: number): number | null {
			let sum = 0;
			let count = 0;
			for (let j = Math.max(0, idx - 6); j <= idx; j++) {
				const val = raw.get(daysList[j]);
				if (val !== undefined) { sum += val; count++; }
			}
			return count > 0 ? +(sum / count).toFixed(1) : null;
		}

		const points: { date: string; score: number | null; mood: number | null; energy: number | null; sleep: number | null }[] = [];
		for (let i = 0; i < daysList.length; i++) {
			points.push({
				date: daysList[i],
				score: rollingAvg(rawScore, i),
				mood: rollingAvg(rawMood, i),
				energy: rollingAvg(rawEnergy, i),
				sleep: rollingAvg(rawSleep, i)
			});
		}
		return points;
	});

	function buildPolyline(points: (number | null)[], maxVal: number, w: number, h: number): string {
		const validPoints: { i: number; v: number }[] = [];
		for (let i = 0; i < points.length; i++) {
			if (points[i] !== null) validPoints.push({ i, v: points[i]! });
		}
		if (validPoints.length < 2) return '';
		const step = w / (points.length - 1);
		return validPoints.map((p) => `${(p.i * step).toFixed(1)},${(h - (p.v / maxVal) * h).toFixed(1)}`).join(' ');
	}

	/* ---------- 6. Consistency Panel ---------- */

	const consistency = $derived.by(() => {
		// Longest streak >= 70
		let longestGoodStreak = 0;
		let currentGoodStreak = 0;
		const sortedScores = dailyScores.toSorted((a, b) => a.date.localeCompare(b.date));
		for (const ds of sortedScores) {
			if (ds.score >= 70) {
				currentGoodStreak++;
				if (currentGoodStreak > longestGoodStreak) longestGoodStreak = currentGoodStreak;
			} else {
				currentGoodStreak = 0;
			}
		}

		// Current daily logging streak
		let loggingStreak = 0;
		const today = new Date();
		for (let i = 0; i < 365; i++) {
			const d = new Date(today);
			d.setDate(d.getDate() - i);
			const key = d.toISOString().slice(0, 10);
			const hasEntry = store.items.some((e) => dateKey(e.createdAt) === key);
			if (hasEntry) {
				loggingStreak++;
			} else {
				break;
			}
		}

		// Most consistent metric (lowest CV)
		const metricArrays: { name: string; values: number[] }[] = [];
		const checkins = store.items.filter((e) => e.type === 'checkin');

		const moods = checkins.map((e) => Number(e.data.mood)).filter((v) => !isNaN(v));
		if (moods.length > 2) metricArrays.push({ name: 'Mood', values: moods });

		const energies = checkins.map((e) => Number(e.data.energy)).filter((v) => !isNaN(v));
		if (energies.length > 2) metricArrays.push({ name: 'Energy', values: energies });

		const sleeps = checkins.map((e) => Number(e.data.sleep)).filter((v) => !isNaN(v));
		if (sleeps.length > 2) metricArrays.push({ name: 'Sleep', values: sleeps });

		const stresses = checkins.map((e) => Number(e.data.stress)).filter((v) => !isNaN(v));
		if (stresses.length > 2) metricArrays.push({ name: 'Stress', values: stresses });

		let mostConsistent = '';
		let lowestCV = Infinity;
		for (const m of metricArrays) {
			const mean = m.values.reduce((a, b) => a + b, 0) / m.values.length;
			if (mean === 0) continue;
			const variance = m.values.reduce((a, v) => a + (v - mean) ** 2, 0) / m.values.length;
			const cv = Math.sqrt(variance) / mean;
			if (cv < lowestCV) { lowestCV = cv; mostConsistent = m.name; }
		}

		// Missing data categories
		const weekAgo = daysAgo(7);
		const recentEntries = store.items.filter((e) => e.createdAt >= weekAgo + 'T00:00:00');
		const categories = [
			{ name: 'Check-in', hasData: recentEntries.some((e) => e.type === 'checkin') },
			{ name: 'Habits', hasData: recentEntries.some((e) => e.type === 'habit') },
			{ name: 'Supplements', hasData: recentEntries.some((e) => e.type === 'supplement') },
			{ name: 'Training', hasData: recentEntries.some((e) => e.type.startsWith('training.')) },
			{ name: 'Hydration', hasData: recentEntries.some((e) => e.type === 'hydration') },
			{ name: 'Journal', hasData: recentEntries.some((e) => e.type === 'journal') }
		];
		const missing = categories.filter((c) => !c.hasData).map((c) => c.name);

		return { longestGoodStreak, loggingStreak, mostConsistent, missing };
	});

	const anomalies = $derived.by(() => {
		const checkins = store.items.filter(e => e.type === 'checkin').toSorted((a, b) => a.createdAt.localeCompare(b.createdAt));
		if (checkins.length < 14) return [];
		const alerts: { type: 'warning' | 'positive'; metric: string; message: string }[] = [];
		const metrics = ['mood', 'energy', 'stress'] as const;
		for (const metric of metrics) {
			const values = checkins.map(e => Number(e.data[metric]) || 5);
			const last7 = values.slice(-7);
			const prev7 = values.slice(-14, -7);
			if (last7.length < 3 || prev7.length < 3) continue;
			const avgLast = last7.reduce((s, v) => s + v, 0) / last7.length;
			const avgPrev = prev7.reduce((s, v) => s + v, 0) / prev7.length;
			const change = avgLast - avgPrev;
			const pctChange = avgPrev > 0 ? Math.abs(change / avgPrev) * 100 : 0;
			if (pctChange >= 20) {
				const direction = change > 0 ? 'increased' : 'decreased';
				const isGood = metric === 'stress' ? change < 0 : change > 0;
				alerts.push({
					type: isGood ? 'positive' : 'warning',
					metric: metric.charAt(0).toUpperCase() + metric.slice(1),
					message: `${metric.charAt(0).toUpperCase() + metric.slice(1)} ${direction} ${pctChange.toFixed(0)}% (${avgPrev.toFixed(1)} → ${avgLast.toFixed(1)})`
				});
			}
		}
		return alerts;
	});

	const recommendations = $derived.by(() => {
		const checkins = store.items.filter(e => e.type === 'checkin');
		if (checkins.length < 7) return [];
		const recs: { icon: string; text: string; priority: 'high' | 'medium' | 'low' }[] = [];
		const recent7 = checkins.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt)).slice(0, 7);
		const avgMood = recent7.reduce((s, e) => s + Number(e.data.mood), 0) / recent7.length;
		const avgEnergy = recent7.reduce((s, e) => s + Number(e.data.energy), 0) / recent7.length;
		const avgStress = recent7.reduce((s, e) => s + Number(e.data.stress), 0) / recent7.length;
		const sleeps = store.items.filter(e => e.type === 'signal.sleep').toSorted((a, b) => b.createdAt.localeCompare(a.createdAt)).slice(0, 7);
		const avgSleep = sleeps.length > 0 ? sleeps.reduce((s, e) => s + Number(e.data.hours), 0) / sleeps.length : 0;
		if (avgMood < 5) recs.push({ icon: '💡', text: 'Mood has been low — try journaling or a walk outdoors', priority: 'high' });
		if (avgEnergy < 5) recs.push({ icon: '⚡', text: 'Energy is low — check sleep quality and hydration', priority: 'high' });
		if (avgStress > 7) recs.push({ icon: '🧘', text: 'High stress — consider meditation or breathing exercises', priority: 'high' });
		if (avgSleep > 0 && avgSleep < 6.5) recs.push({ icon: '😴', text: `Averaging ${avgSleep.toFixed(1)}h sleep — aim for 7-8h`, priority: 'medium' });
		const trainings = store.items.filter(e => e.type.startsWith('training.'));
		const weekAgo = new Date(Date.now() - 7 * 86400000).toISOString();
		const recentTraining = trainings.filter(e => e.createdAt >= weekAgo);
		if (recentTraining.length === 0 && trainings.length > 0) recs.push({ icon: '🏋️', text: 'No training this week — staying active boosts mood', priority: 'medium' });
		if (recs.length === 0 && avgMood >= 7) recs.push({ icon: '✅', text: 'All metrics look good — keep it up!', priority: 'low' });
		return recs;
	});
</script>

<svelte:head>
	<title>Insights | Darink</title>
</svelte:head>

<PageHeader title="Insights" />

{#if uniqueDays < 7}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
	<p>Not enough data yet</p>
	<p class="empty-hint">Log entries for at least 7 different days to unlock insights and analysis.</p>
</div>
{:else}

<!-- Period Selector -->
<section class="period-selector">
	<button class="period-chip" class:active={period === 30} onclick={() => period = 30}>30 days</button>
	<button class="period-chip" class:active={period === 60} onclick={() => period = 60}>60 days</button>
	<button class="period-chip" class:active={period === 90} onclick={() => period = 90}>90 days</button>
</section>

<!-- 1. Score Breakdown Timeline -->
<section class="section">
	<h2>Health Score Timeline</h2>
	{#if dailyScores.length > 0}
	<div class="avg-score" style="color: {scoreColor(avgScore)}">
		<span class="avg-val">{avgScore}</span>
		<span class="avg-label">avg score ({period}d)</span>
	</div>
	{#if dailyScores.length >= 2}
	<div class="chart-wrap">
		<svg class="score-chart" viewBox="0 0 560 180" preserveAspectRatio="none">
			<!-- Grid lines -->
			<line x1="0" y1="18" x2="560" y2="18" stroke="var(--c-border)" stroke-width="0.5" stroke-dasharray="4 4" />
			<line x1="0" y1="72" x2="560" y2="72" stroke="var(--c-border)" stroke-width="0.5" stroke-dasharray="4 4" />
			<line x1="0" y1="126" x2="560" y2="126" stroke="var(--c-border)" stroke-width="0.5" stroke-dasharray="4 4" />
			<!-- Grid labels -->
			<text x="562" y="22" font-size="8" fill="var(--c-text-muted)">90</text>
			<text x="562" y="76" font-size="8" fill="var(--c-text-muted)">55</text>
			<text x="562" y="130" font-size="8" fill="var(--c-text-muted)">20</text>
			<!-- Colored line segments -->
			{#each scoreChartSegments as seg}
				<line x1={seg.x1} y1={seg.y1 + 10} x2={seg.x2} y2={seg.y2 + 10} stroke={seg.color} stroke-width="2.5" stroke-linecap="round" />
			{/each}
		</svg>
	</div>
	<div class="legend">
		<span class="legend-item"><span class="ldot" style="background:#22c55e"></span> Good (>70)</span>
		<span class="legend-item"><span class="ldot" style="background:#f59e0b"></span> Fair (40-70)</span>
		<span class="legend-item"><span class="ldot" style="background:#ef4444"></span> Low (&lt;40)</span>
	</div>
	{/if}
	{:else}
	<p class="no-data">No scored days in this period.</p>
	{/if}
</section>

<!-- 2. Multi-Correlation Matrix -->
<section class="section">
	<h2>Correlation Matrix</h2>
	<div class="matrix-scroll">
		<div class="matrix-grid" style="grid-template-columns: auto repeat({matrixMetrics.length}, 1fr);">
			<!-- Header row -->
			<div class="matrix-corner"></div>
			{#each matrixMetrics as m}
				<div class="matrix-header">{m.label}</div>
			{/each}
			<!-- Data rows -->
			{#each matrixMetrics as rowM, i}
				<div class="matrix-row-label">{rowM.label}</div>
				{#each matrixMetrics as _colM, j}
					{@const r = correlationMatrix[i][j]}
					<button
						class="matrix-cell"
						class:diagonal={i === j}
						style="background:{corrCellColor(r)}; color:{corrTextColor(r)}"
						onclick={() => { if (i !== j) selectedCell = selectedCell?.row === i && selectedCell?.col === j ? null : { row: i, col: j }; }}
						disabled={i === j}
					>
						{#if r !== null}{r.toFixed(2)}{:else}--{/if}
					</button>
				{/each}
			{/each}
		</div>
	</div>
	{#if selectedScatter}
		<div class="scatter-wrap">
			<div class="scatter-title">
				{selectedScatter.labelA} vs {selectedScatter.labelB}
				{#if selectedScatter.r !== null}
					<span class="scatter-r" style="color:{corrTextColor(selectedScatter.r)}">r = {selectedScatter.r > 0 ? '+' : ''}{selectedScatter.r}</span>
				{/if}
				<span class="scatter-n">N = {selectedScatter.n}</span>
			</div>
			<svg class="scatter-plot" viewBox="0 0 200 200">
				<line x1="15" y1="185" x2="185" y2="185" stroke="var(--c-border)" stroke-width="1" />
				<line x1="15" y1="15" x2="15" y2="185" stroke="var(--c-border)" stroke-width="1" />
				<text x="100" y="198" text-anchor="middle" font-size="8" fill="var(--c-text-muted)">{selectedScatter.labelA}</text>
				<text x="6" y="100" text-anchor="middle" font-size="8" fill="var(--c-text-muted)" transform="rotate(-90, 6, 100)">{selectedScatter.labelB}</text>
				{#each selectedScatter.dots as dot}
					<circle cx={dot.cx} cy={dot.cy} r="3" fill="var(--c-accent)" opacity="0.7" />
				{/each}
				{#if selectedScatter.trendline}
					<line
						x1={selectedScatter.trendline.x1} y1={selectedScatter.trendline.y1}
						x2={selectedScatter.trendline.x2} y2={selectedScatter.trendline.y2}
						stroke="var(--c-accent)" stroke-width="1.5" stroke-dasharray="4 2" opacity="0.6"
					/>
				{/if}
			</svg>
		</div>
	{/if}
</section>

<!-- 3. Day-of-Week Patterns -->
<section class="section">
	<h2>Day-of-Week Patterns</h2>
	{#if dowPatterns.days.some((d) => d.hasData)}
	<div class="dow-chart-wrap">
		<svg class="dow-chart" viewBox="0 0 280 140" preserveAspectRatio="xMidYMid meet">
			{#each dowPatterns.days as day, i}
				{@const barW = 280 / 7}
				{@const barH = day.avgScore * 1.2}
				<rect
					x={i * barW + barW * 0.1}
					y={110 - barH}
					width={barW * 0.5}
					height={barH}
					rx="2"
					fill={scoreColor(day.avgScore)}
					opacity="0.85"
				/>
				<!-- Mood mini bar -->
				{#if day.avgMood > 0}
					<rect
						x={i * barW + barW * 0.65}
						y={110 - day.avgMood * 10}
						width={barW * 0.12}
						height={day.avgMood * 10}
						rx="1"
						fill="var(--c-accent)"
						opacity="0.6"
					/>
				{/if}
				<!-- Energy mini bar -->
				{#if day.avgEnergy > 0}
					<rect
						x={i * barW + barW * 0.8}
						y={110 - day.avgEnergy * 10}
						width={barW * 0.12}
						height={day.avgEnergy * 10}
						rx="1"
						fill="var(--c-done)"
						opacity="0.6"
					/>
				{/if}
				<!-- Label -->
				<text
					x={i * barW + barW / 2}
					y="125"
					text-anchor="middle"
					font-size="9"
					fill="var(--c-text-muted)"
				>{day.label}</text>
				<!-- Score text -->
				{#if day.hasData}
					<text
						x={i * barW + barW * 0.35}
						y={110 - barH - 4}
						text-anchor="middle"
						font-size="7"
						fill="var(--c-text)"
					>{day.avgScore}</text>
				{/if}
				<!-- Badge -->
				{#if i === dowPatterns.bestIdx}
					<text x={i * barW + barW / 2} y="137" text-anchor="middle" font-size="7" fill="#22c55e" font-weight="600">BEST</text>
				{/if}
				{#if i === dowPatterns.worstIdx}
					<text x={i * barW + barW / 2} y="137" text-anchor="middle" font-size="7" fill="#ef4444" font-weight="600">WORST</text>
				{/if}
			{/each}
		</svg>
	</div>
	<div class="legend">
		<span class="legend-item"><span class="ldot" style="background:var(--c-accent);opacity:0.6"></span> Mood</span>
		<span class="legend-item"><span class="ldot" style="background:var(--c-done);opacity:0.6"></span> Energy</span>
	</div>
	{:else}
	<p class="no-data">No scored days available.</p>
	{/if}
</section>

<!-- 4. Best & Worst Days -->
<section class="section">
	<h2>Best Days</h2>
	{#if bestDays.length > 0}
	<div class="bw-cards">
		{#each bestDays as day}
			{@const details = dayDetails(day.date)}
			<div class="bw-card best">
				<div class="bw-header">
					<span class="bw-date">{formatDate(day.date)}</span>
					<span class="bw-score" style="color:#22c55e">{day.score}</span>
				</div>
				<div class="bw-detail">
					{#if details.mood !== null}<span>Mood {details.mood}</span>{/if}
					{#if details.sleepHours !== null}<span>Sleep {details.sleepHours}h</span>{/if}
					{#if details.trainingType}<span>{details.trainingType}</span>{/if}
					{#if details.habits.length > 0}<span>{details.habits.length} habits</span>{/if}
				</div>
			</div>
		{/each}
	</div>
	{:else}
	<p class="no-data">No data yet.</p>
	{/if}
</section>

<section class="section">
	<h2>Worst Days</h2>
	{#if worstDays.length > 0}
	<div class="bw-cards">
		{#each worstDays as day}
			{@const details = dayDetails(day.date)}
			<div class="bw-card worst">
				<div class="bw-header">
					<span class="bw-date">{formatDate(day.date)}</span>
					<span class="bw-score" style="color:#ef4444">{day.score}</span>
				</div>
				<div class="bw-detail">
					{#if details.mood !== null}<span>Mood {details.mood}</span>{/if}
					{#if details.sleepHours !== null}<span>Sleep {details.sleepHours}h</span>{/if}
					{#if details.trainingType}<span>{details.trainingType}</span>{/if}
					{#if details.habits.length > 0}<span>{details.habits.length} habits</span>{/if}
				</div>
			</div>
		{/each}
	</div>
	{:else}
	<p class="no-data">No data yet.</p>
	{/if}
</section>

<!-- 5. Rolling Averages -->
<section class="section">
	<h2>Rolling Averages (7-day)</h2>
	<div class="toggle-row">
		<label class="toggle-label"><input type="checkbox" bind:checked={showScore} /> <span class="ldot" style="background:var(--c-accent)"></span> Score</label>
		<label class="toggle-label"><input type="checkbox" bind:checked={showMood} /> <span class="ldot" style="background:#a78bfa"></span> Mood</label>
		<label class="toggle-label"><input type="checkbox" bind:checked={showEnergy} /> <span class="ldot" style="background:var(--c-done)"></span> Energy</label>
		<label class="toggle-label"><input type="checkbox" bind:checked={showSleep} /> <span class="ldot" style="background:#f59e0b"></span> Sleep</label>
	</div>
	{#if rollingData.length > 1}
	{#if true}
		{@const rW = 560}
		{@const rH = 160}
		{@const scorePoints = showScore ? buildPolyline(rollingData.map((p) => p.score), 100, rW, rH) : ''}
		{@const moodPoints = showMood ? buildPolyline(rollingData.map((p) => p.mood), 10, rW, rH) : ''}
		{@const energyPoints = showEnergy ? buildPolyline(rollingData.map((p) => p.energy), 10, rW, rH) : ''}
		{@const sleepPoints = showSleep ? buildPolyline(rollingData.map((p) => p.sleep), 12, rW, rH) : ''}
	<div class="chart-wrap">
		<svg class="rolling-chart" viewBox="0 0 {rW} {rH}" preserveAspectRatio="none">
			{#if scorePoints}
				<polyline fill="none" stroke="var(--c-accent)" stroke-width="2" stroke-linejoin="round" points={scorePoints} />
			{/if}
			{#if moodPoints}
				<polyline fill="none" stroke="#a78bfa" stroke-width="1.5" stroke-linejoin="round" stroke-dasharray="6 3" points={moodPoints} />
			{/if}
			{#if energyPoints}
				<polyline fill="none" stroke="var(--c-done)" stroke-width="1.5" stroke-linejoin="round" stroke-dasharray="3 3" points={energyPoints} />
			{/if}
			{#if sleepPoints}
				<polyline fill="none" stroke="#f59e0b" stroke-width="1.5" stroke-linejoin="round" stroke-dasharray="8 4" points={sleepPoints} />
			{/if}
		</svg>
	</div>
	{/if}
	{:else}
	<p class="no-data">Not enough data for rolling averages.</p>
	{/if}
</section>

<!-- 6. Consistency Panel -->
<section class="section">
	<h2>Consistency</h2>
	<div class="consistency-grid">
		<div class="consist-card">
			<div class="consist-icon">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
			</div>
			<span class="consist-val">{consistency.longestGoodStreak}d</span>
			<span class="consist-lbl">Best streak (score >= 70)</span>
		</div>
		<div class="consist-card">
			<div class="consist-icon">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
			</div>
			<span class="consist-val">{consistency.loggingStreak}d</span>
			<span class="consist-lbl">Logging streak</span>
		</div>
		<div class="consist-card">
			<div class="consist-icon">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
			</div>
			<span class="consist-val">{consistency.mostConsistent || '--'}</span>
			<span class="consist-lbl">Most consistent</span>
		</div>
	</div>
	{#if consistency.missing.length > 0}
	<div class="missing-data">
		<h3>Missing data (last 7 days)</h3>
		<div class="missing-chips">
			{#each consistency.missing as cat}
				<span class="missing-chip">{cat}</span>
			{/each}
		</div>
	</div>
	{/if}
</section>

{#if anomalies.length > 0}
<section class="anomaly-section">
	<h2>Anomaly Alerts</h2>
	{#each anomalies as alert}
		<div class="anomaly-card {alert.type}">
			<span class="anomaly-icon">{alert.type === 'warning' ? '⚠️' : '✅'}</span>
			<span class="anomaly-text">{alert.message}</span>
		</div>
	{/each}
</section>
{/if}

{#if recommendations.length > 0}
<section class="rec-section">
	<h2>Recommendations</h2>
	{#each recommendations as rec}
		<div class="rec-card {rec.priority}">
			<span class="rec-icon">{rec.icon}</span>
			<span class="rec-text">{rec.text}</span>
		</div>
	{/each}
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
	h3 {
		font-size: 0.8rem;
		font-weight: 600;
		color: var(--c-text-muted);
		margin-bottom: 0.35rem;
	}

	.section {
		padding: 0 1rem 1.5rem;
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

	/* Avg Score */
	.avg-score {
		display: flex;
		align-items: baseline;
		gap: 0.5rem;
		margin-bottom: 0.75rem;
	}
	.avg-val {
		font-size: 2.5rem;
		font-weight: 800;
		line-height: 1;
	}
	.avg-label {
		font-size: 0.85rem;
		color: var(--c-text-muted);
	}

	/* Charts */
	.chart-wrap {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem 0.5rem;
		overflow: hidden;
	}
	.score-chart, .rolling-chart {
		width: 100%;
		height: 120px;
		display: block;
	}

	.legend {
		display: flex;
		gap: 1rem;
		font-size: 0.75rem;
		color: var(--c-text-muted);
		margin-top: 0.35rem;
		flex-wrap: wrap;
	}
	.legend-item {
		display: flex;
		align-items: center;
		gap: 0.25rem;
	}
	.ldot {
		display: inline-block;
		width: 8px;
		height: 8px;
		border-radius: 50%;
		flex-shrink: 0;
	}

	.no-data {
		font-size: 0.85rem;
		color: var(--c-text-muted);
		padding: 0.5rem 0;
	}

	/* Correlation Matrix */
	.matrix-scroll {
		overflow-x: auto;
		-webkit-overflow-scrolling: touch;
	}
	.matrix-grid {
		display: grid;
		gap: 1px;
		min-width: 360px;
	}
	.matrix-corner {
		background: var(--c-bg);
	}
	.matrix-header {
		font-size: 0.6rem;
		font-weight: 600;
		text-align: center;
		padding: 0.3rem 0.15rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		background: var(--c-bg);
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.matrix-row-label {
		font-size: 0.65rem;
		font-weight: 600;
		padding: 0.3rem 0.25rem;
		color: var(--c-text-muted);
		display: flex;
		align-items: center;
		background: var(--c-bg);
		white-space: nowrap;
	}
	.matrix-cell {
		font-size: 0.65rem;
		font-weight: 600;
		font-variant-numeric: tabular-nums;
		text-align: center;
		padding: 0.35rem 0.15rem;
		border: 1px solid var(--c-border);
		border-radius: 3px;
		cursor: pointer;
		transition: opacity 0.15s;
		font-family: inherit;
	}
	.matrix-cell:hover:not(.diagonal) {
		opacity: 0.7;
	}
	.matrix-cell.diagonal {
		cursor: default;
		opacity: 0.4;
	}

	/* Scatter */
	.scatter-wrap {
		margin-top: 0.75rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.5rem;
	}
	.scatter-title {
		font-size: 0.8rem;
		font-weight: 600;
		margin-bottom: 0.5rem;
		display: flex;
		align-items: center;
		gap: 0.5rem;
		flex-wrap: wrap;
	}
	.scatter-r {
		font-variant-numeric: tabular-nums;
	}
	.scatter-n {
		font-size: 0.7rem;
		color: var(--c-text-muted);
		font-weight: 400;
		margin-left: auto;
	}
	.scatter-plot {
		width: 100%;
		max-width: 300px;
		display: block;
		margin: 0 auto;
	}

	/* Day of Week Chart */
	.dow-chart-wrap {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.5rem;
	}
	.dow-chart {
		width: 100%;
		height: 140px;
	}

	/* Best / Worst Cards */
	.bw-cards {
		display: flex;
		flex-direction: column;
		gap: 0.4rem;
	}
	.bw-card {
		display: flex;
		flex-direction: column;
		gap: 0.2rem;
		padding: 0.6rem 0.75rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}
	.bw-card.best {
		border-left: 3px solid #22c55e;
	}
	.bw-card.worst {
		border-left: 3px solid #ef4444;
	}
	.bw-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
	}
	.bw-date {
		font-size: 0.85rem;
		font-weight: 600;
	}
	.bw-score {
		font-size: 1.2rem;
		font-weight: 800;
	}
	.bw-detail {
		display: flex;
		flex-wrap: wrap;
		gap: 0.4rem;
		font-size: 0.75rem;
		color: var(--c-text-muted);
	}
	.bw-detail span {
		background: var(--c-bg);
		padding: 0.15rem 0.4rem;
		border-radius: 10px;
		border: 1px solid var(--c-border);
	}

	/* Toggle Row */
	.toggle-row {
		display: flex;
		flex-wrap: wrap;
		gap: 0.75rem;
		margin-bottom: 0.75rem;
	}
	.toggle-label {
		display: flex;
		align-items: center;
		gap: 0.3rem;
		font-size: 0.8rem;
		color: var(--c-text-muted);
		cursor: pointer;
	}
	.toggle-label input[type="checkbox"] {
		width: 14px;
		height: 14px;
		accent-color: var(--c-accent);
	}

	/* Consistency */
	.consistency-grid {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 0.5rem;
	}
	.consist-card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem;
		text-align: center;
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 0.25rem;
	}
	.consist-icon {
		color: var(--c-accent);
	}
	.consist-val {
		font-size: 1.3rem;
		font-weight: 700;
		line-height: 1;
	}
	.consist-lbl {
		font-size: 0.65rem;
		color: var(--c-text-muted);
		line-height: 1.2;
	}

	.missing-data {
		margin-top: 0.75rem;
		padding: 0.6rem 0.75rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-left: 3px solid #f59e0b;
		border-radius: var(--radius);
	}
	.missing-chips {
		display: flex;
		flex-wrap: wrap;
		gap: 0.3rem;
	}
	.missing-chip {
		display: inline-block;
		padding: 0.2rem 0.5rem;
		background: rgba(245, 158, 11, 0.1);
		border: 1px solid rgba(245, 158, 11, 0.3);
		border-radius: 12px;
		font-size: 0.75rem;
		color: #f59e0b;
	}

	/* Empty State */
	.empty-state {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		padding: 3rem 1rem;
		text-align: center;
		color: var(--c-text-muted);
	}
	.empty-state svg {
		margin-bottom: 1rem;
		opacity: 0.5;
	}
	.empty-state p {
		font-size: 1rem;
		font-weight: 600;
	}
	.empty-hint {
		font-size: 0.85rem;
		font-weight: 400 !important;
		margin-top: 0.25rem;
		color: var(--c-text-muted);
	}

	/* Responsive */
	@media (max-width: 480px) {
		.consistency-grid {
			grid-template-columns: 1fr;
		}
		.matrix-grid {
			min-width: 320px;
		}
		.avg-val {
			font-size: 2rem;
		}
	}
	@media (min-width: 600px) {
		.bw-cards {
			display: grid;
			grid-template-columns: repeat(2, 1fr);
		}
	}
	@media (min-width: 900px) {
		.bw-cards {
			grid-template-columns: repeat(3, 1fr);
		}
		.score-chart, .rolling-chart {
			height: 160px;
		}
	}

	.anomaly-section { padding: 1.5rem 1rem 0; }
	.anomaly-card { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); margin-bottom: 0.5rem; }
	.anomaly-card.warning { border-left: 3px solid #e8a735; }
	.anomaly-card.positive { border-left: 3px solid #38a169; }
	.anomaly-icon { font-size: 1.2rem; }
	.anomaly-text { font-size: 0.85rem; }
	.rec-section { padding: 1.5rem 1rem 0; }
	.rec-card { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); margin-bottom: 0.5rem; }
	.rec-card.high { border-left: 3px solid #e53e3e; }
	.rec-card.medium { border-left: 3px solid #e8a735; }
	.rec-card.low { border-left: 3px solid #38a169; }
	.rec-icon { font-size: 1.2rem; }
	.rec-text { font-size: 0.85rem; }
</style>
