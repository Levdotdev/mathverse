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
const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
let particleTimer = null;

function syncBackgroundMotion() {
    if (reducedMotionQuery.matches) {
        clearInterval(particleTimer);
        particleTimer = null;
        document.getElementById('particle-container')?.replaceChildren();
        return;
    }
    if (!particleTimer) particleTimer = setInterval(_spawn, 1500);
}

syncBackgroundMotion();
reducedMotionQuery.addEventListener?.('change', syncBackgroundMotion);

function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    document.getElementById('toast-msg').innerText = message;
    toast.setAttribute('role', isError ? 'alert' : 'status');
    toast.setAttribute('aria-live', isError ? 'assertive' : 'polite');
    toast.classList.toggle('bg-red-500', isError);
    toast.classList.toggle('bg-cyan-500', !isError);

    // Show
    toast.classList.remove('opacity-0', 'pointer-events-none');
    toast.classList.add('opacity-100');

    clearTimeout(globalThis.mathVerseToastTimer);
    globalThis.mathVerseToastTimer = setTimeout(() => {
        toast.classList.remove('opacity-100');
        toast.classList.add('opacity-0', 'pointer-events-none');
    }, 4500);
}

function tglPass(id, icoId) {
    const inp = document.getElementById(id);
    const ico = document.getElementById(icoId);
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

function copyToClipboard(text) {
    navigator.clipboard.writeText(text);
    showToast('Code Copied: ' + text);
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
    const modal = modals.at(-1);
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
    const last = focusable.at(-1);
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
});

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.toggle('hidden');
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

    return true;
}

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
