<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import type { Entry } from '$lib/db';

	const store = useEntries('symptom');

	/* --- Regions & symptoms map --- */
	const regions = ['Head', 'Eyes', 'Ears', 'Throat', 'Chest', 'Stomach', 'Back', 'Left arm', 'Right arm', 'Left leg', 'Right leg', 'Joints', 'Skin', 'General'] as const;

	const symptomsByRegion: Record<string, string[]> = {
		'Head': ['headache', 'migraine', 'brain fog', 'dizziness', 'pressure'],
		'Eyes': ['blurry vision', 'dry eyes', 'light sensitivity'],
		'Ears': ['tinnitus', 'pain', 'pressure'],
		'Throat': ['sore throat', 'difficulty swallowing', 'hoarseness'],
		'Chest': ['tightness', 'palpitations', 'shortness of breath'],
		'Stomach': ['nausea', 'bloating', 'cramps', 'acid reflux', 'diarrhea', 'constipation'],
		'Back': ['lower back pain', 'upper back pain', 'stiffness'],
		'Left arm': ['numbness', 'tingling', 'weakness', 'cramping'],
		'Right arm': ['numbness', 'tingling', 'weakness', 'cramping'],
		'Left leg': ['numbness', 'tingling', 'weakness', 'cramping'],
		'Right leg': ['numbness', 'tingling', 'weakness', 'cramping'],
		'Joints': ['stiffness', 'swelling', 'cracking', 'pain'],
		'Skin': ['rash', 'itching', 'dryness', 'redness'],
		'General': ['fatigue', 'fever', 'chills', 'sweating', 'anxiety', 'insomnia', 'malaise']
	};

	const durationOptions = [
		{ value: '< 1 hour', label: '< 1 hour' },
		{ value: '1-3 hours', label: '1-3 hours' },
		{ value: 'half day', label: 'Half day' },
		{ value: 'full day', label: 'Full day' },
		{ value: 'ongoing', label: 'Ongoing' }
	];

	/* --- Form state --- */
	let date = $state(new Date().toISOString().slice(0, 10));
	let region = $state('');
	let symptom = $state('');
	let severity = $state(5);
	let duration = $state('< 1 hour');
	let triggersInput = $state('');
	let notes = $state('');

	/* --- Derived data --- */
	const todayStr = $derived(new Date().toISOString().slice(0, 10));

	const filteredSymptoms = $derived(region ? (symptomsByRegion[region] ?? []) : []);

	const ongoingToday = $derived(
		store.items.filter((e) => {
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			return d === todayStr && e.data.duration === 'ongoing';
		})
	);

	/* --- Frequency analysis (last 30 days) --- */
	const thirtyDaysAgo = $derived.by(() => {
		const d = new Date();
		d.setDate(d.getDate() - 30);
		return d.toISOString().slice(0, 10);
	});

	const recentEntries = $derived(
		store.items.filter((e) => {
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			return d >= thirtyDaysAgo;
		})
	);

	const topSymptoms = $derived.by(() => {
		const map = new Map<string, { count: number; totalSev: number }>();
		for (const e of recentEntries) {
			const s = (e.data.symptom as string ?? '').toLowerCase();
			if (!s) continue;
			const cur = map.get(s) ?? { count: 0, totalSev: 0 };
			cur.count++;
			cur.totalSev += (e.data.severity as number) ?? 0;
			map.set(s, cur);
		}
		return [...map.entries()]
			.sort((a, b) => b[1].count - a[1].count)
			.slice(0, 10)
			.map(([name, { count, totalSev }]) => ({ name, count, avgSev: Math.round((totalSev / count) * 10) / 10 }));
	});

	const dayOfWeekFrequency = $derived.by(() => {
		const days = [0, 0, 0, 0, 0, 0, 0]; // Mon-Sun
		const labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
		for (const e of recentEntries) {
			const d = new Date((e.data.date as string) ?? e.createdAt.slice(0, 10));
			const dow = d.getDay(); // 0=Sun
			const idx = dow === 0 ? 6 : dow - 1; // convert to Mon=0
			days[idx]++;
		}
		const max = Math.max(...days, 1);
		return labels.map((label, i) => ({ label, count: days[i], pct: Math.round((days[i] / max) * 100) }));
	});

	const triggerFrequency = $derived.by(() => {
		const counts = new Map<string, number>();
		for (const e of store.items) {
			const triggers = e.data.triggers;
			if (Array.isArray(triggers)) {
				for (const t of triggers) {
					const key = (t as string).toLowerCase().trim();
					if (key) counts.set(key, (counts.get(key) ?? 0) + 1);
				}
			}
		}
		return [...counts.entries()]
			.sort((a, b) => b[1] - a[1])
			.map(([trigger, count]) => ({ trigger, count }));
	});

	/* --- Actions --- */
	function selectRegion(r: string) {
		region = r;
	}

	function selectSymptom(s: string) {
		symptom = s;
	}

	function submit() {
		if (!symptom.trim()) return;
		const triggers = triggersInput.trim()
			? triggersInput.split(',').map((t) => t.trim()).filter(Boolean)
			: [];
		entries.add('symptom', {
			date,
			region: region || 'General',
			symptom: symptom.trim(),
			severity,
			duration,
			triggers,
			notes
		});
		date = new Date().toISOString().slice(0, 10);
		region = '';
		symptom = '';
		severity = 5;
		duration = '< 1 hour';
		triggersInput = '';
		notes = '';
		toast.show('Symptom logged');
	}

	function resolveOngoing(entry: Entry) {
		const created = new Date(entry.createdAt);
		const now = new Date();
		const diffMs = now.getTime() - created.getTime();
		const diffH = diffMs / (1000 * 60 * 60);
		let resolved: string;
		if (diffH < 1) resolved = '< 1 hour';
		else if (diffH < 3) resolved = '1-3 hours';
		else if (diffH < 12) resolved = 'half day';
		else resolved = 'full day';
		entries.update(entry.id, { ...entry.data, duration: resolved });
		toast.show('Marked as resolved');
	}

	function severityColor(sev: number): string {
		if (sev <= 3) return 'var(--c-done)';
		if (sev <= 6) return '#e6a817';
		return 'var(--c-cancel)';
	}
