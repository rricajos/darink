<script lang="ts">
	import { page } from '$app/state';
	import { useLocale } from '$lib/stores/locale.svelte';

	const { t } = useLocale();

	function recover() {
		if ('serviceWorker' in navigator) {
			navigator.serviceWorker.getRegistrations().then((regs) => {
				Promise.all(regs.map((r) => r.unregister())).then(() => {
					caches.keys().then((keys) => {
						Promise.all(keys.map((k) => caches.delete(k))).then(() => {
							localStorage.removeItem('darink-sw-v2');
							location.href = '/';
						});
					});
				});
			});
		} else {
			location.href = '/';
		}
	}
</script>

<div class="error">
	<h1>{page.status}</h1>
	<p>{page.error?.message}</p>
	<button onclick={recover}>{t.error.resetApp}</button>
</div>

<style>
	.error {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		min-height: 60dvh;
		gap: 1rem;
		padding: 2rem;
		text-align: center;
	}
	h1 {
		font-size: 3rem;
		margin: 0;
		opacity: 0.5;
	}
	p {
		opacity: 0.7;
	}
	button {
		padding: 0.75rem 1.5rem;
		border: none;
		border-radius: 0.5rem;
		background: var(--accent, #4aa3ff);
		color: #fff;
		font-size: 1rem;
		cursor: pointer;
	}
</style>
