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
setInterval(_spawn, 1500);

function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    document.getElementById('toast-msg').innerText = message;
    toast.classList.toggle('bg-red-500', isError);
    toast.classList.toggle('bg-cyan-500', !isError);

    // Show
    toast.classList.remove('opacity-0', 'pointer-events-none');
    toast.classList.add('opacity-100');

    // Hide after 2.5s
    setTimeout(() => {
        toast.classList.remove('opacity-100');
        toast.classList.add('opacity-0', 'pointer-events-none');
    }, 2500);
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
    m.classList.remove('hidden');
    const f = m.querySelector('.portal-frame');
    if (f) { f.classList.remove('animate-fade-in'); void f.offsetWidth; f.classList.add('animate-fade-in'); }
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

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

    const section = new URLSearchParams(window.location.search).get('section');
    const visible = sections.find(item => !item.classList.contains('hidden'));
    const fallback = visible?.id.replace(/^sec-/, '') ?? 'stats';
    showSection(section || fallback);
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
