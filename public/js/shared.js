const _syms = ['+','−','×','÷','=','π','∑','√','Δ','∞','∫','f(x)','y²','x³'];

function _spawn() {
    const c = document.getElementById('particle-container');
    if (!c || c.children.length > 10) return;
    const p      = document.createElement('div');
    p.className  = 'particle';
    p.innerText  = _syms[Math.floor(Math.random() * _syms.length)];
    p.style.left = Math.random() * 100 + 'vw';
    p.style.color = Math.random() > 0.5 ? '#00f2ff' : '#bc13fe';
    p.style.fontSize = (Math.random() * 15 + 15) + 'px';
    c.appendChild(p);
    setTimeout(() => p.remove(), 10000);
}
const reducedMotionQuery = typeof window.matchMedia === 'function'
    ? window.matchMedia('(prefers-reduced-motion: reduce)')
    : { matches: false };
let particleTimer = null;

function syncBackgroundMotion() {
    if (reducedMotionQuery.matches) {
        clearInterval(particleTimer);
        particleTimer = null;
        const particleContainer = document.getElementById('particle-container');
        if (particleContainer) particleContainer.textContent = '';
        return;
    }
    if (!particleTimer) particleTimer = setInterval(_spawn, 1500);
}

syncBackgroundMotion();
if (typeof reducedMotionQuery.addEventListener === 'function') {
    reducedMotionQuery.addEventListener('change', syncBackgroundMotion);
} else if (typeof reducedMotionQuery.addListener === 'function') {
    reducedMotionQuery.addListener(syncBackgroundMotion);
}

function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    const messageNode = document.getElementById('toast-msg');
    if (messageNode) messageNode.textContent = String(message ?? '');
    toast.setAttribute('role', isError ? 'alert' : 'status');
    toast.setAttribute('aria-live', isError ? 'assertive' : 'polite');
    toast.setAttribute('aria-hidden', 'false');
    toast.dataset.initialVisible = 'false';
    toast.classList.toggle('bg-red-500', isError);
    toast.classList.toggle('bg-cyan-500', !isError);
    toast.classList.toggle('text-white', isError);
    toast.classList.toggle('text-black', !isError);

    const icon = toast.querySelector('[data-toast-icon]');
    if (icon) {
        icon.classList.toggle('fa-circle-exclamation', isError);
        icon.classList.toggle('fa-circle-check', !isError);
    }

    toast.classList.remove('opacity-0', 'pointer-events-none');
    toast.classList.add('opacity-100');

    window.clearTimeout(window.mathVerseToastTimer);
    window.mathVerseToastTimer = window.setTimeout(hideToast, 6000);
}

function hideToast() {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.setAttribute('aria-hidden', 'true');
    toast.classList.remove('opacity-100');
    toast.classList.add('opacity-0', 'pointer-events-none');
}

function handleAuthConfirmationReturn() {
    const url = new URL(window.location.href);
    const hashParams = new URLSearchParams(url.hash.replace(/^#/, ''));
    const action = url.searchParams.get('auth_action') || hashParams.get('type');
    const hasAuthError = url.searchParams.has('error') || hashParams.has('error');
    const messages = {
        signup: 'Email confirmed successfully. You can now sign in.',
        email_change: 'Email confirmation received. Complete any other confirmation link to finish updating your email address.',
        email_change_current: 'Email confirmation received. Complete any other confirmation link to finish updating your email address.',
        email_change_new: 'Email confirmation received. Complete any other confirmation link to finish updating your email address.',
    };
    const isConfirmationReturn = Object.prototype.hasOwnProperty.call(messages, action);

    if (!isConfirmationReturn) return;

    showToast(
        hasAuthError ? 'This email confirmation link is invalid or expired.' : messages[action],
        hasAuthError
    );

    ['auth_action', 'code', 'token', 'token_hash', 'type', 'error', 'error_code', 'error_description']
        .forEach(parameter => url.searchParams.delete(parameter));
    url.hash = '';
    const cleanUrl = url.pathname + (url.searchParams.size ? `?${url.searchParams.toString()}` : '');
    window.history.replaceState(window.history.state, document.title, cleanUrl);
}

const scheduleAuthConfirmationReturn = () => window.setTimeout(handleAuthConfirmationReturn, 0);
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleAuthConfirmationReturn, { once: true });
} else {
    scheduleAuthConfirmationReturn();
}

