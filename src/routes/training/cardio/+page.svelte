<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { useLocale } from '$lib/stores/locale.svelte';
	import type { Entry } from '$lib/db';

	const { t } = useLocale();

	const store = useEntries('training.cardio');

	let date = $state(new Date().toISOString().slice(0, 10));
	let activity = $state('');
	let distanceKm = $state(0);
	let durationMin = $state(0);
	let zone = $state(2);
	let notes = $state('');

	function submit() {
		if (!activity.trim()) return;
		entries.add('training.cardio', { date, activity: activity.trim(), distanceKm, durationMin, zone, notes });
		activity = ''; notes = '';
		date = new Date().toISOString().slice(0, 10);
		toast.show(t.training.cardioLogged);
	}

	function repeatLast() {
		const last = store.items.toSorted((a, b) => b.createdAt.localeCompare(a.createdAt))[0];
		if (!last) return;
		activity = last.data.activity as string;
		distanceKm = last.data.distanceKm as number;
		durationMin = last.data.durationMin as number;
		zone = last.data.zone as number;
		notes = (last.data.notes as string) || '';
		toast.show(t.common.prefilled);
	}

	const quickActivities = $derived.by(() => {
		const counts = new Map<string, { count: number; last: Record<string, unknown>; lastCreated: string }>();
		for (const e of store.items) {
			const key = (e.data.activity as string)?.toLowerCase().trim();
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
			.map(([, info]) => ({ name: info.last.activity as string, count: info.count, last: info.last }));
	});

	function prefillActivity(item: { name: string; last: Record<string, unknown> }) {
		activity = item.last.activity as string;
		distanceKm = item.last.distanceKm as number;
		durationMin = item.last.durationMin as number;
		zone = item.last.zone as number;
		toast.show(t.common.prefilled);
	}

	/* ── Analytics ── */

	const totalDistance = $derived(store.items.reduce((s, e) => s + (e.data.distanceKm as number || 0), 0));
	const totalDurationHrs = $derived(store.items.reduce((s, e) => s + (e.data.durationMin as number || 0), 0) / 60);

	const sessionsThisWeek = $derived.by(() => {
		const now = new Date();
		const day = now.getDay();
		const mondayOffset = day === 0 ? 6 : day - 1;
		const monday = new Date(now);
		monday.setDate(now.getDate() - mondayOffset);
		const mondayStr = monday.toISOString().slice(0, 10);
		return store.items.filter(e => (e.data.date as string) >= mondayStr).length;
	});

	/* Pace Progression for top activity */
	const topActivity = $derived.by(() => {
		const counts = new Map<string, number>();
		for (const e of store.items) {
			const a = (e.data.activity as string)?.toLowerCase().trim();
			if (a) counts.set(a, (counts.get(a) || 0) + 1);
		}
		let best = '';
		let bestCount = 0;
		for (const [k, v] of counts) {
			if (v > bestCount) { best = k; bestCount = v; }
		}
		return best;
	});

	const paceProgression = $derived.by(() => {
		if (!topActivity) return [];
		return store.items
			.filter(e => (e.data.activity as string)?.toLowerCase().trim() === topActivity && (e.data.distanceKm as number) > 0)
			.toSorted((a, b) => ((a.data.date as string) || '').localeCompare((b.data.date as string) || ''))
			.map(e => ({
				date: e.data.date as string,
				pace: (e.data.durationMin as number) / (e.data.distanceKm as number)
			}));
	});

	const topActivityDisplay = $derived.by(() => {
		if (!topActivity) return '';
		for (const e of store.items) {
			if ((e.data.activity as string)?.toLowerCase().trim() === topActivity) return e.data.activity as string;
		}
		return topActivity;
	});

	const paceTrend = $derived.by(() => {
		if (paceProgression.length < 2) return 'neutral';
		const current = paceProgression[paceProgression.length - 1].pace;
		const compareIdx = Math.max(0, paceProgression.length - 6);
		const old = paceProgression[compareIdx].pace;
		if (current < old - 0.1) return 'faster';
		if (current > old + 0.1) return 'slower';
		return 'neutral';
	});

	/* Distance & Duration trends (last 20) */
	const last20 = $derived.by(() => {
		return store.items
			.toSorted((a, b) => ((a.data.date as string) || '').localeCompare((b.data.date as string) || ''))
			.slice(-20)
			.map(e => ({
				dist: e.data.distanceKm as number || 0,
				dur: e.data.durationMin as number || 0,
				date: e.data.date as string
			}));
	});

	/* Zone distribution */
	const zoneDistribution = $derived.by(() => {
		const counts = [0, 0, 0, 0, 0];
		for (const e of store.items) {
			const z = e.data.zone as number;
			if (z >= 1 && z <= 5) counts[z - 1]++;
		}
		const total = store.items.length || 1;
		return counts.map((c, i) => ({ zone: i + 1, count: c, pct: Math.round((c / total) * 100) }));
	});

	const zoneColors = ['#22c55e', '#4aa3ff', '#f97316', '#ef4444', '#dc2626'];
	const zoneLabels = $derived.by(() => [t.training.zoneRecovery, t.training.zoneEndurance, t.training.zoneTempo, t.training.zoneThreshold, t.training.zoneVO2max]);

	/* Personal bests */
	const personalBests = $derived.by(() => {
		const activities = new Map<string, { fastestPace: number; fastestDate: string; longestDist: number; longestDistDate: string; longestDur: number; longestDurDate: string; display: string }>();
		for (const e of store.items) {
			const aRaw = e.data.activity as string;
			const a = aRaw?.toLowerCase().trim();
			if (!a) continue;
			const dist = e.data.distanceKm as number || 0;
			const dur = e.data.durationMin as number || 0;
			const d = e.data.date as string || '';
			const pace = dist > 0 ? dur / dist : Infinity;
			const existing = activities.get(a);
			if (!existing) {
				activities.set(a, { fastestPace: pace, fastestDate: d, longestDist: dist, longestDistDate: d, longestDur: dur, longestDurDate: d, display: aRaw });
			} else {
				if (pace < existing.fastestPace) { existing.fastestPace = pace; existing.fastestDate = d; }
				if (dist > existing.longestDist) { existing.longestDist = dist; existing.longestDistDate = d; }
				if (dur > existing.longestDur) { existing.longestDur = dur; existing.longestDurDate = d; }
			}
		}
		return [...activities.values()];
	});

	function fmtPace(p: number): string {
		if (!isFinite(p)) return '--';
		const m = Math.floor(p);
		const s = Math.round((p - m) * 60);
		return `${m}:${s.toString().padStart(2, '0')}`;
	}

	function svgPolyline(data: number[], w: number, h: number, padY = 4): string {
		if (data.length < 2) return '';
		const min = Math.min(...data);
		const max = Math.max(...data);
		const range = max - min || 1;
		return data.map((v, i) => {
			const x = (i / (data.length - 1)) * w;
			const y = padY + ((max - v) / range) * (h - padY * 2);
			return `${x.toFixed(1)},${y.toFixed(1)}`;
		}).join(' ');
	}
</script>

<svelte:head>
  <title>{t.training.cardio} | Darink</title>
</svelte:head>

<PageHeader title={t.training.cardio} back="/training" breadcrumbs={[{ href: "/training", label: t.training.title }]} />

{#if quickActivities.length > 0}
<section class="quick-add">
	<h2>{t.training.quickAdd}</h2>
	<div class="quick-chips">
		{#each quickActivities as item}
			<button class="quick-chip" onclick={() => prefillActivity(item)}>
				{item.name}
				<span class="quick-count">{item.count}</span>
			</button>
		{/each}
	</div>
</section>
{/if}

<section class="form">
	<label>{t.common.date} <input type="date" bind:value={date} /></label>
	<label>{t.training.activity} <input type="text" bind:value={activity} placeholder="Run, Bike, Swim..." /></label>
	<div class="row">
		<label>{t.training.distanceKm} <input type="number" min="0" step="0.1" bind:value={distanceKm} /></label>
		<label>{t.training.durationMin} <input type="number" min="0" bind:value={durationMin} /></label>
		<label>{t.training.zone15} <input type="number" min="1" max="5" bind:value={zone} /></label>
	</div>
	<label>{t.common.notes} <textarea bind:value={notes} rows="2"></textarea></label>
	<div class="form-actions">
		<button class="primary" onclick={submit}>{t.training.logCardio}</button>
		{#if store.items.length > 0}
			<button onclick={repeatLast}>{t.common.repeatLast}</button>
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
			activity: (fd.get('activity') as string).trim(),
			distanceKm: Number(fd.get('distanceKm')),
			durationMin: Number(fd.get('durationMin')),
			zone: Number(fd.get('zone')),
			notes: (fd.get('notes') as string).trim()
		});
		toast.show(t.common.updated);
		done();
	}}>
		<label>{t.common.date} <input type="date" name="date" value={data.date || ''} /></label>
		<label>{t.training.activity} <input type="text" name="activity" value={data.activity} /></label>
		<div class="row">
			<label>{t.training.distanceKm} <input type="number" name="distanceKm" min="0" step="0.1" value={data.distanceKm} /></label>
			<label>{t.training.durationMin} <input type="number" name="durationMin" min="0" value={data.durationMin} /></label>
			<label>{t.training.zone15} <input type="number" name="zone" min="1" max="5" value={data.zone} /></label>
		</div>
		<label>{t.common.notes} <textarea name="notes" rows="2">{data.notes}</textarea></label>
		<div class="edit-actions">
			<button type="submit">{t.common.save}</button>
			<button type="button" onclick={done}>{t.common.cancel}</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div><strong>{item.data.activity}</strong> <span class="meta">{item.data.distanceKm}km · {item.data.durationMin}min · Z{item.data.zone}</span></div>
	{/snippet}
