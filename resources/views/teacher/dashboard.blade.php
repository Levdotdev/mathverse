@extends('layouts.dashboard')

@section('title', 'Teacher Hub')
@section('sidebar-subtitle', 'Instructional Hub')
@section('mobile-title', 'Overview')

@section('sidebar-nav')
<button onclick="showSection('overview')"     class="nav-link active w-full" id="btn-overview">
    <i class="fas fa-satellite-dish mr-3 w-5 text-cyan-400"></i> Dashboard
</button>
<button onclick="showSection('quiz-creator')" class="nav-link w-full" id="btn-quiz-creator">
    <i class="fas fa-vr-cardboard mr-3 w-5 text-purple-400"></i> VR Quiz Bees
</button>
<button onclick="showSection('classes')"      class="nav-link w-full" id="btn-classes">
    <i class="fas fa-chalkboard mr-3 w-5 text-yellow-400"></i> Classrooms
</button>
<button onclick="showSection('student-list')" class="nav-link w-full" id="btn-student-list">
    <i class="fas fa-users mr-3 w-5 text-green-400"></i> Student List
</button>
<button onclick="showSection('profile')"      class="nav-link w-full" id="btn-profile">
    <i class="fas fa-id-card mr-3 w-5 text-blue-400"></i> Profile Settings
</button>
@endsection

@section('dashboard-content')

{{-- OVERVIEW --}}
<section id="sec-overview" class="content-section">
    <h2 class="text-xl md:text-2xl font-orbitron font-bold mb-6 uppercase border-b border-white/10 pb-2">
        System <span class="text-cyan-400">Overview</span>
    </h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
        <div class="portal-frame !p-5 border-l-2 border-cyan-500 flex justify-between items-center">
            <div>
                <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Total Students</p>
                <h3 class="text-2xl font-orbitron mt-1">{{ $studentCount }}</h3>
            </div>
            <i class="fas fa-user-graduate text-3xl opacity-10"></i>
        </div>
        <div class="portal-frame !p-5 border-l-2 border-purple-500 flex justify-between items-center">
            <div>
                <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Quizzes Created</p>
                <h3 class="text-2xl font-orbitron mt-1">{{ $quizCount }}</h3>
            </div>
            <i class="fas fa-scroll text-3xl opacity-10"></i>
        </div>
    </div>

    <div class="portal-frame !p-5">
        <h4 class="font-orbitron text-xs text-cyan-400 uppercase tracking-widest mb-4">
            <i class="fas fa-history mr-2"></i> Recent Quiz Sessions
        </h4>
        <div class="space-y-1">
            @forelse($recentQuizzes as $q)
                <div class="flex justify-between items-center border-b border-white/5 py-3 text-sm hover:bg-white/5 px-2 rounded">
                    <div>
                        <span class="font-bold text-white block sm:inline">{{ $q['topic'] }}</span>
                        <span class="text-[10px] text-cyan-500 font-mono sm:ml-2 block sm:inline">
                            Code: {{ $q['room_code'] }}
                        </span>
                    </div>
                    <button onclick="openResultsModal('{{ $q['id'] }}', '{{ addslashes($q['topic']) }}')"
                            class="bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 hover:bg-cyan-500 hover:text-black transition-all px-4 py-1.5 rounded text-[10px] font-bold uppercase shrink-0">
                        View
                    </button>
                </div>
            @empty
                <p class="text-slate-500 text-xs py-2">No quiz sessions found.</p>
            @endforelse
        </div>
    </div>
</section>

