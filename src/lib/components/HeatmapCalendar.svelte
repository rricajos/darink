<script lang="ts">
	import type { Entry } from '$lib/db';

	let { items, typeFilter = null }: { items: Entry[]; typeFilter?: string | null } = $props();

	const CELL = 11;
	const GAP = 2;
	const STEP = CELL + GAP;
	const WEEKS = 53;
	const DAYS = 7;
	const LABEL_LEFT = 28;
	const LABEL_TOP = 16;
	const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	const DAY_LABELS: [number, string][] = [[1, 'Mon'], [3, 'Wed'], [5, 'Fri']];

	let hoveredCell = $state<{ x: number; y: number; date: string; count: number } | null>(null);

	const gridData = $derived.by(() => {
		const filtered = typeFilter ? items.filter((e) => e.type === typeFilter || e.type.startsWith(typeFilter + '.')) : items;

		// Count entries per day
		const counts = new Map<string, number>();
		for (const e of filtered) {
			const day = e.createdAt.slice(0, 10);
			counts.set(day, (counts.get(day) ?? 0) + 1);
		}

		// Build 53x7 grid going back from today
		const today = new Date();
		const todayDow = today.getDay(); // 0=Sun
		// Adjust so Monday=0: (dow + 6) % 7
		const todayIdx = (todayDow + 6) % 7;

		// The last column ends at today's row
		// Total days to go back: (WEEKS - 1) * 7 + todayIdx
		const totalDays = (WEEKS - 1) * 7 + todayIdx;

		const cells: { col: number; row: number; date: string; count: number }[] = [];
		const monthMarkers: { col: number; label: string }[] = [];
		const seenMonths = new Set<string>();

		for (let i = 0; i <= totalDays; i++) {
			const d = new Date(today);
			d.setDate(d.getDate() - (totalDays - i));
			const iso = d.toISOString().slice(0, 10);
			const dow = d.getDay();
			const row = (dow + 6) % 7; // Mon=0
			const col = Math.floor(i / 7);
			const count = counts.get(iso) ?? 0;
			cells.push({ col, row, date: iso, count });

			// Track month boundaries
			const monthKey = `${d.getFullYear()}-${d.getMonth()}`;
			if (!seenMonths.has(monthKey) && d.getDate() <= 7) {
				seenMonths.add(monthKey);
				monthMarkers.push({ col, label: MONTH_LABELS[d.getMonth()] });
			}
		}

		return { cells, monthMarkers };
	});

	const svgWidth = $derived(LABEL_LEFT + WEEKS * STEP);
	const svgHeight = $derived(LABEL_TOP + DAYS * STEP + 4);

	function getColor(count: number): string {
		if (count === 0) return 'var(--c-border)';
		if (count === 1) return 'var(--heatmap-l1)';
		if (count <= 3) return 'var(--heatmap-l2)';
		return 'var(--heatmap-l3)';
	}

	function handleMouseEnter(cell: { col: number; row: number; date: string; count: number }) {
		hoveredCell = {
			x: LABEL_LEFT + cell.col * STEP + CELL / 2,
			y: LABEL_TOP + cell.row * STEP,
			date: cell.date,
			count: cell.count
		};
	}

	function handleMouseLeave() {
		hoveredCell = null;
	}

	function formatDate(iso: string): string {
		const d = new Date(iso + 'T12:00:00');
		return d.toLocaleDateString('en', { month: 'short', day: 'numeric', year: 'numeric' });
	}
</script>

<div class="heatmap-container">
	<svg
		class="heatmap-svg"
		viewBox="0 0 {svgWidth} {svgHeight}"
		role="img"
		aria-label="Activity heatmap for the past year"
	>
		<!-- Month labels -->
		{#each gridData.monthMarkers as marker}
			<text
				x={LABEL_LEFT + marker.col * STEP}
				y={10}
				font-size="9"
				fill="var(--c-text-muted)"
			>{marker.label}</text>
		{/each}

		<!-- Day labels -->
		{#each DAY_LABELS as [row, label]}
			<text
				x={0}
				y={LABEL_TOP + row * STEP + CELL - 1}
				font-size="9"
				fill="var(--c-text-muted)"
			>{label}</text>
		{/each}

		<!-- Cells -->
		{#each gridData.cells as cell}
			<rect
				x={LABEL_LEFT + cell.col * STEP}
				y={LABEL_TOP + cell.row * STEP}
				width={CELL}
				height={CELL}
				rx="2"
				ry="2"
				fill={getColor(cell.count)}
				class="heatmap-cell"
				role="gridcell"
				tabindex="-1"
				aria-label="{cell.date}: {cell.count} entries"
				onmouseenter={() => handleMouseEnter(cell)}
				onmouseleave={handleMouseLeave}
				onfocus={() => handleMouseEnter(cell)}
				onblur={handleMouseLeave}
			/>
		{/each}
	</svg>

	{#if hoveredCell}
		<div
			class="heatmap-tooltip"
			style="left: {hoveredCell.x}px; top: {hoveredCell.y - 8}px;"
		>
			<strong>{hoveredCell.count} {hoveredCell.count === 1 ? 'entry' : 'entries'}</strong>
			<span>{formatDate(hoveredCell.date)}</span>
		</div>
	{/if}
</div>

<style>
	.heatmap-container {
		position: relative;
		overflow-x: auto;
		-webkit-overflow-scrolling: touch;
	}

	.heatmap-svg {
		display: block;
		width: 100%;
		min-width: 680px;
		height: auto;
	}

	.heatmap-cell {
		cursor: pointer;
		transition: opacity 0.1s;
	}
	.heatmap-cell:hover {
		opacity: 0.8;
		stroke: var(--c-text);
		stroke-width: 1;
	}

	.heatmap-tooltip {
		position: absolute;
		transform: translate(-50%, -100%);
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		padding: 0.3rem 0.5rem;
		font-size: 0.75rem;
		white-space: nowrap;
		pointer-events: none;
		z-index: 10;
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 0.1rem;
		box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
	}
	.heatmap-tooltip strong {
		font-weight: 600;
		color: var(--c-text);
	}
	.heatmap-tooltip span {
		color: var(--c-text-muted);
	}

	/* Heatmap color levels using accent */
	:global(:root) {
		--heatmap-l1: color-mix(in srgb, var(--c-accent) 30%, var(--c-border));
		--heatmap-l2: color-mix(in srgb, var(--c-accent) 60%, var(--c-border));
		--heatmap-l3: var(--c-accent);
	}
</style>
