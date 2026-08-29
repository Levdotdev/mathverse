document.addEventListener('DOMContentLoaded', () => {
    const requested = new URLSearchParams(window.location.search).get('section') ?? 'overview';
    const allowed = ['overview', 'stats', 'students', 'teachers', 'role-verify', 'notifications', 'audit', 'reports', 'profile', 'password'];
    showSection(allowed.includes(requested) ? requested : 'overview');

    if (typeof applyChartDefaults === 'function') {
        applyChartDefaults();
    }

    if (requested === 'stats' && typeof loadAdminStats === 'function') {
        requestAnimationFrame(() => requestAnimationFrame(() => loadAdminStats()));
    }
});

function confirmDelete(id, name) {
    document.getElementById('deleteUserForm').action = `/admin/user/${id}`;
    document.getElementById('delete-user-name').textContent = name || 'Selected user';
    openModal('deleteUserModal');
}

function confirmSuspend(id, name, section) {
    document.getElementById('suspendUserForm').action = `/admin/user/${id}/suspend`;
    document.getElementById('suspend-user-name').textContent = name || 'Selected user';
    document.getElementById('suspend-return-section').value = section;
    document.getElementById('suspension-reason').value = '';
    openModal('suspendUserModal');
}
