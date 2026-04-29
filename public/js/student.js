async function openRosterModal(classId, className) {
    document.getElementById('roster-modal-title').innerText = className + ' - Classmates';
    const tbody = document.getElementById('roster-tbody');
    tbody.innerHTML = '<tr><td colspan="2" class="text-center py-8 text-slate-500"><i class="fas fa-circle-notch fa-spin text-2xl"></i></td></tr>';
    openModal('viewClassRosterModal');

    const data = await fetch(`/student/class-roster/${classId}`).then(r => r.json());

    if (!data.length) {
        tbody.innerHTML = '<tr><td colspan="2" class="text-center py-6 text-slate-500 text-xs uppercase">No classmates found.</td></tr>';
        return;
    }

    tbody.innerHTML = data.map(m => `
        <tr class="border-b border-white/5 hover:bg-white/5">
            <td class="py-4 font-bold">
                <i class="fas fa-user-graduate text-slate-500 mr-2"></i>
                ${m.last_name ?? 'Unknown'}, ${m.first_name ?? 'Unknown'}
            </td>
            <td class="py-4 text-cyan-400 font-mono text-right">Level ${m.level ?? 1}</td>
        </tr>
    `).join('');
}

function openLeaveModal(classId) {
    document.getElementById('leave-class-id').value = classId;
    openModal('deleteUserModal');
}

document.addEventListener('DOMContentLoaded', () => {
    const section = new URLSearchParams(window.location.search).get('section');

    showSection(section || 'stats');
});