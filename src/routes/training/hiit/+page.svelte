<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('training.hiit');

	let date = $state(new Date().toISOString().slice(0, 10));
	let name = $state('');
	let rounds = $state(8);
	let workSec = $state(20);
	let restSec = $state(10);
	let maxHr = $state(0);
	let notes = $state('');

	function submit() {
		if (!name.trim()) return;
		entries.add('training.hiit', { date, name: name.trim(), rounds, workSec, restSec, maxHr, notes });
		name = ''; notes = '';
		date = new Date().toISOString().slice(0, 10);
		toast.show('HIIT logged');
	}

	function repeatLast() {
		const last = store.items.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt))[0];
		if (!last) return;
		name = last.data.name as string;
		rounds = last.data.rounds as number;
		workSec = last.data.workSec as number;
		restSec = last.data.restSec as number;
		maxHr = last.data.maxHr as number;
		notes = (last.data.notes as string) || '';
		toast.show('Fields pre-filled');
	}

	/* ── Quick Add Chips ── */

	const quickWorkouts = $derived.by(() => {
		const counts = new Map<string, { count: number; last: Record<string, unknown>; lastCreated: string }>();
		for (const e of store.items) {
			const key = (e.data.name as string)?.toLowerCase().trim();
			if (!key) continue;
			const existing = counts.get(key);
			if (!existing) {
				counts.set(key, { count: 1, last: e.data, lastCreated: e.createdAt });
			} else {
				existing.count++;
				if (e.createdAt > existing.lastCreated) {
					existing.last = e.data;
					existing.lastCreated = e.createdAt;
				}
			}
		}
		return [...counts.entries()]
			.sort((a, b) => b[1].count - a[1].count)
			.slice(0, 5)
			.map(([, info]) => ({ name: info.last.name as string, count: info.count, last: info.last }));
	});

	function prefillWorkout(item: { name: string; last: Record<string, unknown> }) {
		name = item.last.name as string;
		rounds = item.last.rounds as number;
		workSec = item.last.workSec as number;
		restSec = item.last.restSec as number;
		maxHr = item.last.maxHr as number;
		notes = (item.last.notes as string) || '';
		toast.show('Pre-filled');
	}

	/* ── Analytics ── */

	const totalSessions = $derived(store.items.length);

	const totalWorkMin = $derived.by(() => {
		const totalSec = store.items.reduce((s, e) => s + (Number(e.data.rounds) || 0) * (Number(e.data.workSec) || 0), 0);
		return Math.round(totalSec / 60);
	});

	const avgMaxHr = $derived.by(() => {
		const withHr = store.items.filter(e => (Number(e.data.maxHr) || 0) > 0);
		if (withHr.length === 0) return 0;
		const sum = withHr.reduce((s, e) => s + Number(e.data.maxHr), 0);
		return Math.round(sum / withHr.length);
	});

	/* Session intensity chart — last 15 */
	const intensityBars = $derived.by(() => {
		return store.items
			.toSorted((a, b) => ((a.data.date as string) || '').localeCompare((b.data.date as string) || '') || a.createdAt.localeCompare(b.createdAt))
			.slice(-15)
			.map(e => {
				const r = Number(e.data.rounds) || 0;
				const w = Number(e.data.workSec) || 0;
				const rest = Number(e.data.restSec) || 0;
				const totalWork = r * w;
				const ratio = w / (w + rest || 1);
				const color = ratio > 0.6 ? '#e53e3e' : ratio > 0.4 ? '#e8a735' : '#2e8b57';
				return { date: e.data.date as string, name: e.data.name as string, totalWork, ratio, color };
			});
	});

	/* HR trend — last 20 with HR > 0 */
	const hrTrend = $derived.by(() => {
		return store.items
			.filter(e => (Number(e.data.maxHr) || 0) > 0)
			.toSorted((a, b) => ((a.data.date as string) || '').localeCompare((b.data.date as string) || '') || a.createdAt.localeCompare(b.createdAt))
			.slice(-20)
			.map(e => Number(e.data.maxHr));
	});

	const hrAvgLine = $derived(hrTrend.length > 0 ? Math.round(hrTrend.reduce((s, v) => s + v, 0) / hrTrend.length) : 0);

	/* Weekly volume — last 4 weeks */
	const weeklyVolume = $derived.by(() => {
		const now = new Date();
		const weeks: { label: string; start: string; end: string }[] = [];
		for (let i = 0; i < 4; i++) {
			const end = new Date(now.getFullYear(), now.getMonth(), now.getDate() - i * 7);
			const start = new Date(end.getFullYear(), end.getMonth(), end.getDate() - 6);
			const labels = ['This week', 'Last week', '2 weeks ago', '3 weeks ago'];
			weeks.push({ label: labels[i], start: start.toISOString().slice(0, 10), end: end.toISOString().slice(0, 10) });
		}
		return weeks.map((w, i) => {
			const vol = store.items
				.filter(e => {
					const d = e.data.date as string;
					return d >= w.start && d <= w.end;
				})
				.reduce((s, e) => s + (Number(e.data.rounds) || 0) * (Number(e.data.workSec) || 0), 0);
			return { label: w.label, vol, idx: i };
		});
	});

	/* Work:Rest ratio stats */
	const ratioStats = $derived.by(() => {
		const items = store.items.filter(e => (Number(e.data.workSec) || 0) > 0);
		if (items.length === 0) return { avg: 0, high: 0, medium: 0, low: 0 };
		const ratios = items.map(e => {
			const w = Number(e.data.workSec) || 0;
			const r = Number(e.data.restSec) || 0;
			return w / (w + r || 1);
		});
		const avg = ratios.reduce((s, v) => s + v, 0) / ratios.length;
		const high = ratios.filter(r => r > 0.6).length;
		const medium = ratios.filter(r => r > 0.4 && r <= 0.6).length;
		const low = ratios.filter(r => r <= 0.4).length;
		return { avg, high, medium, low };
	});

	/* SVG helpers */

	function svgPolyline(values: number[], w: number, h: number, pad = 6): string {
		if (values.length < 2) return '';
		const min = Math.min(...values);
		const max = Math.max(...values);
		const range = max - min || 1;
		return values.map((v, i) => {
			const x = pad + (i / (values.length - 1)) * (w - pad * 2);
			const y = pad + (1 - (v - min) / range) * (h - pad * 2);
			return `${x.toFixed(1)},${y.toFixed(1)}`;
		}).join(' ');
	}

	function svgDots(values: number[], w: number, h: number, pad = 6): { x: number; y: number }[] {
		if (values.length < 2) return [];
		const min = Math.min(...values);
		const max = Math.max(...values);
		const range = max - min || 1;
		return values.map((v, i) => ({
			x: pad + (i / (values.length - 1)) * (w - pad * 2),
			y: pad + (1 - (v - min) / range) * (h - pad * 2)
		}));
	}

	function hrYPos(hr: number, values: number[], h: number, pad = 6): number {
		const min = Math.min(...values);
		const max = Math.max(...values);
		const range = max - min || 1;
		return pad + (1 - (hr - min) / range) * (h - pad * 2);
	}
