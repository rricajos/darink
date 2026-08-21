<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { useLocale } from '$lib/stores/locale.svelte';
	import type { Entry } from '$lib/db';

	const { t } = useLocale();
	const store = useEntries('checkin');

	let date = $state(new Date().toISOString().slice(0, 10));
	let mood = $state(5);
	let energy = $state(5);
	let sleep = $state(7);
	let stress = $state(3);
	let morningErection = $state(false);
	let notes = $state('');
	let errors = $state<Record<string, string>>({});

	function validate(): boolean {
		const e: Record<string, string> = {};
		const m = Number(mood);
		const en = Number(energy);
		const st = Number(stress);
		const sl = Number(sleep);
		if (!Number.isInteger(m) || m < 1 || m > 10) e.mood = t.checkin.moodMustBe;
		if (!Number.isInteger(en) || en < 1 || en > 10) e.energy = t.checkin.energyMustBe;
		if (!Number.isInteger(st) || st < 1 || st > 10) e.stress = t.checkin.stressMustBe;
		if (isNaN(sl) || sl < 0 || sl > 24) e.sleep = t.checkin.sleepMustBe;
		errors = e;
		return Object.keys(e).length === 0;
	}

	function clearError(field: string) {
		if (errors[field]) {
			const next = { ...errors };
			delete next[field];
			errors = next;
		}
	}

	function submit() {
		if (!validate()) return;
		entries.add('checkin', {
			date, mood, energy, sleep, stress, morningErection, notes,
			period: new Date().getHours() < 14 ? 'morning' : 'night'
		});
		date = new Date().toISOString().slice(0, 10);
		mood = 5; energy = 5; sleep = 7; stress = 3;
		morningErection = false; notes = '';
		errors = {};
		toast.show(t.checkin.checkinSaved);
	}

	const dayOfWeekPattern = $derived.by(() => {
		if (store.items.length < 7) return null;
		const dayNames = [t.days.mon, t.days.tue, t.days.wed, t.days.thu, t.days.fri, t.days.sat, t.days.sun];
		const buckets: { mood: number[]; energy: number[]; stress: number[] }[] = Array.from({ length: 7 }, () => ({ mood: [], energy: [], stress: [] }));
		for (const e of store.items) {
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			const day = (new Date(d + 'T12:00:00').getDay() + 6) % 7;
			buckets[day].mood.push(Number(e.data.mood) || 5);
			buckets[day].energy.push(Number(e.data.energy) || 5);
			buckets[day].stress.push(Number(e.data.stress) || 3);
		}
		const avg = (a: number[]) => a.length ? +(a.reduce((s, v) => s + v, 0) / a.length).toFixed(1) : 0;
		return dayNames.map((name, i) => ({
			name,
			mood: avg(buckets[i].mood),
			energy: avg(buckets[i].energy),
			stress: avg(buckets[i].stress),
			count: buckets[i].mood.length
		}));
	});

	const moodEnergyCorrelation = $derived.by(() => {
		if (store.items.length < 10) return null;
		const pairs = store.items.map(e => ({ mood: Number(e.data.mood) || 5, energy: Number(e.data.energy) || 5 }));
		const n = pairs.length;
		const sumM = pairs.reduce((s, p) => s + p.mood, 0);
		const sumE = pairs.reduce((s, p) => s + p.energy, 0);
		const sumME = pairs.reduce((s, p) => s + p.mood * p.energy, 0);
		const sumM2 = pairs.reduce((s, p) => s + p.mood * p.mood, 0);
		const sumE2 = pairs.reduce((s, p) => s + p.energy * p.energy, 0);
		const num = n * sumME - sumM * sumE;
		const den = Math.sqrt((n * sumM2 - sumM * sumM) * (n * sumE2 - sumE * sumE));
		const r = den > 0 ? +(num / den).toFixed(2) : 0;
		const label = Math.abs(r) >= 0.7 ? t.common.strong : Math.abs(r) >= 0.4 ? t.common.moderate : t.common.weak;
		return { r, label, n };
	});

	const consistencyScore = $derived.by(() => {
		if (store.items.length < 14) return null;
		const last14 = store.items.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt)).slice(0, 14);
		const moods = last14.map(e => Number(e.data.mood) || 5);
		const avg = moods.reduce((s, v) => s + v, 0) / moods.length;
		const variance = moods.reduce((s, v) => s + (v - avg) ** 2, 0) / moods.length;
		const stdDev = +Math.sqrt(variance).toFixed(1);
		const score = Math.max(0, Math.min(10, +(10 - stdDev * 2).toFixed(1)));
		return { stdDev, score, label: score >= 7 ? 'Stable' : score >= 4 ? 'Variable' : 'Volatile' };
	});

	function repeatLast() {
		const last = store.items.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt))[0];
		if (!last) return;
		mood = Number(last.data.mood) || 5;
		energy = Number(last.data.energy) || 5;
		stress = Number(last.data.stress) || 3;
		sleep = Number(last.data.sleep) || 7;
		toast.show(t.common.prefilled);
	}

	const moodDistribution = $derived.by(() => {
		if (store.items.length < 5) return null;
		const buckets = Array.from({ length: 10 }, (_, i) => ({ value: i + 1, count: 0 }));
		for (const e of store.items) {
			const m = Math.round(Number(e.data.mood) || 5);
			if (m >= 1 && m <= 10) buckets[m - 1].count++;
		}
		const maxCount = Math.max(...buckets.map(b => b.count), 1);
		return { buckets, maxCount };
	});

	const periodComparison = $derived.by(() => {
		if (store.items.length < 3) return null;
		const mornings = store.items.filter(e => e.data.period === 'morning');
		const nights = store.items.filter(e => e.data.period === 'night');
		if (mornings.length < 2 || nights.length < 2) return null;
		const avg = (arr: typeof store.items, field: string) =>
			+(arr.reduce((s, e) => s + (Number(e.data[field]) || 0), 0) / arr.length).toFixed(1);
		return {
			morning: { mood: avg(mornings, 'mood'), energy: avg(mornings, 'energy'), stress: avg(mornings, 'stress'), count: mornings.length },
			night: { mood: avg(nights, 'mood'), energy: avg(nights, 'energy'), stress: avg(nights, 'stress'), count: nights.length }
		};
	});
