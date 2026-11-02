/* Service worker mínimo: só mostra a página "sem conexão" quando a internet cai. Não guarda páginas nem dados dos alunos. */
const V = 'ead-offline-v1';
self.addEventListener('install', e => e.waitUntil(caches.open(V).then(c => c.add('offline.html')).then(() => self.skipWaiting())));
self.addEventListener('activate', e => e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== V).map(k => caches.delete(k)))).then(() => self.clients.claim())));
self.addEventListener('fetch', e => {
  if (e.request.mode === 'navigate') e.respondWith(fetch(e.request).catch(() => caches.match('offline.html')));
});