function tglPass(id, icoId) {
    const inp = document.getElementById(id);
    const ico = document.getElementById(icoId);
    if (!inp || !ico) return;
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.classList.replace('fa-eye-slash', 'fa-eye');
        ico.classList.add('text-cyan-400');
    } else {
        inp.type = 'password';
        ico.classList.replace('fa-eye', 'fa-eye-slash');
        ico.classList.remove('text-cyan-400');
    }
}

async function copyToClipboard(text) {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
        } else {
            const helper = document.createElement('textarea');
            helper.value = text;
            helper.setAttribute('readonly', '');
            helper.style.position = 'fixed';
            helper.style.opacity = '0';
            document.body.appendChild(helper);
            helper.select();
            if (!document.execCommand('copy')) throw new Error('Copy failed');
            helper.remove();
        }
        showToast('Code copied: ' + text);
    } catch (error) {
        showToast('The code could not be copied. Select and copy it manually.', true);
    }
}

function openModal(id) {
    const m = document.getElementById(id);
    if (!m) return;

    m.dataset.previousFocusId = ensureElementId(document.activeElement, 'modal-trigger');
    m.setAttribute('role', m.getAttribute('role') || 'dialog');
    m.setAttribute('aria-modal', 'true');
    m.setAttribute('aria-hidden', 'false');
    ensureModalLabel(m, id);
    m.classList.remove('hidden');
    const f = m.querySelector('.portal-frame');
    if (f && !reducedMotionQuery.matches) {
        f.classList.remove('animate-fade-in');
        void f.offsetWidth;
        f.classList.add('animate-fade-in');
    }
    document.body.classList.add('modal-open');
    requestAnimationFrame(() => {
        const focusTarget = modalFocusableElements(m)[0] ?? f ?? m;
        if (!focusTarget.hasAttribute('tabindex') && !focusTarget.matches('button, a, input, select, textarea')) {
            focusTarget.setAttribute('tabindex', '-1');
        }
        focusTarget.focus({ preventScroll: true });
    });
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    modal.querySelector('.portal-frame')?.classList.remove('animate-fade-in');

    if (!document.querySelector('.modal-overlay:not(.hidden)')) {
        document.body.classList.remove('modal-open');
    }

    const previousFocus = modal.dataset.previousFocusId
        ? document.getElementById(modal.dataset.previousFocusId)
        : null;
    previousFocus?.focus({ preventScroll: true });
}

function modalFocusableElements(modal) {
    return [...modal.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )].filter(element => !element.closest('.hidden') && element.getClientRects().length > 0);
}

function ensureElementId(element, prefix) {
    if (!(element instanceof HTMLElement) || element === document.body) return '';
    if (!element.id) element.id = `${prefix}-${globalThis.crypto?.randomUUID?.() ?? Date.now()}`;
    return element.id;
}

function ensureModalLabel(modal, id) {
    if (modal.hasAttribute('aria-label') || modal.hasAttribute('aria-labelledby')) return;
    const title = modal.querySelector('h1, h2, h3');
    if (!title) {
        modal.setAttribute('aria-label', 'Dialog');
        return;
    }
    if (!title.id) title.id = `${id}-title`;
    modal.setAttribute('aria-labelledby', title.id);
}

document.addEventListener('keydown', event => {
    const modals = [...document.querySelectorAll('.modal-overlay:not(.hidden)')];
    const modal = modals[modals.length - 1];
    if (!modal) return;

    if (event.key === 'Escape') {
        event.preventDefault();
        closeModal(modal.id);
        return;
    }
    if (event.key !== 'Tab') return;

    const focusable = modalFocusableElements(modal);
    if (!focusable.length) {
        event.preventDefault();
        modal.focus();
        return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
});

function toggleSidebar(forceOpen = null) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (!sidebar || !overlay) return;

    const currentlyOpen = !sidebar.classList.contains('-translate-x-full');
    const shouldOpen = forceOpen === null ? !currentlyOpen : Boolean(forceOpen);
    sidebar.classList.toggle('-translate-x-full', !shouldOpen);
    overlay.classList.toggle('hidden', !shouldOpen);
    document.body.classList.toggle('sidebar-open', shouldOpen && window.innerWidth < 768);
    document.querySelectorAll('[data-sidebar-toggle]').forEach(button => {
        button.setAttribute('aria-expanded', String(shouldOpen));
        button.setAttribute('aria-label', shouldOpen ? 'Close navigation' : 'Open navigation');
    });
}