{{-- QUIZ CREATOR --}}
<section id="sec-quiz-creator" class="content-section hidden">

    {{-- Quiz list view --}}
    <div id="quiz-list-container">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
            <h2 class="text-xl font-orbitron font-bold uppercase">
                VR Quiz <span class="text-purple-400">Bees</span>
            </h2>
            <button onclick="loadQuizBuilder()"
                    class="btn-rect-primary sm:!w-auto px-6">
                <i class="fas fa-plus mr-2"></i> Create New
            </button>
        </div>

        <div class="space-y-3">
            @forelse($allQuizzes as $q)
                <div class="portal-frame !p-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 hover:border-cyan-500/50 transition-colors">
                    <div class="flex items-center gap-4 w-full sm:w-auto">
                        <div class="w-12 h-12 bg-purple-500/10 border border-purple-500/20 rounded flex items-center justify-center shrink-0">
                            <i class="fas fa-vr-cardboard text-purple-400 text-xl"></i>
                        </div>
                        <div>
                            <p class="font-bold text-lg text-white mb-1">{{ $q['topic'] }}</p>
                            <div class="flex flex-wrap items-center gap-3 text-[10px] font-mono text-slate-400">
                                <span class="bg-cyan-500/10 text-cyan-400 px-2 py-0.5 rounded font-bold">
                                    VR CODE: {{ $q['room_code'] }}
                                </span>
                                <button onclick="copyToClipboard('{{ $q['room_code'] }}')" class="hover:text-white">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <span>|</span>
                                <span><i class="far fa-calendar-alt mr-1"></i>
                                    {{ \Carbon\Carbon::parse($q['created_at'])->format('M d, Y') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <button onclick="openLobbyModal('{{ $q['id'] }}','{{ $q['room_code'] }}','{{ addslashes($q['topic']) }}','{{ $q['status'] ?? 'waiting' }}')"
                                class="flex-1 sm:flex-none btn-rect-secondary !py-2 !px-4 text-[10px] !border-green-500/30 hover:!bg-green-500/10 hover:!text-green-400">
                            <i class="fas fa-play sm:mr-1"></i><span class="sm:hidden"> Host</span>
                        </button>
                        <button onclick="openResultsModal('{{ $q['id'] }}','{{ addslashes($q['topic']) }}')"
                                class="flex-1 sm:flex-none btn-rect-secondary !py-2 !px-4 text-[10px] !border-cyan-500/30 hover:!bg-cyan-500/10 hover:!text-cyan-400">
                            <i class="fas fa-chart-bar sm:mr-1"></i><span class="sm:hidden"> Results</span>
                        </button>
                        <button onclick="loadQuizBuilder('{{ $q['id'] }}')"
                                class="flex-1 sm:flex-none btn-rect-secondary !py-2 !px-4 text-[10px] !border-purple-500/30 hover:!bg-purple-500/10 hover:!text-purple-400">
                            <i class="fas fa-edit sm:mr-1"></i><span class="sm:hidden"> Edit</span>
                        </button>
                        <button type="button"
                            onclick="openDeleteQuizModal('{{ $q['id'] }}')"
                            class="w-full btn-rect-secondary !py-2 !px-4 text-[10px] !border-red-500/30 hover:!bg-red-500/10 hover:!text-red-500">

                            <i class="fas fa-trash-alt sm:mr-1"></i>
                            <span class="sm:hidden"> Delete</span>

                        </button>
                    </div>
                </div>
            @empty
                <div class="portal-frame !p-8 text-center text-slate-500 uppercase text-xs tracking-widest">
                    No VR Quiz Bees created yet.
                </div>
            @endforelse
        </div>
    </div>

    {{-- Quiz builder/editor (hidden by default, shown via JS) --}}
    <div id="quiz-editor-container" class="hidden">
        <div class="portal-frame !p-6 md:!p-8 relative">
            <button onclick="toggleQuizView('list')" class="absolute top-4 right-4 text-slate-500 hover:text-white">
                <i class="fas fa-times-circle"></i>
            </button>
            <h3 id="builder-title" class="text-lg font-orbitron font-bold mb-6 uppercase">
                Assessment <span class="text-cyan-400">Builder</span>
            </h3>

            {{-- The form posts to /teacher/quiz (POST for new, PUT for edit via hidden _method) --}}
            <form id="quiz-form" method="POST" action="/teacher/quiz">
                @csrf
                <span id="method-field"></span>{{-- filled by JS for PUT --}}

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div class="form-group">
                        <label class="input-label">Quiz Topic</label>
                        <div class="relative">
                            <i class="fas fa-tag input-icon"></i>
                            <input type="text" name="topic" id="q-topic" placeholder="e.g. Addition"
                                   class="input-mobile-ultra" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="input-label">Max Participants</label>
                        <div class="relative">
                            <i class="fas fa-users-cog input-icon"></i>
                            <input type="number" name="max_members" id="q-max-members"
                                   value="50" min="1" class="input-mobile-ultra !pl-[42px]">
                        </div>
                    </div>
                </div>

                <div class="form-group mb-4">
                    <label class="input-label text-yellow-400">Assign to Class (Optional)</label>
                    <div class="relative">
                        <i class="fas fa-users input-icon"></i>
                        <select name="class_id" id="q-class"
                                class="input-mobile-ultra bg-slate-900 border-yellow-500/30 text-yellow-400">
                            <option value="">-- Global Assessment (No Class) --</option>
                            @foreach($classes as $c)
                                <option value="{{ $c['id'] }}">{{ $c['class_name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group mb-8">
                    <label class="input-label">Access Code</label>
                    <div class="relative">
                        <i class="fas fa-barcode input-icon"></i>
                        <input type="text" name="room_code" id="q-room-code" readonly
                               class="input-mobile-ultra !bg-white/5 text-cyan-400 font-bold pr-12">
                        <button type="button" onclick="copyToClipboard(document.getElementById('q-room-code').value)"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-cyan-500 hover:text-white">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div id="questions-builder" class="space-y-6"></div>

                <div class="mt-8 flex flex-col sm:flex-row gap-4">
                    <button type="button" onclick="addNewQuestion()"
                            class="btn-rect-secondary flex-1">Add Question</button>
                    <button type="submit" id="save-quiz-btn"
                            class="btn-rect-primary flex-1">Save Assessment</button>
                </div>
            </form>
        </div>
    </div>
</section>

{{-- CLASSROOMS --}}
<section id="sec-classes" class="content-section hidden">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
        <h2 class="text-xl font-orbitron font-bold uppercase">My <span class="text-yellow-400">Classrooms</span></h2>
        <button onclick="openModal('createClassModal')"
                class="btn-rect-primary sm:!w-auto px-6 !bg-yellow-500 !text-black">
            <i class="fas fa-plus mr-2"></i> Create Class
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($classes as $c)
            <div class="portal-frame !p-6 flex flex-col justify-between hover:border-yellow-500/50 transition-all group">
                <div>
                    <div class="flex justify-between items-start">
                        <div class="w-12 h-12 bg-yellow-500/10 rounded flex items-center justify-center text-yellow-400 text-xl mb-4 group-hover:scale-110 transition-transform">
                            <i class="fas fa-chalkboard"></i>
                        </div>
                        <button type="button"
                            onclick="openDeleteClassModal('{{ $c['id'] }}')"
                            class="text-slate-500 hover:text-red-500 transition-colors">

                            <i class="fas fa-trash-alt"></i>

                        </button>
                    </div>
                    <h3 class="font-bold text-lg mb-1">{{ $c['class_name'] }}</h3>
                </div>
                <div class="mt-4 pt-4 border-t border-white/5 flex flex-col gap-3">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-[9px] text-slate-500 uppercase tracking-widest font-bold">Join Code</p>
                            <p class="font-mono text-cyan-400 font-bold tracking-widest">{{ $c['join_code'] }}</p>
                        </div>
                        <button onclick="copyToClipboard('{{ $c['join_code'] }}')"
                                class="text-slate-400 hover:text-white">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <button onclick="openRosterModal('{{ $c['id'] }}', '{{ addslashes($c['class_name']) }}')"
                            class="btn-rect-secondary !py-2 !text-[10px] !border-yellow-500/30 hover:!bg-yellow-500/10 hover:!text-yellow-400 w-full">
                        <i class="fas fa-users mr-2"></i> Manage Roster
                    </button>
                </div>
            </div>
        @empty
            <div class="portal-frame !p-8 text-center text-slate-500 uppercase text-xs tracking-widest sm:col-span-2 lg:col-span-3">
                No classes created yet.
            </div>
        @endforelse
    </div>
</section>

{{-- STUDENT LIST --}}
<section id="sec-student-list" class="content-section hidden">
    <div class="portal-frame !p-6 md:!p-8">
        <h2 class="text-xl font-orbitron font-bold mb-6 uppercase">
            Student <span class="text-cyan-400">Registry</span>
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[500px]">
                <thead class="text-slate-500 text-[10px] uppercase border-b border-white/5">
                    <tr>
                        <th class="pb-4">Email</th>
                        <th class="pb-4">First Name</th>
                        <th class="pb-4">Last Name</th>
                        <th class="pb-4">Grade</th>
                    </tr>
                </thead>
                <tbody class="text-sm font-rajdhani text-white">
                    @forelse($students as $s)
                        <tr class="border-b border-white/5 hover:bg-white/5">
                            <td class="py-4 font-mono text-cyan-500">{{ $s['email'] ?? '—' }}</td>
                            <td class="py-4">{{ $s['first_name'] ?? '—' }}</td>
                            <td class="py-4">{{ $s['last_name'] ?? '—' }}</td>
                            <td class="py-4">Grade {{ $s['grade_level'] ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-slate-500 text-xs uppercase">No students found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

{{-- PROFILE --}}
<section id="sec-profile" class="content-section hidden">
    <div class="portal-frame !p-6 md:!p-10">
        <h2 class="text-xl font-orbitron font-bold mb-10 uppercase">
            <i class="fas fa-user-circle mr-2"></i> Profile <span class="text-cyan-400">Settings</span>
        </h2>
        <form method="POST" action="/teacher/profile" class="space-y-6 max-w-2xl mx-auto">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="form-group">
                    <label class="input-label">First Name</label>
                    <div class="relative">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="first_name" value="{{ $user['username'] ?? '' }}"
                               class="input-mobile-ultra" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="input-label">Last Name</label>
                    <div class="relative">
                        <i class="fas fa-id-card input-icon"></i>
                        <input type="text" name="last_name" value="{{ $user['last_name'] ?? '' }}"
                               class="input-mobile-ultra" required>
                    </div>
                </div>
                <div class="form-group sm:col-span-2 border-t border-white/10 pt-4 mt-2">
                    <label class="input-label text-orange-400">Current Password (only if you wish to change password)</label>
                    <div class="relative">
                        <i class="fas fa-unlock-alt input-icon"></i>
                        <input type="password" id="t-curr-pass" name="current_password"
                               class="input-mobile-ultra pr-12" placeholder="Enter current password">
                        <button type="button" onclick="tglPass('t-curr-pass','t-ico-curr')"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
                            <i id="t-ico-curr" class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="input-label">New Password</label>
                    <div class="relative">
                        <i class="fas fa-key input-icon"></i>
                        <input type="password" id="t-new-pass" name="new_password"
                               class="input-mobile-ultra pr-12" placeholder="••••••••">
                        <button type="button" onclick="tglPass('t-new-pass','t-ico-new')"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
                            <i id="t-ico-new" class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="input-label">Confirm Password</label>
                    <div class="relative">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="t-conf-pass" name="new_password_confirmation"
                               class="input-mobile-ultra pr-12" placeholder="••••••••">
                        <button type="button" onclick="tglPass('t-conf-pass','t-ico-conf')"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
                            <i id="t-ico-conf" class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-rect-primary mt-4">
                <i class="fas fa-save mr-2"></i> Update Profile
            </button>
        </form>
    </div>
</section>

@endsection

{{-- ── MODALS ─────────────────────────────────────────── --}}
@section('modals')

<div id="createClassModal" class="modal-overlay hidden">
    <div class="portal-frame !p-8 w-full max-w-sm text-center border-yellow-500/30">
        <i class="fas fa-chalkboard text-4xl text-yellow-400 mb-4"></i>
        <h3 class="font-orbitron font-bold mb-6 uppercase text-white">Create <span class="text-yellow-400">Class</span></h3>
        <form method="POST" action="/teacher/class">
            @csrf
            <div class="form-group text-left mb-6">
                <label class="input-label">Class Name</label>
                <input type="text" name="class_name" placeholder="e.g. Section A - Algebra"
                       class="input-mobile-ultra !pl-4" required>
            </div>
            <button type="submit" class="btn-rect-primary !bg-yellow-500 !text-black uppercase text-xs">
                Create & Generate Code
            </button>
        </form>
        <button onclick="closeModal('createClassModal')"
                class="text-[10px] font-bold mt-4 uppercase text-slate-500 block w-full">Cancel</button>
    </div>
</div>

<div id="viewResultsModal" class="modal-overlay hidden">
    <div class="portal-frame !p-6 md:!p-8 w-full max-w-2xl text-left border-cyan-500/30">
        <div class="flex justify-between items-center border-b border-white/10 pb-4 mb-6">
            <h3 class="font-orbitron font-bold uppercase text-lg text-cyan-400" id="results-modal-title">Quiz Results</h3>
            <button onclick="closeModal('viewResultsModal')" class="text-slate-500 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="overflow-x-auto max-h-96">
            <table class="w-full text-left min-w-[400px]">
                <thead class="text-slate-500 text-[10px] uppercase border-b border-white/5">
                    <tr>
                        <th class="pb-4">Student Name</th>
                        <th class="pb-4">Score</th>
                        <th class="pb-4">Accuracy</th>
                        <th class="pb-4">Date</th>
                    </tr>
                </thead>
                <tbody id="results-tbody" class="text-sm font-rajdhani text-white"></tbody>
            </table>
        </div>
        <button onclick="closeModal('viewResultsModal')" class="btn-rect-secondary mt-6 w-full text-xs">Close Panel</button>
    </div>
</div>

<div id="manageClassModal" class="modal-overlay hidden">
    <div class="portal-frame !p-6 md:!p-8 w-full max-w-2xl text-left border-yellow-500/30">
        <div class="flex justify-between items-center border-b border-white/10 pb-4 mb-6">
            <h3 class="font-orbitron font-bold uppercase text-lg text-yellow-400" id="manage-class-title">Class Roster</h3>
            <button onclick="closeModal('manageClassModal')" class="text-slate-500 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="overflow-x-auto max-h-96">
            <table class="w-full text-left min-w-[400px]">
                <thead class="text-slate-500 text-[10px] uppercase border-b border-white/5">
                    <tr>
                        <th class="pb-4">Student Name</th>
                        <th class="pb-4">Email</th>
                        <th class="pb-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody id="class-roster-tbody" class="text-sm font-rajdhani text-white"></tbody>
            </table>
        </div>
        <button onclick="closeModal('manageClassModal')" class="btn-rect-secondary mt-6 w-full text-xs">Close Panel</button>
    </div>
</div>

<div id="liveLobbyModal" class="modal-overlay hidden">
    <div class="portal-frame !p-6 md:!p-8 w-full max-w-2xl text-left border-purple-500/30">
        <div class="flex justify-between items-center border-b border-white/10 pb-4 mb-6">
            <div>
                <h3 class="font-orbitron font-bold uppercase text-lg text-purple-400" id="lobby-title">Live Lobby</h3>
                <p class="text-[10px] text-slate-500 font-mono tracking-widest mt-1">
                    VR ROOM CODE: <span id="lobby-code" class="text-white font-bold text-sm bg-white/10 px-2 py-1 rounded ml-1">----</span>
                </p>
            </div>
            <button onclick="closeLobbyModal()" class="text-slate-500 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="flex justify-between items-center mb-4">
            <h4 class="font-orbitron text-xs text-white uppercase tracking-widest">Waiting Room</h4>
            <button onclick="fetchLobbyParticipants()" class="text-cyan-400 text-[10px] hover:text-white">
                <i class="fas fa-sync-alt mr-1"></i> Refresh
            </button>
        </div>
        <div class="overflow-y-auto max-h-64 mb-6 border border-white/5 bg-black/40 rounded">
            <table class="w-full text-left">
                <tbody id="lobby-tbody" class="text-sm font-rajdhani text-white"></tbody>
            </table>
        </div>
        <div class="flex flex-col sm:flex-row gap-2 mt-6">
            <button id="start-quiz-btn" class="btn-rect-primary !bg-purple-600 !text-white flex-1">
                <i class="fas fa-play mr-2"></i> Start Quiz Sequence
            </button>
            <button onclick="closeLobbyModal()" class="btn-rect-secondary flex-1 sm:flex-none sm:w-1/3">Close</button>
        </div>
    </div>
</div>

<div id="deleteQuizModal" class="modal-overlay hidden">
    <div class="portal-frame !p-10 w-full max-w-xs text-center border-red-500/50">
        <i class="fas fa-trash-alt text-4xl text-red-600 mb-4"></i>

        <h3 class="font-orbitron font-bold mb-2 uppercase text-white">
            Delete Quiz?
        </h3>

        <p class="text-[10px] text-slate-500 mb-8 uppercase">
            This removes your quiz data permanently.
        </p>

        <div class="flex flex-col gap-2">

            <form id="deleteQuizForm" method="POST">
                @csrf
                @method('DELETE')

                <button type="submit"
                    class="btn-rect-primary !bg-red-600 !text-white uppercase text-xs">
                    Delete Quiz
                </button>
            </form>

            <button onclick="closeModal('deleteQuizModal')"
                class="text-[10px] font-bold mt-4 uppercase text-slate-500">
                Cancel
            </button>

        </div>
    </div>
</div>

<div id="deleteClassModal" class="modal-overlay hidden">
    <div class="portal-frame !p-10 w-full max-w-xs text-center border-red-500/50">
        <i class="fas fa-trash-alt text-4xl text-red-600 mb-4"></i>

        <h3 class="font-orbitron font-bold mb-2 uppercase text-white">
            Disband Class?
        </h3>

        <p class="text-[10px] text-slate-500 mb-8 uppercase">
            This permanently removes the class and its data.
        </p>

        <div class="flex flex-col gap-2">

            <form id="deleteClassForm" method="POST">
                @csrf
                @method('DELETE')

                <button type="submit"
                    class="btn-rect-primary !bg-red-600 !text-white uppercase text-xs">
                    Disband Class
                </button>
            </form>

            <button onclick="closeModal('deleteClassModal')"
                class="text-[10px] font-bold mt-4 uppercase text-slate-500">
                Cancel
            </button>

        </div>
    </div>
</div>

<div id="removeStudentModal" class="modal-overlay hidden">
    <div class="portal-frame !p-10 w-full max-w-xs text-center border-red-500/50">
        <i class="fas fa-user-minus text-4xl text-red-600 mb-4"></i>

        <h3 class="font-orbitron font-bold mb-2 uppercase text-white">
            Remove Student?
        </h3>

        <p class="text-[10px] text-slate-500 mb-8 uppercase">
            This removes the student from the class.
        </p>

        <div class="flex flex-col gap-2">

            <button id="confirmRemoveStudentBtn"
                class="btn-rect-primary !bg-red-600 !text-white uppercase text-xs">
                Remove Student
            </button>

            <button onclick="closeModal('removeStudentModal')"
                class="text-[10px] font-bold mt-4 uppercase text-slate-500">
                Cancel
            </button>

        </div>
    </div>
</div>

<div id="logoutModal" class="modal-overlay hidden">
    <div class="portal-frame !p-10 w-full max-w-xs text-center shadow-[0_0_100px_rgba(0,0,0,1)]">
        <i class="fas fa-sign-out-alt text-4xl text-cyan-400 mb-6"></i>
        <h3 class="font-orbitron font-bold text-white mb-2 uppercase">Are you sure you want to log out?</h3>
        <p class="text-xs text-slate-500 uppercase mb-8">You will need to log in again to access your account.</p>
        <div class="space-y-3">
            <form id="logoutForm" method="POST" action="/logout" class="mt-10">
                @csrf
                <button onclick="handleLogout()" class="btn-rect-primary !py-3">Confirm Logout</button>
            </form>
            <button onclick="closeModal('logoutModal')" class="w-full text-[10px] font-bold text-slate-500 uppercase">Cancel</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('js/teacher.js') }}"></script>
@endpush