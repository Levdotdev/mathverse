document.addEventListener('DOMContentLoaded', async () => {
    const buttons = [...document.querySelectorAll('[data-push-toggle]')];
    if (!buttons.length) return;
    const statuses = [...document.querySelectorAll('[data-push-status]')];

    const publicKey = buttons[0].dataset.vapidKey ?? '';
    const supported = 'serviceWorker' in navigator
        && 'PushManager' in window
        && 'Notification' in window;

    if (!supported) {
        setButtonsDisabled(true);
        setStatus('This browser does not support Web Push.');
        return;
    }

    if (!publicKey) {
        setButtonsDisabled(true);
        setStatus('Push keys still need to be configured.');
        return;
    }

    let registration;
    try {
        registration = await navigator.serviceWorker.register('/mathverse-sw.js');
        await updateButton(registration);
    } catch (error) {
        setButtonsDisabled(true);
        setStatus('The notification service worker could not start.');
        return;
    }

    buttons.forEach(button => {
        button.addEventListener('click', async () => {
            setButtonsDisabled(true);
            try {
                const existing = await registration.pushManager.getSubscription();
                if (existing) {
                    await removeSubscription(existing, button.dataset.subscriptionUrl);
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
                        await saveSubscription(subscription, button.dataset.subscriptionUrl);
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
    });

    async function updateButton(activeRegistration) {
        const subscription = await activeRegistration.pushManager.getSubscription();
        const enabled = subscription !== null;
        if (!enabled && Notification.permission === 'denied') {
            setButtonsDisabled(true);
            setButtonText('Permission Blocked');
            setStatus('Allow MathVerse notifications in your browser settings.');
            return;
        }
        setButtonsDisabled(false);
        setButtonText(enabled ? 'Disable Browser Alerts' : 'Enable Browser Alerts');
        setStatus(enabled
            ? 'OS notifications are enabled on this device.'
            : 'Enable operating-system alerts for non-email MathVerse events.');
    }

    function setButtonsDisabled(disabled) {
        buttons.forEach(button => { button.disabled = disabled; });
    }

    function setButtonText(message) {
        buttons.forEach(button => { button.textContent = message; });
    }

    function setStatus(message) {
        statuses.forEach(status => { status.textContent = message; });
    }
});

async function saveSubscription(subscription, endpoint = '/push-subscription') {
    const response = await fetch(endpoint || '/push-subscription', {
        method: 'POST',
        headers: pushHeaders(),
        body: JSON.stringify(subscription.toJSON()),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'The subscription could not be saved.');
}

async function removeSubscription(subscription, endpoint = '/push-subscription') {
    const response = await fetch(endpoint || '/push-subscription', {
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
