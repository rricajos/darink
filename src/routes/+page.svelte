<script lang="ts">
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { ui } from '$lib/db';
	import type { Entry } from '$lib/db';
	import { useLocale } from '$lib/stores/locale.svelte';

	const { t, locale } = useLocale();
	const store = useEntries();

	const today = $derived(new Date().toISOString().slice(0, 10));

	const greeting = $derived.by(() => {
		const h = new Date().getHours();
		if (h < 12) return t.today_page.goodMorning;
		if (h < 18) return t.today_page.goodAfternoon;
		return t.today_page.goodEvening;
	});

	const dateLabel = $derived(
		new Date().toLocaleDateString(locale === 'en' ? 'en-US' : 'es-ES', { weekday: 'long', month: 'long', day: 'numeric' })
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
	const defaultHabits = $derived([
		{ id: 'cold', label: t.habits.cold },
		{ id: 'sun', label: t.habits.sun },
		{ id: 'fasting', label: t.habits.fasting },
		{ id: 'meditation', label: t.habits.meditation },
		{ id: 'wimhof', label: t.habits.wimhof },
		{ id: 'ejaculation', label: t.habits.ejaculation }
	]);

	const allHabitTypes = $derived.by(() => {
		const custom = ui.get().customHabits;
		const extra = Array.isArray(custom) ? (custom as Array<{id: string}>).map(h => h.id) : [];
		return [...defaultHabits.map(h => h.id), ...extra];
	});

	const supplementStack = $derived.by(() => {
		const stack = ui.get().supplementStack;
		return Array.isArray(stack) ? stack as Array<{name: string}> : [];
	});

	/* --- Medication regimen --- */
	interface MedItem {
		name: string;
		dose: string;
		frequency: 'daily' | '2x_daily' | '3x_daily' | 'weekly' | 'as_needed';
		prescriber: string;
		refillDate: string;
	}

	const medicationRegimen = $derived.by(() => {
		const saved = ui.get().medicationRegimen;
		return Array.isArray(saved) ? saved as MedItem[] : [];
	});

	function doseSlotsForFreq(freq: string): string[] {
		if (freq === 'daily') return ['morning'];
		if (freq === '2x_daily') return ['morning', 'evening'];
		if (freq === '3x_daily') return ['morning', 'noon', 'evening'];
		if (freq === 'weekly') return ['morning'];
		return [];
	}

	const timeLabels = $derived<Record<string, string>>({
		morning: t.common.morning,
		noon: t.common.noon,
		evening: t.common.evening,
		night: t.common.night
	});

	const scheduledMeds = $derived(
		medicationRegimen.filter((m) => m.frequency !== 'as_needed')
	);

	const todayMedEntries = $derived(
		store.items.filter((e) => e.type === 'medication' && dateOf(e) === today)
	);

	const doseChecklist = $derived.by(() => {
		return scheduledMeds.map((med) => {
			const slots = doseSlotsForFreq(med.frequency);
			const checks = slots.map((slot) => {
				const taken = todayMedEntries.some(
					(e) => (e.data.name as string)?.toLowerCase() === med.name.toLowerCase()
						&& (e.data.time as string) === slot
				);
				return { slot, taken };
			});
			return { med, checks };
		});
	});

	const allDosesTaken = $derived(
		scheduledMeds.length > 0 && doseChecklist.every((dc) => dc.checks.every((c) => c.taken))
	);

	const remainingDoses = $derived(
		doseChecklist.reduce((sum, dc) => sum + dc.checks.filter((c) => !c.taken).length, 0)
	);

	function logDose(med: MedItem, slot: string) {
		entries.add('medication', {
			date: today,
			name: med.name,
			dose: med.dose,
			time: slot,
			sideEffects: [],
			severity: 0,
			notes: ''
		});
		toast.show(`${med.name} (${timeLabels[slot] ?? slot}) ${t.today_page.logged}`);
	}

	/* --- Active symptoms --- */
	const ongoingSymptoms = $derived(
		store.items.filter((e) => {
			return e.type === 'symptom' && dateOf(e) === today && e.data.duration === 'ongoing';
		})
	);

	function severityColor(sev: number): string {
		if (sev <= 3) return 'var(--c-done)';
		if (sev <= 6) return '#e8a735';
		return '#e53e3e';
	}

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

	const weekGrid = $derived.by(() => {
		return weekScores.map(s => {
			const cks = store.items.filter(e => e.type === 'checkin' && dateOf(e) === s.date);
			const last = cks[cks.length - 1];
			return {
				...s,
				dayLabel: new Date(s.date + 'T12:00:00').toLocaleDateString(locale === 'en' ? 'en-US' : 'es-ES', { weekday: 'short' }),
				dayNum: new Date(s.date + 'T12:00:00').getDate(),
				mood: last ? Number(last.data.mood) || null : null,
				energy: last ? Number(last.data.energy) || null : null,
				sleep: last ? Number(last.data.sleep) || null : null,
				isToday: s.date === today
			};
		});
	});

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

	/* --- Insights --- */
	type Insight = { text: string; type: 'positive' | 'neutral' | 'warning' };

	const insights = $derived.by((): Insight[] => {
		const allItems = store.items;
		const result: Insight[] = [];

		// Helper: get unique dates from entries
		function uniqueDates(entries: Entry[]): Set<string> {
			return new Set(entries.map(e => dateOf(e)));
		}

		// 1. Habit-mood link: compare avg mood on days WITH a habit vs WITHOUT
		const checkinsByDate = new Map<string, Entry[]>();
		for (const e of allItems.filter(e => e.type === 'checkin')) {
			const d = dateOf(e);
			if (!checkinsByDate.has(d)) checkinsByDate.set(d, []);
			checkinsByDate.get(d)!.push(e);
		}

		const habitsByDate = new Map<string, Set<string>>();
		for (const e of allItems.filter(e => e.type === 'habit')) {
			const d = dateOf(e);
			if (!habitsByDate.has(d)) habitsByDate.set(d, new Set());
			habitsByDate.get(d)!.add(e.data.habit as string);
		}

		const datesWithMood = [...checkinsByDate.entries()].filter(([_, entries]) => {
			const latest = entries[entries.length - 1];
			return typeof latest.data.mood === 'number' && latest.data.mood > 0;
		});

		if (datesWithMood.length >= 10) {
			const trackableHabits = ['meditation', 'cold', 'fasting', 'wimhof'];
			let bestHabit = '';
			let bestDelta = 0;

			for (const habit of trackableHabits) {
				const withHabit: number[] = [];
				const withoutHabit: number[] = [];

				for (const [date, entries] of datesWithMood) {
					const mood = Number(entries[entries.length - 1].data.mood) || 0;
					const hasHabit = habitsByDate.get(date)?.has(habit) ?? false;
					if (hasHabit) withHabit.push(mood);
					else withoutHabit.push(mood);
				}

				if (withHabit.length >= 5 && withoutHabit.length >= 5) {
					const avgWith = withHabit.reduce((s, v) => s + v, 0) / withHabit.length;
					const avgWithout = withoutHabit.reduce((s, v) => s + v, 0) / withoutHabit.length;
					const delta = avgWith - avgWithout;
					if (delta > bestDelta) {
						bestDelta = delta;
						bestHabit = habit;
					}
				}
			}

			if (bestDelta >= 0.3 && bestHabit) {
				const label = bestHabit.charAt(0).toUpperCase() + bestHabit.slice(1);
				result.push({
					text: t.insights.moodHigherOnDays.replace('{delta}', bestDelta.toFixed(1)).replace('{habit}', label.toLowerCase()),
					type: 'positive'
				});
			}
		}

		// 2. Training streak
		const trainingDates = uniqueDates(allItems.filter(e => TRAINING_TYPES.includes(e.type)));
		if (trainingDates.size > 0) {
			let currentStreak = 0;
			for (let i = 0; i <= 365; i++) {
				const d = isoForDaysAgo(i);
				if (trainingDates.has(d)) currentStreak++;
				else break;
			}

			let bestStreak = 0;
			let tempStreak = 0;
			const sortedDates = [...trainingDates].sort();
			for (let i = 0; i < sortedDates.length; i++) {
				if (i === 0) {
					tempStreak = 1;
				} else {
					const prev = new Date(sortedDates[i - 1] + 'T12:00:00');
					const curr = new Date(sortedDates[i] + 'T12:00:00');
					const diff = Math.round((curr.getTime() - prev.getTime()) / 86400000);
					if (diff === 1) tempStreak++;
					else tempStreak = 1;
				}
				bestStreak = Math.max(bestStreak, tempStreak);
			}

			if (currentStreak >= 2) {
				result.push({
					text: t.insights.trainingStreak.replace('{count}', String(currentStreak)) + (bestStreak > currentStreak ? ` ${t.insights.bestStreak.replace('{count}', String(bestStreak))}` : ` ${t.insights.newRecord}`),
					type: 'positive'
				});
			}
		}

		// 3. Sleep-energy correlation
		const checkins30 = allItems
			.filter(e => e.type === 'checkin' && dateOf(e) >= isoForDaysAgo(30))
			.filter(e => typeof e.data.sleep === 'number' && (e.data.sleep as number) > 0);

		if (checkins30.length >= 7) {
			const sleepValues = checkins30.map(e => Number(e.data.sleep) || 0);
			const avgSleep = sleepValues.reduce((s, v) => s + v, 0) / sleepValues.length;

			const yesterdaySleepEntry = yesterdayCheckins.find(e => typeof e.data.sleep === 'number' && (e.data.sleep as number) > 0)
				?? todayCheckins.find(e => typeof e.data.sleep === 'number' && (e.data.sleep as number) > 0);

			if (yesterdaySleepEntry) {
				const lastSleep = Number(yesterdaySleepEntry.data.sleep) || 0;
				const diff = lastSleep - avgSleep;
				if (diff > 1) {
					result.push({
						text: t.insights.greatSleep,
						type: 'positive'
					});
				} else if (diff < -1) {
					result.push({
						text: t.insights.belowSleep,
						type: 'warning'
					});
				}
			}
		}

		// 4. Supplement adherence trend (this week vs last week)
		if (supplementStack.length > 0) {
			function weekAdherence(startDaysAgo: number): number {
				let totalMatched = 0;
				let totalDays = 0;
				for (let i = startDaysAgo; i < startDaysAgo + 7; i++) {
					const d = isoForDaysAgo(i);
					const daySupps = allItems.filter(e => e.type === 'supplement' && dateOf(e) === d);
					const taken = new Set(daySupps.map(e => (e.data.name as string ?? '').toLowerCase()));
					const matched = supplementStack.filter(s => taken.has(s.name.toLowerCase())).length;
					totalMatched += matched;
					totalDays++;
				}
				return (totalMatched / (totalDays * supplementStack.length)) * 100;
			}

			const thisWeek = weekAdherence(0);
			const lastWeek = weekAdherence(7);

			if (lastWeek > 0) {
				const delta = Math.round(thisWeek - lastWeek);
				if (delta > 5) {
					result.push({
						text: t.insights.suppAdherenceUp.replace('{pct}', String(delta)),
						type: 'positive'
					});
				} else if (delta < -10) {
					result.push({
						text: t.insights.suppAdherenceDown.replace('{pct}', String(Math.abs(delta))),
						type: 'warning'
					});
				}
			}
		}

		// 5. Check-in consistency (last 7 days)
		let checkinDaysCount = 0;
		for (let i = 0; i < 7; i++) {
			const d = isoForDaysAgo(i);
			if (allItems.some(e => e.type === 'checkin' && dateOf(e) === d)) {
				checkinDaysCount++;
			}
		}

		if (checkinDaysCount > 0 && checkinDaysCount < 5) {
			result.push({
				text: t.insights.checkinPartial.replace('{count}', String(checkinDaysCount)),
				type: 'warning'
			});
		} else if (checkinDaysCount === 7) {
			result.push({
				text: t.today_page.perfectStreak,
				type: 'positive'
			});
		}

		// 6. Hydration vs target
		const hydrationTarget = Number(ui.get().hydrationTarget) || 0;
		if (hydrationTarget > 0) {
			const todayHydration = allItems.filter(e => e.type === 'hydration' && dateOf(e) === today);
			const totalMl = todayHydration.reduce((s, e) => s + (Number(e.data.amount) || 0), 0);
			const pct = Math.round((totalMl / hydrationTarget) * 100);
			if (pct >= 100) {
				result.push({
					text: `${t.today_page.hydrationReached} (${(totalMl / 1000).toFixed(1)}L / ${(hydrationTarget / 1000).toFixed(1)}L)`,
					type: 'positive'
				});
			} else if (pct > 0) {
				result.push({
					text: t.insights.hydrationProgress.replace('{current}', (totalMl / 1000).toFixed(1)).replace('{target}', (hydrationTarget / 1000).toFixed(1)).replace('{pct}', String(pct)),
					type: pct >= 50 ? 'neutral' : 'warning'
				});
			}
		}

		return result.slice(0, 4);
	});

	/* --- Onboarding --- */
	interface OnboardingStep {
		id: string;
		title: string;
		description: string;
		href: string;
		done: boolean;
	}

	const onboardingSteps = $derived.by((): OnboardingStep[] => {
		const allItems = store.items;
		return [
			{
				id: 'profile',
				title: t.today_page.setProfile,
				description: t.today_page.setProfileDesc,
				href: '/profile',
				done: allItems.some(e => e.type === 'weight')
			},
			{
				id: 'checkin',
				title: t.today_page.firstCheckin,
				description: t.today_page.firstCheckinDesc,
				href: '/checkin',
				done: allItems.some(e => e.type === 'checkin')
			},
			{
				id: 'intake',
				title: t.today_page.logMeal,
				description: t.today_page.logMealDesc,
				href: '/intake',
				done: allItems.some(e => e.type === 'intake')
			},
			{
				id: 'habit',
				title: t.today_page.defineHabits,
				description: t.today_page.defineHabitsDesc,
				href: '/habits',
				done: allItems.some(e => e.type === 'habit')
			},
			{
				id: 'goal',
				title: t.today_page.setGoal,
				description: t.today_page.setGoalDesc,
				href: '/goals',
				done: allItems.some(e => e.type === 'goal')
			}
		];
	});

	const allOnboardingDone = $derived(onboardingSteps.every(s => s.done));
	const showOnboarding = $derived(!ui.get().onboardingComplete && store.items.length < 3);

	// Auto-complete onboarding if all steps are done and user has data
	$effect(() => {
		if (allOnboardingDone && !ui.get().onboardingComplete && store.items.length >= 3) {
			ui.patch({ onboardingComplete: true });
		}
	});

	function completeOnboarding() {
		ui.patch({ onboardingComplete: true });
	}

	/* --- What's missing --- */
	const missingItems = $derived.by(() => {
		const items: Array<{ label: string; hint: string; href: string; icon: string }> = [];

		if (todayCheckins.length === 0) {
			items.push({
				label: t.today_page.checkin,
				hint: t.today_page.checkinTask,
				href: '/checkin',
				icon: 'checkin'
			});
		}

		if (todayTraining.length === 0) {
			items.push({
				label: t.today_page.training,
				hint: t.today_page.trainingTask,
				href: '/training',
				icon: 'training'
			});
		}

		const loggedHabitIds = new Set(todayHabits.map(e => e.data.habit as string));
		const remainingHabits = allHabitTypes.filter(id => !loggedHabitIds.has(id)).length;
		if (remainingHabits > 0) {
			items.push({
				label: `${t.today_page.habits} (${remainingHabits} ${t.today_page.habitsRemaining})`,
				hint: t.today_page.trackHabits,
				href: '/habits',
				icon: 'habit'
			});
		}

		const takenNames = new Set(todaySupplements.map(e => (e.data.name as string ?? '').toLowerCase()));
		const remainingSupps = supplementStack.filter(s => !takenNames.has(s.name.toLowerCase())).length;
		if (remainingSupps > 0) {
			items.push({
				label: `${t.today_page.supplements} (${remainingSupps} ${t.today_page.supplementsRemaining})`,
				hint: t.today_page.logSupplements,
				href: '/supplements',
				icon: 'supplement'
			});
		}

		if (scheduledMeds.length > 0 && !allDosesTaken) {
			items.push({
				label: `${t.today_page.medications} (${remainingDoses} ${remainingDoses !== 1 ? t.today_page.doses : t.today_page.dose} ${t.today_page.medicationsRemaining})`,
				hint: t.today_page.logMedications,
				href: '/medications',
				icon: 'medication'
			});
		}

		if (todayJournal.length === 0) {
			items.push({
				label: t.today_page.journal,
				hint: t.today_page.writeThoughts,
				href: '/journal',
				icon: 'journal'
			});
		}

		return items;
	});
</script>

<svelte:head>
	<title>{t.nav.today} | Darink</title>
</svelte:head>

<section class="today-page">
	<!-- Greeting -->
	<header class="greeting">
		<h1>{greeting}</h1>
		<p class="date-label">{dateLabel}</p>
	</header>

	{#if showOnboarding}
		<!-- Onboarding wizard -->
		<div class="onboarding">
			<div class="onboarding-header">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--c-accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
				<div>
					<h2 class="onboarding-title">{t.today_page.getStarted}</h2>
					<p class="onboarding-subtitle">{t.today_page.setupSteps}</p>
				</div>
			</div>

			{#if allOnboardingDone}
				<div class="onboarding-complete">
					<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--c-done)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
						<path d="m9 11 3 3L22 4"/>
					</svg>
					<p class="complete-title">{t.today_page.allSet}</p>
					<p class="complete-hint">{t.today_page.trackerReady}</p>
					<button class="complete-btn" onclick={completeOnboarding}>{t.today_page.startTracking}</button>
				</div>
			{:else}
				<div class="onboarding-steps">
					{#each onboardingSteps as step, i}
						<a href={step.href} class="onboarding-step" class:step-done={step.done}>
							<span class="step-num">
								{#if step.done}
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--c-done)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 12 2 2 4-4"/></svg>
								{:else}
									{i + 1}
								{/if}
							</span>
							<span class="step-content">
								<span class="step-title">{step.title}</span>
								<span class="step-desc">{step.description}</span>
							</span>
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--c-text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
						</a>
					{/each}
				</div>
			{/if}
		</div>
	{:else}
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
					>{t.today_page.dailyScore}</text>
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
					>{t.today_page.noDataToday}</text>
				</svg>
			{/if}
		</div>

		<!-- 7-day trend sparkline -->
		{#if weekWithData.length >= 2}
			<div class="trend-section">
				<h2>{t.today_page.sevenDayTrend}</h2>
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
								{new Date(s.date + 'T12:00:00').toLocaleDateString(locale === 'en' ? 'en-US' : 'es-ES', { weekday: 'narrow' })}
							</span>
						{/each}
					</div>
				</div>
			</div>
		{/if}

		<!-- Week Grid -->
		{#if weekWithData.length >= 2}
			<div class="week-grid-section">
				<h2>{t.common.thisWeek}</h2>
				<div class="week-grid">
					{#each weekGrid as day}
						<div class="week-grid-cell" class:today={day.isToday} class:empty={!day.hasData}>
							<span class="wg-day">{day.dayLabel}</span>
							<span class="wg-num">{day.dayNum}</span>
							{#if day.hasData}
								<span class="wg-score" style="color:{scoreColor(day.score)}">{day.score}</span>
								{#if day.mood !== null}<span class="wg-metric">{t.common.mood} {day.mood}</span>{/if}
								{#if day.energy !== null}<span class="wg-metric">{t.common.energy} {day.energy}</span>{/if}
								{#if day.sleep !== null}<span class="wg-metric">{t.common.sleep} {day.sleep}h</span>{/if}
							{:else}
								<span class="wg-empty">—</span>
							{/if}
						</div>
					{/each}
				</div>
			</div>
		{/if}

		<!-- Insights -->
		{#if insights.length > 0}
			<div class="insights-section">
				<h2>{t.today_page.insights}</h2>
				<div class="insights-list">
					{#each insights as insight}
						<div class="insight-card insight-{insight.type}">
							<span class="insight-stripe"></span>
							<span class="insight-icon">
								{#if insight.type === 'positive'}
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--c-done)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2h0a3.13 3.13 0 0 1 3 3.88Z"/></svg>
								{:else if insight.type === 'warning'}
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e8a735" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
								{:else}
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--c-text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
								{/if}
							</span>
							<span class="insight-text">{insight.text}</span>
						</div>
					{/each}
				</div>
			</div>
		{/if}

		<!-- Medications -->
		{#if scheduledMeds.length > 0}
			<div class="meds-section">
				<h2>{t.today_page.medications}</h2>
				{#if allDosesTaken}
					<div class="meds-alldone">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--c-done)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
						<span>{t.today_page.allDosesTaken}</span>
					</div>
				{:else}
					<div class="meds-list">
						{#each doseChecklist as { med, checks }}
							<div class="med-card">
								<div class="med-card-header">
									<span class="med-card-name">{med.name}</span>
									{#if med.dose}
										<span class="med-card-dose">{med.dose}</span>
									{/if}
								</div>
								<div class="med-card-dots">
									{#each checks as { slot, taken }}
										{#if taken}
											<span class="dose-dot dose-dot-taken" title="{timeLabels[slot] ?? slot}">
												<svg width="12" height="12" viewBox="0 0 24 24" fill="var(--c-done)" stroke="none"><circle cx="12" cy="12" r="10"/></svg>
											</span>
										{:else}
											<button
												class="dose-dot dose-dot-pending"
												title="{timeLabels[slot] ?? slot}"
												onclick={() => logDose(med, slot)}
											>
												<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--c-text-muted)" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
											</button>
										{/if}
									{/each}
								</div>
							</div>
						{/each}
					</div>
				{/if}
			</div>
		{/if}

		<!-- Active Symptoms -->
		{#if ongoingSymptoms.length > 0}
			<div class="symptoms-section">
				<h2>{t.today_page.activeSymptoms}</h2>
				<a href="/symptoms" class="symptoms-banner">
					<div class="symptoms-items">
						{#each ongoingSymptoms.slice(0, 3) as entry}
							{@const sev = Number(entry.data.severity) || 0}
							<span class="symptom-tag">
								<span class="symptom-dot" style="background:{severityColor(sev)}"></span>
								<span class="symptom-name">{entry.data.symptom}</span>
							</span>
						{/each}
						{#if ongoingSymptoms.length > 3}
							<span class="symptom-more">+{ongoingSymptoms.length - 3} {t.today_page.more}</span>
						{/if}
					</div>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--c-text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
				</a>
			</div>
		{/if}

		<!-- What's missing today -->
		<div class="missing-section">
			<h2>{t.today_page.todayProgress}</h2>
			{#if missingItems.length === 0}
				<div class="all-done">
					<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--c-done)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
						<path d="m9 11 3 3L22 4"/>
					</svg>
					<p>{t.today_page.allCaughtUp}</p>
					<p class="all-done-hint">{t.today_page.loggedEverything}</p>
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
								{:else if item.icon === 'medication'}
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
	{/if}
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

	/* Week Grid */
	.week-grid-section { padding: 0.75rem 0; }
	.week-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
	.week-grid-cell {
		display: flex; flex-direction: column; align-items: center; gap: 2px;
		padding: 0.4rem 0.2rem; background: var(--c-bg-card); border: 1px solid var(--c-border);
		border-radius: var(--radius); text-align: center; min-width: 0;
	}
	.week-grid-cell.today { border-color: var(--c-accent); }
	.week-grid-cell.empty { opacity: 0.5; }
	.wg-day { font-size: 0.6rem; color: var(--c-text-muted); text-transform: uppercase; font-weight: 600; }
	.wg-num { font-size: 0.75rem; font-weight: 700; }
	.wg-score { font-size: 1.1rem; font-weight: 700; }
	.wg-metric { font-size: 0.55rem; color: var(--c-text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
	.wg-empty { font-size: 1rem; color: var(--c-text-muted); }

	/* Insights */
	.insights-section {
		padding: 0.75rem 0;
	}

	.insights-list {
		display: flex;
		flex-direction: column;
		gap: 0.375rem;
	}

	.insight-card {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.6rem 0.75rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		position: relative;
		overflow: hidden;
	}

	.insight-stripe {
		position: absolute;
		left: 0;
		top: 0;
		bottom: 0;
		width: 3px;
	}

	.insight-positive .insight-stripe {
		background: var(--c-done);
	}

	.insight-neutral .insight-stripe {
		background: var(--c-text-muted);
	}

	.insight-warning .insight-stripe {
		background: #e8a735;
	}

	.insight-icon {
		display: flex;
		align-items: center;
		flex-shrink: 0;
	}

	.insight-text {
		font-size: 0.82rem;
		color: var(--c-text);
		line-height: 1.3;
	}

	/* Onboarding */
	.onboarding {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 1.25rem;
		margin-top: 0.5rem;
	}

	.onboarding-header {
		display: flex;
		align-items: center;
		gap: 0.75rem;
		margin-bottom: 1rem;
	}

	.onboarding-title {
		font-size: 1rem;
		font-weight: 700;
		margin: 0;
		color: var(--c-text);
		text-transform: none;
		letter-spacing: normal;
	}

	.onboarding-subtitle {
		font-size: 0.8rem;
		color: var(--c-text-muted);
		margin: 0.15rem 0 0;
	}

	.onboarding-steps {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}

	.onboarding-step {
		display: flex;
		align-items: center;
		gap: 0.75rem;
		padding: 0.75rem;
		background: var(--c-bg);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		text-decoration: none;
		color: var(--c-text);
		transition: border-color 0.15s;
	}

	.onboarding-step:hover {
		border-color: var(--c-accent);
	}

	.onboarding-step.step-done {
		opacity: 0.6;
	}

	.step-num {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 28px;
		height: 28px;
		flex-shrink: 0;
		border-radius: 50%;
		background: var(--c-accent-bg);
		color: var(--c-accent);
		font-size: 0.8rem;
		font-weight: 700;
	}

	.step-done .step-num {
		background: transparent;
	}

	.step-content {
		flex: 1;
		display: flex;
		flex-direction: column;
		gap: 0.1rem;
	}

	.step-title {
		font-weight: 600;
		font-size: 0.9rem;
	}

	.step-done .step-title {
		text-decoration: line-through;
	}

	.step-desc {
		font-size: 0.75rem;
		color: var(--c-text-muted);
	}

	.onboarding-complete {
		text-align: center;
		padding: 1rem 0;
	}

	.complete-title {
		margin: 0.75rem 0 0.25rem;
		font-weight: 700;
		font-size: 1.1rem;
		color: var(--c-done);
	}

	.complete-hint {
		margin: 0 0 1rem;
		font-size: 0.85rem;
		color: var(--c-text-muted);
	}

	.complete-btn {
		display: inline-block;
		padding: 0.6rem 1.5rem;
		background: var(--c-accent);
		color: white;
		border: none;
		border-radius: var(--radius);
		font-weight: 600;
		font-size: 0.9rem;
		cursor: pointer;
		transition: opacity 0.15s;
	}

	.complete-btn:hover {
		opacity: 0.9;
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

	/* Medications section */
	.meds-section {
		padding: 0.75rem 0;
	}

	.meds-alldone {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.6rem 0.75rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-done);
		border-radius: var(--radius);
		color: var(--c-done);
		font-weight: 600;
		font-size: 0.85rem;
	}

	.meds-list {
		display: flex;
		flex-direction: column;
		gap: 0.375rem;
	}

	.med-card {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 0.75rem;
		padding: 0.5rem 0.75rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}

	.med-card-header {
		display: flex;
		align-items: baseline;
		gap: 0.4rem;
		min-width: 0;
		flex: 1;
	}

	.med-card-name {
		font-weight: 600;
		font-size: 0.85rem;
		color: var(--c-text);
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.med-card-dose {
		font-size: 0.75rem;
		color: var(--c-text-muted);
		white-space: nowrap;
	}

	.med-card-dots {
		display: flex;
		align-items: center;
		gap: 0.35rem;
		flex-shrink: 0;
	}

	.dose-dot {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 20px;
		height: 20px;
	}

	.dose-dot-pending {
		background: none;
		border: none;
		padding: 0;
		cursor: pointer;
		border-radius: 50%;
		transition: transform 0.1s;
	}

	.dose-dot-pending:hover {
		transform: scale(1.3);
	}

	.dose-dot-taken {
		cursor: default;
	}

	/* Active Symptoms section */
	.symptoms-section {
		padding: 0.75rem 0;
	}

	.symptoms-banner {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.6rem 0.75rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		text-decoration: none;
		color: var(--c-text);
		transition: border-color 0.15s;
	}

	.symptoms-banner:hover {
		border-color: var(--c-accent);
	}

	.symptoms-items {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		flex: 1;
		flex-wrap: wrap;
	}

	.symptom-tag {
		display: flex;
		align-items: center;
		gap: 0.3rem;
		font-size: 0.82rem;
	}

	.symptom-dot {
		width: 8px;
		height: 8px;
		border-radius: 50%;
		flex-shrink: 0;
	}

	.symptom-name {
		text-transform: capitalize;
	}

	.symptom-more {
		font-size: 0.75rem;
		color: var(--c-text-muted);
		font-weight: 500;
	}
</style>
