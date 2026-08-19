/** Static mapping from bloodwork marker keys to supplement name patterns (lowercase). */
export const markerSupplementMap: Record<string, string[]> = {
	vitaminD: ['vitamin d', 'vitamin d3', 'd3'],
	b12: ['b12', 'vitamin b12', 'methylcobalamin'],
	iron: ['iron', 'iron bisglycinate'],
	ferritin: ['iron', 'iron bisglycinate'],
	magnesium: ['magnesium', 'mag glycinate', 'magnesium glycinate'],
	zinc: ['zinc', 'zinc picolinate'],
	hdl: ['omega 3', 'fish oil', 'omega-3'],
	triglycerides: ['omega 3', 'fish oil', 'omega-3'],
	testosterone: ['ashwagandha', 'tongkat ali', 'boron'],
	freeT: ['ashwagandha', 'tongkat ali', 'boron'],
	glucose: ['berberine', 'chromium', 'cinnamon'],
	hba1c: ['berberine', 'chromium', 'cinnamon'],
	cortisol: ['ashwagandha', 'phosphatidylserine'],
	tsh: ['selenium', 'iodine'],
	freeT4: ['selenium', 'iodine']
};

/**
 * Find supplements from the user's stack that match a given bloodwork marker.
 * Case-insensitive match using includes() against the mapping patterns.
 */
export function findRelevantSupplements(
	markerKey: string,
	supplementStack: Array<{ name: string }>
): string[] {
	const patterns = markerSupplementMap[markerKey];
	if (!patterns) return [];
	const matched: string[] = [];
	for (const supp of supplementStack) {
		const lower = supp.name.toLowerCase();
		for (const pattern of patterns) {
			if (lower.includes(pattern)) {
				matched.push(supp.name);
				break;
			}
		}
	}
	return matched;
}

/**
 * Returns the full list of suggested supplement names from the mapping for a marker.
 */
export function findSuggestedSupplements(markerKey: string): string[] {
	return markerSupplementMap[markerKey] ?? [];
}
