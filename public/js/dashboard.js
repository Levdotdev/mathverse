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

    if (window.innerWidth < 768) {
        document.getElementById('sidebar')
            .classList.add('-translate-x-full');

        document.getElementById('sidebar-overlay')
            .classList.add('hidden');
    }

    return true;
}

const profileMenu = document.getElementById('profileMenu');
const profileArrow = document.getElementById('profileArrow');
const profileBtn = document.querySelector('#sidebar button');

function toggleProfileMenu(e) {
    e.stopPropagation();
    profileMenu.classList.toggle('open');
    profileArrow.classList.toggle('rotate-180');
}

document.addEventListener('click', function (e) {
    const isClickInside =
        profileMenu.contains(e.target) ||
        profileBtn.contains(e.target);

    if (!isClickInside) {
        profileMenu.classList.remove('open');
        profileArrow.classList.remove('rotate-180');
    }
});
