<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { useLocale } from '$lib/stores/locale.svelte';
	import type { Entry } from '$lib/db';

	const { t } = useLocale();
	const store = useEntries('signal.sleep');
	const allStore = useEntries();

	let date = $state(new Date().toISOString().slice(0, 10));
	let hours = $state(7);
	let quality = $state(5);
	let dreams = $state(false);
	let bedtime = $state('23:00');
	let wakeTime = $state('07:00');
	let notes = $state('');

	const sorted = $derived(store.items.toSorted((a, b) => a.createdAt.localeCompare(b.createdAt)));
	const last30 = $derived(sorted.slice(-30));
	const qualityChart = $derived(last30.map(e => ({ value: Number(e.data.quality) })));
	const hoursChart = $derived(last30.map(e => ({ value: Number(e.data.hours) })));

	const thirtyDaysAgo = $derived(new Date(Date.now() - 30 * 86400000).toISOString());
	const recent30 = $derived(sorted.filter(e => e.createdAt >= thirtyDaysAgo));

	const sleepMoodCorrelation = $derived.by(() => {
		const checkins = allStore.items.filter(e => e.type === 'checkin');
		if (checkins.length < 3 || store.items.length < 3) return null;
		const sleepByDate: Record<string, number> = {};
		for (const e of store.items) {
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			sleepByDate[d] = Number(e.data.hours);
		}
		let goodMoods: number[] = [], poorMoods: number[] = [];
		let goodEnergies: number[] = [], poorEnergies: number[] = [];
		for (const c of checkins) {
			const d = (c.data.date as string) ?? c.createdAt.slice(0, 10);
			const h = sleepByDate[d];
			if (h === undefined) continue;
			const mood = Number(c.data.mood);
			const energy = Number(c.data.energy);
			if (h >= 7) { goodMoods.push(mood); goodEnergies.push(energy); }
			else { poorMoods.push(mood); poorEnergies.push(energy); }
		}
		if (goodMoods.length === 0 || poorMoods.length === 0) return null;
		const avg = (a: number[]) => +(a.reduce((s, v) => s + v, 0) / a.length).toFixed(1);
		return { goodMood: avg(goodMoods), poorMood: avg(poorMoods), goodEnergy: avg(goodEnergies), poorEnergy: avg(poorEnergies), goodN: goodMoods.length, poorN: poorMoods.length };
	});

	const weeklyPattern = $derived.by(() => {
		if (store.items.length < 7) return null;
		const dayNames = [t.days.sun, t.days.mon, t.days.tue, t.days.wed, t.days.thu, t.days.fri, t.days.sat];
		const buckets: number[][] = Array.from({ length: 7 }, () => []);
		for (const e of store.items) {
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			const day = new Date(d + 'T12:00:00').getDay();
			buckets[day].push(Number(e.data.hours));
		}
		const data = dayNames.map((name, i) => ({
			name,
			avg: buckets[i].length ? +(buckets[i].reduce((s, v) => s + v, 0) / buckets[i].length).toFixed(1) : 0,
			count: buckets[i].length
		}));
		const maxAvg = Math.max(...data.map(d => d.avg), 1);
		return { data, maxAvg };
	});

	const sleepDebt = $derived.by(() => {
		if (store.items.length < 7) return null;
		const target = 8;
		const recent7 = sorted.slice(-7);
		const totalHours = recent7.reduce((s, e) => s + Number(e.data.hours), 0);
		const debt = +(target * 7 - totalHours).toFixed(1);
		const avgRecent = +(totalHours / recent7.length).toFixed(1);
		return { debt, avgRecent, target };
	});

	const trainingRecovery = $derived.by(() => {
		const checkins = allStore.items.filter(e => e.type === 'checkin');
		const trainings = allStore.items.filter(e => e.type.startsWith('training.'));
		if (checkins.length < 3 || trainings.length < 2 || store.items.length < 3) return null;
		const trainingDates = new Set(trainings.map(e => e.createdAt.slice(0, 10)));
		const sleepByDate: Record<string, number> = {};
		for (const e of store.items) {
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			sleepByDate[d] = Number(e.data.quality);
		}
		let postTrainQ: number[] = [], restDayQ: number[] = [];
		for (const d of Object.keys(sleepByDate)) {
			const prev = new Date(d + 'T12:00:00');
			prev.setDate(prev.getDate() - 1);
			const prevKey = prev.toISOString().slice(0, 10);
			if (trainingDates.has(prevKey)) postTrainQ.push(sleepByDate[d]);
			else restDayQ.push(sleepByDate[d]);
		}
		if (postTrainQ.length === 0 || restDayQ.length === 0) return null;
		const avg = (a: number[]) => +(a.reduce((s, v) => s + v, 0) / a.length).toFixed(1);
		return { postTrain: avg(postTrainQ), restDay: avg(restDayQ) };
	});

	function submit() {
		entries.add('signal.sleep', { date, hours, quality, dreams, bedtime, wakeTime, notes });
		date = new Date().toISOString().slice(0, 10);
		notes = '';
		toast.show(t.sleep.sleepLogged);
	}
