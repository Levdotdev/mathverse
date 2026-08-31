self.addEventListener('push', event => {
    let payload = {};
    try {
        payload = event.data?.json() ?? {};
    } catch (error) {
        payload = { body: event.data?.text() ?? 'MathVerse has a new notification.' };
    }

    const title = payload.title || 'MathVerse Notification';
    const options = {
        body: payload.body || 'A new item needs your attention.',
        icon: '/logo.png',
        badge: '/logo.png',
        tag: payload.tag || 'mathverse-notification',
        renotify: true,
        data: { url: payload.url || '/' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    const destination = new URL(event.notification.data?.url || '/', self.location.origin).href;

    event.waitUntil((async () => {
        const windows = await clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of windows) {
            if (new URL(client.url).origin === self.location.origin) {
                await client.navigate(destination);
                return client.focus();
            }
        }
        return clients.openWindow(destination);
    })());
});
