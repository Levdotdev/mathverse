// ── Quiz builder ──────────────────────────────────────────

let editingQuizId = null;

function loadQuizBuilder(quizId = null) {
    editingQuizId = quizId;
    const form    = document.getElementById('quiz-form');
    const method  = document.getElementById('method-field');
    const title   = document.getElementById('builder-title');
    const saveBtn = document.getElementById('save-quiz-btn');
    const builder = document.getElementById('questions-builder');

    builder.innerHTML = '';

    if (quizId) {
        form.action       = `/teacher/quiz/${quizId}`;
        method.innerHTML  = `<input type="hidden" name="_method" value="PUT">`;
        title.innerHTML   = `Edit <span class="text-cyan-400">Assessment</span>`;
        saveBtn.innerHTML = `<i class="fas fa-check-circle mr-2"></i> Update Assessment`;
    } else {
        form.action       = '/teacher/quiz';
        method.innerHTML  = '';
        title.innerHTML   = `Create <span class="text-cyan-400">New Quiz</span>`;
        saveBtn.innerHTML = `<i class="fas fa-save mr-2"></i> Publish Assessment`;
        document.getElementById('q-topic').value       = '';
        document.getElementById('q-max-members').value = '50';
        document.getElementById('q-room-code').value   = Math.floor(1000 + Math.random() * 9000).toString();
        document.getElementById('q-class').value       = '';
        addNewQuestion();
    }

    toggleQuizView('create');
}

function toggleQuizView(view) {
    const list   = document.getElementById('quiz-list-container');
    const editor = document.getElementById('quiz-editor-container');
    if (view === 'create') {
        list.classList.add('hidden');
        editor.classList.remove('hidden');
        void editor.offsetWidth;
        editor.classList.add('animate-fade-in');
    } else {
        editor.classList.add('hidden');
        list.classList.remove('hidden');
    }
}

function addNewQuestion() {
    const n = document.getElementById('questions-builder').children.length + 1;
    addQuestionBlock(n, '', ['', '', '', ''], 0);
}

function addQuestionBlock(num, text, opts, correctIndex) {
    const container = document.getElementById('questions-builder');
    const idx       = container.children.length;
    const div       = document.createElement('div');
    div.className   = 'p-6 bg-black/40 border border-white/5 rounded relative question-block';
    div.innerHTML   = `
        <button type="button" onclick="this.parentElement.remove()"
                class="absolute top-4 right-4 text-slate-600 hover:text-red-500 transition-all">
            <i class="fas fa-trash"></i>
        </button>
        <div class="mb-4">
            <label class="input-label opacity-50">Problem ${num}</label>
            <div class="relative">
                <i class="fas fa-pen-fancy input-icon"></i>
                <input type="text" name="questions[${idx}][question]" value="${text}"
                       placeholder="Enter question..." class="input-mobile-ultra" required>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            ${[0,1,2,3].map(i => `
            <div class="relative">
                <i class="fas fa-circle input-icon !text-[8px]"></i>
                <input type="text" name="questions[${idx}][options][]"
                       value="${opts[i] || ''}" placeholder="Option ${i+1}" class="input-mobile-ultra">
            </div>`).join('')}
        </div>
        <div class="form-group">
            <label class="input-label text-green-400">Correct Answer</label>
            <select name="questions[${idx}][correct]"
                    class="input-mobile-ultra bg-slate-900 border-green-500/30 text-green-400">
                ${[0,1,2,3].map(i =>
                    `<option value="${i}" ${correctIndex === i ? 'selected' : ''}>Option ${i+1}</option>`
                ).join('')}
            </select>
        </div>`;
    container.appendChild(div);
}

// ── Results modal ─────────────────────────────────────────

async function openResultsModal(sessionId, topic) {
    document.getElementById('results-modal-title').innerText = topic + ' - Analytics';
    const tbody = document.getElementById('results-tbody');
    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-8 text-slate-500"><i class="fas fa-circle-notch fa-spin text-2xl"></i></td></tr>';
    openModal('viewResultsModal');

    const data = await fetch(`/teacher/quiz/${sessionId}/results`).then(r => r.json());

    if (!data.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-6 text-slate-500 text-xs uppercase">No attempts yet.</td></tr>';
        return;
    }

    tbody.innerHTML = data.map(r => {
        const name     = r.profiles ? `${r.profiles.last_name}, ${r.profiles.username}` : 'Unknown';
        const accuracy = r.total_questions > 0
            ? Math.round((r.correct_answers / r.total_questions) * 100) : 0;
        const color    = accuracy >= 75 ? 'text-green-500' : (accuracy >= 50 ? 'text-yellow-500' : 'text-red-500');
        return `
        <tr class="border-b border-white/5 hover:bg-white/5">
            <td class="py-4 font-bold">${name}</td>
            <td class="py-4 text-cyan-400 font-mono font-bold">${r.correct_answers} / ${r.total_questions}</td>
            <td class="py-4 font-mono ${color}">${accuracy}%</td>
            <td class="py-4 text-slate-500 text-xs">${new Date(r.created_at).toLocaleDateString()}</td>
        </tr>`;
    }).join('');
}

