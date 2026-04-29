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
    document.querySelectorAll('.content-section').forEach(s => {
        s.classList.add('hidden');
        s.classList.remove('animate-fade-in');
    });
    document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
    const sec = document.getElementById('sec-' + id);
    sec.classList.remove('hidden');
    void sec.offsetWidth;
    sec.classList.add('animate-fade-in');
    document.getElementById('btn-' + id).classList.add('active');
    if (window.innerWidth < 768) toggleSidebar();
}

// Read CSRF token from meta tag (set in layout)
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}