<script lang="ts">
	import { onMount } from 'svelte';
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { ui } from '$lib/db';
	import type { Entry } from '$lib/db';

	const store = useEntries('hydration');
	const checkinStore = useEntries('checkin');

	/* --- Target --- */
	let target = $state(3000);

	onMount(() => {
		const saved = ui.get();
		if (typeof saved.hydrationTarget === 'number') {
			target = saved.hydrationTarget;
		}
	});

	function saveTarget() {
		ui.patch({ hydrationTarget: target });
		toast.show('Target updated');
	}

	/* --- Quick log --- */
	const todayStr = $derived(new Date().toISOString().slice(0, 10));

	function quickLog(amount: number, source: string = 'water') {
		entries.add('hydration', { date: todayStr, amount, source });
		toast.show(`+${amount}ml logged`);
	}

	/* --- Custom form --- */
	let customAmount = $state('');
	let customSource = $state('water');

	function submitCustom() {
		const amt = parseInt(customAmount);
		if (isNaN(amt) || amt <= 0) return;
		entries.add('hydration', { date: todayStr, amount: amt, source: customSource });
		customAmount = '';
		toast.show(`+${amt}ml logged`);
	}

	/* --- Today's progress --- */
	const todayEntries = $derived(
		store.items.filter((e) => {
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			return d === todayStr;
		})
	);

	const todayTotal = $derived(
		todayEntries.reduce((sum, e) => sum + (e.data.amount as number), 0)
	);

	const todayPct = $derived(target > 0 ? Math.min(Math.round((todayTotal / target) * 100), 100) : 0);

	/* --- SVG progress ring --- */
	const ringRadius = 70;
	const ringCircumference = 2 * Math.PI * ringRadius;
	const ringOffset = $derived(ringCircumference - (todayPct / 100) * ringCircumference);

	/* --- Weekly bar chart --- */
	const weeklyData = $derived.by(() => {
		const days: { label: string; date: string; total: number; ratio: number }[] = [];
		for (let i = 6; i >= 0; i--) {
			const d = new Date();
			d.setDate(d.getDate() - i);
			const key = d.toISOString().slice(0, 10);
			const weekday = d.toLocaleDateString('en', { weekday: 'short' });
			const dayTotal = store.items
				.filter((e) => {
					const ed = (e.data.date as string) ?? e.createdAt.slice(0, 10);
					return ed === key;
				})
				.reduce((sum, e) => sum + (e.data.amount as number), 0);
			days.push({ label: weekday, date: key, total: dayTotal, ratio: target > 0 ? dayTotal / target : 0 });
		}
		return days;
	});

	/* --- Source labels --- */
	const sourceLabels: Record<string, string> = {
		water: 'Water',
		tea: 'Tea',
		coffee: 'Coffee',
		juice: 'Juice',
		other: 'Other'
	};

	const sourceColors: Record<string, string> = {
		water: '#4aa3ff',
		tea: '#2e8b57',
		coffee: '#8b5cf6',
		juice: '#f97316',
		other: '#6366f1'
	};

	/* --- Hydration-Energy Correlation (30 days) --- */
	const correlationData = $derived.by(() => {
		const pairs: { hydration: number; energy: number }[] = [];
		for (let i = 0; i < 30; i++) {
			const d = new Date();
			d.setDate(d.getDate() - i);
			const key = d.toISOString().slice(0, 10);
			const dayHydration = store.items
				.filter((e) => ((e.data.date as string) ?? e.createdAt.slice(0, 10)) === key)
				.reduce((sum, e) => sum + (e.data.amount as number), 0);
			const dayCheckins = checkinStore.items.filter(
				(e) => ((e.data.date as string) ?? e.createdAt.slice(0, 10)) === key
			);
			if (dayHydration > 0 && dayCheckins.length > 0) {
				const avgEnergy =
					dayCheckins.reduce((s, e) => s + (Number(e.data.energy) || 0), 0) / dayCheckins.length;
				pairs.push({ hydration: dayHydration, energy: avgEnergy });
			}
		}
		if (pairs.length < 7) return null;
		const n = pairs.length;
		const sumX = pairs.reduce((s, p) => s + p.hydration, 0);
		const sumY = pairs.reduce((s, p) => s + p.energy, 0);
		const sumXY = pairs.reduce((s, p) => s + p.hydration * p.energy, 0);
		const sumX2 = pairs.reduce((s, p) => s + p.hydration * p.hydration, 0);
		const sumY2 = pairs.reduce((s, p) => s + p.energy * p.energy, 0);
		const denom = Math.sqrt((n * sumX2 - sumX * sumX) * (n * sumY2 - sumY * sumY));
		if (denom === 0) return null;
		const r = (n * sumXY - sumX * sumY) / denom;
		const absR = Math.abs(r);
		let strength: string;
		let color: string;
		if (absR < 0.2) {
			strength = 'Very weak';
			color = 'var(--c-text-muted)';
		} else if (absR < 0.4) {
			strength = 'Weak';
			color = r > 0 ? '#22c55e' : '#ef4444';
		} else if (absR < 0.6) {
			strength = 'Moderate';
			color = r > 0 ? '#22c55e' : '#ef4444';
		} else if (absR < 0.8) {
			strength = 'Strong';
			color = r > 0 ? '#16a34a' : '#dc2626';
		} else {
			strength = 'Very strong';
			color = r > 0 ? '#16a34a' : '#dc2626';
		}
		const message =
			r > 0.2
				? 'Higher hydration days tend to have better energy'
				: r < -0.2
					? 'Higher hydration days tend to have lower energy'
					: 'No clear link between hydration and energy';
		return { r, strength, color, message, n };
	});

	/* --- Source Distribution --- */
	const sourceDistribution = $derived.by(() => {
		const totals: Record<string, number> = {};
		let grandTotal = 0;
		for (const e of store.items) {
			const src = (e.data.source as string) || 'water';
			const amt = e.data.amount as number;
			totals[src] = (totals[src] || 0) + amt;
			grandTotal += amt;
		}
		if (grandTotal === 0) return null;
		const sources = Object.entries(totals)
			.sort((a, b) => b[1] - a[1])
			.map(([source, ml]) => ({
				source,
				ml,
				pct: (ml / grandTotal) * 100,
				color: sourceColors[source] || sourceColors.other,
				label: sourceLabels[source] || source
			}));
		return { sources, grandTotal };
	});

	/* --- Intake Timing Patterns --- */
	const timingBlocks = $derived.by(() => {
		const blocks = [
			{ label: '6-10', start: 6, end: 10, total: 0 },
			{ label: '10-14', start: 10, end: 14, total: 0 },
			{ label: '14-18', start: 14, end: 18, total: 0 },
			{ label: '18-22', start: 18, end: 22, total: 0 },
			{ label: '22-6', start: 22, end: 6, total: 0 }
		];
		for (const e of store.items) {
			const hour = new Date(e.createdAt).getHours();
			if (hour >= 6 && hour < 10) blocks[0].total += e.data.amount as number;
			else if (hour >= 10 && hour < 14) blocks[1].total += e.data.amount as number;
			else if (hour >= 14 && hour < 18) blocks[2].total += e.data.amount as number;
			else if (hour >= 18 && hour < 22) blocks[3].total += e.data.amount as number;
			else blocks[4].total += e.data.amount as number;
		}
		const maxBlock = Math.max(...blocks.map((b) => b.total), 1);
		return { blocks, maxBlock };
	});

	/* --- Hydration Streak --- */
	const streakData = $derived.by(() => {
		let current = 0;
		let best = 0;
		let runStreak = 0;
		let counting = true;
		const now = new Date();
		const monthStart = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
		let daysHitThisMonth = 0;

		for (let i = 0; i < 365; i++) {
			const d = new Date();
			d.setDate(d.getDate() - i);
			const key = d.toISOString().slice(0, 10);
			const dayTotal = store.items
				.filter((e) => ((e.data.date as string) ?? e.createdAt.slice(0, 10)) === key)
				.reduce((sum, e) => sum + (e.data.amount as number), 0);
			const hit = dayTotal >= target;
			if (key >= monthStart && hit) daysHitThisMonth++;
			if (counting) {
				if (hit) current++;
				else counting = false;
			}
			if (hit) {
				runStreak++;
				if (runStreak > best) best = runStreak;
			} else {
				runStreak = 0;
			}
		}
		return { current, best, daysHitThisMonth };
	});

	/* --- Monthly Trend (30 days) --- */
	const monthlyTrend = $derived.by(() => {
		const days: { date: string; label: string; total: number }[] = [];
		let sum = 0;
		for (let i = 29; i >= 0; i--) {
			const d = new Date();
			d.setDate(d.getDate() - i);
			const key = d.toISOString().slice(0, 10);
			const dayLabel = d.toLocaleDateString('en', { day: 'numeric', month: 'short' });
			const dayTotal = store.items
				.filter((e) => ((e.data.date as string) ?? e.createdAt.slice(0, 10)) === key)
				.reduce((s, e) => s + (e.data.amount as number), 0);
			days.push({ date: key, label: dayLabel, total: dayTotal });
			sum += dayTotal;
		}
		const avg = Math.round(sum / 30);
		const maxVal = Math.max(...days.map((d) => d.total), target, 1);
		const targetY = 150 - (target / maxVal) * 140;
		return { days, avg, maxVal, targetY };
	});
