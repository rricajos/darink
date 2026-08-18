<script lang="ts">
	import { onMount } from 'svelte';
	import PageHeader from '$lib/components/PageHeader.svelte';
	import EntryList from '$lib/components/EntryList.svelte';
	import { useEntries, entries } from '$lib/stores/entries.svelte';
	import { toast } from '$lib/stores/toast.svelte';
	import { ui } from '$lib/db';
	import type { Entry } from '$lib/db';

	const store = useEntries('medication');

	/* --- Regimen state --- */
	interface MedItem {
		name: string;
		dose: string;
		frequency: 'daily' | '2x_daily' | '3x_daily' | 'weekly' | 'as_needed';
		prescriber: string;
		refillDate: string;
	}
	let regimen: MedItem[] = $state([]);
	let manageOpen = $state(false);
	let medName = $state('');
	let medDose = $state('');
	let medFrequency = $state<MedItem['frequency']>('daily');
	let medPrescriber = $state('');
	let medRefillDate = $state('');

	onMount(() => {
		const saved = ui.get();
		if (Array.isArray(saved.medicationRegimen)) {
			regimen = saved.medicationRegimen as MedItem[];
		}
	});

	function addMed() {
		if (!medName.trim()) return;
		const item: MedItem = {
			name: medName.trim(),
			dose: medDose.trim(),
			frequency: medFrequency,
			prescriber: medPrescriber.trim(),
			refillDate: medRefillDate
		};
		regimen = [...regimen, item];
		ui.patch({ medicationRegimen: regimen });
		medName = ''; medDose = ''; medFrequency = 'daily'; medPrescriber = ''; medRefillDate = '';
		toast.show('Medication added');
	}

	function removeMed(index: number) {
		regimen = regimen.filter((_, i) => i !== index);
		ui.patch({ medicationRegimen: regimen });
		toast.show('Medication removed');
	}

	/* --- Frequency helpers --- */
	const freqLabels: Record<string, string> = {
		daily: 'Daily',
		'2x_daily': '2x daily',
		'3x_daily': '3x daily',
		weekly: 'Weekly',
		as_needed: 'As needed'
	};

	function doseSlotsForFreq(freq: string): string[] {
		if (freq === 'daily') return ['morning'];
		if (freq === '2x_daily') return ['morning', 'evening'];
		if (freq === '3x_daily') return ['morning', 'noon', 'evening'];
		if (freq === 'weekly') return ['morning'];
		return [];
	}

	const timeLabels: Record<string, string> = {
		morning: 'Morning',
		noon: 'Noon',
		evening: 'Evening',
		night: 'Night'
	};

	/* --- Today's state --- */
	const todayStr = $derived(new Date().toISOString().slice(0, 10));

	const todayEntries = $derived(
		store.items.filter((e) => {
			const d = (e.data.date as string) ?? e.createdAt.slice(0, 10);
			return d === todayStr;
		})
	);

	const todayDow = $derived(new Date().getDay());

	const scheduledMeds = $derived(
		regimen.filter((m) => m.frequency !== 'as_needed')
	);

	const doseChecklist = $derived.by(() => {
		return scheduledMeds.map((med) => {
			const slots = doseSlotsForFreq(med.frequency);
			const checks = slots.map((slot) => {
				const taken = todayEntries.some(
					(e) => (e.data.name as string)?.toLowerCase() === med.name.toLowerCase()
						&& (e.data.time as string) === slot
				);
				return { slot, taken };
			});
			return { med, checks };
		});
	});

	function logDose(med: MedItem, slot: string) {
		entries.add('medication', {
			date: todayStr,
			name: med.name,
			dose: med.dose,
			time: slot,
			sideEffects: [],
			severity: 0,
			notes: ''
		});
		toast.show(`${med.name} (${timeLabels[slot] ?? slot}) logged`);
	}

	/* --- Weekly adherence chart --- */
	const weeklyAdherence = $derived.by(() => {
		if (scheduledMeds.length === 0) return [];
		const days: { label: string; pct: number }[] = [];
		for (let i = 6; i >= 0; i--) {
			const d = new Date();
			d.setDate(d.getDate() - i);
			const key = d.toISOString().slice(0, 10);
			const weekday = d.toLocaleDateString('en', { weekday: 'short' });
			const dayEntries = store.items.filter((e) => {
				const ed = (e.data.date as string) ?? e.createdAt.slice(0, 10);
				return ed === key;
			});
			let totalSlots = 0;
			let takenSlots = 0;
			for (const med of scheduledMeds) {
				const slots = doseSlotsForFreq(med.frequency);
				totalSlots += slots.length;
				for (const slot of slots) {
					const hit = dayEntries.some(
						(e) => (e.data.name as string)?.toLowerCase() === med.name.toLowerCase()
							&& (e.data.time as string) === slot
					);
					if (hit) takenSlots++;
				}
			}
			days.push({ label: weekday, pct: totalSlots > 0 ? Math.round((takenSlots / totalSlots) * 100) : 0 });
		}
		return days;
	});

	/* --- Side effect frequency --- */
	const sideEffectCounts = $derived.by(() => {
		const counts = new Map<string, number>();
		for (const e of store.items) {
			const effects = e.data.sideEffects;
			if (Array.isArray(effects)) {
				for (const se of effects) {
					const s = (se as string).toLowerCase();
					if (s) counts.set(s, (counts.get(s) ?? 0) + 1);
				}
			}
		}
		return [...counts.entries()]
			.sort((a, b) => b[1] - a[1])
			.map(([name, count]) => ({ name, count }));
	});

	/* --- Refill alerts --- */
	const refillAlerts = $derived.by(() => {
		const today = new Date();
		today.setHours(0, 0, 0, 0);
		return regimen
			.filter((m) => m.refillDate)
			.map((m) => {
				const refill = new Date(m.refillDate + 'T00:00:00');
				const diff = Math.ceil((refill.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
				return { name: m.name, refillDate: m.refillDate, daysLeft: diff };
			})
			.filter((a) => a.daysLeft <= 7)
			.sort((a, b) => a.daysLeft - b.daysLeft);
	});

	/* --- Log form state --- */
	let date = $state(new Date().toISOString().slice(0, 10));
	let logName = $state('');
	let logNameFree = $state('');
	let logDoseVal = $state('');
	let logTime = $state('morning');
	let logSeverity = $state(0);
	let logNotes = $state('');

	const allSideEffects = ['nausea', 'headache', 'dizziness', 'fatigue', 'insomnia', 'appetite change', 'other'];
	let selectedEffects: string[] = $state([]);

	function toggleEffect(effect: string) {
		if (selectedEffects.includes(effect)) {
			selectedEffects = selectedEffects.filter((e) => e !== effect);
		} else {
			selectedEffects = [...selectedEffects, effect];
		}
	}

	const effectiveName = $derived(logName === '__free__' ? logNameFree.trim() : logName);

	function submitLog() {
		if (!effectiveName) return;
		entries.add('medication', {
			date,
			name: effectiveName,
			dose: logDoseVal,
			time: logTime,
			sideEffects: [...selectedEffects],
			severity: logSeverity,
			notes: logNotes
		});
		date = new Date().toISOString().slice(0, 10);
		logName = ''; logNameFree = ''; logDoseVal = ''; logTime = 'morning';
		logSeverity = 0; logNotes = ''; selectedEffects = [];
		toast.show('Medication logged');
	}
</script>

<svelte:head>
	<title>Medications | Darink</title>
</svelte:head>

<PageHeader title="Medications" />

<!-- Refill Alerts -->
{#if refillAlerts.length > 0}
<section class="refill-section">
	<h2>Refill Alerts</h2>
	{#each refillAlerts as alert}
		<div class="refill-card" class:danger={alert.daysLeft <= 0} class:warning={alert.daysLeft > 0 && alert.daysLeft <= 7}>
			<div class="refill-icon">
				{#if alert.daysLeft <= 0}
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
				{:else}
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
				{/if}
			</div>
			<div class="refill-info">
				<strong>{alert.name}</strong>
				{#if alert.daysLeft <= 0}
					<span class="refill-text danger-text">Overdue by {Math.abs(alert.daysLeft)} day{Math.abs(alert.daysLeft) !== 1 ? 's' : ''}</span>
				{:else}
					<span class="refill-text warning-text">{alert.daysLeft} day{alert.daysLeft !== 1 ? 's' : ''} until refill</span>
				{/if}
			</div>
		</div>
	{/each}
</section>
{/if}

<!-- Today's Dose Checklist -->
{#if scheduledMeds.length > 0}
<section class="checklist-section">
	<h2>Today's Doses</h2>
	{#each doseChecklist as { med, checks }}
		<div class="dose-card">
			<div class="dose-header">
				<strong>{med.name}</strong>
				{#if med.dose}
					<span class="meta">{med.dose}</span>
				{/if}
			</div>
			<div class="dose-slots">
				{#each checks as { slot, taken }}
					<button
						class="dose-slot"
						class:done={taken}
						disabled={taken}
						onclick={() => logDose(med, slot)}
					>
						{#if taken}
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
						{:else}
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>
						{/if}
						<span>{timeLabels[slot] ?? slot}</span>
					</button>
				{/each}
			</div>
		</div>
	{/each}
</section>
{/if}

<!-- Weekly Adherence Chart -->
{#if weeklyAdherence.length > 0}
<section class="chart-section">
	<h2>Weekly Adherence</h2>
	<svg class="week-chart" viewBox="0 0 280 100" preserveAspectRatio="xMidYMid meet">
		{#each weeklyAdherence as day, i}
			{@const barW = 280 / 7}
			{@const barH = day.pct}
			<rect
				x={i * barW + barW * 0.15}
				y={100 - barH}
				width={barW * 0.7}
				height={barH}
				rx="2"
				fill={day.pct >= 80 ? 'var(--c-done)' : day.pct >= 50 ? '#e6a817' : 'var(--c-cancel)'}
			/>
			<text
				x={i * barW + barW / 2}
				y="98"
				text-anchor="middle"
				font-size="8"
				fill="var(--c-text-muted)"
			>{day.label}</text>
			{#if day.pct > 0}
				<text
					x={i * barW + barW / 2}
					y={100 - barH - 3}
					text-anchor="middle"
					font-size="7"
					fill="var(--c-text)"
				>{day.pct}%</text>
			{/if}
		{/each}
	</svg>
</section>
{/if}

<!-- Side Effect Frequency -->
{#if sideEffectCounts.length > 0}
<section class="effects-section">
	<h2>Side Effect Frequency</h2>
	<ol class="effects-list">
		{#each sideEffectCounts as se, i}
			<li class="effects-item">
				<span class="effects-rank">{i + 1}.</span>
				<span class="effects-name">{se.name}</span>
				<span class="effects-count">{se.count}x</span>
			</li>
		{/each}
	</ol>
</section>
{/if}

<!-- Manage Medications -->
<section class="manage-section">
	<button class="manage-toggle" onclick={() => manageOpen = !manageOpen}>
		<span>Manage medications</span>
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class:rotate={manageOpen}><path d="m6 9 6 6 6-6"/></svg>
	</button>
	{#if manageOpen}
		<div class="manage-body">
			<div class="manage-add">
				<label>Name <input type="text" bind:value={medName} placeholder="e.g. Metformin" /></label>
				<label>Dose <input type="text" bind:value={medDose} placeholder="e.g. 500mg" /></label>
				<label>
					Frequency
					<select bind:value={medFrequency}>
						<option value="daily">Daily</option>
						<option value="2x_daily">2x daily</option>
						<option value="3x_daily">3x daily</option>
						<option value="weekly">Weekly</option>
						<option value="as_needed">As needed</option>
					</select>
				</label>
				<label>Prescriber <input type="text" bind:value={medPrescriber} placeholder="Dr. Smith" /></label>
				<label>Refill date <input type="date" bind:value={medRefillDate} /></label>
				<button class="primary" onclick={addMed}>Add medication</button>
			</div>
			{#if regimen.length > 0}
				<ul class="med-type-list">
					{#each regimen as med, i}
						<li class="med-type-item">
							<div class="med-type-info">
								<strong>{med.name}</strong>
								{#if med.dose}
									<span class="meta">{med.dose}</span>
								{/if}
								<span class="meta">{freqLabels[med.frequency] ?? med.frequency}</span>
								{#if med.prescriber}
									<span class="meta">({med.prescriber})</span>
								{/if}
							</div>
							<button class="remove-btn" onclick={() => removeMed(i)} title="Remove">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
							</button>
						</li>
					{/each}
				</ul>
			{/if}
		</div>
	{/if}
</section>

<!-- Empty State -->
{#if regimen.length === 0 && store.items.length === 0}
<div class="empty-state">
	<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>
	<p>No medications tracked yet</p>
	<p class="empty-hint">Add your medications above and start tracking doses and side effects.</p>
</div>
{/if}

<!-- Log Form -->
<section class="form">
	<h2>Log Medication</h2>
	<label>Date <input type="date" bind:value={date} /></label>
	<label>
		Medication
		<select bind:value={logName}>
			<option value="">-- Select --</option>
			{#each regimen as m}
				<option value={m.name}>{m.name}</option>
			{/each}
			<option value="__free__">Other (type below)</option>
		</select>
	</label>
	{#if logName === '__free__'}
		<label>Name <input type="text" bind:value={logNameFree} placeholder="Medication name" /></label>
	{/if}
	<div class="row">
		<label>Dose <input type="text" bind:value={logDoseVal} placeholder="500mg, 1 tab..." /></label>
		<label>
			Time of day
			<select bind:value={logTime}>
				<option value="morning">Morning</option>
				<option value="noon">Noon</option>
				<option value="evening">Evening</option>
				<option value="night">Night</option>
			</select>
		</label>
	</div>
	<div class="field">
		<span class="field-label">Side effects</span>
		<div class="chips">
			{#each allSideEffects as effect}
				<button
					class="chip"
					class:active={selectedEffects.includes(effect)}
					onclick={() => toggleEffect(effect)}
					type="button"
				>{effect}</button>
			{/each}
		</div>
	</div>
	<label>
		Severity ({logSeverity}/5)
		<input type="range" min="0" max="5" step="1" bind:value={logSeverity} />
	</label>
	<label>Notes <textarea bind:value={logNotes} rows="2"></textarea></label>
	<button class="primary" onclick={submitLog}>Log medication</button>
</section>

<!-- Entry History -->
{#snippet editForm(item: Entry, done: () => void)}
	{@const data = item.data}
	<form class="edit-inline" onsubmit={(e) => {
		e.preventDefault();
		const fd = new FormData(e.currentTarget);
		const effectEls = e.currentTarget.querySelectorAll<HTMLInputElement>('input[name="se"]:checked');
		const effects: string[] = [];
		effectEls.forEach((el) => effects.push(el.value));
		entries.update(item.id, {
			date: fd.get('date') as string,
			name: (fd.get('name') as string).trim(),
			dose: fd.get('dose') as string,
			time: fd.get('time') as string,
			sideEffects: effects,
			severity: Number(fd.get('severity')),
			notes: (fd.get('notes') as string).trim()
		});
		toast.show('Updated');
		done();
	}}>
		<label>Date <input type="date" name="date" value={data.date as string ?? ''} /></label>
		<label>Name <input type="text" name="name" value={data.name} /></label>
		<div class="row">
			<label>Dose <input type="text" name="dose" value={data.dose} /></label>
			<label>
				Time
				<select name="time">
					<option value="morning" selected={data.time === 'morning'}>Morning</option>
					<option value="noon" selected={data.time === 'noon'}>Noon</option>
					<option value="evening" selected={data.time === 'evening'}>Evening</option>
					<option value="night" selected={data.time === 'night'}>Night</option>
				</select>
			</label>
		</div>
		<fieldset class="edit-effects">
			<legend>Side effects</legend>
			{#each allSideEffects as effect}
				<label class="edit-effect-label">
					<input type="checkbox" name="se" value={effect} checked={Array.isArray(data.sideEffects) && (data.sideEffects as string[]).includes(effect)} />
					{effect}
				</label>
			{/each}
		</fieldset>
		<label>Severity <input type="range" name="severity" min="0" max="5" step="1" value={data.severity ?? 0} /></label>
		<label>Notes <textarea name="notes" rows="2">{data.notes ?? ''}</textarea></label>
		<div class="edit-actions">
			<button type="submit">Save</button>
			<button type="button" onclick={done}>Cancel</button>
		</div>
	</form>
{/snippet}

<EntryList items={store.items} {editForm}>
	{#snippet row(item)}
		<div class="entry-row">
			<strong>{item.data.name}</strong>
			<span class="meta">{item.data.dose} · {timeLabels[(item.data.time as string)] ?? item.data.time}</span>
			{#if Array.isArray(item.data.sideEffects) && (item.data.sideEffects as string[]).length > 0}
				<div class="entry-chips">
					{#each item.data.sideEffects as se}
						<span class="se-chip">{se}</span>
					{/each}
				</div>
			{/if}
		</div>
	{/snippet}
</EntryList>

<style>
	.form { display: flex; flex-direction: column; gap: 1rem; padding: 0 1rem; }
	.row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
	.row label { flex: 1; min-width: 120px; }
	.meta { font-size: 0.85rem; color: var(--c-text-muted); }
	.edit-inline { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
	.edit-actions { display: flex; gap: 0.5rem; }
	.edit-actions button { flex: 1; }

	h2 {
		font-size: 0.9rem;
		font-weight: 600;
		margin-bottom: 0.5rem;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}

	/* Manage Medications */
	.manage-section { padding: 0 1rem; margin-top: 0.5rem; }
	.manage-toggle {
		display: flex;
		align-items: center;
		justify-content: space-between;
		width: 100%;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.6rem 0.75rem;
		font-size: 0.85rem;
		font-weight: 600;
		color: var(--c-text-muted);
		cursor: pointer;
	}
	.manage-toggle svg { transition: transform 0.2s; }
	.manage-toggle svg.rotate { transform: rotate(180deg); }
	.manage-body {
		border: 1px solid var(--c-border);
		border-top: none;
		border-radius: 0 0 var(--radius) var(--radius);
		padding: 0.75rem;
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}
	.manage-add {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}
	.med-type-list {
		list-style: none;
		margin: 0;
		padding: 0;
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
	}
	.med-type-item {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0.4rem 0.5rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		font-size: 0.85rem;
	}
	.med-type-info { display: flex; align-items: baseline; gap: 0.5rem; flex-wrap: wrap; }
	.med-type-info strong { font-size: 0.9rem; }
	.remove-btn {
		display: flex;
		align-items: center;
		justify-content: center;
		background: none;
		border: none;
		color: var(--c-text-muted);
		cursor: pointer;
		padding: 0.15rem;
		border-radius: 4px;
	}
	.remove-btn:hover { color: var(--c-cancel); background: var(--c-accent-bg); }

	/* Today's Dose Checklist */
	.checklist-section { padding: 0 1rem 1rem; }
	.dose-card {
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.6rem 0.75rem;
		margin-bottom: 0.4rem;
	}
	.dose-header {
		display: flex;
		align-items: baseline;
		gap: 0.5rem;
		margin-bottom: 0.4rem;
	}
	.dose-header strong { font-size: 0.9rem; }
	.dose-slots {
		display: flex;
		gap: 0.4rem;
		flex-wrap: wrap;
	}
	.dose-slot {
		display: flex;
		align-items: center;
		gap: 0.3rem;
		padding: 0.3rem 0.6rem;
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		background: var(--c-bg);
		font-size: 0.8rem;
		cursor: pointer;
		color: var(--c-text);
	}
	.dose-slot:hover:not(:disabled) {
		border-color: var(--c-accent);
		background: var(--c-accent-bg);
	}
	.dose-slot.done {
		border-color: var(--c-done);
		background: color-mix(in srgb, var(--c-done) 8%, transparent);
		color: var(--c-done);
		cursor: default;
	}
	.dose-slot:disabled { opacity: 0.8; }

	/* Weekly Chart */
	.chart-section { padding: 0 1rem 1.5rem; }
	.week-chart {
		width: 100%;
		height: 110px;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
	}

	/* Side Effect Frequency */
	.effects-section { padding: 0 1rem 1rem; }
	.effects-list {
		list-style: none;
		margin: 0;
		padding: 0;
		display: flex;
		flex-direction: column;
		gap: 0.3rem;
	}
	.effects-item {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.4rem 0.6rem;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		font-size: 0.9rem;
	}
	.effects-rank { color: var(--c-text-muted); font-weight: 600; width: 1.5rem; }
	.effects-name { flex: 1; text-transform: capitalize; }
	.effects-count { color: var(--c-accent); font-weight: 600; }

	/* Refill Alerts */
	.refill-section { padding: 0 1rem 1rem; }
	.refill-card {
		display: flex;
		align-items: center;
		gap: 0.6rem;
		padding: 0.6rem 0.75rem;
		border-radius: var(--radius);
		margin-bottom: 0.4rem;
	}
	.refill-card.warning {
		background: color-mix(in srgb, #e6a817 12%, transparent);
		border: 1px solid #e6a817;
	}
	.refill-card.danger {
		background: color-mix(in srgb, var(--c-cancel) 12%, transparent);
		border: 1px solid var(--c-cancel);
	}
	.refill-icon { display: flex; align-items: center; flex-shrink: 0; }
	.refill-card.warning .refill-icon { color: #e6a817; }
	.refill-card.danger .refill-icon { color: var(--c-cancel); }
	.refill-info { flex: 1; }
	.refill-info strong { display: block; font-size: 0.9rem; }
	.refill-text { font-size: 0.8rem; }
	.warning-text { color: #b8860b; }
	.danger-text { color: var(--c-cancel); }

	/* Chips / Side effects */
	.field { display: flex; flex-direction: column; gap: 0.4rem; }
	.field-label { font-size: 0.85rem; font-weight: 500; }
	.chips { display: flex; flex-wrap: wrap; gap: 0.3rem; }
	.chip {
		padding: 0.3rem 0.65rem;
		border: 1px solid var(--c-border);
		border-radius: 20px;
		font-size: 0.8rem;
		background: var(--c-bg-card);
		cursor: pointer;
		color: var(--c-text);
	}
	.chip:hover { border-color: var(--c-accent); }
	.chip.active {
		background: var(--c-accent-bg);
		border-color: var(--c-accent);
		color: var(--c-accent);
		font-weight: 600;
	}

	/* Entry row chips */
	.entry-row { display: flex; flex-direction: column; gap: 0.2rem; }
	.entry-chips { display: flex; flex-wrap: wrap; gap: 0.2rem; margin-top: 0.15rem; }
	.se-chip {
		display: inline-block;
		padding: 0.1rem 0.4rem;
		background: color-mix(in srgb, var(--c-cancel) 10%, transparent);
		border: 1px solid color-mix(in srgb, var(--c-cancel) 30%, transparent);
		border-radius: 10px;
		font-size: 0.7rem;
		color: var(--c-cancel);
		text-transform: capitalize;
	}

	/* Edit effects */
	.edit-effects {
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.5rem;
		display: flex;
		flex-wrap: wrap;
		gap: 0.5rem;
	}
	.edit-effects legend {
		font-size: 0.85rem;
		font-weight: 500;
		color: var(--c-text-muted);
		padding: 0 0.25rem;
	}
	.edit-effect-label {
		display: flex;
		align-items: center;
		gap: 0.25rem;
		font-size: 0.8rem;
		text-transform: capitalize;
	}

	.empty-hint {
		font-size: 0.85rem;
		color: var(--c-text-muted);
		margin-bottom: 0.75rem;
	}
</style>