</script>

<svelte:head>
  <title>{t.checkin.title} | Darink</title>
</svelte:head>

<PageHeader title={t.checkin.title} />

<section class="form">
	<label>{t.common.date} <input type="date" bind:value={date} /></label>
	<label class:field-has-error={!!errors.mood}>{t.common.mood} ({mood}/10) <input type="range" min="1" max="10" bind:value={mood} oninput={() => clearError('mood')} /></label>
	{#if errors.mood}<span class="field-error">{errors.mood}</span>{/if}
	<label class:field-has-error={!!errors.energy}>{t.common.energy} ({energy}/10) <input type="range" min="1" max="10" bind:value={energy} oninput={() => clearError('energy')} /></label>
	{#if errors.energy}<span class="field-error">{errors.energy}</span>{/if}
	<label class:field-has-error={!!errors.sleep}>{t.checkin.sleepLabel} ({sleep}) <input type="number" min="0" max="14" step="0.5" bind:value={sleep} oninput={() => clearError('sleep')} /></label>
	{#if errors.sleep}<span class="field-error">{errors.sleep}</span>{/if}
	<label class:field-has-error={!!errors.stress}>{t.common.stress} ({stress}/10) <input type="range" min="1" max="10" bind:value={stress} oninput={() => clearError('stress')} /></label>
	{#if errors.stress}<span class="field-error">{errors.stress}</span>{/if}
	<label class="checkbox"><input type="checkbox" bind:checked={morningErection} /> {t.checkin.morningErection}</label>
	<label>{t.common.notes} <textarea bind:value={notes} placeholder={t.checkin.howFeeling} rows="2"></textarea></label>
	<div class="form-actions">
		<button class="primary" onclick={submit}>{t.checkin.saveCheckin}</button>
		{#if store.items.length > 0}
			<button onclick={repeatLast}>{t.common.repeatLast}</button>
		{/if}
	</div>
</section>

<!-- Quick Stats Row -->
{#if store.items.length > 0}
	{@const totalCheckins = store.items.length}
	{@const avgMoodAll = (store.items.reduce((s, e) => s + (Number(e.data.mood) || 0), 0) / totalCheckins).toFixed(1)}
	{@const daySet = new Set(store.items.map(e => e.createdAt.slice(0, 10)))}
	{@const sortedDays = [...daySet].sort().reverse()}
	{@const streak = (() => {
		let count = 0;
		const today = new Date();
		for (let i = 0; i < sortedDays.length; i++) {
			const expected = new Date(today.getFullYear(), today.getMonth(), today.getDate() - i).toISOString().slice(0, 10);
			if (sortedDays[i] === expected) count++;
			else break;
		}
		return count;
	})()}
	<section class="quick-stats">
		<div class="quick-stat-card">
			<span class="quick-stat-value">{totalCheckins}</span>
			<span class="quick-stat-label">{t.checkin.checkins}</span>
		</div>
		<div class="quick-stat-card">
			<span class="quick-stat-value">{avgMoodAll}</span>
			<span class="quick-stat-label">{t.common.average} {t.common.mood.toLowerCase()}</span>
		</div>
		<div class="quick-stat-card">
			<span class="quick-stat-value">{streak}<span class="quick-stat-unit">d</span></span>
			<span class="quick-stat-label">{t.common.streak}</span>
		</div>
	</section>
{/if}

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			mood: Number(fd.get('mood')),
			energy: Number(fd.get('energy')),
			sleep: Number(fd.get('sleep')),
			stress: Number(fd.get('stress')),
			morningErection: fd.has('morningErection'),
			notes: fd.get('notes') ?? ''
		});
		toast.show(t.common.updated);
		done();
	}}>
		<label>{t.common.mood} ({data.mood}/10) <input type="range" name="mood" min="1" max="10" value={data.mood} /></label>
		<label>{t.common.energy} ({data.energy}/10) <input type="range" name="energy" min="1" max="10" value={data.energy} /></label>
		<label>{t.checkin.sleepLabel} <input type="number" name="sleep" min="0" max="14" step="0.5" value={data.sleep} /></label>
		<label>{t.common.stress} ({data.stress}/10) <input type="range" name="stress" min="1" max="10" value={data.stress} /></label>
		<label class="checkbox"><input type="checkbox" name="morningErection" checked={!!data.morningErection} /> {t.checkin.morningErection}</label>
		<label>{t.common.notes} <textarea name="notes" rows="2">{data.notes ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">{t.common.save}</button>
			<button type="button" onclick={done}>{t.common.cancel}</button>
		</div>
	</form>
{/snippet}

{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M9 14h.01"/><path d="M13 14h.01"/><path d="M9 18h.01"/><path d="M13 18h.01"/></svg>
	<p>{t.checkin.noCheckins}</p>
	<p class="empty-hint">{t.checkin.noCheckinsHint}</p>
</div>
{/if}

<EntryList items={store.items} limit={7} {editForm}>
	{#snippet row(item)}
		<span class="checkin-row">{#if item.data.period === 'morning'}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; display: inline-block;"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>{:else}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; display: inline-block;"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>{/if} M{item.data.mood} E{item.data.energy} S{item.data.stress} · {item.data.sleep}h</span>
	{/snippet}
</EntryList>

<!-- Analytics: Trend Charts (last 30 check-ins) -->
{#if store.items.length > 1}
	{@const sorted = [...store.items].sort((a, b) => a.createdAt.localeCompare(b.createdAt)).slice(-30)}
	{@const stepX = 280 / Math.max(sorted.length - 1, 1)}
	{@const charts = [
		{ key: 'mood', label: t.common.mood, color: '#4aa3ff', min: 1, max: 10, vals: sorted.map(e => Number(e.data.mood) || 5) },
		{ key: 'energy', label: t.common.energy, color: '#2e8b57', min: 1, max: 10, vals: sorted.map(e => Number(e.data.energy) || 5) },
		{ key: 'sleep', label: t.checkin.sleepH, color: '#9b59b6', min: 0, max: 14, vals: sorted.map(e => Number(e.data.sleep) || 7) },
		{ key: 'stress', label: t.common.stress, color: '#e53e3e', min: 1, max: 10, vals: sorted.map(e => Number(e.data.stress) || 3) }
	]}
	<section class="trend-section">
		<h2>{t.checkin.trendsLast} {sorted.length})</h2>
		{#each charts as chart}
			{@const rangeY = chart.max - chart.min || 1}
			{@const pts = chart.vals.map((v, i) => `${i * stepX},${52 - ((v - chart.min) / rangeY) * 44}`)}
			{@const current = chart.vals[chart.vals.length - 1]}
			{@const compare = chart.vals.length >= 8 ? chart.vals[chart.vals.length - 8] : chart.vals[0]}
			{@const diff = current - compare}
			{@const arrow = diff > 0.5 ? 'up' : diff < -0.5 ? 'down' : 'flat'}
			<div class="trend-row">
				<div class="trend-meta">
					<span class="trend-label" style="color:{chart.color}">{chart.label}</span>
					<span class="trend-current">{chart.key === 'sleep' ? current.toFixed(1) : current}
						{#if arrow === 'up'}
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#2e8b57" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
						{:else if arrow === 'down'}
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#e53e3e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
						{:else}
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--c-text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
						{/if}
					</span>
				</div>
				<svg class="mini-chart" viewBox="0 0 280 60">
					<polyline points={pts.join(' ')} fill="none" stroke={chart.color} stroke-width="2" stroke-linejoin="round" />
					{#each chart.vals as v, i}
						<circle cx={i * stepX} cy={52 - ((v - chart.min) / rangeY) * 44} r="2.5" fill={chart.color} />
					{/each}
				</svg>
			</div>
		{/each}
	</section>
{/if}

<!-- Analytics: Weekly Averages (last 4 weeks) -->
{#if store.items.length > 0}
	{@const now = new Date()}
	{@const weeks = [0, 1, 2, 3].map(w => {
		const end = new Date(now.getFullYear(), now.getMonth(), now.getDate() - w * 7);
		const start = new Date(now.getFullYear(), now.getMonth(), now.getDate() - (w + 1) * 7);
		const items = store.items.filter(e => {
			const d = new Date(e.createdAt);
			return d > start && d <= end;
		});
		if (items.length === 0) return null;
		return {
			label: w === 0 ? t.common.thisWeek : w === 1 ? t.common.lastWeek : `${w}${t.common.weeksAgo}`,
			count: items.length,
			mood: items.reduce((s, e) => s + (Number(e.data.mood) || 0), 0) / items.length,
			energy: items.reduce((s, e) => s + (Number(e.data.energy) || 0), 0) / items.length,
			sleep: items.reduce((s, e) => s + (Number(e.data.sleep) || 0), 0) / items.length,
			stress: items.reduce((s, e) => s + (Number(e.data.stress) || 0), 0) / items.length
		};
	})}
	{@const validWeeks = weeks.filter(Boolean) as { label: string; count: number; mood: number; energy: number; sleep: number; stress: number }[]}
	{#if validWeeks.length > 0}
		{@const bestMoodIdx = validWeeks.reduce((bi, w, i, arr) => w.mood > arr[bi].mood ? i : bi, 0)}
		{@const bestEnergyIdx = validWeeks.reduce((bi, w, i, arr) => w.energy > arr[bi].energy ? i : bi, 0)}
		{@const bestSleepIdx = validWeeks.reduce((bi, w, i, arr) => w.sleep > arr[bi].sleep ? i : bi, 0)}
		{@const bestStressIdx = validWeeks.reduce((bi, w, i, arr) => w.stress < arr[bi].stress ? i : bi, 0)}
		<section class="weekly-section">
			<h2>{t.checkin.weeklyAverages}</h2>
			<div class="weekly-table">
				<div class="weekly-header">
					<span class="weekly-cell weekly-label-cell"></span>
					<span class="weekly-cell" style="color:#4aa3ff">{t.common.mood}</span>
					<span class="weekly-cell" style="color:#2e8b57">{t.common.energy}</span>
					<span class="weekly-cell" style="color:#9b59b6">{t.common.sleep}</span>
					<span class="weekly-cell" style="color:#e53e3e">{t.common.stress}</span>
				</div>
				{#each validWeeks as week, wi}
					<div class="weekly-row">
						<span class="weekly-cell weekly-label-cell">{week.label}</span>
						<span class="weekly-cell" class:weekly-best={wi === bestMoodIdx}>{week.mood.toFixed(1)}</span>
						<span class="weekly-cell" class:weekly-best={wi === bestEnergyIdx}>{week.energy.toFixed(1)}</span>
						<span class="weekly-cell" class:weekly-best={wi === bestSleepIdx}>{week.sleep.toFixed(1)}</span>
						<span class="weekly-cell" class:weekly-best={wi === bestStressIdx}>{week.stress.toFixed(1)}</span>
					</div>
				{/each}
			</div>
		</section>
	{/if}
{/if}

<!-- Analytics: Morning vs Night Comparison -->
{#if store.items.length > 0}
	{@const morningItems = store.items.filter(e => e.data.period === 'morning')}
	{@const nightItems = store.items.filter(e => e.data.period === 'night')}
	{#if morningItems.length > 0 && nightItems.length > 0}
		{@const mornMood = (morningItems.reduce((s, e) => s + (Number(e.data.mood) || 0), 0) / morningItems.length).toFixed(1)}
		{@const nightMood = (nightItems.reduce((s, e) => s + (Number(e.data.mood) || 0), 0) / nightItems.length).toFixed(1)}
		{@const mornEnergy = (morningItems.reduce((s, e) => s + (Number(e.data.energy) || 0), 0) / morningItems.length).toFixed(1)}
		{@const nightEnergy = (nightItems.reduce((s, e) => s + (Number(e.data.energy) || 0), 0) / nightItems.length).toFixed(1)}
		<section class="period-section">
			<h2>{t.checkin.morningVsNight}</h2>
			<div class="period-grid">
				<div class="period-card">
					<div class="period-header">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
						<span>{t.checkin.morningMood}</span>
					</div>
					<span class="period-value">{mornMood}</span>
					<span class="period-count">{morningItems.length} {t.common.entries}</span>
				</div>
				<div class="period-card">
					<div class="period-header">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
						<span>{t.checkin.nightMood}</span>
					</div>
					<span class="period-value">{nightMood}</span>
					<span class="period-count">{nightItems.length} {t.common.entries}</span>
				</div>
				<div class="period-card">
					<div class="period-header">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
						<span>{t.checkin.morningEnergy}</span>
					</div>
					<span class="period-value">{mornEnergy}</span>
					<span class="period-count">{morningItems.length} {t.common.entries}</span>
				</div>
				<div class="period-card">
					<div class="period-header">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
						<span>{t.checkin.nightEnergy}</span>
					</div>
					<span class="period-value">{nightEnergy}</span>
					<span class="period-count">{nightItems.length} {t.common.entries}</span>
				</div>
			</div>
		</section>
	{/if}
{/if}

{#if dayOfWeekPattern}
<section class="analytics">
	<h2>{t.checkin.dayOfWeekPattern}</h2>
	<div class="dow-table">
		<div class="dow-header">
			<span>{t.checkin.dayHeader}</span><span style="color:#4aa3ff">{t.common.mood}</span><span style="color:#2e8b57">{t.common.energy}</span><span style="color:#e53e3e">{t.common.stress}</span><span>{t.checkin.countHeader}</span>
		</div>
		{#each dayOfWeekPattern as day}
			<div class="dow-row">
				<span class="dow-name">{day.name}</span>
				<span style="font-weight:{day.mood >= 7 ? 700 : 400}">{day.mood}</span>
				<span style="font-weight:{day.energy >= 7 ? 700 : 400}">{day.energy}</span>
				<span style="font-weight:{day.stress <= 4 ? 700 : 400}">{day.stress}</span>
				<span class="dow-count">{day.count}</span>
			</div>
		{/each}
	</div>
</section>
{/if}

{#if moodEnergyCorrelation}
<section class="analytics">
	<h2>{t.checkin.moodEnergyCorrelation}</h2>
	<div class="corr-card">
		<span class="corr-r" style="color:{Math.abs(moodEnergyCorrelation.r) >= 0.7 ? 'var(--c-done)' : Math.abs(moodEnergyCorrelation.r) >= 0.4 ? '#e8a735' : 'var(--c-text-muted)'}">r = {moodEnergyCorrelation.r}</span>
		<span class="corr-label">{moodEnergyCorrelation.label} {moodEnergyCorrelation.r >= 0 ? t.common.positive : t.common.negative} {t.common.correlation}</span>
		<span class="corr-hint">{t.checkin.basedOn} {moodEnergyCorrelation.n} {t.checkin.checkins}</span>
	</div>
</section>
{/if}

{#if consistencyScore}
<section class="analytics">
	<h2>{t.checkin.moodConsistency}</h2>
	<div class="consistency-row">
		<div class="consistency-card">
			<span class="consistency-value" style="color:{consistencyScore.score >= 7 ? 'var(--c-done)' : consistencyScore.score >= 4 ? '#e8a735' : '#e53e3e'}">{consistencyScore.score}/10</span>
			<span class="consistency-label">{consistencyScore.label}</span>
		</div>
		<div class="consistency-card">
			<span class="consistency-value">{consistencyScore.stdDev}</span>
			<span class="consistency-label">{t.checkin.stdDeviation}</span>
		</div>
	</div>
</section>
{/if}

{#if moodDistribution}
<section class="analytics">
	<h2>{t.checkin.moodDistribution ?? 'Mood Distribution'}</h2>
	<div class="distrib-chart">
		{#each moodDistribution.buckets as bucket}
			<div class="distrib-col">
				<div class="distrib-bar-wrap">
					<div class="distrib-bar" style="height:{(bucket.count / moodDistribution.maxCount) * 100}%;background:{bucket.value <= 3 ? '#e53e3e' : bucket.value <= 6 ? '#e8a735' : '#22c55e'}"></div>
				</div>
				<span class="distrib-count">{bucket.count}</span>
				<span class="distrib-val">{bucket.value}</span>
			</div>
		{/each}
	</div>
</section>
{/if}

{#if periodComparison}
<section class="analytics">
	<h2>{t.checkin.morningVsNight ?? 'Morning vs Night'}</h2>
	<div class="period-grid">
		<div class="period-header"></div>
		<div class="period-header period-h-label">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
			AM ({periodComparison.morning.count})
		</div>
		<div class="period-header period-h-label">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
			PM ({periodComparison.night.count})
		</div>
		<div class="period-metric">{t.common.mood}</div>
		<div class="period-val" style="font-weight:{periodComparison.morning.mood >= periodComparison.night.mood ? 700 : 400}">{periodComparison.morning.mood}</div>
		<div class="period-val" style="font-weight:{periodComparison.night.mood >= periodComparison.morning.mood ? 700 : 400}">{periodComparison.night.mood}</div>
		<div class="period-metric">{t.common.energy}</div>
		<div class="period-val" style="font-weight:{periodComparison.morning.energy >= periodComparison.night.energy ? 700 : 400}">{periodComparison.morning.energy}</div>
		<div class="period-val" style="font-weight:{periodComparison.night.energy >= periodComparison.morning.energy ? 700 : 400}">{periodComparison.night.energy}</div>
		<div class="period-metric">{t.common.stress}</div>
		<div class="period-val" style="font-weight:{periodComparison.morning.stress <= periodComparison.night.stress ? 700 : 400}">{periodComparison.morning.stress}</div>
		<div class="period-val" style="font-weight:{periodComparison.night.stress <= periodComparison.morning.stress ? 700 : 400}">{periodComparison.night.stress}</div>
	</div>
</section>
{/if}

<style>
	.field-error { font-size: 0.75rem; color: var(--c-cancel); margin-top: 0.15rem; display: block; }
	.field-has-error { color: var(--c-cancel); }
	.field-has-error input { border-color: var(--c-cancel); }
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem 1rem; }
	.checkbox { flex-direction: row; align-items: center; gap: 0.5rem; }
	.checkbox input { width: auto; }
	input[type="range"] { padding: 0; }
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-inline input[type="range"] { padding: 0; }
	.edit-inline .checkbox { flex-direction: row; align-items: center; gap: 0.5rem; }
	.edit-inline .checkbox input { width: auto; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }
	.form-actions { display: flex; gap: 0.5rem; }
	.form-actions button { flex: 1; }

	h2 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

	/* Quick Stats */
	.quick-stats { display: flex; gap: 0.5rem; padding: 0 1rem 0.5rem; }
	.quick-stat-card { flex: 1; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.5rem 0.25rem; text-align: center; display: flex; flex-direction: column; gap: 0.1rem; }
	.quick-stat-value { font-size: 1.25rem; font-weight: 700; }
	.quick-stat-unit { font-size: 0.75rem; font-weight: 400; color: var(--c-text-muted); }
	.quick-stat-label { font-size: 0.65rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.03em; }

	/* Trend Charts */
	.trend-section { padding: 1.5rem 1rem 0; }
	.trend-row { background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.5rem 0.75rem; margin-bottom: 0.5rem; }
	.trend-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem; }
	.trend-label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
	.trend-current { font-size: 0.85rem; font-weight: 700; display: flex; align-items: center; gap: 0.2rem; }
	.mini-chart { width: 100%; height: 60px; }

	/* Weekly Averages */
	.weekly-section { padding: 1.5rem 1rem 0; }
	.weekly-table { background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); overflow: hidden; }
	.weekly-header, .weekly-row { display: flex; }
	.weekly-header { border-bottom: 1px solid var(--c-border); }
	.weekly-row + .weekly-row { border-top: 1px solid var(--c-border); }
	.weekly-cell { flex: 1; padding: 0.5rem 0.25rem; text-align: center; font-size: 0.8rem; }
	.weekly-label-cell { flex: 1.2; text-align: left; padding-left: 0.75rem; font-weight: 600; color: var(--c-text-muted); font-size: 0.75rem; }
	.weekly-header .weekly-cell { font-weight: 700; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.03em; }
	.weekly-best { background: var(--c-accent-bg); font-weight: 700; }

	/* Morning vs Night */
	.period-section { padding: 1.5rem 1rem 0; }
	.period-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
	.period-card { background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; display: flex; flex-direction: column; gap: 0.2rem; }
	.period-header { display: flex; align-items: center; gap: 0.35rem; font-size: 0.7rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; }
	.period-value { font-size: 1.4rem; font-weight: 700; }
	.period-count { font-size: 0.65rem; color: var(--c-text-muted); }

	.analytics { padding: 1.5rem 1rem 0; }
	.dow-table { background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); overflow: hidden; }
	.dow-header, .dow-row { display: grid; grid-template-columns: 2.5rem 1fr 1fr 1fr 2rem; padding: 0.4rem 0.75rem; font-size: 0.8rem; text-align: center; }
	.dow-header { font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; font-size: 0.7rem; border-bottom: 1px solid var(--c-border); }
	.dow-row:not(:last-child) { border-bottom: 1px solid var(--c-border); }
	.dow-name { text-align: left; font-weight: 600; color: var(--c-text-muted); }
	.dow-count { font-size: 0.7rem; color: var(--c-text-muted); }
	.corr-card { background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 1rem; text-align: center; display: flex; flex-direction: column; gap: 0.25rem; }
	.corr-r { font-size: 1.8rem; font-weight: 700; }
	.corr-label { font-size: 0.85rem; }
	.corr-hint { font-size: 0.75rem; color: var(--c-text-muted); }
	.consistency-row { display: flex; gap: 0.75rem; }
	.consistency-card { flex: 1; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; text-align: center; display: flex; flex-direction: column; gap: 0.15rem; }
	.consistency-value { font-size: 1.4rem; font-weight: 700; }
	.consistency-label { font-size: 0.75rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; }

	/* Mood Distribution */
	.distrib-chart {
		display: flex;
		align-items: flex-end;
		gap: 3px;
		height: 110px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.5rem 0.4rem 0;
	}
	.distrib-col {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		height: 100%;
		justify-content: flex-end;
	}
	.distrib-bar-wrap {
		width: 100%;
		height: 70%;
		display: flex;
		align-items: flex-end;
	}
	.distrib-bar {
		width: 100%;
		border-radius: 3px 3px 0 0;
		min-height: 2px;
		transition: height 0.3s;
	}
	.distrib-count { font-size: 0.6rem; font-weight: 600; margin-top: 2px; color: var(--c-text-muted); }
	.distrib-val { font-size: 0.65rem; font-weight: 700; }

	/* Period Comparison */
	.period-grid {
		display: grid;
		grid-template-columns: 1.2fr 1fr 1fr;
		gap: 0;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		overflow: hidden;
	}
	.period-grid > div {
		padding: 0.5rem 0.6rem;
		font-size: 0.8rem;
		border-bottom: 1px solid var(--c-border);
	}
	.period-grid > div:nth-last-child(-n+3) { border-bottom: none; }
	.period-header {
		font-weight: 600;
		font-size: 0.7rem;
		text-transform: uppercase;
		color: var(--c-text-muted);
		letter-spacing: 0.03em;
		background: var(--c-bg);
	}
	.period-h-label { display: flex; align-items: center; gap: 0.3rem; }
	.period-metric { font-weight: 500; }
	.period-val { text-align: center; font-variant-numeric: tabular-nums; }
</style>
