/* Service worker: menampilkan push notification desktop & mobile. */

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let payload;

    try {
        payload = event.data.json();
    } catch (error) {
        payload = { title: 'Dashboard Keuangan', body: event.data.text() };
    }

    const title = payload.title || 'Dashboard Keuangan';
    const options = {
        body: payload.body,
        icon: payload.icon || '/icons/icon-192.png',
        badge: payload.badge || '/icons/badge-72.png',
        tag: payload.tag,
        renotify: Boolean(payload.tag),
        requireInteraction: payload.requireInteraction || false,
        data: payload.data || {},
        actions: payload.actions || [],
        vibrate: payload.vibrate || [100, 50, 100],
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = new URL(event.notification.data?.url || '/dashboard', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const existing = clients.find((client) => client.url.startsWith(self.location.origin));

            if (existing) {
                existing.focus();

                return existing.navigate(target);
            }

            return self.clients.openWindow(target);
        }),
    );
});
