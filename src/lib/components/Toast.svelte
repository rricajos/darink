<script lang="ts">
	import { toast } from '$lib/stores/toast.svelte';
</script>

{#if toast.visible}
	<div class="toast" role="status">
		{toast.message}
	</div>
{/if}

<style>
	.toast {
		position: fixed;
		bottom: calc(var(--nav-height) + 12px);
		left: 50%;
		transform: translateX(-50%);
		background: var(--c-done);
		color: #fff;
		padding: 0.6rem 1.25rem;
		border-radius: 20px;
		font-size: 0.9rem;
		font-weight: 500;
		z-index: 200;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
		animation: slideUp 0.2s ease-out;
	}

	@keyframes slideUp {
		from { opacity: 0; transform: translateX(-50%) translateY(8px); }
		to { opacity: 1; transform: translateX(-50%) translateY(0); }
	}
</style>