</script>

<svelte:head>
  <title>HIIT | Darink</title>
</svelte:head>

<PageHeader title="HIIT" back="/training" />

{#if quickWorkouts.length > 0}
<section class="quick-add">
	<h2>Quick add</h2>
	<div class="quick-chips">
		{#each quickWorkouts as item}
			<button class="quick-chip" onclick={() => prefillWorkout(item)}>
				{item.name}
				<span class="quick-count">{item.count}</span>
			</button>
		{/each}
	</div>
</section>
{/if}

<section class="form">
	<label>Date <input type="date" bind:value={date} /></label>
	<label>Name <input type="text" bind:value={name} placeholder="Tabata, Sprint..." /></label>
	<div class="row">
		<label>Rounds <input type="number" min="1" max="50" bind:value={rounds} /></label>
		<label>Work (s) <input type="number" min="1" bind:value={workSec} /></label>
		<label>Rest (s) <input type="number" min="0" bind:value={restSec} /></label>
	</div>
	<label>Max HR <input type="number" min="0" max="250" bind:value={maxHr} /></label>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<div class="form-actions">
		<button class="primary" onclick={submit}>Log session</button>
		{#if store.items.length > 0}
			<button onclick={repeatLast}>Repeat last</button>
		{/if}
	</div>
</section>

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			date: fd.get('date') as string,
			name: (fd.get('name') as string).trim(),
			rounds: Number(fd.get('rounds')),
			workSec: Number(fd.get('workSec')),
			restSec: Number(fd.get('restSec')),
			maxHr: Number(fd.get('maxHr')),
			notes: (fd.get('notes') as string).trim()
		});
		toast.show('Updated');
		done();
	}}>
		<label>Date <input type="date" name="date" value={data.date || ''} /></label>
		<label>Name <input type="text" name="name" value={data.name} /></label>
		<div class="row">
			<label>Rounds <input type="number" name="rounds" min="1" max="50" value={data.rounds} /></label>
			<label>Work (s) <input type="number" name="workSec" min="1" value={data.workSec} /></label>
			<label>Rest (s) <input type="number" name="restSec" min="0" value={data.restSec} /></label>
		</div>
		<label>Max HR <input type="number" name="maxHr" min="0" max="250" value={data.maxHr} /></label>
		<label>Notes <textarea name="notes" rows="2">{data.notes}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div><strong>{item.data.name}</strong> <span class="meta">{item.data.rounds}r · {item.data.workSec}s/{item.data.restSec}s</span></div>
	{/snippet}
