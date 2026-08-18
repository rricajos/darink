<script lang="ts">
	let { tags, onchange, allTags = [] }: {
		tags: string[];
		onchange: (tags: string[]) => void;
		allTags?: string[];
	} = $props();

	let input = $state('');
	let showSuggestions = $state(false);
	let inputEl: HTMLInputElement;

	const suggestions = $derived.by(() => {
		const q = input.trim().toLowerCase();
		if (!q) return [];
		return allTags
			.filter((t) => t.includes(q) && !tags.includes(t))
			.slice(0, 8);
	});

	function addTag(tag: string): void {
		const t = tag.trim().toLowerCase();
		if (!t || tags.includes(t)) return;
		onchange([...tags, t]);
		input = '';
		showSuggestions = false;
		inputEl?.focus();
	}

	function removeTag(tag: string): void {
		onchange(tags.filter((t) => t !== tag));
	}

	function handleKeydown(e: KeyboardEvent): void {
		if (e.key === 'Enter' || e.key === ',') {
			e.preventDefault();
			if (input.trim()) {
				addTag(input);
			}
		} else if (e.key === 'Backspace' && !input && tags.length > 0) {
			removeTag(tags[tags.length - 1]);
		}
	}

	function handleInput(): void {
		if (input.includes(',')) {
			const parts = input.split(',');
			for (const part of parts) {
				if (part.trim()) addTag(part);
			}
			input = '';
		}
		showSuggestions = input.trim().length > 0;
	}

	function handleBlur(): void {
		setTimeout(() => { showSuggestions = false; }, 150);
	}
</script>

<div class="tag-input-wrap">
	<div class="tag-chips">
		{#each tags as tag}
			<span class="tag-chip">
				{tag}
				<button class="tag-remove" onclick={() => removeTag(tag)} aria-label="Remove tag {tag}">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
				</button>
			</span>
		{/each}
		<input
			bind:this={inputEl}
			bind:value={input}
			oninput={handleInput}
			onkeydown={handleKeydown}
			onfocus={() => showSuggestions = input.trim().length > 0}
			onblur={handleBlur}
			placeholder={tags.length === 0 ? 'Add tags...' : ''}
			class="tag-text-input"
		/>
	</div>
	{#if showSuggestions && suggestions.length > 0}
		<div class="suggestions">
			{#each suggestions as s}
				<button class="suggestion-item" onmousedown={() => addTag(s)}>
					{s}
				</button>
			{/each}
		</div>
	{/if}
</div>

<style>
	.tag-input-wrap {
		position: relative;
	}

	.tag-chips {
		display: flex;
		flex-wrap: wrap;
		gap: 0.25rem;
		align-items: center;
		padding: 0.35rem 0.5rem;
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		background: var(--c-bg-card);
		max-height: 6rem;
		overflow-y: auto;
		cursor: text;
	}

	.tag-chips:focus-within {
		border-color: var(--c-accent);
	}

	.tag-chip {
		display: inline-flex;
		align-items: center;
		gap: 0.2rem;
		font-size: 0.72rem;
		font-weight: 500;
		padding: 0.15rem 0.4rem;
		border-radius: 10px;
		background: var(--c-accent-bg);
		color: var(--c-accent);
		white-space: nowrap;
	}

	.tag-remove {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		border: none;
		background: none;
		color: var(--c-accent);
		padding: 0;
		cursor: pointer;
		opacity: 0.7;
		line-height: 1;
	}

	.tag-remove:hover {
		opacity: 1;
		background: none;
		transform: none;
		box-shadow: none;
	}

	.tag-text-input {
		flex: 1;
		min-width: 60px;
		border: none;
		outline: none;
		background: transparent;
		font-size: 0.85rem;
		color: var(--c-text);
		padding: 0.1rem 0;
	}

	.suggestions {
		position: absolute;
		top: 100%;
		left: 0;
		right: 0;
		z-index: 50;
		background: var(--c-bg-card);
		border: 1px solid var(--c-border);
		border-radius: var(--radius);
		margin-top: 0.2rem;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
		max-height: 10rem;
		overflow-y: auto;
	}

	.suggestion-item {
		display: block;
		width: 100%;
		text-align: left;
		padding: 0.4rem 0.6rem;
		font-size: 0.8rem;
		border: none;
		background: none;
		color: var(--c-text);
		cursor: pointer;
	}

	.suggestion-item:hover {
		background: var(--c-accent-bg);
		color: var(--c-accent);
		transform: none;
		box-shadow: none;
	}
</style>