</script>

<svelte:head>
	<title>Symptoms | Darink</title>
</svelte:head>

<PageHeader title="Symptoms" />

<!-- Body region selector -->
<section class="section">
	<h2>Body Region</h2>
	<div class="chips">
		{#each regions as r}
			<button
				class="chip"
				class:selected={region === r}
				onclick={() => selectRegion(r)}
			>{r}</button>
		{/each}
	</div>
</section>

<!-- Common symptoms -->
{#if filteredSymptoms.length > 0}
	<section class="section">
		<h2>Common Symptoms</h2>
		<div class="chips">
			{#each filteredSymptoms as s}
				<button
					class="chip symptom-chip"
					class:selected={symptom === s}
					onclick={() => selectSymptom(s)}
				>{s}</button>
			{/each}
		</div>
	</section>
{/if}

<!-- Log form -->
<section class="form">
	<label>Date <input type="date" bind:value={date} /></label>
	<label>Region
		<input type="text" bind:value={region} placeholder="Select above or type..." />
	</label>
	<label>Symptom
		<input type="text" bind:value={symptom} placeholder="Select above or type custom..." />
	</label>
	<label>
		Severity ({severity}/10)
		<input type="range" min="1" max="10" bind:value={severity} class="severity-range" style="accent-color: {severityColor(severity)}" />
		<div class="severity-labels">
			<span>Mild</span>
			<span>Severe</span>
		</div>
	</label>
	<label>Duration
		<select bind:value={duration}>
			{#each durationOptions as opt}
				<option value={opt.value}>{opt.label}</option>
			{/each}
		</select>
	</label>
	<label>Possible triggers
		<input type="text" bind:value={triggersInput} placeholder="stress, food, weather... (comma-separated)" />
	</label>
	<label>Notes <textarea bind:value={notes} rows="2"></textarea></label>
	<button class="primary" onclick={submit}>Log symptom</button>
</section>

<!-- Active (ongoing) symptoms today -->
{#if ongoingToday.length > 0}
	<section class="section">
		<h2>Active Symptoms</h2>
		<div class="ongoing-list">
			{#each ongoingToday as entry}
				<div class="ongoing-card">
					<div class="ongoing-info">
						<span class="region-badge">{entry.data.region}</span>
						<strong>{entry.data.symptom}</strong>
						<span class="sev-dot" style="background: {severityColor(entry.data.severity as number)}">{entry.data.severity}</span>
					</div>
					<button class="resolve-btn" onclick={() => resolveOngoing(entry)}>
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
						Resolved
					</button>
				</div>
			{/each}
		</div>
	</section>
{/if}

<!-- Frequency analysis -->
{#if store.items.length > 0}
	<section class="section">
		<h2>Frequency Analysis (30 days)</h2>

		<!-- Most common symptoms -->
		{#if topSymptoms.length > 0}
			<h3>Most Common Symptoms</h3>
			<ol class="top-list">
				{#each topSymptoms as s, i}
					<li class="top-item">
						<span class="top-rank">{i + 1}.</span>
						<span class="top-name">{s.name}</span>
						<span class="top-stats">
							<span class="top-count">{s.count}x</span>
							<span class="top-sev" style="color: {severityColor(s.avgSev)}">avg {s.avgSev}</span>
						</span>
					</li>
				{/each}
			</ol>
		{/if}

		<!-- Day of week chart -->
		{#if recentEntries.length > 0}
			<h3>Symptoms by Day of Week</h3>
			<svg class="week-chart" viewBox="0 0 280 100" preserveAspectRatio="xMidYMid meet">
				{#each dayOfWeekFrequency as day, i}
					{@const barW = 280 / 7}
					{@const barH = day.pct * 0.75}
					<rect
						x={i * barW + barW * 0.15}
						y={80 - barH}
						width={barW * 0.7}
						height={barH}
						rx="2"
						fill={day.count > 0 ? 'var(--c-accent)' : 'var(--c-border)'}
					/>
					<text
						x={i * barW + barW / 2}
						y="94"
						text-anchor="middle"
						font-size="8"
						fill="var(--c-text-muted)"
					>{day.label}</text>
					{#if day.count > 0}
						<text
							x={i * barW + barW / 2}
							y={80 - barH - 3}
							text-anchor="middle"
							font-size="7"
							fill="var(--c-text)"
						>{day.count}</text>
					{/if}
				{/each}
			</svg>
		{/if}

		<!-- Trigger frequency -->
		{#if triggerFrequency.length > 0}
			<h3>Trigger Frequency</h3>
			<div class="trigger-cloud">
				{#each triggerFrequency as t}
					<span class="trigger-tag">
						{t.trigger}
						<span class="trigger-count">{t.count}</span>
					</span>
				{/each}
			</div>
		{/if}
	</section>
{/if}

<!-- Empty state -->
{#if store.items.length === 0}
	<div class="empty-state">
		<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
		<p>No symptoms tracked yet</p>
		<p class="empty-hint">Log your first symptom to start tracking patterns.</p>
	</div>
{/if}

<!-- Entry history -->
{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	{@const editTriggers = Array.isArray(data.triggers) ? (data.triggers as string[]).join(', ') : ''}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		const rawTriggers = (fd.get('triggers') as string).trim();
		const parsedTriggers = rawTriggers ? rawTriggers.split(',').map((t) => t.trim()).filter(Boolean) : [];
		entries.update(item.id, {
			date: fd.get('date') as string,
			region: (fd.get('region') as string).trim(),
			symptom: (fd.get('symptom') as string).trim(),
			severity: Number(fd.get('severity')),
			duration: fd.get('duration') as string,
			triggers: parsedTriggers,
			notes: (fd.get('notes') as string).trim()
		});
		toast.show('Updated');
		done();
	}}>
		<label>Date <input type="date" name="date" value={data.date as string ?? ''} /></label>
		<label>Region <input type="text" name="region" value={data.region as string ?? ''} /></label>
		<label>Symptom <input type="text" name="symptom" value={data.symptom as string ?? ''} /></label>
		<label>
			Severity
			<input type="range" name="severity" min="1" max="10" value={data.severity as number ?? 5} />
		</label>
		<label>Duration
			<select name="duration">
				{#each durationOptions as opt}
					<option value={opt.value} selected={data.duration === opt.value}>{opt.label}</option>
				{/each}
			</select>
		</label>
		<label>Triggers <input type="text" name="triggers" value={editTriggers} placeholder="comma-separated" /></label>
		<label>Notes <textarea name="notes" rows="2">{data.notes ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		{@const sev = item.data.severity as number ?? 0}
		<div class="entry-row">
			<span class="region-badge">{item.data.region}</span>
			<strong>{item.data.symptom}</strong>
			<span class="sev-badge" style="background: {severityColor(sev)}">{sev}</span>
			<span class="date">{(item.data.date as string) ?? item.createdAt.slice(0, 10)}</span>
		</div>
	{/snippet}
</EntryList>

<style>
	.section { padding: 0 1rem 1rem; }

	h2 {
		font-size: 0.9rem;
		font-weight: 600;
		margin-bottom: 0.5rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}

	h3 {
		font-size: 0.85rem;
		font-weight: 600;
		margin: 1rem 0 0.5rem;
		color: var(--c-text-muted);
	}

	/* Chips */
	.chips {
		display: flex;
		flex-wrap: wrap;
		gap: 0.35rem;
	}

	.chip {
		display: inline-block;
		padding: 0.35rem 0.75rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: 20px;
		font-size: 0.82rem;
		cursor: pointer;
		transition: background 0.15s, border-color 0.15s, color 0.15s;
	}

	.chip:hover {
		border-color: var(--c-accent);
		background: var(--c-accent-bg);
		color: var(--c-text);
		transform: none;
		box-shadow: none;
	}

	.chip.selected {
		background: var(--c-accent);
		color: #fff;
		border-color: var(--c-accent);
	}

	.symptom-chip {
		text-transform: capitalize;
	}

	/* Form */
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem 1rem; }

	.severity-range {
		width: 100%;
		cursor: pointer;
	}

	.severity-labels {
		display: flex;
		justify-content: space-between;
		font-size: 0.75rem;
		color: var(--c-text-muted);
		margin-top: -0.25rem;
	}

	/* Ongoing / Active symptoms */
	.ongoing-list {
		display: flex;
		flex-direction: column;
		gap: 0.4rem;
	}

	.ongoing-card {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 0.5rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-cancel);
		border-radius: var(--radius);
		padding: 0.6rem 0.75rem;
	}

	.ongoing-info {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		flex-wrap: wrap;
		min-width: 0;
	}

	.resolve-btn {
		display: flex;
		align-items: center;
		gap: 0.3rem;
		font-size: 0.8rem;
		padding: 0.3rem 0.6rem;
		white-space: nowrap;
		background: var(--c-accent-bg);
		border: 1px solid var(--c-done);
		color: var(--c-done);
		border-radius: var(--radius);
	}

	.resolve-btn:hover {
		background: var(--c-done);
		color: #fff;
	}

	/* Region badge */
	.region-badge {
		display: inline-block;
		padding: 0.15rem 0.5rem;
		background: var(--c-accent-bg);
		border: 1px solid var(--c-accent);
		border-radius: 12px;
		font-size: 0.75rem;
		color: var(--c-accent);
		white-space: nowrap;
		font-weight: 600;
	}

	/* Severity dot / badge */
	.sev-dot {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 1.4rem;
		height: 1.4rem;
		border-radius: 50%;
		color: #fff;
		font-size: 0.7rem;
		font-weight: 700;
		flex-shrink: 0;
	}

	.sev-badge {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 1.4rem;
		height: 1.4rem;
		border-radius: 10px;
		padding: 0 0.3rem;
		color: #fff;
		font-size: 0.7rem;
		font-weight: 700;
		flex-shrink: 0;
	}

	/* Top symptoms list */
	.top-list {
		list-style: none;
		margin: 0;
		padding: 0;
		display: flex;
		flex-direction: column;
		gap: 0.3rem;
	}

	.top-item {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.4rem 0.6rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		font-size: 0.9rem;
	}

	.top-rank { color: var(--c-text-muted); font-weight: 600; width: 1.5rem; }
	.top-name { flex: 1; text-transform: capitalize; }
	.top-stats { display: flex; gap: 0.75rem; align-items: center; }
	.top-count { color: var(--c-accent); font-weight: 600; }
	.top-sev { font-size: 0.8rem; font-weight: 500; }

	/* Week chart */
	.week-chart {
		width: 100%;
		height: 110px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}

	/* Trigger cloud */
	.trigger-cloud {
		display: flex;
		flex-wrap: wrap;
		gap: 0.35rem;
	}

	.trigger-tag {
		display: inline-flex;
		align-items: center;
		gap: 0.3rem;
		padding: 0.3rem 0.65rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: 16px;
		font-size: 0.82rem;
		text-transform: capitalize;
	}

	.trigger-count {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 1.1rem;
		height: 1.1rem;
		border-radius: 50%;
		background: var(--c-accent);
		color: #fff;
		font-size: 0.65rem;
		font-weight: 700;
	}

	/* Entry row */
	.entry-row {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		flex-wrap: wrap;
	}

	.date { font-size: 0.8rem; color: var(--c-text-muted); margin-left: auto; }

	/* Edit inline */
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }
</style>