function showSection(id) {
    const sec = document.getElementById('sec-' + id);
    if (!sec) return false;

    document.querySelectorAll('.content-section').forEach(s => {
        s.classList.add('hidden');
        s.classList.remove('animate-fade-in');
    });
    document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
    sec.classList.remove('hidden');
    void sec.offsetWidth;
    sec.classList.add('animate-fade-in');
    document.getElementById('btn-' + id)?.classList.add('active');

    if (window.innerWidth < 768) toggleSidebar(false);

    return true;
}

window.addEventListener('resize', () => {
    if (window.innerWidth >= 768) {
        document.getElementById('sidebar-overlay')?.classList.add('hidden');
        document.body.classList.remove('sidebar-open');
        document.querySelectorAll('[data-sidebar-toggle]').forEach(button => {
            button.setAttribute('aria-expanded', 'false');
        });
    }
});

// Read CSRF token from meta tag (set in layout)
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}
document.addEventListener('DOMContentLoaded', () => {
    const sections = [...document.querySelectorAll('.content-section')];
    if (!sections.length) return;

    const requested = new URLSearchParams(window.location.search).get('section');
    const section = requested === 'password' ? 'security' : requested;
    const visible = sections.find(item => !item.classList.contains('hidden'));
    const fallback = visible?.id.replace(/^sec-/, '') ?? 'stats';
    showSection(section || fallback);
});

function syncTemporalInputTone(input) {
    input?.classList.toggle('temporal-input-empty', !input.value);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[type="date"], input[type="datetime-local"], input[type="month"], input[type="time"]')
        .forEach(input => {
            syncTemporalInputTone(input);
            input.addEventListener('input', () => syncTemporalInputTone(input));
            input.addEventListener('change', () => syncTemporalInputTone(input));
        });
});

const MAX_AVATAR_SIZE_BYTES = 2 * 1024 * 1024;
const AVATAR_SIZE_ERROR = 'The selected image must be 2 MB or less.';
let oversizedAvatarInput = null;

function avatarExceedsSizeLimit(file) {
    return file && file.size > MAX_AVATAR_SIZE_BYTES;
}

function showAvatarSizeModal(input) {
    const file = input.files?.[0];
    if (!file) return;

    oversizedAvatarInput = input;
    const fileDetails = document.getElementById('image-size-file');

    if (fileDetails) {
        const sizeInMb = (file.size / (1024 * 1024)).toFixed(2);
        fileDetails.innerText = `${file.name} (${sizeInMb} MB)`;
    }

    openModal('imageSizeModal');
}

function validateAvatarSize(input) {
    const file = input.files?.[0];

    if (!file || !avatarExceedsSizeLimit(file)) {
        input.removeAttribute('aria-invalid');
        input.setCustomValidity('');
        if (oversizedAvatarInput === input) {
            oversizedAvatarInput = null;
        }
        return true;
    }

    input.setAttribute('aria-invalid', 'true');
    input.setCustomValidity(AVATAR_SIZE_ERROR);
    showAvatarSizeModal(input);
    return false;
}

function chooseAnotherAvatar() {
    const input = oversizedAvatarInput
        ?? document.querySelector('input[type="file"][name="avatar"]');

    closeModal('imageSizeModal');
    setTimeout(() => input?.click(), 100);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[type="file"][name="avatar"]').forEach(input => {
        const form = input.closest('form');
        if (!form) return;

        input.addEventListener('change', () => previewAvatar(input));

        form.addEventListener('submit', event => {
            if (!validateAvatarSize(input)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        });
    });
});

function previewAvatar(input) {
    const file = input.files?.[0];
    const preview = document.getElementById('avatar-preview');
    const placeholder = document.getElementById('avatar-placeholder');

    if (!file || !validateAvatarSize(input) || !preview) return;

    const reader = new FileReader();
    reader.onload = e => {
        preview.src = e.target.result;
        preview.classList.remove('hidden');
        placeholder?.classList.add('hidden');
    };
    reader.readAsDataURL(file);
}
