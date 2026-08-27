let lobbyTimer = null;
let currentLobbyUrl = null;
let pendingQuizAction = null;

function openLobby(classId, sessionId, topic, code) {
    currentLobbyUrl = `/teacher/classes/${classId}/quizzes/${sessionId}/lobby`;
    document.getElementById('lobby-title').textContent = `${topic} - Lobby`;
    document.getElementById('lobby-code').textContent = code;
    openModal('liveLobbyModal');
    fetchLobby();
    clearInterval(lobbyTimer);
    lobbyTimer = setInterval(fetchLobby, 3000);
}

async function fetchLobby() {
    if (!currentLobbyUrl) return;
    const body = document.getElementById('lobby-tbody');

    try {
        const response = await fetch(currentLobbyUrl);
        if (!response.ok) throw new Error('The lobby could not be loaded.');
        const participants = await response.json();

        if (!participants.length) {
            body.innerHTML = '<tr><td class="p-8 text-center text-slate-500 text-xs uppercase">Waiting for students to connect...</td></tr>';
            return;
        }

        body.innerHTML = participants.map((participant) => {
            const profile = participant.profiles ?? {};
            const name = `${profile.last_name ?? ''}, ${profile.first_name ?? 'Unknown'}`;
            return `<tr class="border-b border-white/5">
                <td class="p-4"><i class="fas fa-vr-cardboard text-purple-400 mr-3"></i>${escapeClassroomHtml(name)}</td>
                <td class="p-4 text-right text-cyan-400 font-mono text-xs">Level ${Number(profile.level ?? 1)}</td>
            </tr>`;
        }).join('');
    } catch (error) {
        body.innerHTML = `<tr><td class="p-8 text-center text-red-400 text-xs">${escapeClassroomHtml(error.message)}</td></tr>`;
    }
}

function closeLobby() {
    clearInterval(lobbyTimer);
    lobbyTimer = null;
    currentLobbyUrl = null;
    closeModal('liveLobbyModal');
}

async function openResults(classId, sessionId, topic) {
    document.getElementById('results-modal-title').textContent = `${topic} - Analytics`;
    const body = document.getElementById('results-tbody');
    body.innerHTML = '<tr><td colspan="4" class="py-8 text-center text-slate-500"><i class="fas fa-circle-notch fa-spin text-2xl"></i></td></tr>';
    openModal('viewResultsModal');

    try {
        const response = await fetch(`/teacher/classes/${classId}/quizzes/${sessionId}/results`);
        if (!response.ok) throw new Error('Quiz analytics could not be loaded.');
        const results = await response.json();

        if (!results.length) {
            body.innerHTML = '<tr><td colspan="4" class="py-8 text-center text-slate-500 text-xs uppercase">No attempts yet.</td></tr>';
            return;
        }

        body.innerHTML = results.map((result) => {
            const profile = result.profiles ?? {};
            const total = Number(result.total_questions ?? 0);
            const correct = Number(result.correct_answers ?? 0);
            const accuracy = total > 0 ? Math.round((correct / total) * 100) : 0;
            const color = accuracy >= 75 ? 'text-green-400' : (accuracy >= 50 ? 'text-yellow-400' : 'text-red-400');
            const name = `${profile.last_name ?? 'Unknown'}, ${profile.first_name ?? 'Unknown'}`;

            return `<tr class="border-b border-white/5">
                <td class="py-4 font-bold">${escapeClassroomHtml(name)}</td>
                <td class="py-4 text-cyan-400 font-mono">${correct} / ${total}</td>
                <td class="py-4 font-bold ${color}">${accuracy}%</td>
                <td class="py-4 text-slate-500 text-xs">${new Date(result.created_at).toLocaleDateString()}</td>
            </tr>`;
        }).join('');
    } catch (error) {
        body.innerHTML = `<tr><td colspan="4" class="py-8 text-center text-red-400 text-xs">${escapeClassroomHtml(error.message)}</td></tr>`;
    }
}

function openQuizAction(classId, sessionId, action, topic) {
    pendingQuizAction = { classId, sessionId, action };
    const isEnd = action === 'end';
    const icon = document.getElementById('quiz-action-icon');
    const button = document.getElementById('confirmQuizAction');

    document.getElementById('quiz-action-title').textContent = isEnd ? 'End Quiz?' : 'Start Quiz?';
    document.getElementById('quiz-action-topic').textContent = topic;
    icon.className = `fas ${isEnd ? 'fa-stop text-red-400' : 'fa-play text-green-400'} text-4xl mb-4`;
    button.textContent = isEnd ? 'End Quiz' : 'Start Quiz';
    button.classList.toggle('!bg-red-600', isEnd);
    button.classList.toggle('!text-white', isEnd);
    openModal('quizActionModal');
}

document.getElementById('confirmQuizAction')?.addEventListener('click', async () => {
    if (!pendingQuizAction) return;
    const { classId, sessionId, action } = pendingQuizAction;
    const button = document.getElementById('confirmQuizAction');
    button.disabled = true;
    button.classList.add('opacity-50');

    try {
        const response = await fetch(`/teacher/classes/${classId}/quizzes/${sessionId}/${action}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message ?? 'The quiz status could not be changed.');
        window.location.reload();
    } catch (error) {
        showToast(error.message, true);
        button.disabled = false;
        button.classList.remove('opacity-50');
        closeModal('quizActionModal');
    }
});

function openSessionReport(sessionId, topic) {
    document.getElementById('quiz-report-topic').textContent = topic;
    document.getElementById('quiz-report-pdf').href = `/teacher/report/quiz/${sessionId}?format=pdf`;
    document.getElementById('quiz-report-csv').href = `/teacher/report/quiz/${sessionId}?format=csv`;
    openModal('quizReportModal');
}

function openRemoveStudent(classId, studentId, name) {
    document.getElementById('removeStudentForm').action = `/teacher/classes/${classId}/students/${studentId}`;
    document.getElementById('remove-student-name').textContent = name;
    openModal('removeStudentModal');
}

function escapeClassroomHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