</EntryList>

<!-- ── Analytics ── -->
{#if store.items.length > 0}

<!-- Quick Stats -->
<section class="analytics">
	<h2>{t.training.stats}</h2>
	<div class="stat-row">
		<div class="stat-card">
			<span class="stat-value">{totalDistance.toFixed(1)}</span>
			<span class="stat-label">{t.training.kmTotal}</span>
		</div>
		<div class="stat-card">
			<span class="stat-value">{totalDurationHrs.toFixed(1)}</span>
			<span class="stat-label">{t.training.hoursTotal}</span>
		</div>
		<div class="stat-card">
			<span class="stat-value">{sessionsThisWeek}</span>
			<span class="stat-label">{t.common.thisWeek}</span>
		</div>
	</div>
</section>

<!-- Pace Progression -->
{#if paceProgression.length >= 2}
<section class="analytics">
	<h2>{t.training.pace} — {topActivityDisplay}</h2>
	<div class="chart-card">
		<div class="chart-meta">
			<span>{t.training.current}: <strong>{fmtPace(paceProgression[paceProgression.length - 1].pace)}</strong> min/km</span>
			{#if paceTrend === 'faster'}
				<span class="trend trend-good">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
					{t.training.faster}
				</span>
			{:else if paceTrend === 'slower'}
				<span class="trend trend-bad">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
					{t.training.slower}
				</span>
			{:else}
				<span class="trend">{t.training.steady}</span>
			{/if}
		</div>
		<svg viewBox="0 0 280 80" class="line-chart" preserveAspectRatio="none">
			<polyline
				points={svgPolyline(paceProgression.map(p => -p.pace), 280, 80)}
				fill="none"
				stroke="#2e8b57"
				stroke-width="2"
				stroke-linejoin="round"
			/>
		</svg>
		<div class="chart-axis">
			<span>{paceProgression[0].date}</span>
			<span>{paceProgression[paceProgression.length - 1].date}</span>
		</div>
	</div>
</section>
{/if}

<!-- Distance & Duration Trends -->
{#if last20.length >= 2}
<section class="analytics">
	<h2>{t.training.trendsLast} {last20.length} {t.training.sessions}</h2>
	<div class="chart-card">
		<div class="chart-legend">
			<span class="legend-item"><span class="legend-dot" style="background:#4aa3ff"></span> {t.training.distanceKm}</span>
			<span class="legend-item"><span class="legend-dot" style="background:#2e8b57"></span> {t.training.durationMin}</span>
		</div>
		<svg viewBox="0 0 280 80" class="line-chart" preserveAspectRatio="none">
			<polyline
				points={svgPolyline(last20.map(d => d.dist), 280, 80)}
				fill="none"
				stroke="#4aa3ff"
				stroke-width="2"
				stroke-linejoin="round"
			/>
			<polyline
				points={svgPolyline(last20.map(d => d.dur), 280, 80)}
				fill="none"
				stroke="#2e8b57"
				stroke-width="2"
				stroke-linejoin="round"
				stroke-dasharray="4 2"
			/>
		</svg>
		<div class="chart-axis">
			<span>{last20[0].date}</span>
			<span>{last20[last20.length - 1].date}</span>
		</div>
	</div>
</section>
{/if}

<!-- Zone Distribution -->
<section class="analytics">
	<h2>{t.training.zoneDistribution}</h2>
	<div class="zone-bars">
		{#each zoneDistribution as z, i}
			<div class="zone-row">
				<span class="zone-label">Z{z.zone} <span class="zone-name">{zoneLabels[i]}</span></span>
				<div class="zone-bar-track">
					<div class="zone-bar-fill" style="width:{z.pct}%; background:{zoneColors[i]}"></div>
				</div>
				<span class="zone-pct">{z.pct}%</span>
			</div>
		{/each}
	</div>
</section>

<!-- Personal Bests -->
{#if personalBests.length > 0}
<section class="analytics">
	<h2>{t.training.personalBests}</h2>
	<div class="pb-list">
		{#each personalBests as pb}
			<div class="pb-card">
				<div class="pb-activity">{pb.display}</div>
				<div class="pb-records">
					{#if isFinite(pb.fastestPace)}
						<div class="pb-item">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
							<span class="pb-val">{fmtPace(pb.fastestPace)} min/km</span>
							<span class="pb-date">{pb.fastestDate}</span>
						</div>
					{/if}
					{#if pb.longestDist > 0}
						<div class="pb-item">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
							<span class="pb-val">{pb.longestDist} km</span>
							<span class="pb-date">{pb.longestDistDate}</span>
						</div>
					{/if}
					{#if pb.longestDur > 0}
						<div class="pb-item">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
							<span class="pb-val">{pb.longestDur} min</span>
							<span class="pb-date">{pb.longestDurDate}</span>
						</div>
					{/if}
				</div>
			</div>
		{/each}
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
	.analytics { padding: 0 1rem; margin-top: 1.5rem; }
	.stat-row { display: flex; gap: 0.5rem; }
	.stat-card {
		flex: 1; display: flex; flex-direction: column; align-items: center;
		padding: 0.6rem 0.4rem; background: var(--c-bg-card);
		border: 1px solid var(--c-border); border-radius: var(--radius);
	}
	.stat-value { font-size: 1.3rem; font-weight: 700; }
	.stat-label { font-size: 0.7rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.03em; }

	.chart-card {
		background: var(--c-bg-card); border: 1px solid var(--c-border);
		border-radius: var(--radius); padding: 0.75rem; margin-top: 0.5rem;
	}
	.chart-meta { display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; margin-bottom: 0.5rem; }
	.trend { display: inline-flex; align-items: center; gap: 0.2rem; font-size: 0.75rem; font-weight: 600; }
	.trend-good { color: var(--c-done, #22c55e); }
	.trend-bad { color: #ef4444; }
	.line-chart { width: 100%; height: 80px; display: block; }
	.chart-axis { display: flex; justify-content: space-between; font-size: 0.65rem; color: var(--c-text-muted); margin-top: 0.25rem; }
	.chart-legend { display: flex; gap: 1rem; font-size: 0.7rem; color: var(--c-text-muted); margin-bottom: 0.5rem; }
	.legend-item { display: inline-flex; align-items: center; gap: 0.3rem; }
	.legend-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

	.zone-bars { display: flex; flex-direction: column; gap: 0.4rem; margin-top: 0.5rem; }
	.zone-row { display: flex; align-items: center; gap: 0.5rem; }
	.zone-label { font-size: 0.75rem; font-weight: 600; min-width: 5.5rem; }
	.zone-name { font-weight: 400; color: var(--c-text-muted); font-size: 0.7rem; }
	.zone-bar-track { flex: 1; height: 14px; background: var(--c-border); border-radius: 7px; overflow: hidden; }
	.zone-bar-fill { height: 100%; border-radius: 7px; transition: width 0.3s; min-width: 2px; }
	.zone-pct { font-size: 0.75rem; min-width: 2rem; text-align: right; color: var(--c-text-muted); }

	.pb-list { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem; }
	.pb-card {
		background: var(--c-bg-card); border: 1px solid var(--c-border);
		border-radius: var(--radius); padding: 0.6rem 0.75rem;
	}
	.pb-activity { font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem; }
	.pb-records { display: flex; flex-direction: column; gap: 0.3rem; }
	.pb-item { display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; }
	.pb-item svg { color: var(--c-accent); flex-shrink: 0; }
	.pb-val { font-weight: 600; }
	.pb-date { color: var(--c-text-muted); font-size: 0.7rem; margin-left: auto; }
</style>