</script>

<svelte:head>
  <title>Hydration | Darink</title>
</svelte:head>

<PageHeader title="Hydration" />

<!-- Daily Target -->
<section class="target-section">
	<h2>Daily Target</h2>
	<div class="target-row">
		<input
			type="number"
			class="target-input"
			bind:value={target}
			min="500"
			max="10000"
			step="100"
		/>
		<span class="target-unit">ml</span>
		<button class="target-save" onclick={saveTarget} aria-label="Save target">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
		</button>
	</div>
</section>

<!-- Progress Ring -->
<section class="progress-section">
	<div class="ring-container">
		<svg class="progress-ring" viewBox="0 0 180 180">
			<circle
				cx="90"
				cy="90"
				r={ringRadius}
				fill="none"
				stroke="var(--c-border)"
				stroke-width="12"
			/>
			<circle
				cx="90"
				cy="90"
				r={ringRadius}
				fill="none"
				stroke={todayPct >= 80 ? 'var(--c-done)' : todayPct >= 50 ? '#f59e0b' : 'var(--c-accent)'}
				stroke-width="12"
				stroke-linecap="round"
				stroke-dasharray={ringCircumference}
				stroke-dashoffset={ringOffset}
				transform="rotate(-90 90 90)"
				class="ring-progress"
			/>
			<text x="90" y="82" text-anchor="middle" class="ring-pct">{todayPct}%</text>
			<text x="90" y="105" text-anchor="middle" class="ring-total">{todayTotal}ml</text>
		</svg>
	</div>
	<div class="progress-label">of {target}ml target</div>
