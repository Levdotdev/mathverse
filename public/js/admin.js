document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-registry-search]').forEach(input => {
        input.addEventListener('input', () => {
            const registry = input.dataset.registrySearch;
            const term = input.value.toLowerCase().trim();
            document.querySelectorAll(`.registry-row[data-registry="${registry}"]`).forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(term) ? '' : 'none';
            });
            syncRegistryClearButton(registry);
        });
    });

    document.querySelectorAll('[data-registry-clear]').forEach(button => {
        button.addEventListener('click', () => {
            const registry = button.dataset.registryClear;
            const search = document.querySelector(`[data-registry-search="${registry}"]`);
            const grade = registry === 'students'
                ? document.getElementById('student-grade-filter')
                : null;

            if (grade?.value) {
                window.location.href = '/admin/dashboard?section=students';
                return;
            }

            if (search) {
                search.value = '';
                search.dispatchEvent(new Event('input'));
            }
            syncRegistryClearButton(registry);
        });
    });

    document.getElementById('student-grade-filter')?.addEventListener('change', event => {
        const grade = event.target.value;
        const params = new URLSearchParams({ section: 'students' });
        if (grade) params.set('grade', grade);
        window.location.href = `/admin/dashboard?${params.toString()}`;
    });

    const requested = new URLSearchParams(window.location.search).get('section') ?? 'overview';
    const allowed = ['overview', 'stats', 'students', 'teachers', 'role-verify', 'reports', 'profile', 'password'];
    showSection(allowed.includes(requested) ? requested : 'overview');

    if (typeof applyChartDefaults === 'function') {
        applyChartDefaults();
    }

    if (requested === 'stats' && typeof loadAdminStats === 'function') {
        requestAnimationFrame(() => requestAnimationFrame(() => loadAdminStats()));
    }

    syncRegistryClearButton('students');
    syncRegistryClearButton('teachers');
});

function syncRegistryClearButton(registry) {
    const search = document.querySelector(`[data-registry-search="${registry}"]`);
    const grade = registry === 'students'
        ? document.getElementById('student-grade-filter')
        : null;
    const button = document.querySelector(`[data-registry-clear="${registry}"]`);
    const hasFilters = Boolean(search?.value.trim() || grade?.value);

    button?.classList.toggle('hidden', !hasFilters);
}

function confirmDelete(id, name) {
    document.getElementById('deleteUserForm').action = `/admin/user/${id}`;
    document.getElementById('delete-user-name').textContent = name || 'Selected user';
    openModal('deleteUserModal');
}