</EntryList>

<!-- Analytics -->
{#if store.items.length > 0}

<!-- 1. Quick Stats -->
<section class="analytics">
	<h2>Stats</h2>
	<div class="stat-row">
		<div class="stat-card">
			<span class="stat-value">{totalSessions}</span>
			<span class="stat-label">Sessions</span>
		</div>
		<div class="stat-card">
			<span class="stat-value">{totalWorkMin}<small>m</small></span>
			<span class="stat-label">Work time</span>
		</div>
		{#if avgMaxHr > 0}
		<div class="stat-card">
			<span class="stat-value">{avgMaxHr}</span>
			<span class="stat-label">Avg max HR</span>
		</div>
		{/if}
	</div>
</section>

<!-- 2. Session Intensity Chart -->
{#if intensityBars.length > 1}
<section class="analytics">
	<h2>Session intensity (last 15)</h2>
	<div class="chart-wrap">
		<svg viewBox="0 0 280 80" class="chart-svg">
			{#each intensityBars as bar, i}
				{@const maxWork = Math.max(...intensityBars.map(b => b.totalWork), 1)}
				{@const barW = (280 - 16) / intensityBars.length - 2}
				{@const barH = (bar.totalWork / maxWork) * 64}
				{@const x = 8 + i * ((280 - 16) / intensityBars.length)}
				<rect
					x={x}
					y={72 - barH}
					width={barW}
					height={barH}
					fill={bar.color}
					rx="2"
				/>
			{/each}
			<line x1="8" y1="72" x2="272" y2="72" stroke="var(--c-border)" stroke-width="0.5" />
		</svg>
		<div class="chart-legend">
			<span class="legend-item"><span class="legend-dot" style="background:#e53e3e"></span> High (&gt;0.6)</span>
			<span class="legend-item"><span class="legend-dot" style="background:#e8a735"></span> Medium</span>
			<span class="legend-item"><span class="legend-dot" style="background:#2e8b57"></span> Low (&le;0.4)</span>
		</div>
	</div>
</section>
{/if}

<!-- 3. HR Trend Chart -->
{#if hrTrend.length >= 2}
<section class="analytics">
	<h2>HR trend (last 20)</h2>
	<div class="chart-wrap">
		<svg viewBox="0 0 280 80" class="chart-svg">
			<!-- Average HR dashed reference line -->
			<line
				x1="6" y1={hrYPos(hrAvgLine, hrTrend, 80)}
				x2="274" y2={hrYPos(hrAvgLine, hrTrend, 80)}
				stroke="var(--c-text-muted)" stroke-width="0.7" stroke-dasharray="4 3"
			/>
			<text x="274" y={hrYPos(hrAvgLine, hrTrend, 80) - 3} fill="var(--c-text-muted)" font-size="7" text-anchor="end">avg {hrAvgLine}</text>
			<!-- Polyline -->
			<polyline
				points={svgPolyline(hrTrend, 280, 80)}
				fill="none" stroke="#e53e3e" stroke-width="1.5" stroke-linejoin="round"
			/>
			<!-- Dots -->
			{#each svgDots(hrTrend, 280, 80) as dot}
				<circle cx={dot.x} cy={dot.y} r="2.5" fill="#e53e3e" />
			{/each}
		</svg>
	</div>
</section>
{/if}

<!-- 4. Weekly Volume Progression -->
{#if weeklyVolume.some(w => w.vol > 0)}
<section class="analytics">
	<h2>Weekly volume</h2>
	<div class="weekly-bars">
		{#each weeklyVolume as week, i}
			{@const maxVol = Math.max(...weeklyVolume.map(w => w.vol), 1)}
			{@const pct = (week.vol / maxVol) * 100}
			{@const prevVol = i < weeklyVolume.length - 1 ? weeklyVolume[i + 1].vol : 0}
			{@const delta = prevVol > 0 ? Math.round(((week.vol - prevVol) / prevVol) * 100) : 0}
			<div class="weekly-row">
				<span class="weekly-label">{week.label}</span>
				<div class="weekly-bar-track">
					<div class="weekly-bar-fill" style="width:{pct}%"></div>
				</div>
				<span class="weekly-val">{Math.round(week.vol / 60)}m</span>
				{#if prevVol > 0 && week.vol > 0}
					<span class="weekly-delta" class:positive={delta > 0} class:negative={delta < 0}>
						{delta > 0 ? '+' : ''}{delta}%
					</span>
				{/if}
			</div>
		{/each}
	</div>
</section>
{/if}

<!-- 6. Work:Rest Ratio Stats -->
{#if ratioStats.avg > 0}
<section class="analytics">
	<h2>Work:Rest ratio</h2>
	<div class="stat-row">
		<div class="stat-card">
			<span class="stat-value">{ratioStats.avg.toFixed(2)}</span>
			<span class="stat-label">Avg ratio</span>
		</div>
		<div class="stat-card">
			<span class="stat-value" style="color:#e53e3e">{ratioStats.high}</span>
			<span class="stat-label">High</span>
		</div>
		<div class="stat-card">
			<span class="stat-value" style="color:#e8a735">{ratioStats.medium}</span>
			<span class="stat-label">Medium</span>
		</div>
		<div class="stat-card">
			<span class="stat-value" style="color:#2e8b57">{ratioStats.low}</span>
			<span class="stat-label">Low</span>
		</div>
	</div>
</section>
{/if}

{/if}

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }
	.form-actions { display: flex; gap: 0.5rem; }
	.form-actions button { flex: 1; }

	/* Quick add */
	.quick-add { padding: 0 1rem 0.5rem; }
	h2 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
	.quick-chips { display: flex; flex-wrap: wrap; gap: 0.25rem; }
	.quick-chip {
		display: inline-flex; align-items: center; gap: 0.25rem;
		padding: 0.3rem 0.6rem; background: var(--c-bg-card);
		border: 1px solid var(--c-border); border-radius: 16px;
		font-size: 0.8rem; cursor: pointer; transition: border-color 0.15s;
	}
	.quick-chip:hover { border-color: var(--c-accent); background: var(--c-accent-bg); }
	.quick-count { font-size: 0.65rem; color: var(--c-text-muted); }

	/* Analytics */
	.analytics { padding: 0.75rem 1rem; }
	.stat-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.stat-card {
		flex: 1; min-width: 70px; display: flex; flex-direction: column; align-items: center;
		padding: 0.5rem; background: var(--c-bg-card); border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}
	.stat-value { font-size: 1.25rem; font-weight: 700; }
	.stat-value small { font-size: 0.75rem; font-weight: 400; }
	.stat-label { font-size: 0.7rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.03em; }

	/* Charts */
	.chart-wrap {
		background: var(--c-bg-card); border: 1px solid var(--c-border);
		border-radius: var(--radius); padding: 0.5rem; overflow: hidden;
	}
	.chart-svg { width: 100%; height: auto; display: block; }
	.chart-legend { display: flex; gap: 0.75rem; justify-content: center; padding-top: 0.35rem; }
	.legend-item { font-size: 0.65rem; color: var(--c-text-muted); display: flex; align-items: center; gap: 0.2rem; }
	.legend-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

	/* Weekly volume */
	.weekly-bars { display: flex; flex-direction: column; gap: 0.4rem; }
	.weekly-row { display: flex; align-items: center; gap: 0.5rem; }
	.weekly-label { font-size: 0.75rem; color: var(--c-text-muted); width: 80px; flex-shrink: 0; }
	.weekly-bar-track { flex: 1; height: 12px; background: var(--c-border); border-radius: 6px; overflow: hidden; }
	.weekly-bar-fill { height: 100%; background: var(--c-accent); border-radius: 6px; transition: width 0.3s; }
	.weekly-val { font-size: 0.75rem; font-weight: 600; width: 30px; text-align: right; }
	.weekly-delta { font-size: 0.65rem; width: 36px; text-align: right; }
	.weekly-delta.positive { color: var(--c-done); }
	.weekly-delta.negative { color: var(--c-cancel); }
</style>