</section>

<!-- Quick Tap Buttons -->
<section class="quick-section">
	<h2>Quick Add</h2>
	<div class="quick-row">
		<button class="quick-btn" onclick={() => quickLog(250)}>
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><line x1="6" y1="2" x2="6" y2="4"/><line x1="10" y1="2" x2="10" y2="4"/><line x1="14" y1="2" x2="14" y2="4"/></svg>
			<span class="quick-label">250ml</span>
			<span class="quick-desc">Glass</span>
		</button>
		<button class="quick-btn" onclick={() => quickLog(500)}>
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2v6a6 6 0 0 0 12 0V2"/><path d="M6 8h12"/><path d="M10 18h4"/><path d="M12 12v10"/></svg>
			<span class="quick-label">500ml</span>
			<span class="quick-desc">Bottle</span>
		</button>
		<button class="quick-btn" onclick={() => quickLog(750)}>
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.5 20H8"/><path d="M17 3l1 12"/><path d="M12 3l1 12"/><path d="M6.5 3l1 12"/><path d="M4.5 15h16"/><path d="M5.5 20h13"/></svg>
			<span class="quick-label">750ml</span>
			<span class="quick-desc">Large</span>
		</button>
	</div>
</section>

<!-- Custom Amount -->
<section class="custom-section">
	<h2>Custom Amount</h2>
	<div class="custom-row">
		<input
			type="number"
			class="custom-input"
			bind:value={customAmount}
			placeholder="ml"
			min="1"
		/>
		<select class="custom-select" bind:value={customSource}>
			<option value="water">Water</option>
			<option value="tea">Tea</option>
			<option value="coffee">Coffee</option>
			<option value="juice">Juice</option>
			<option value="other">Other</option>
		</select>
		<button class="primary custom-add" onclick={submitCustom}>Add</button>
	</div>