// ── Class roster modal ────────────────────────────────────

let currentClassId = null;

async function openRosterModal(classId, className) {
    currentClassId = classId;
    document.getElementById('manage-class-title').innerText = className + ' - Roster';
    openModal('manageClassModal');
    await fetchRoster(classId);
}

async function fetchRoster(classId) {
    const tbody = document.getElementById('class-roster-tbody');
    tbody.innerHTML = '<tr><td colspan="3" class="text-center py-8 text-slate-500"><i class="fas fa-circle-notch fa-spin text-2xl"></i></td></tr>';

    const data = await fetch(`/teacher/class/${classId}/roster`).then(r => r.json());

    if (!data.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center py-6 text-slate-500 text-xs uppercase">No students yet.</td></tr>';
        return;
    }

    tbody.innerHTML = data.map(m => {
        const s = m.profiles || {};
        return `
        <tr class="border-b border-white/5 hover:bg-white/5">
            <td class="py-4 font-bold">${s.last_name ?? 'Unknown'}, ${s.first_name ?? 'Unknown'}</td>
            <td class="py-4 text-slate-400">${s.email ?? 'N/A'}</td>
            <td class="py-4 text-right">
                <button onclick="removeStudent('${s.id}', '${classId}')"
                        class="text-red-500 hover:text-white text-[10px] font-bold uppercase">
                    <i class="fas fa-user-minus mr-1"></i> Remove
                </button>
            </td>
        </tr>`;
    }).join('');
}

async function removeStudent(studentId, classId) {
    if (!confirm('Remove this student?')) return;
    await fetch(`/teacher/class/${classId}/student/${studentId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken() }
    });
    showToast('Student removed.');
    fetchRoster(classId);
}

// ── Live lobby ────────────────────────────────────────────

let currentLobbyId = null;
let lobbyInterval  = null;

function openLobbyModal(sessionId, roomCode, topic, status) {
    currentLobbyId = sessionId;
    document.getElementById('lobby-title').innerText = topic + ' - Live Lobby';
    document.getElementById('lobby-code').innerText  = roomCode;

    const btn = document.getElementById('start-quiz-btn');
    if (status === 'active') {
        btn.innerHTML = '<i class="fas fa-stop mr-2"></i> Terminate Quiz';
        btn.onclick   = endQuiz;
    } else {
        btn.innerHTML = '<i class="fas fa-play mr-2"></i> Start Quiz Sequence';
        btn.onclick   = startQuiz;
    }

    openModal('liveLobbyModal');
    fetchLobbyParticipants();
    lobbyInterval = setInterval(fetchLobbyParticipants, 3000);
}

function closeLobbyModal() {
    clearInterval(lobbyInterval);
    closeModal('liveLobbyModal');
}

async function fetchLobbyParticipants() {
    const tbody = document.getElementById('lobby-tbody');
    const data  = await fetch(`/teacher/lobby/${currentLobbyId}`).then(r => r.json());

    if (!data.length) {
        tbody.innerHTML = '<tr><td class="p-6 text-center text-slate-500 text-xs uppercase">Awaiting connections...</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(p => {
        const s = p.profiles || {};
        return `<tr class="border-b border-white/5">
            <td class="p-4"><i class="fas fa-vr-cardboard text-purple-400 mr-3"></i>
                ${s.last_name ?? ''}, ${s.username ?? 'Unknown'}
            </td>
            <td class="p-4 text-right text-cyan-400 font-mono text-xs">Level ${s.level ?? 1}</td>
        </tr>`;
    }).join('');
}

async function startQuiz() {
    if (!confirm('Start the quiz for all connected students?')) return;
    await fetch(`/teacher/quiz/${currentLobbyId}/start`, {
        method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken() }
    });
    showToast('Quiz Sequence Initiated!');
    closeLobbyModal();
}

async function endQuiz() {
    if (!confirm('Terminate the quiz?')) return;
    await fetch(`/teacher/quiz/${currentLobbyId}/end`, {
        method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken() }
    });
    showToast('Quiz Terminated.');
    closeLobbyModal();
}

// Auto-open section from URL ?section= param
document.addEventListener('DOMContentLoaded', () => {
    const section = new URLSearchParams(window.location.search).get('section');
    if (section) showSection(section);
});