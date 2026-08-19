<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { onMount } from 'svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('intake');
	const checkinStore = useEntries('checkin');

	let what = $state('');
	let amount = $state('normal');
	let moodVal = $state('verde');
	let meal = $state('other');
	let timeStart = $state('');
	let timeEnd = $state('');

	onMount(() => {
		const now = new Date();
		const hh = String(now.getHours()).padStart(2, '0');
		const mm = String(now.getMinutes()).padStart(2, '0');
		timeStart = `${hh}:${mm}`;
		timeEnd = `${hh}:${mm}`;
	});

	let errors = $state<Record<string, string>>({});

	function validate(): boolean {
		const e: Record<string, string> = {};
		if (!what.trim()) e.what = 'Food or drink name is required';
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
		const today = new Date().toISOString().slice(0, 10);
		entries.add('intake', {
			what: what.trim(), amount, mood: moodVal, meal,
			whenStart: `${today} ${timeStart}`,
			whenEnd: `${today} ${timeEnd}`
		});
		what = '';
		errors = {};
		toast.show('Intake logged');
	}

	function repeatLast() {
		const last = store.items.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt))[0];
		if (!last) return;
		what = String(last.data.what || '');
		amount = String(last.data.amount || 'normal');
		moodVal = String(last.data.mood || 'verde');
		meal = String(last.data.meal || 'other');
		toast.show('Fields pre-filled');
	}

	const quickItems = $derived.by(() => {
		const counts = new Map<string, { count: number; last: Record<string, unknown>; lastDate: string }>();
		for (const e of store.items) {
			const key = (e.data.what as string)?.toLowerCase().trim();
			if (!key) continue;
			const existing = counts.get(key);
			if (!existing) {
				counts.set(key, { count: 1, last: e.data, lastDate: e.createdAt });
			} else {
				existing.count++;
				if (e.createdAt > existing.lastDate) {
					existing.last = e.data;
					existing.lastDate = e.createdAt;
				}
			}
		}
		return [...counts.entries()]
			.sort((a, b) => b[1].count - a[1].count)
			.slice(0, 8)
			.map(([, info]) => ({ name: info.last.what as string, count: info.count, last: info.last }));
	});

	function prefill(item: { name: string; count: number; last: Record<string, unknown> }) {
		what = item.last.what as string;
		amount = (item.last.amount as string) ?? 'normal';
		moodVal = (item.last.mood as string) ?? 'verde';
		meal = (item.last.meal as string) ?? 'other';
		toast.show('Pre-filled');
	}

	// --- Quick Stats ---
	const quickStats = $derived.by(() => {
		const total = store.items.length;
		const now = new Date();
		const weekAgoDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 7);
		const weekItems = store.items.filter(e => new Date(e.createdAt) >= weekAgoDate);
		const avgPerDay = (weekItems.length / 7).toFixed(1);

		const foodCounts = new Map<string, number>();
		for (const e of store.items) {
			const w = String(e.data.what || '').trim().toLowerCase();
			if (w) foodCounts.set(w, (foodCounts.get(w) || 0) + 1);
		}
		let mostCommon = '-';
		let maxC = 0;
		for (const [name, count] of foodCounts) {
			if (count > maxC) { maxC = count; mostCommon = name; }
		}

		return { total, avgPerDay, mostCommon };
	});

	// --- Daily Intake Summary (today) ---
	const todaySummary = $derived.by(() => {
		const today = new Date().toISOString().slice(0, 10);
		const todayItems = store.items
			.filter(e => e.createdAt.startsWith(today))
			.toSorted((a, b) => {
				const tA = String(a.data.whenStart || '').split(' ')[1] ?? '';
				const tB = String(b.data.whenStart || '').split(' ')[1] ?? '';
				return tA.localeCompare(tB);
			});
		return todayItems;
	});

	// --- Meal Frequency Chart (last 14 days) ---
	const freqChart = $derived.by(() => {
		const now = new Date();
		const days: { label: string; date: string; count: number; dominant: string }[] = [];
		const dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
		for (let i = 13; i >= 0; i--) {
			const d = new Date(now.getFullYear(), now.getMonth(), now.getDate() - i);
			const ds = d.toISOString().slice(0, 10);
			days.push({ label: dayLabels[d.getDay()], date: ds, count: 0, dominant: 'normal' });
		}
		const dateMap = new Map(days.map(d => [d.date, d]));
		for (const e of store.items) {
			const eDate = e.createdAt.slice(0, 10);
			const slot = dateMap.get(eDate);
			if (slot) {
				slot.count++;
				const amt = String(e.data.amount || 'normal');
				if (amt === 'mucho') slot.dominant = 'mucho';
				else if (amt === 'poco' && slot.dominant !== 'mucho') slot.dominant = 'poco';
			}
		}
		const maxCount = Math.max(1, ...days.map(d => d.count));
		return { days, maxCount };
	});

	// --- Meal Timing Patterns ---
	const timingPatterns = $derived.by(() => {
		const slots = [
			{ name: 'Morning', range: [5, 11], count: 0 },
			{ name: 'Midday', range: [11, 15], count: 0 },
			{ name: 'Afternoon', range: [15, 19], count: 0 },
			{ name: 'Evening', range: [19, 29], count: 0 }
		];
		let totalWithTime = 0;
		for (const e of store.items) {
			const ws = String(e.data.whenStart || '');
			const timePart = ws.split(' ')[1] ?? '';
			if (!timePart) continue;
			const hh = parseInt(timePart.split(':')[0], 10);
			if (isNaN(hh)) continue;
			totalWithTime++;
			const normalizedH = hh < 5 ? hh + 24 : hh;
			for (const slot of slots) {
				if (normalizedH >= slot.range[0] && normalizedH < slot.range[1]) {
					slot.count++;
					break;
				}
			}
		}
		const show = totalWithTime >= 5;
		return { slots, totalWithTime, show };
	});

	// --- Food-Mood Correlation ---
	const foodMoodCorrelation = $derived.by(() => {
		const foodDays = new Map<string, Set<string>>();
		const foodCounts = new Map<string, number>();
		for (const e of store.items) {
			const w = String(e.data.what || '').trim().toLowerCase();
			if (!w) continue;
			const day = e.createdAt.slice(0, 10);
			foodCounts.set(w, (foodCounts.get(w) || 0) + 1);
			if (!foodDays.has(w)) foodDays.set(w, new Set());
			foodDays.get(w)!.add(day);
		}

		const checkinByDate = new Map<string, number>();
		for (const c of checkinStore.items) {
			const d = String(c.data.date || c.createdAt.slice(0, 10));
			const m = Number(c.data.mood);
			if (!isNaN(m)) checkinByDate.set(d, m);
		}

		if (checkinByDate.size < 3) return [];

		const allMoods = [...checkinByDate.values()];
		const globalAvg = allMoods.reduce((s, v) => s + v, 0) / allMoods.length;

		const top5 = [...foodCounts.entries()]
			.sort((a, b) => b[1] - a[1])
			.slice(0, 5)
			.filter(([, count]) => count >= 3);

		const results: { food: string; count: number; avgMood: number; delta: number }[] = [];
		for (const [food] of top5) {
			const daysSet = foodDays.get(food)!;
			const moods: number[] = [];
			for (const day of daysSet) {
				const m = checkinByDate.get(day);
				if (m !== undefined) moods.push(m);
			}
			if (moods.length < 2) continue;
			const avgMood = moods.reduce((s, v) => s + v, 0) / moods.length;
			const delta = avgMood - globalAvg;
			results.push({ food, count: daysSet.size, avgMood, delta });
		}

		return results.sort((a, b) => Math.abs(b.delta) - Math.abs(a.delta));
	});
