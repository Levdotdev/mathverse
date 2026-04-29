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
    showToast('User updated. Reloading...');
    closeModal('editUserModal');
    setTimeout(() => location.reload(), 1000);
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
    showToast('User purged. Reloading...');
    closeModal('deleteUserModal');
    setTimeout(() => location.reload(), 1000);
}