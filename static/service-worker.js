const CACHE = 'darink-v2';

self.addEventListener('install', (e) => {
	e.waitUntil(
		caches.keys().then((keys) =>
			Promise.all(keys.map((k) => caches.delete(k)))
		)
	);
	self.skipWaiting();
});

self.addEventListener('activate', (e) => {
	e.waitUntil(
		caches.keys().then((keys) =>
			Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
		)
	);
	self.clients.claim();
});

self.addEventListener('fetch', (e) => {
	const req = e.request;

	if (req.method !== 'GET') return;

	if (req.mode === 'navigate') {
		e.respondWith(
			fetch(req)
				.then((res) => {
					const copy = res.clone();
					caches.open(CACHE).then((c) => c.put(req, copy));
					return res;
				})
				.catch(() => caches.match('/index.html'))
		);
		return;
	}

	e.respondWith(
		fetch(req)
			.then((res) => {
				const copy = res.clone();
				caches.open(CACHE).then((c) => c.put(req, copy));
				return res;
			})
			.catch(() => caches.match(req))
	);
});
