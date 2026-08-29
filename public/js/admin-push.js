document.addEventListener('DOMContentLoaded', async () => {
    const button = document.getElementById('admin-push-toggle');
    const status = document.getElementById('admin-push-status');
    if (!button || !status) return;

    const publicKey = button.dataset.vapidKey ?? '';
    const supported = 'serviceWorker' in navigator
        && 'PushManager' in window
        && 'Notification' in window;

    if (!supported) {
        button.disabled = true;
        status.textContent = 'This browser does not support Web Push.';
        return;
    }

    if (!publicKey) {
        button.disabled = true;
        status.textContent = 'Push keys still need to be configured.';
        return;
    }

    let registration;
    try {
        registration = await navigator.serviceWorker.register('/mathverse-sw.js');
        await updateButton(registration);
    } catch (error) {
        button.disabled = true;
        status.textContent = 'The notification service worker could not start.';
        return;
    }

    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            const existing = await registration.pushManager.getSubscription();
            if (existing) {
                await removeSubscription(existing);
                await existing.unsubscribe();
                showToast('Browser alerts disabled.');
            } else {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    throw new Error('Notification permission was not granted.');
                }

                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(publicKey),
                });

                try {
                    await saveSubscription(subscription);
                } catch (error) {
                    await subscription.unsubscribe();
                    throw error;
                }
                showToast('Browser alerts enabled.');
            }
        } catch (error) {
            showToast(error.message || 'Browser alerts could not be updated.', true);
        } finally {
            await updateButton(registration);
        }
    });

    async function updateButton(activeRegistration) {
        const subscription = await activeRegistration.pushManager.getSubscription();
        const enabled = subscription !== null;
        if (!enabled && Notification.permission === 'denied') {
            button.disabled = true;
            button.textContent = 'Permission Blocked';
            status.textContent = 'Allow MathVerse notifications in your browser settings.';
            return;
        }
        button.disabled = false;
        button.textContent = enabled ? 'Disable Browser Alerts' : 'Enable Browser Alerts';
        status.textContent = enabled
            ? 'OS notifications are enabled on this device.'
            : 'Enable alerts for teacher registrations and quiz reports.';
    }
});

async function saveSubscription(subscription) {
    const response = await fetch('/admin/push-subscription', {
        method: 'POST',
        headers: pushHeaders(),
        body: JSON.stringify(subscription.toJSON()),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'The subscription could not be saved.');
}

async function removeSubscription(subscription) {
    const response = await fetch('/admin/push-subscription', {
        method: 'DELETE',
        headers: pushHeaders(),
        body: JSON.stringify({ endpoint: subscription.endpoint }),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'The subscription could not be removed.');
}

function pushHeaders() {
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
    };
}

function urlBase64ToUint8Array(value) {
    const padding = '='.repeat((4 - value.length % 4) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    return Uint8Array.from([...raw].map(character => character.charCodeAt(0)));
}
