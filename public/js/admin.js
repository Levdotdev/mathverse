// Search filter
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('admin-search')?.addEventListener('input', function () {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.user-row').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(term) ? '' : 'none';
        });
    });

    const section = new URLSearchParams(window.location.search).get('section');
    if (section) showSection(section);
});
    
// Edit user
let editingUserId = null;

function openEditModal(id, name, role) {
    editingUserId = id;
    document.getElementById('edit-u-name').value = name;
    document.getElementById('edit-u-role').value = role;
    openModal('editUserModal');
}

async function saveEditUser() {
    await fetch(`/admin/user/${editingUserId}`, {
        method:  'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body:    JSON.stringify({
            username: document.getElementById('edit-u-name').value,
            role:     document.getElementById('edit-u-role').value,
        })
    });
    window.location.href = '/admin/dashboard?section=user-lists';
}

// Delete user
let deletingUserId = null;

function confirmDelete(id) {
    deletingUserId = id;
    openModal('deleteUserModal');
}

async function executeDelete() {
    await fetch(`/admin/user/${deletingUserId}`, {
        method:  'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken() }
    });
    window.location.href = '/admin/dashboard?section=user-lists';
}

document.addEventListener('DOMContentLoaded', () => {
    const section = new URLSearchParams(window.location.search).get('section');

    showSection(section || 'overview');
});

function showSection(id) {
    document.querySelectorAll('.content-section').forEach(s => {
        s.classList.add('hidden');
        s.classList.remove('animate-fade-in');
    });

    document.querySelectorAll('.nav-link').forEach(b => {
        b.classList.remove('active');
    });

    const sec = document.getElementById('sec-' + id);

    if (!sec) {
        console.error('Missing section:', id);
        return;
    }

    sec.classList.remove('hidden');

    void sec.offsetWidth;

    sec.classList.add('animate-fade-in');

    const btn = document.getElementById('btn-' + id);

    if (btn) {
        btn.classList.add('active');
    } else {
        console.error('Missing button:', id);
    }

    if (window.innerWidth < 768) {
        toggleSidebar();
    }
}