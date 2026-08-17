<script lang="ts">
	import PageHeader from '$lib/components/PageHeader.svelte';

	let expanded = $state<Set<string>>(new Set());

	function toggle(id: string) {
		const next = new Set(expanded);
		if (next.has(id)) next.delete(id);
		else next.add(id);
		expanded = next;
	}
</script>

<PageHeader title="Reference" />

<section class="ref-list">

	<!-- HORMONAL -->
	<div class="ref-section">
		<button class="ref-header" onclick={() => toggle('hormonal')}>
			<span class="arrow">{#if expanded.has('hormonal')}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>{:else}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>{/if}</span>
			<span>Hormonal System</span>
		</button>
		{#if expanded.has('hormonal')}
			<div class="ref-body">
				<table class="ref-table">
					<thead>
						<tr><th>Hormone</th><th>Optimal (male)</th><th>Affects</th><th>Low signs</th><th>High signs</th></tr>
					</thead>
					<tbody>
						<tr>
							<td><strong>Testosterone</strong></td>
							<td>500–900 ng/dL</td>
							<td>Muscle, libido, mood, energy</td>
							<td>Fatigue, low libido, brain fog</td>
							<td>Aggression, acne, hair loss</td>
						</tr>
						<tr>
							<td><strong>Estradiol (E2)</strong></td>
							<td>20–35 pg/mL</td>
							<td>Bone density, joint health, libido</td>
							<td>Joint pain, dry skin, low mood</td>
							<td>Bloating, gyno, emotional</td>
						</tr>
						<tr>
							<td><strong>DHT</strong></td>
							<td>30–85 ng/dL</td>
							<td>Body hair, masculinization, skin</td>
							<td>Weak erections, low drive</td>
							<td>Hair loss, prostate growth, acne</td>
						</tr>
						<tr>
							<td><strong>Cortisol</strong></td>
							<td>6–18 μg/dL (AM)</td>
							<td>Stress response, inflammation, glucose</td>
							<td>Fatigue, salt cravings, low BP</td>
							<td>Insomnia, belly fat, anxiety</td>
						</tr>
						<tr>
							<td><strong>DHEA-S</strong></td>
							<td>280–640 μg/dL</td>
							<td>Precursor to T/E2, adrenal health</td>
							<td>Low energy, poor recovery</td>
							<td>Acne, oily skin</td>
						</tr>
						<tr>
							<td><strong>SHBG</strong></td>
							<td>20–50 nmol/L</td>
							<td>Binds sex hormones, regulates free T</td>
							<td>High free T, acne, oily skin</td>
							<td>Low free T despite normal total T</td>
						</tr>
						<tr>
							<td><strong>Progesterone</strong></td>
							<td>0.3–1.2 ng/mL</td>
							<td>Sleep, GABA activity, anti-anxiety</td>
							<td>Insomnia, anxiety, restlessness</td>
							<td>Drowsiness, low motivation</td>
						</tr>
						<tr>
							<td><strong>TSH</strong></td>
							<td>0.5–2.5 mIU/L</td>
							<td>Thyroid regulation, metabolism</td>
							<td>Hyper metabolism, tremor, heat</td>
							<td>Weight gain, cold, fatigue (hypo)</td>
						</tr>
						<tr>
							<td><strong>Free T3</strong></td>
							<td>3.0–4.2 pg/mL</td>
							<td>Active thyroid hormone, energy, metabolism</td>
							<td>Cold, sluggish, brain fog</td>
							<td>Anxiety, tremor, weight loss</td>
						</tr>
						<tr>
							<td><strong>Free T4</strong></td>
							<td>1.0–1.8 ng/dL</td>
							<td>Thyroid reserve, converts to T3</td>
							<td>Fatigue, dry skin, constipation</td>
							<td>Palpitations, sweating</td>
						</tr>
					</tbody>
				</table>
			</div>
		{/if}
	</div>

	<!-- NEUROTRANSMITTERS -->
	<div class="ref-section">
		<button class="ref-header" onclick={() => toggle('neuro')}>
			<span class="arrow">{#if expanded.has('neuro')}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>{:else}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>{/if}</span>
			<span>Neurotransmitters</span>
		</button>
		{#if expanded.has('neuro')}
			<div class="ref-body">
				<table class="ref-table">
					<thead>
						<tr><th>Neurotransmitter</th><th>Function</th><th>Deficiency signs</th><th>Natural boosters</th></tr>
					</thead>
					<tbody>
						<tr>
							<td><strong>Dopamine</strong></td>
							<td>Motivation, reward, focus, drive</td>
							<td>Apathy, procrastination, low pleasure</td>
							<td>Tyrosine-rich foods (eggs, meat), cold exposure, exercise, sunlight</td>
						</tr>
						<tr>
							<td><strong>Serotonin</strong></td>
							<td>Mood stability, sleep, digestion</td>
							<td>Depression, anxiety, carb cravings, insomnia</td>
							<td>Tryptophan (turkey, banana), sunlight, exercise, gut health</td>
						</tr>
						<tr>
							<td><strong>GABA</strong></td>
							<td>Calming, anti-anxiety, sleep onset</td>
							<td>Anxiety, racing thoughts, tension, insomnia</td>
							<td>Fermented foods, magnesium, L-theanine, yoga, breathing</td>
						</tr>
						<tr>
							<td><strong>Acetylcholine</strong></td>
							<td>Memory, learning, muscle control</td>
							<td>Brain fog, poor memory, dry mouth</td>
							<td>Eggs (choline), liver, fish, alpha-GPC supplement</td>
						</tr>
						<tr>
							<td><strong>Norepinephrine</strong></td>
							<td>Alertness, attention, fight-or-flight</td>
							<td>Low energy, poor focus, depression</td>
							<td>Cold exposure, exercise, adequate sleep, phenylalanine</td>
						</tr>
						<tr>
							<td><strong>Glutamate</strong></td>
							<td>Excitatory signaling, learning, synaptic plasticity</td>
							<td>Fatigue, poor concentration, low alertness</td>
							<td>Balanced diet; excess causes excitotoxicity — avoid MSG excess</td>
						</tr>
					</tbody>
				</table>
			</div>
		{/if}
	</div>

	<!-- PROTOCOLS -->
	<div class="ref-section">
		<button class="ref-header" onclick={() => toggle('protocols')}>
			<span class="arrow">{#if expanded.has('protocols')}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>{:else}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>{/if}</span>
			<span>Protocols</span>
		</button>
		{#if expanded.has('protocols')}
			<div class="ref-body">
				<dl class="ref-dl">
					<dt>Cold exposure (Huberman)</dt>
					<dd>11 min total per week across 2–4 sessions. Each session 1–3 min in cold water (10–15 °C). Boosts dopamine 250–300% for several hours. End on cold, not warm.</dd>

					<dt>Morning sun exposure</dt>
					<dd>10–30 min of direct sunlight within first hour of waking. No sunglasses. Sets circadian rhythm, boosts cortisol awakening response, increases serotonin and vitamin D synthesis.</dd>

					<dt>Wim Hof breathing</dt>
					<dd>3–4 rounds of 30–40 deep breaths (inhale fully, passive exhale), followed by 15–60 s breath hold after final exhale. Alkalizes blood, activates sympathetic nervous system, reduces inflammation.</dd>

					<dt>Intermittent fasting</dt>
					<dd>16:8 (eat within 8h window) or 18:6 for deeper autophagy. First meal ideally after morning activity. Improves insulin sensitivity, growth hormone, and cellular cleanup. Avoid if chronically stressed.</dd>

					<dt>Sleep optimization</dt>
					<dd>7–9 hours, consistent bed/wake time (±30 min). Room temperature 18–19 °C. No screens 1h before bed. Last meal 3h before sleep. Magnesium glycinate and dim lighting in evening.</dd>

					<dt>Zone 2 cardio</dt>
					<dd>150–180 min per week at conversational pace (can speak in full sentences). Walking, cycling, light jogging. Builds mitochondrial density, improves fat oxidation, supports cardiovascular health.</dd>
				</dl>
			</div>
		{/if}
	</div>

	<!-- NUTRITION -->
	<div class="ref-section">
		<button class="ref-header" onclick={() => toggle('nutrition')}>
			<span class="arrow">{#if expanded.has('nutrition')}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>{:else}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>{/if}</span>
			<span>Nutrition</span>
		</button>
		{#if expanded.has('nutrition')}
			<div class="ref-body">
				<h3 class="ref-subtitle">Key micronutrients</h3>
				<table class="ref-table">
					<thead>
						<tr><th>Nutrient</th><th>Daily dose</th><th>Food sources</th><th>Hormonal role</th></tr>
					</thead>
					<tbody>
						<tr>
							<td><strong>Zinc</strong></td>
							<td>15–30 mg</td>
							<td>Oysters, beef, pumpkin seeds</td>
							<td>5-alpha reductase, testosterone production, immune function</td>
						</tr>
						<tr>
							<td><strong>Magnesium</strong></td>
							<td>400–600 mg</td>
							<td>Dark chocolate, spinach, almonds</td>
							<td>Lowers SHBG (frees testosterone), sleep, GABA activity</td>
						</tr>
						<tr>
							<td><strong>Vitamin D3</strong></td>
							<td>4000–5000 IU</td>
							<td>Sunlight, fatty fish, egg yolks</td>
							<td>Steroid hormone precursor, testosterone, bone health</td>
						</tr>
						<tr>
							<td><strong>Vitamin K2 (MK-7)</strong></td>
							<td>100–200 μg</td>
							<td>Natto, hard cheese, egg yolk</td>
							<td>Directs calcium to bones (D3 synergy), supports testosterone</td>
						</tr>
						<tr>
							<td><strong>B-complex</strong></td>
							<td>Varies per B</td>
							<td>Liver, eggs, leafy greens, meat</td>
							<td>Methylation, neurotransmitter synthesis, energy metabolism</td>
						</tr>
						<tr>
							<td><strong>Omega-3 (EPA/DHA)</strong></td>
							<td>2–3 g total</td>
							<td>Wild salmon, sardines, mackerel</td>
							<td>Anti-inflammatory, cell membrane integrity, brain health</td>
						</tr>
						<tr>
							<td><strong>Boron</strong></td>
							<td>6–10 mg</td>
							<td>Raisins, almonds, avocado</td>
							<td>Lowers SHBG, increases free testosterone, reduces estrogen</td>
						</tr>
						<tr>
							<td><strong>Selenium</strong></td>
							<td>100–200 μg</td>
							<td>Brazil nuts (2–3/day), tuna, eggs</td>
							<td>Thyroid T4→T3 conversion, antioxidant, sperm quality</td>
						</tr>
					</tbody>
				</table>

				<h3 class="ref-subtitle">Macros reference</h3>
				<table class="ref-table">
					<thead>
						<tr><th>Macro</th><th>Target</th><th>Notes</th></tr>
					</thead>
					<tbody>
						<tr>
							<td><strong>Protein</strong></td>
							<td>1.6–2.2 g/kg body weight</td>
							<td>Essential for muscle synthesis, satiety, and neurotransmitter precursors</td>
						</tr>
						<tr>
							<td><strong>Fat</strong></td>
							<td>Min 0.7 g/kg (25–35% kcal)</td>
							<td>Critical for steroid hormone production — never go below minimum</td>
						</tr>
						<tr>
							<td><strong>Carbs</strong></td>
							<td>Remainder of calories</td>
							<td>Fuel for training, supports thyroid and cortisol regulation</td>
						</tr>
					</tbody>
				</table>
			</div>
		{/if}
	</div>

	<!-- TOXINS & DISRUPTORS -->
	<div class="ref-section">
		<button class="ref-header" onclick={() => toggle('toxins')}>
			<span class="arrow">{#if expanded.has('toxins')}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>{:else}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>{/if}</span>
			<span>Toxins & Disruptors</span>
		</button>
		{#if expanded.has('toxins')}
			<div class="ref-body">
				<h3 class="ref-subtitle">Endocrine disruptors</h3>
				<table class="ref-table">
					<thead>
						<tr><th>Disruptor</th><th>Where found</th><th>How to avoid</th><th>Health effects</th></tr>
					</thead>
					<tbody>
						<tr>
							<td><strong>BPA / BPS</strong></td>
							<td>Plastic bottles, can linings, receipts</td>
							<td>Glass/steel containers, avoid canned food, decline receipts</td>
							<td>Xenoestrogenic — mimics estradiol, lowers testosterone</td>
						</tr>
						<tr>
							<td><strong>Phthalates</strong></td>
							<td>Fragrances, soft plastics, cosmetics</td>
							<td>Fragrance-free products, avoid plastic food containers</td>
							<td>Anti-androgenic — blocks DHT, disrupts reproductive development</td>
						</tr>
						<tr>
							<td><strong>Parabens</strong></td>
							<td>Shampoo, lotions, deodorants</td>
							<td>Paraben-free personal care, read labels</td>
							<td>Estrogenic activity, linked to hormonal imbalance</td>
						</tr>
						<tr>
							<td><strong>Lead</strong></td>
							<td>Old paint, water pipes, some spices</td>
							<td>Water filter (reverse osmosis), test home for lead</td>
							<td>Neurotoxic, damages kidneys, lowers sperm quality</td>
						</tr>
						<tr>
							<td><strong>Mercury</strong></td>
							<td>Large fish (tuna, swordfish), amalgam fillings</td>
							<td>Choose small fish (sardines, anchovies), limit tuna</td>
							<td>Neurotoxic, thyroid disruption, immune suppression</td>
						</tr>
						<tr>
							<td><strong>Cadmium</strong></td>
							<td>Tobacco smoke, rice, chocolate, leafy greens</td>
							<td>Don't smoke, rinse rice, vary food sources</td>
							<td>Kidney damage, bone loss, estrogenic effects</td>
						</tr>
					</tbody>
				</table>

				<h3 class="ref-subtitle">Anti-nutrients</h3>
				<dl class="ref-dl">
					<dt>Phytates (phytic acid)</dt>
					<dd>Found in grains, legumes, nuts. Binds zinc, iron, calcium — reducing absorption. Mitigate by soaking, sprouting, or fermenting.</dd>

					<dt>Oxalates</dt>
					<dd>High in spinach, almonds, beets, chocolate. Can contribute to kidney stones and block calcium/magnesium absorption. Cook to reduce, rotate greens.</dd>

					<dt>Lectins</dt>
					<dd>Found in raw legumes, grains, nightshades. Can irritate gut lining. Pressure cooking and soaking deactivates most lectins.</dd>
				</dl>
			</div>
		{/if}
	</div>

	<!-- TISSUES & BODY SIGNALS -->
	<div class="ref-section">
		<button class="ref-header" onclick={() => toggle('tissues')}>
			<span class="arrow">{#if expanded.has('tissues')}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>{:else}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>{/if}</span>
			<span>Tissues & Body Signals</span>
		</button>
		{#if expanded.has('tissues')}
			<div class="ref-body">
				<h3 class="ref-subtitle">What to track and why</h3>
				<table class="ref-table">
					<thead>
						<tr><th>Signal</th><th>Indicates</th><th>What to look for</th></tr>
					</thead>
					<tbody>
						<tr>
							<td><strong>Skin</strong></td>
							<td>DHT / estrogen balance</td>
							<td>Acne (high DHT/androgen), dryness (low estrogen), oiliness (high androgens)</td>
						</tr>
						<tr>
							<td><strong>Hair</strong></td>
							<td>DHT / thyroid status</td>
							<td>Thinning/miniaturization (high DHT), shedding (thyroid, stress), dryness (hypothyroid)</td>
						</tr>
						<tr>
							<td><strong>Nails</strong></td>
							<td>Mineral status</td>
							<td>White spots (zinc), ridges (B vitamins), brittleness (iron, biotin), spooning (anemia)</td>
						</tr>
						<tr>
							<td><strong>Genital</strong></td>
							<td>Testosterone / blood flow</td>
							<td>Morning erection quality (free T), sensitivity (DHT), testicular size (LH/FSH)</td>
						</tr>
					</tbody>
				</table>

				<h3 class="ref-subtitle">Body composition reference</h3>
				<table class="ref-table">
					<thead>
						<tr><th>Metric</th><th>Range</th><th>Notes</th></tr>
					</thead>
					<tbody>
						<tr>
							<td><strong>Body fat % (male)</strong></td>
							<td>10–15% athletic, 15–20% fit, 20–25% average</td>
							<td>Below 8% impacts hormones negatively. Sweet spot for T: 12–18%</td>
						</tr>
						<tr>
							<td><strong>BMI</strong></td>
							<td>18.5–24.9 normal</td>
							<td>Unreliable for muscular individuals. Use alongside body fat %</td>
						</tr>
						<tr>
							<td><strong>Waist-to-hip ratio</strong></td>
							<td>&lt; 0.90 (male)</td>
							<td>Above 0.90 = increased cardiovascular and metabolic risk</td>
						</tr>
					</tbody>
				</table>

				<h3 class="ref-subtitle">Recovery markers</h3>
				<table class="ref-table">
					<thead>
						<tr><th>Marker</th><th>Optimal</th><th>Interpretation</th></tr>
					</thead>
					<tbody>
						<tr>
							<td><strong>HRV (RMSSD)</strong></td>
							<td>50–100+ ms</td>
							<td>Higher = better parasympathetic tone. Track trend, not absolutes. Low = under-recovered</td>
						</tr>
						<tr>
							<td><strong>Resting HR</strong></td>
							<td>50–65 bpm</td>
							<td>Lower = better fitness. Sudden increase = illness, overtraining, or poor sleep</td>
						</tr>
						<tr>
							<td><strong>Sleep quality</strong></td>
							<td>85%+ score</td>
							<td>Based on deep sleep (1.5–2h), REM (1.5–2h), awakenings (&lt;2), and total time (7–9h)</td>
						</tr>
					</tbody>
				</table>
			</div>
		{/if}
	</div>

</section>

<style>
	.ref-list {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
		padding: 0 1rem;
	}

	.ref-section {
		margin-bottom: 0.25rem;
	}

	.ref-header {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		width: 100%;
		padding: 0.75rem 1rem;
		cursor: pointer;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		font-weight: 600;
		font-size: 1rem;
		color: var(--c-text);
		text-align: left;
	}

	.ref-header:hover {
		border-color: var(--c-accent);
		background: var(--c-bg-card);
	}

	.arrow {
		width: 1rem;
		flex-shrink: 0;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.ref-body {
		padding: 0.75rem 1rem;
		overflow-x: auto;
	}

	.ref-subtitle {
		font-size: 0.85rem;
		font-weight: 600;
		color: var(--c-text-muted);
		text-transform: uppercase;
		letter-spacing: 0.03em;
		margin-top: 1.25rem;
		margin-bottom: 0.5rem;
	}

	.ref-subtitle:first-child {
		margin-top: 0;
	}

	.ref-table {
		width: 100%;
		border-collapse: collapse;
		font-size: 0.85rem;
		margin-bottom: 0.5rem;
	}

	.ref-table th {
		text-align: left;
		padding: 0.4rem;
		border-bottom: 2px solid var(--c-border);
		color: var(--c-text-muted);
		font-size: 0.75rem;
		text-transform: uppercase;
	}

	.ref-table td {
		padding: 0.4rem;
		border-bottom: 1px solid var(--c-border);
		vertical-align: top;
	}

	.ref-dl {
		list-style: none;
		font-size: 0.85rem;
	}

	.ref-dl dt {
		font-weight: 600;
		margin-top: 0.75rem;
		color: var(--c-text);
	}

	.ref-dl dt:first-child {
		margin-top: 0;
	}

	.ref-dl dd {
		margin-left: 0;
		margin-top: 0.2rem;
		color: var(--c-text-muted);
		line-height: 1.5;
	}
</style>