</section>

<!-- Weekly Bar Chart -->
<section class="chart-section">
	<h2>Last 7 Days</h2>
	<svg class="week-chart" viewBox="0 0 280 120" preserveAspectRatio="xMidYMid meet">
		<!-- Target line -->
		<line x1="0" y1="10" x2="280" y2="10" stroke="var(--c-text-muted)" stroke-width="0.5" stroke-dasharray="4 2" />
		<text x="275" y="8" text-anchor="end" font-size="6" fill="var(--c-text-muted)">target</text>
		{#each weeklyData as day, i}
			{@const barW = 280 / 7}
			{@const maxRatio = Math.max(...weeklyData.map((d) => d.ratio), 1)}
			{@const normalizedH = maxRatio > 0 ? (day.ratio / maxRatio) * 90 : 0}
			{@const barColor = day.ratio >= 0.8 ? 'var(--c-done)' : day.ratio >= 0.5 ? '#f59e0b' : '#ef4444'}
			<rect
				x={i * barW + barW * 0.15}
				y={100 - normalizedH}
				width={barW * 0.7}
				height={normalizedH}
				rx="2"
				fill={day.total > 0 ? barColor : 'var(--c-border)'}
			/>
			<text
				x={i * barW + barW / 2}
				y="115"
				text-anchor="middle"
				font-size="8"
				fill="var(--c-text-muted)"
			>{day.label}</text>
			{#if day.total > 0}
				<text
					x={i * barW + barW / 2}
					y={100 - normalizedH - 3}
					text-anchor="middle"
					font-size="7"
					fill="var(--c-text)"
				>{day.total >= 1000 ? (day.total / 1000).toFixed(1) + 'L' : day.total + ''}</text>
			{/if}
		{/each}
	</svg>
</section>

<!-- Hydration-Energy Correlation -->
{#if correlationData}
<section class="insight-section">
	<h2>Hydration-Energy Link</h2>
	<div class="correlation-card" style="border-left: 3px solid {correlationData.color}">
		<div class="correlation-header">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
			<span class="correlation-value" style="color: {correlationData.color}">r = {correlationData.r.toFixed(2)}</span>
			<span class="correlation-strength">{correlationData.strength}</span>
		</div>
		<p class="correlation-msg">{correlationData.message}</p>
		<p class="correlation-meta">Based on {correlationData.n} days with both hydration and check-in data</p>
	</div>
</section>
{/if}

<!-- Source Distribution -->
{#if sourceDistribution}
<section class="insight-section">
	<h2>Source Distribution</h2>
	<div class="source-bar-container">
		<div class="source-stacked-bar">
			{#each sourceDistribution.sources as src}
				<div
					class="source-bar-segment"
					style="width: {src.pct}%; background: {src.color}"
					title="{src.label}: {src.pct.toFixed(1)}%"
				></div>
			{/each}
		</div>
		<div class="source-legend">
			{#each sourceDistribution.sources as src}
				<div class="source-legend-item">
					<span class="source-dot" style="background: {src.color}"></span>
					<span class="source-name">{src.label}</span>
					<span class="source-pct">{src.pct.toFixed(0)}%</span>
				</div>
			{/each}
		</div>
	</div>
</section>
{/if}

<!-- Intake Timing Patterns -->
{#if store.items.length > 0}
<section class="insight-section">
	<h2>Intake Timing</h2>
	<div class="timing-chart">
		{#each timingBlocks.blocks as block}
			{@const ratio = timingBlocks.maxBlock > 0 ? block.total / timingBlocks.maxBlock : 0}
			{@const barColor = ratio > 0.7 ? 'var(--c-accent)' : ratio > 0.3 ? '#f59e0b' : 'var(--c-border)'}
			<div class="timing-row">
				<span class="timing-label">{block.label}</span>
				<div class="timing-bar-track">
					<div
						class="timing-bar-fill"
						style="width: {ratio * 100}%; background: {block.total > 0 ? barColor : 'var(--c-border)'}"
					></div>
				</div>
				<span class="timing-value">{block.total > 0 ? (block.total >= 1000 ? (block.total / 1000).toFixed(1) + 'L' : block.total + 'ml') : '-'}</span>
			</div>
		{/each}
	</div>
	<p class="timing-hint">Based on entry creation times. Identify gaps in your drinking pattern.</p>
</section>
{/if}

<!-- Hydration Streak -->
{#if store.items.length > 0}
<section class="insight-section">
	<h2>Streaks</h2>
	<div class="streak-row">
		<div class="streak-card">
			<div class="streak-icon streak-fire">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
			</div>
			<span class="streak-value">{streakData.current}</span>
			<span class="streak-label">Current streak</span>
		</div>
		<div class="streak-card">
			<div class="streak-icon streak-trophy">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
			</div>
			<span class="streak-value">{streakData.best}</span>
			<span class="streak-label">Best streak</span>
		</div>
		<div class="streak-card">
			<div class="streak-icon streak-month">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
			</div>
			<span class="streak-value">{streakData.daysHitThisMonth}</span>
			<span class="streak-label">Target hit this month</span>
		</div>
	</div>
</section>
{/if}

<!-- Monthly Trend -->
{#if store.items.length > 0}
<section class="insight-section">
	<h2>Last 30 Days</h2>
	<div class="monthly-avg">Average: <strong>{monthlyTrend.avg >= 1000 ? (monthlyTrend.avg / 1000).toFixed(1) + 'L' : monthlyTrend.avg + 'ml'}</strong>/day</div>
	<svg class="monthly-chart" viewBox="0 0 600 160" preserveAspectRatio="xMidYMid meet">
		<!-- Target line -->
		<line x1="0" y1={monthlyTrend.targetY} x2="600" y2={monthlyTrend.targetY} stroke="var(--c-text-muted)" stroke-width="1" stroke-dasharray="6 3" />
		<text x="595" y={monthlyTrend.targetY - 4} text-anchor="end" font-size="8" fill="var(--c-text-muted)">target</text>
		<!-- Data points and lines -->
		{#each monthlyTrend.days as day, i}
			{@const x = (i / 29) * 580 + 10}
			{@const y = 150 - (day.total / monthlyTrend.maxVal) * 140}
			{@const dotColor = day.total >= target ? '#22c55e' : '#f59e0b'}
			{#if i > 0}
				{@const prevX = ((i - 1) / 29) * 580 + 10}
				{@const prevY = 150 - (monthlyTrend.days[i - 1].total / monthlyTrend.maxVal) * 140}
				<line x1={prevX} y1={prevY} x2={x} y2={y} stroke="var(--c-border)" stroke-width="1.5" />
			{/if}
			<circle cx={x} cy={y} r="3" fill={dotColor} />
			{#if i % 5 === 0}
				<text x={x} y="158" text-anchor="middle" font-size="7" fill="var(--c-text-muted)">{day.label}</text>
			{/if}
		{/each}
	</svg>
</section>
{/if}

<!-- Empty State -->
{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
	<p>No hydration entries yet</p>
	<p class="empty-hint">Use the quick buttons above to start tracking your water intake.</p>
</div>
{/if}

<!-- Entry History -->
{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		const amt = parseInt(fd.get('amount') as string);
		if (isNaN(amt) || amt <= 0) return;
		entries.update(item.id, {
			date: fd.get('date') as string,
			amount: amt,
			source: fd.get('source') as string
		});
		toast.show('Updated');
		done();
	}}>
		<div class="row">
			<label>Amount (ml) <input type="number" name="amount" value={data.amount} min="1" /></label>
			<label>
				Source
				<select name="source">
					<option value="water" selected={data.source === 'water'}>Water</option>
					<option value="tea" selected={data.source === 'tea'}>Tea</option>
					<option value="coffee" selected={data.source === 'coffee'}>Coffee</option>
					<option value="juice" selected={data.source === 'juice'}>Juice</option>
					<option value="other" selected={data.source === 'other'}>Other</option>
				</select>
			</label>
		</div>
		<label>Date <input type="date" name="date" value={data.date} /></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div><strong>{item.data.amount}ml</strong> <span class="meta">{sourceLabels[(item.data.source as string)] ?? item.data.source}</span></div>
	{/snippet}
</EntryList>

<style>
	h2 {
		font-size: 0.9rem;
		font-weight: 600;
		margin-bottom: 0.5rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}

	/* Target */
	.target-section { padding: 0 1rem 1rem; }
	.target-row {
		display: flex;
		align-items: center;
		gap: 0.5rem;
	}
	.target-input {
		width: 90px;
		text-align: center;
		font-weight: 600;
	}
	.target-unit {
		font-size: 0.85rem;
		color: var(--c-text-muted);
	}
	.target-save {
		background: none;
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		color: var(--c-done);
		padding: 0.35rem 0.5rem;
		cursor: pointer;
		display: flex;
		align-items: center;
	}
	.target-save:hover {
		border-color: var(--c-done);
	}

	/* Progress Ring */
	.progress-section {
		padding: 0 1rem 1.5rem;
		text-align: center;
	}
	.ring-container {
		display: flex;
		justify-content: center;
	}
	.progress-ring {
		width: 180px;
		height: 180px;
	}
	.ring-progress {
		transition: stroke-dashoffset 0.4s ease;
	}
	.ring-pct {
		font-size: 28px;
		font-weight: 700;
		fill: var(--c-text);
	}
	.ring-total {
		font-size: 14px;
		font-weight: 500;
		fill: var(--c-text-muted);
	}
	.progress-label {
		font-size: 0.85rem;
		color: var(--c-text-muted);
		margin-top: 0.25rem;
	}

	/* Quick Tap */
	.quick-section { padding: 0 1rem 1.5rem; }
	.quick-row {
		display: flex;
		gap: 0.5rem;
	}
	.quick-btn {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 0.3rem;
		padding: 0.75rem 0.5rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		cursor: pointer;
		transition: all 0.15s;
		color: var(--c-text);
	}
	.quick-btn:hover {
		border-color: var(--c-accent);
		background: var(--c-accent-bg);
	}
	.quick-btn:active {
		transform: scale(0.96);
	}
	.quick-label {
		font-size: 0.95rem;
		font-weight: 700;
	}
	.quick-desc {
		font-size: 0.75rem;
		color: var(--c-text-muted);
	}

	/* Custom */
	.custom-section { padding: 0 1rem 1.5rem; }
	.custom-row {
		display: flex;
		gap: 0.5rem;
		align-items: stretch;
	}
	.custom-input {
		flex: 1;
		min-width: 80px;
	}
	.custom-select {
		flex: 1;
		min-width: 80px;
	}
	.custom-add {
		white-space: nowrap;
	}

	/* Weekly Chart */
	.chart-section { padding: 0 1rem 1.5rem; }
	.week-chart {
		width: 100%;
		height: 120px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}

	/* Form helpers */
	.row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }
	.empty-hint {
		font-size: 0.85rem;
		color: var(--c-text-muted);
	}

	/* Insight sections */
	.insight-section { padding: 0 1rem 1.5rem; }

	/* Correlation */
	.correlation-card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem 1rem;
	}
	.correlation-header {
		display: flex;
		align-items: center;
		gap: 0.5rem;
	}
	.correlation-value {
		font-weight: 700;
		font-size: 1rem;
	}
	.correlation-strength {
		font-size: 0.8rem;
		color: var(--c-text-muted);
		background: var(--c-accent-bg);
		padding: 0.1rem 0.4rem;
		border-radius: var(--radius);
	}
	.correlation-msg {
		font-size: 0.85rem;
		margin: 0.4rem 0 0.2rem;
		color: var(--c-text);
	}
	.correlation-meta {
		font-size: 0.75rem;
		color: var(--c-text-muted);
		margin: 0;
	}

	/* Source Distribution */
	.source-bar-container {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem 1rem;
	}
	.source-stacked-bar {
		display: flex;
		height: 20px;
		border-radius: calc(var(--radius) / 2);
		overflow: hidden;
	}
	.source-bar-segment {
		min-width: 2px;
		transition: width 0.3s ease;
	}
	.source-legend {
		display: flex;
		flex-wrap: wrap;
		gap: 0.5rem 1rem;
		margin-top: 0.6rem;
	}
	.source-legend-item {
		display: flex;
		align-items: center;
		gap: 0.3rem;
		font-size: 0.8rem;
	}
	.source-dot {
		width: 8px;
		height: 8px;
		border-radius: 50%;
		flex-shrink: 0;
	}
	.source-name {
		color: var(--c-text);
	}
	.source-pct {
		color: var(--c-text-muted);
		font-weight: 600;
	}

	/* Timing Patterns */
	.timing-chart {
		display: flex;
		flex-direction: column;
		gap: 0.4rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.75rem 1rem;
	}
	.timing-row {
		display: flex;
		align-items: center;
		gap: 0.5rem;
	}
	.timing-label {
		font-size: 0.8rem;
		color: var(--c-text-muted);
		width: 40px;
		flex-shrink: 0;
		text-align: right;
	}
	.timing-bar-track {
		flex: 1;
		height: 14px;
		background: var(--c-accent-bg);
		border-radius: calc(var(--radius) / 2);
		overflow: hidden;
	}
	.timing-bar-fill {
		height: 100%;
		border-radius: calc(var(--radius) / 2);
		transition: width 0.3s ease;
	}
	.timing-value {
		font-size: 0.75rem;
		color: var(--c-text-muted);
		width: 45px;
		flex-shrink: 0;
		text-align: right;
	}
	.timing-hint {
		font-size: 0.75rem;
		color: var(--c-text-muted);
		margin-top: 0.3rem;
	}

	/* Streak */
	.streak-row {
		display: flex;
		gap: 0.5rem;
	}
	.streak-card {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 0.2rem;
		padding: 0.75rem 0.5rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}
	.streak-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 36px;
		height: 36px;
		border-radius: 50%;
	}
	.streak-fire { background: #fef3c7; color: #f59e0b; }
	.streak-trophy { background: #dbeafe; color: #3b82f6; }
	.streak-month { background: #dcfce7; color: #22c55e; }
	.streak-value {
		font-size: 1.4rem;
		font-weight: 700;
		color: var(--c-text);
	}
	.streak-label {
		font-size: 0.7rem;
		color: var(--c-text-muted);
		text-align: center;
	}

	/* Monthly Trend */
	.monthly-avg {
		font-size: 0.85rem;
		color: var(--c-text-muted);
		margin-bottom: 0.5rem;
	}
	.monthly-chart {
		width: 100%;
		height: 160px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}
</style>