</script>

<svelte:head>
  <title>{t.sleep.title} | Darink</title>
</svelte:head>

<PageHeader title={t.sleep.title} back="/signals" breadcrumbs={[{ href: "/signals", label: t.more.signals }]} />

<section class="form">
	<label>{t.common.date} <input type="date" bind:value={date} /></label>
	<label>{t.sleep.hoursLabel} ({hours}) <input type="number" min="0" max="14" step="0.5" bind:value={hours} /></label>
	<label>{t.sleep.qualityLabel.replace('(/10)', `(${quality}/10)`)} <input type="range" min="1" max="10" bind:value={quality} /></label>
	<div class="row">
		<label>{t.sleep.bedtimeLabel} <input type="time" bind:value={bedtime} /></label>
		<label>{t.sleep.wakeLabel} <input type="time" bind:value={wakeTime} /></label>
	</div>
	<label class="checkbox"><input type="checkbox" bind:checked={dreams} /> {t.sleep.vividDreams}</label>
	<label>{t.common.notes} <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>{t.sleep.logSleep}</button>
</section>

{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		entries.update(item.id, {
			date: fd.get('date') as string,
			hours: Number(fd.get('hours')),
			quality: Number(fd.get('quality')),
			bedtime: fd.get('bedtime') as string,
			wakeTime: fd.get('wakeTime') as string,
			dreams: (fd.get('dreams') as string).trim()
		});
		toast.show(t.common.updated);
		done();
	}}>
		<label>{t.common.date} <input type="date" name="date" value={data.date as string ?? ''} /></label>
		<label>{t.sleep.hoursLabel} <input type="number" name="hours" min="0" max="14" step="0.5" value={data.hours} /></label>
		<label>{t.sleep.qualityLabel} <input type="range" name="quality" min="1" max="10" value={data.quality} /></label>
		<div class="row">
			<label>{t.sleep.bedtimeLabel} <input type="time" name="bedtime" value={data.bedtime} /></label>
			<label>{t.sleep.wakeLabel} <input type="time" name="wakeTime" value={data.wakeTime} /></label>
		</div>
		<label>{t.sleep.vividDreams} <textarea name="dreams" rows="2">{data.dreams ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">{t.common.save}</button>
			<button type="button" onclick={done}>{t.common.cancel}</button>
		</div>
	</form>
{/snippet}

{#if store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
	<p>{t.sleep.noEntries}</p>
	<p class="empty-hint">{t.sleep.noEntriesHint}</p>
</div>
{/if}

<EntryList items={store.items} {editForm} limit={7}>
	{#snippet row(item)}
		<div><span class="date">{new Date(item.createdAt).toLocaleDateString()}</span> <span class="meta">{item.data.hours}h · Q{item.data.quality}/10</span></div>
	{/snippet}
</EntryList>

{#if qualityChart.length > 1}
{@const pts = qualityChart}
{@const minV = Math.min(...pts.map(p => p.value))}
{@const maxV = Math.max(...pts.map(p => p.value))}
{@const rangeV = maxV - minV || 1}
{@const stepX = 280 / Math.max(pts.length - 1, 1)}
<section class="chart-section">
	<h2>{t.sleep.sleepQualityTrend}</h2>
	<svg class="line-chart" viewBox="0 0 280 100" preserveAspectRatio="none">
		<polyline fill="none" stroke="var(--c-accent)" stroke-width="2" stroke-linejoin="round"
			points={pts.map((p, i) => `${i * stepX},${100 - ((p.value - minV) / rangeV) * 80 - 10}`).join(' ')} />
	</svg>
</section>
{/if}

{#if hoursChart.length > 1}
{@const pts = hoursChart}
{@const minV = Math.min(...pts.map(p => p.value))}
{@const maxV = Math.max(...pts.map(p => p.value))}
{@const rangeV = maxV - minV || 1}
{@const stepX = 280 / Math.max(pts.length - 1, 1)}
<section class="chart-section">
	<h2>{t.sleep.hoursTrend}</h2>
	<svg class="line-chart" viewBox="0 0 280 100" preserveAspectRatio="none">
		<polyline fill="none" stroke="#6ec6ff" stroke-width="2" stroke-linejoin="round"
			points={pts.map((p, i) => `${i * stepX},${100 - ((p.value - minV) / rangeV) * 80 - 10}`).join(' ')} />
	</svg>
</section>
{/if}

{#if recent30.length > 0}
{@const avgHours = (recent30.reduce((s, e) => s + Number(e.data.hours), 0) / recent30.length).toFixed(1)}
{@const avgQuality = (recent30.reduce((s, e) => s + Number(e.data.quality), 0) / recent30.length).toFixed(1)}
{@const bestEntry = recent30.reduce((a, b) => Number(a.data.quality) >= Number(b.data.quality) ? a : b)}
{@const worstEntry = recent30.reduce((a, b) => Number(a.data.quality) <= Number(b.data.quality) ? a : b)}
<section class="metrics">
	<h2>{t.sleep.averages30}</h2>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value">{avgHours}</span>
			<span class="metric-label">{t.sleep.avgHours}</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{avgQuality}</span>
			<span class="metric-label">{t.sleep.avgQuality}</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{new Date(bestEntry.createdAt).toLocaleDateString()}</span>
			<span class="metric-label">{t.sleep.bestQuality}</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{new Date(worstEntry.createdAt).toLocaleDateString()}</span>
			<span class="metric-label">{t.sleep.worstQuality}</span>
		</div>
	</div>
</section>
{/if}

{#if sleepMoodCorrelation}
<section class="analytics">
	<h2>{t.sleep.sleepMoodCorrelation}</h2>
	<p class="hint">{t.sleep.sleepMoodHint}</p>
	<div class="corr-grid">
		<div class="corr-card">
			<span class="corr-label">≥7h {t.common.sleep.toLowerCase()} (n={sleepMoodCorrelation.goodN})</span>
			<div class="corr-bars">
				<div class="corr-row"><span>{t.common.mood}</span><div class="bar-bg"><div class="bar-fill" style="width:{sleepMoodCorrelation.goodMood * 10}%;background:var(--c-done)"></div></div><span>{sleepMoodCorrelation.goodMood}</span></div>
				<div class="corr-row"><span>{t.common.energy}</span><div class="bar-bg"><div class="bar-fill" style="width:{sleepMoodCorrelation.goodEnergy * 10}%;background:#6ec6ff"></div></div><span>{sleepMoodCorrelation.goodEnergy}</span></div>
			</div>
		</div>
		<div class="corr-card">
			<span class="corr-label">&lt;7h {t.common.sleep.toLowerCase()} (n={sleepMoodCorrelation.poorN})</span>
			<div class="corr-bars">
				<div class="corr-row"><span>{t.common.mood}</span><div class="bar-bg"><div class="bar-fill" style="width:{sleepMoodCorrelation.poorMood * 10}%;background:#e8a735"></div></div><span>{sleepMoodCorrelation.poorMood}</span></div>
				<div class="corr-row"><span>{t.common.energy}</span><div class="bar-bg"><div class="bar-fill" style="width:{sleepMoodCorrelation.poorEnergy * 10}%;background:#e8a735"></div></div><span>{sleepMoodCorrelation.poorEnergy}</span></div>
			</div>
		</div>
	</div>
</section>
{/if}

{#if weeklyPattern}
<section class="analytics">
	<h2>{t.sleep.weeklySleepPattern}</h2>
	<div class="week-chart">
		{#each weeklyPattern.data as day}
			<div class="week-bar-col">
				<span class="week-val">{day.avg}h</span>
				<div class="week-bar" style="height:{(day.avg / weeklyPattern.maxAvg) * 100}%;background:{day.avg >= 7 ? 'var(--c-done)' : day.avg >= 6 ? '#e8a735' : '#e53e3e'}"></div>
				<span class="week-label">{day.name}</span>
			</div>
		{/each}
	</div>
</section>
{/if}

{#if sleepDebt}
<section class="analytics">
	<h2>{t.sleep.sleepDebtLast7}</h2>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value" style="color:{sleepDebt.debt <= 0 ? 'var(--c-done)' : sleepDebt.debt <= 5 ? '#e8a735' : '#e53e3e'}">{sleepDebt.debt > 0 ? '+' : ''}{sleepDebt.debt}h</span>
			<span class="metric-label">{t.sleep.sleepDebtLabel}</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{sleepDebt.avgRecent}h</span>
			<span class="metric-label">{t.common.average} (7d)</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{sleepDebt.target}h</span>
			<span class="metric-label">{t.sleep.target}</span>
		</div>
	</div>
</section>
{/if}

{#if trainingRecovery}
<section class="analytics">
	<h2>{t.sleep.trainingRecovery}</h2>
	<p class="hint">{t.sleep.trainingRecoveryHint}</p>
	<div class="metrics-row">
		<div class="metric-card">
			<span class="metric-value">{trainingRecovery.postTrain}/10</span>
			<span class="metric-label">{t.sleep.postTraining}</span>
		</div>
		<div class="metric-card">
			<span class="metric-value">{trainingRecovery.restDay}/10</span>
			<span class="metric-label">{t.sleep.restDay}</span>
		</div>
		<div class="metric-card">
			<span class="metric-value" style="color:{trainingRecovery.postTrain >= trainingRecovery.restDay ? 'var(--c-done)' : '#e53e3e'}">{trainingRecovery.postTrain >= trainingRecovery.restDay ? '+' : ''}{+(trainingRecovery.postTrain - trainingRecovery.restDay).toFixed(1)}</span>
			<span class="metric-label">{t.sleep.difference}</span>
		</div>
	</div>
</section>
{/if}

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }
	.checkbox { flex-direction: row; align-items: center; gap: 0.5rem; }
	.checkbox input { width: auto; }
	input[type="range"] { padding: 0; }
	.date { font-weight: 600; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }
	h2 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
	.chart-section { padding: 1.5rem 1rem 0; }
	.line-chart { width: 100%; height: 100px; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.5rem; }
	.metrics { padding: 1.5rem 1rem 0; }
	.metrics-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.metric-card { flex: 1; min-width: 80px; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; text-align: center; display: flex; flex-direction: column; gap: 0.15rem; }
	.metric-value { font-size: 1.4rem; font-weight: 700; }
	.metric-label { font-size: 0.75rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; }
	.analytics { padding: 1.5rem 1rem 0; }
	.hint { font-size: 0.8rem; color: var(--c-text-muted); margin-bottom: 0.75rem; }
	.corr-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
	.corr-card { background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); padding: 0.75rem; }
	.corr-label { font-size: 0.75rem; font-weight: 600; color: var(--c-text-muted); text-transform: uppercase; display: block; margin-bottom: 0.5rem; }
	.corr-bars { display: flex; flex-direction: column; gap: 0.4rem; }
	.corr-row { display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; }
	.corr-row span:first-child { width: 3rem; font-size: 0.75rem; color: var(--c-text-muted); }
	.corr-row span:last-child { width: 2rem; text-align: right; font-weight: 600; }
	.bar-bg { flex: 1; height: 10px; background: var(--c-border); border-radius: 5px; overflow: hidden; }
	.bar-fill { height: 100%; border-radius: 5px; transition: width 0.3s; }
	.week-chart { display: flex; align-items: flex-end; gap: 0.25rem; height: 120px; padding: 0.5rem; background: var(--c-bg-card); border: 1px solid var(--c-border); border-radius: var(--radius); }
	.week-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; }
	.week-val { font-size: 0.65rem; color: var(--c-text-muted); margin-bottom: 2px; }
	.week-bar { width: 70%; border-radius: 3px 3px 0 0; min-height: 4px; transition: height 0.3s; }
	.week-label { font-size: 0.7rem; color: var(--c-text-muted); margin-top: 4px; }
</style>