</script>

<svelte:head>
  <title>Intake | Darink</title>
</svelte:head>

<PageHeader title="Intake" />

{#if quickItems.length > 0}
<section class="quick-add">
	<h2>Quick add</h2>
	<div class="quick-chips">
		{#each quickItems as item}
			<button class="quick-chip" onclick={() => prefill(item)}>
				{item.name}
				<span class="quick-count">{item.count}</span>
			</button>
		{/each}
	</div>
</section>
{/if}

<section class="form">
	<label class:field-has-error={!!errors.what}>What <input type="text" bind:value={what} placeholder="Food, drink..." oninput={() => clearError('what')} /></label>
	{#if errors.what}<span class="field-error">{errors.what}</span>{/if}
	<div class="row">
		<label>
			Amount
			<select bind:value={amount}>
				<option value="poco">Small</option>
				<option value="normal">Normal</option>
				<option value="mucho">Large</option>
			</select>
		</label>
		<label>
			Mood
			<select bind:value={moodVal}>
				<option value="verde">Good</option>
				<option value="ambar">Neutral</option>
				<option value="rojo">Bad</option>
			</select>
		</label>
		<label>
			Meal
			<select bind:value={meal}>
				<option value="breakfast">Breakfast</option>
				<option value="lunch">Lunch</option>
				<option value="dinner">Dinner</option>
				<option value="snack">Snack</option>
				<option value="other">Other</option>
			</select>
		</label>
	</div>
	<div class="row">
		<label>Start <input type="time" bind:value={timeStart} /></label>
		<label>End <input type="time" bind:value={timeEnd} /></label>
	</div>
	<div class="form-actions">
		<button class="primary" onclick={submit}>Add entry</button>
		{#if store.items.length > 0}
			<button onclick={repeatLast}>Repeat last</button>
		{/if}
	</div>
</section>

<!-- Quick Stats Row -->
{#if store.items.length > 0}
<section class="metrics quick-stats-section">
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value">{quickStats.total}</span>
			<span class="metric-label">Total meals</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{quickStats.avgPerDay}</span>
			<span class="metric-label">Avg / day (7d)</span>
		</div>
		<div class="metric-card">
			<span class="metric-value qs-food">{quickStats.mostCommon}</span>
			<span class="metric-label">Most common</span>
		</div>
	</div>
</section>
{/if}

<!-- Daily Intake Summary (today) -->
{#if todaySummary.length > 0}
<section class="daily-summary">
	<h2>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
		Today — {todaySummary.length} meal{todaySummary.length === 1 ? '' : 's'}
	</h2>
	<div class="timeline">
		{#each todaySummary as item}
			{@const time = String(item.data.whenStart || '').split(' ')[1] ?? ''}
			{@const amtClass = item.data.amount === 'poco' ? 'amt-poco' : item.data.amount === 'mucho' ? 'amt-mucho' : 'amt-normal'}
			<div class="timeline-item">
				<span class="timeline-time">{time}</span>
				<span class="timeline-dot {amtClass}"></span>
				<span class="timeline-food">{item.data.what}</span>
				<span class="timeline-amount">{item.data.amount === 'poco' ? 'small' : item.data.amount === 'mucho' ? 'large' : 'normal'}</span>
			</div>
		{/each}
	</div>
</section>
{/if}

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		const today = new Date().toISOString().slice(0, 10);
		entries.update(item.id, {
			what: (fd.get('what') as string).trim(),
			amount: fd.get('amount') as string,
			mood: fd.get('mood') as string,
			meal: fd.get('meal') as string,
			whenStart: `${today} ${fd.get('timeStart')}`,
			whenEnd: `${today} ${fd.get('timeEnd')}`
		});
		toast.show('Updated');
		done();
	}}>
		<label>What <input type="text" name="what" value={data.what} /></label>
		<div class="row">
			<label>
				Amount
				<select name="amount">
					<option value="poco" selected={data.amount === 'poco'}>Small</option>
					<option value="normal" selected={data.amount === 'normal'}>Normal</option>
					<option value="mucho" selected={data.amount === 'mucho'}>Large</option>
				</select>
			</label>
			<label>
				Mood
				<select name="mood">
					<option value="verde" selected={data.mood === 'verde'}>Good</option>
					<option value="ambar" selected={data.mood === 'ambar'}>Neutral</option>
					<option value="rojo" selected={data.mood === 'rojo'}>Bad</option>
				</select>
			</label>
			<label>
				Meal
				<select name="meal">
					<option value="breakfast" selected={data.meal === 'breakfast'}>Breakfast</option>
					<option value="lunch" selected={data.meal === 'lunch'}>Lunch</option>
					<option value="dinner" selected={data.meal === 'dinner'}>Dinner</option>
					<option value="snack" selected={data.meal === 'snack'}>Snack</option>
					<option value="other" selected={data.meal === 'other'}>Other</option>
				</select>
			</label>
		</div>
		<div class="row">
			<label>Start <input type="time" name="timeStart" value={(data.whenStart as string)?.split(' ')[1] ?? ''} /></label>
			<label>End <input type="time" name="timeEnd" value={(data.whenEnd as string)?.split(' ')[1] ?? ''} /></label>
		</div>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>
	<p>No intake entries yet</p>
	<p class="empty-hint">Log what you eat and drink to discover your nutrition patterns.</p>
</div>
{/if}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div class="intake-row">
			<span class="ball {item.data.mood} {item.data.amount === 'poco' ? 'small' : item.data.amount === 'mucho' ? 'large' : ''}"></span>
			<strong>{item.data.what}</strong>
			<span class="meal-badge">{item.data.meal ?? 'other'}</span>
			<span class="time">{(item.data.whenStart as string)?.split(' ')[1] ?? ''}</span>
		</div>
	{/snippet}
</EntryList>

<!-- Analytics: Meal Frequency Chart (last 14 days) -->
{#if store.items.length > 0}
	{@const fc = freqChart}
	{@const barW = 280 / 14 - 2}
	<section class="chart-section">
		<h2>
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
			Meal frequency (14 days)
		</h2>
		<svg class="freq-chart" viewBox="0 0 280 80" preserveAspectRatio="xMidYMid meet">
			{#each fc.days as day, i}
				{@const barH = fc.maxCount > 0 ? (day.count / fc.maxCount) * 55 : 0}
				{@const fillColor = day.dominant === 'mucho' ? '#e8a735' : day.dominant === 'poco' ? '#2e8b57' : '#4aa3ff'}
				<rect
					x={i * 20 + 1}
					y={60 - barH}
					width={barW}
					height={barH}
					rx="2"
					fill={fillColor}
					opacity="0.85"
				/>
				<text
					x={i * 20 + 1 + barW / 2}
					y="75"
					text-anchor="middle"
					font-size="5"
					fill="var(--c-text-muted)"
				>{day.label.charAt(0)}</text>
			{/each}
		</svg>
		<div class="freq-legend">
			<span class="freq-legend-item"><span class="freq-dot" style="background:#4aa3ff"></span>Normal</span>
			<span class="freq-legend-item"><span class="freq-dot" style="background:#2e8b57"></span>Light</span>
			<span class="freq-legend-item"><span class="freq-dot" style="background:#e8a735"></span>Heavy</span>
		</div>
	</section>
{/if}

<!-- Analytics: Meal Timing Patterns -->
{#if timingPatterns.show}
	{@const tp = timingPatterns}
	<section class="metrics">
		<h2>
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v10l4.24 4.24"/><circle cx="12" cy="12" r="10"/></svg>
			Meal timing patterns
		</h2>
		<div class="mood-bars">
			{#each tp.slots as slot}
				{@const pct = tp.totalWithTime > 0 ? Math.round((slot.count / tp.totalWithTime) * 100) : 0}
				<div class="mood-bar-row">
					<span class="mood-bar-label timing-label">{slot.name}</span>
					<div class="mood-bar-track">
						<div class="mood-bar-fill timing-fill" style="width: {pct}%"></div>
					</div>
					<span class="mood-bar-pct">{pct}%</span>
				</div>
			{/each}
		</div>
	</section>
{/if}

<!-- Analytics: Food-Mood Correlation -->
{#if foodMoodCorrelation.length > 0}
	<section class="metrics">
		<h2>
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
			Food-mood correlation
		</h2>
		<div class="correlation-list">
			{#each foodMoodCorrelation as item}
				{@const sign = item.delta >= 0 ? '+' : ''}
				{@const colorClass = item.delta >= 0 ? 'corr-positive' : 'corr-negative'}
				<div class="correlation-row">
					<span class="corr-food">{item.food}</span>
					<span class="corr-delta {colorClass}">{sign}{item.delta.toFixed(1)} mood</span>
					<span class="corr-info">{item.count}d logged</span>
				</div>
			{/each}
		</div>
		<p class="corr-note">Compared to average mood across all days</p>
	</section>
{/if}

<!-- Analytics: Meal timing chart (intakes per hour of day) -->
{#if store.items.length > 0}
	{@const hourCounts = Array.from({ length: 18 }, (_, i) => ({ hour: i + 6, count: 0 }))}
	{@const _fill = store.items.forEach(e => {
		const ws = String(e.data.whenStart || '');
		const timePart = ws.split(' ')[1] ?? '';
		const hh = parseInt(timePart.split(':')[0], 10);
		if (!isNaN(hh) && hh >= 6 && hh <= 23) {
			const slot = hourCounts.find(h => h.hour === hh);
			if (slot) slot.count++;
		}
	})}
	{@const maxCount = Math.max(1, ...hourCounts.map(h => h.count))}
	{@const barW = 280 / 18 - 1.5}
	<section class="chart-section">
		<h2>Meal timing</h2>
		<svg class="line-chart" viewBox="0 0 280 100" preserveAspectRatio="none">
			{#each hourCounts as slot, i}
				{@const barH = (slot.count / maxCount) * 80}
				<rect
					x={i * (280 / 18) + 0.75}
					y={90 - barH}
					width={barW}
					height={barH}
					rx="1.5"
					fill="var(--c-accent)"
					opacity="0.75"
				/>
			{/each}
		</svg>
		<div class="chart-range">
			<span>6h</span>
			<span>12h</span>
			<span>18h</span>
			<span>23h</span>
		</div>
	</section>
{/if}

<!-- Analytics: Intake mood distribution -->
{#if store.items.length > 0}
	{@const total = store.items.length}
	{@const verde = store.items.filter(e => e.data.mood === 'verde').length}
	{@const ambar = store.items.filter(e => e.data.mood === 'ambar').length}
	{@const rojo = store.items.filter(e => e.data.mood === 'rojo').length}
	{@const pctVerde = Math.round((verde / total) * 100)}
	{@const pctAmbar = Math.round((ambar / total) * 100)}
	{@const pctRojo = Math.round((rojo / total) * 100)}
	<section class="metrics">
		<h2>Intake mood</h2>
		<div class="mood-bars">
			<div class="mood-bar-row">
				<span class="mood-bar-label">Good</span>
				<div class="mood-bar-track">
					<div class="mood-bar-fill verde" style="width: {pctVerde}%"></div>
				</div>
				<span class="mood-bar-pct">{pctVerde}%</span>
			</div>
			<div class="mood-bar-row">
				<span class="mood-bar-label">Neutral</span>
				<div class="mood-bar-track">
					<div class="mood-bar-fill ambar" style="width: {pctAmbar}%"></div>
				</div>
				<span class="mood-bar-pct">{pctAmbar}%</span>
			</div>
			<div class="mood-bar-row">
				<span class="mood-bar-label">Bad</span>
				<div class="mood-bar-track">
					<div class="mood-bar-fill rojo" style="width: {pctRojo}%"></div>
				</div>
				<span class="mood-bar-pct">{pctRojo}%</span>
			</div>
		</div>
	</section>
{/if}

<!-- Analytics: Meal distribution -->
{#if store.items.length > 0}
	{@const mealTotal = store.items.length}
	{@const mealTypes = ['breakfast', 'lunch', 'dinner', 'snack', 'other'] as const}
	{@const mealCounts = mealTypes.map(m => ({
		name: m,
		count: store.items.filter(e => (e.data.meal ?? 'other') === m).length
	}))}
	{@const mealMax = Math.max(1, ...mealCounts.map(m => m.count))}
	<section class="metrics">
		<h2>Meal distribution</h2>
		<div class="mood-bars">
			{#each mealCounts as mc}
				{@const pct = Math.round((mc.count / mealTotal) * 100)}
				<div class="mood-bar-row">
					<span class="mood-bar-label meal-bar-label">{mc.name}</span>
					<div class="mood-bar-track">
						<div class="mood-bar-fill meal-fill" style="width: {(mc.count / mealMax) * 100}%"></div>
					</div>
					<span class="mood-bar-pct">{pct}%</span>
				</div>
			{/each}
		</div>
	</section>
{/if}

<!-- Analytics: Most logged items (top 10) -->
{#if store.items.length > 0}
	{@const whatCounts = store.items.reduce((acc, e) => {
		const w = String(e.data.what || '').trim().toLowerCase();
		if (w) acc.set(w, (acc.get(w) || 0) + 1);
		return acc;
	}, new Map())}
	{@const top10 = [...whatCounts.entries()].sort((a, b) => b[1] - a[1]).slice(0, 10)}
	{#if top10.length > 0}
		<section class="metrics">
			<h2>Most logged items</h2>
			<ol class="ranked-list">
				{#each top10 as [name, count], i}
					<li>
						<span class="rank">#{i + 1}</span>
						<span class="ranked-name">{name}</span>
						<span class="ranked-count">{count}</span>
					</li>
				{/each}
			</ol>
		</section>
	{/if}
{/if}

<!-- Analytics: Weekly intake count -->
{#if store.items.length > 0}
	{@const now = new Date()}
	{@const weekAgo = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 7)}
	{@const weekItems = store.items.filter(e => new Date(e.createdAt) >= weekAgo)}
	{@const totalWeek = weekItems.length}
	{@const avgPerDay = totalWeek > 0 ? (totalWeek / 7).toFixed(1) : '0'}
	{@const weekMoodCounts = weekItems.reduce((acc, e) => {
		const m = String(e.data.mood || '');
		if (m) acc.set(m, (acc.get(m) || 0) + 1);
		return acc;
	}, new Map())}
	{@const commonMood = [...weekMoodCounts.entries()].sort((a, b) => b[1] - a[1])[0]?.[0] ?? '-'}
	{@const moodLabel = commonMood === 'verde' ? 'Good' : commonMood === 'ambar' ? 'Neutral' : commonMood === 'rojo' ? 'Bad' : '-'}
	<section class="metrics">
		<h2>This week</h2>
		<div class="metrics-row">
			<div class="metric-card">
				<span class="metric-value">{totalWeek}</span>
				<span class="metric-label">Intakes</span>
			</div>
			<div class="metric-card">
				<span class="metric-value">{avgPerDay}</span>
				<span class="metric-label">Avg / day</span>
			</div>
			<div class="metric-card">
				<span class="metric-value">{moodLabel}</span>
				<span class="metric-label">Top mood</span>
			</div>
		</div>
	</section>
{/if}

<style>
	.quick-add { padding: 0 1rem 0.5rem; }
	.quick-chips { display: flex; flex-wrap: wrap; gap: 0.25rem; }
	.quick-chip {
		display: inline-flex;
		align-items: center;
		gap: 0.25rem;
		padding: 0.3rem 0.6rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: 16px;
		font-size: 0.8rem;
		cursor: pointer;
		transition: border-color 0.15s;
	}
	.quick-chip:hover { border-color: var(--c-accent); background: var(--c-accent-bg); }
	.quick-count { font-size: 0.65rem; color: var(--c-text-muted); }

	.field-error { font-size: 0.75rem; color: var(--c-cancel); margin-top: 0.15rem; display: block; }
	.field-has-error { color: var(--c-cancel); }
	.field-has-error input { border-color: var(--c-cancel); }
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.75rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }

	.intake-row { display: flex; align-items: center; gap: 0.5rem; }
	.intake-row strong { flex: 1; }
	.time { font-size: 0.85rem; color: var(--c-text-muted); }

	.ball { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
	.ball:global(.verde) { background: #228b22; }
	.ball:global(.ambar) { background: #ff8c00; }
	.ball:global(.rojo) { background: #dc143c; }
	.ball:global(.small) { transform: scale(0.7); }
	.ball:global(.large) { transform: scale(1.3); }

	.meal-badge {
		font-size: 0.7rem;
		padding: 0.1rem 0.4rem;
		border-radius: 8px;
		background: var(--c-accent-bg);
		color: var(--c-accent);
		text-transform: capitalize;
	}

	.meal-bar-label { text-transform: capitalize; }
	.meal-fill { background: var(--c-accent); }

	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }
	.form-actions { display: flex; gap: 0.5rem; }
	.form-actions button { flex: 1; }

	h2 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
	.chart-section { padding: 1.5rem 1rem 0; }
	.line-chart { width: 100%; height: 100px; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.5rem; }
	.chart-range { display: flex; justify-content: space-between; font-size: 0.7rem; color: var(--c-text-muted); margin-top: 0.2rem; padding: 0 0.25rem; }
	.metrics { padding: 1.5rem 1rem 0; }
	.metrics-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.metric-card { flex: 1; min-width: 80px; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; text-align: center; display: flex; flex-direction: column; gap: 0.15rem; }
	.metric-value { font-size: 1.4rem; font-weight: 700; }
	.metric-label { font-size: 0.75rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; }

	.mood-bars { display: flex; flex-direction: column; gap: 0.5rem; }
	.mood-bar-row { display: flex; align-items: center; gap: 0.5rem; }
	.mood-bar-label { font-size: 0.75rem; font-weight: 600; color: var(--c-text-muted); width: 50px; text-align: right; }
	.mood-bar-track { flex: 1; height: 18px; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); overflow: hidden; }
	.mood-bar-fill { height: 100%; border-radius: var(--radius); transition: width 0.3s; }
	.mood-bar-fill.verde { background: #228b22; }
	.mood-bar-fill.ambar { background: #ff8c00; }
	.mood-bar-fill.rojo { background: #dc143c; }
	.mood-bar-pct { font-size: 0.75rem; font-weight: 600; color: var(--c-text-muted); width: 35px; }

	.ranked-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.25rem; }
	.ranked-list li { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.6rem; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); }
	.rank { font-size: 0.7rem; font-weight: 700; color: var(--c-text-muted); width: 24px; }
	.ranked-name { flex: 1; font-size: 0.85rem; text-transform: capitalize; }
	.ranked-count { font-size: 0.85rem; font-weight: 700; color: var(--c-accent); }

	/* Quick Stats */
	.quick-stats-section { padding-top: 1rem; }
	.qs-food { font-size: 1rem; text-transform: capitalize; }

	/* Daily Summary */
	.daily-summary { padding: 1rem 1rem 0; }
	.daily-summary h2 { display: flex; align-items: center; gap: 0.35rem; }
	.timeline { display: flex; flex-direction: column; gap: 0.25rem; }
	.timeline-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.6rem; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); }
	.timeline-time { font-size: 0.8rem; font-weight: 600; color: var(--c-text-muted); width: 40px; flex-shrink: 0; }
	.timeline-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
	.timeline-dot.amt-normal { background: #4aa3ff; }
	.timeline-dot.amt-poco { background: #2e8b57; }
	.timeline-dot.amt-mucho { background: #e8a735; }
	.timeline-food { flex: 1; font-size: 0.85rem; text-transform: capitalize; }
	.timeline-amount { font-size: 0.7rem; color: var(--c-text-muted); text-transform: capitalize; }

	/* Meal Frequency Chart */
	.freq-chart { width: 100%; height: 80px; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.25rem; }
	.freq-legend { display: flex; gap: 0.75rem; justify-content: center; margin-top: 0.35rem; }
	.freq-legend-item { display: flex; align-items: center; gap: 0.25rem; font-size: 0.7rem; color: var(--c-text-muted); }
	.freq-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

	/* Timing Patterns */
	.timing-label { width: 70px; }
	.timing-fill { background: var(--c-accent); }

	/* Food-Mood Correlation */
	.correlation-list { display: flex; flex-direction: column; gap: 0.35rem; }
	.correlation-row { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.6rem; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); }
	.corr-food { flex: 1; font-size: 0.85rem; font-weight: 600; text-transform: capitalize; }
	.corr-delta { font-size: 0.85rem; font-weight: 700; }
	.corr-positive { color: var(--c-done, #228b22); }
	.corr-negative { color: #dc143c; }
	.corr-info { font-size: 0.7rem; color: var(--c-text-muted); width: 55px; text-align: right; }
	.corr-note { font-size: 0.7rem; color: var(--c-text-muted); margin-top: 0.35rem; font-style: italic; }
</style>
