'use strict';

self.addEventListener('push', event => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = { body: event.data ? event.data.text() : 'Ada pembaruan baru di SatSet.' };
    }

    const title = payload.title || 'SatSet';
    const options = {
        body: payload.body || 'Ada pembaruan baru di SatSet.',
        icon: payload.icon || '/assets/images/satset-icon-192.png',
        badge: payload.badge || '/assets/images/k_favicon_32x.png',
        tag: payload.tag || 'satset-notification',
        renotify: true,
        requireInteraction: true,
        data: {
            url: payload.url || '/ticket/tiket-saya',
            payload: payload.data || {},
        },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    const targetUrl = new URL(event.notification.data?.url || '/', self.location.origin);
    if (targetUrl.origin !== self.location.origin) targetUrl.href = self.location.origin;

    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
        for (const client of windowClients) {
            if ('focus' in client) {
                client.navigate(targetUrl.href);
                return client.focus();
            }
        }
        return clients.openWindow ? clients.openWindow(targetUrl.href) : undefined;
    }));
});
