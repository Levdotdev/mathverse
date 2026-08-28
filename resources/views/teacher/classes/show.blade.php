@extends('layouts.dashboard')

@section('title', $class['class_name'])
@section('sidebar-subtitle', 'Instructional Hub')
@section('mobile-title', $class['class_name'])

@section('sidebar-nav')
    @include('teacher.partials.sidebar-nav', ['activePage' => 'classes'])
@endsection

@section('dashboard-content')
@php
    $iconMap = [
        'chalkboard' => 'fa-chalkboard', 'calculator' => 'fa-calculator',
        'rocket' => 'fa-rocket', 'atom' => 'fa-atom',
        'shapes' => 'fa-shapes', 'gamepad' => 'fa-gamepad',
    ];
    $patternStyles = [
        'grid' => 'linear-gradient(rgba(255,255,255,.08) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.08) 1px, transparent 1px); background-size: 24px 24px;',
        'stars' => 'radial-gradient(circle at 20% 30%, rgba(255,255,255,.35) 1px, transparent 2px), radial-gradient(circle at 75% 55%, rgba(255,255,255,.25) 1px, transparent 2px); background-size: 44px 44px;',
        'circuit' => 'linear-gradient(90deg, transparent 48%, rgba(255,255,255,.12) 49%, rgba(255,255,255,.12) 51%, transparent 52%); background-size: 38px 38px;',
        'waves' => 'radial-gradient(ellipse at bottom, rgba(255,255,255,.16), transparent 65%);',
        'plain' => 'none;',
    ];
    $themeColor = $customization['theme_color'];
    $classIcon = $iconMap[$customization['icon']] ?? 'fa-chalkboard';
    $patternStyle = $patternStyles[$customization['banner_pattern']] ?? $patternStyles['grid'];
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <a href="/teacher/dashboard?section=classes" class="text-xs text-slate-400 hover:text-white font-bold uppercase">
        <i class="fas fa-arrow-left mr-2"></i> All Classes
    </a>
    <a href="/teacher/classes/{{ $class['id'] }}/settings" class="btn-rect-secondary !py-2 !px-4 !w-auto">
        <i class="fas fa-cog mr-2"></i> Settings
    </a>
</div>

<header class="portal-frame overflow-hidden mb-8" style="border-color: {{ $themeColor }}66;">
    <div class="relative p-6 md:p-10 min-h-[210px] flex items-end"
         style="background-color: {{ $themeColor }}22; background-image: {{ $patternStyle }}">
        <div class="absolute top-7 right-7 w-16 h-16 rounded-xl flex items-center justify-center text-3xl"
             style="color: {{ $themeColor }}; background: {{ $themeColor }}22; border: 1px solid {{ $themeColor }}66;">
            <i class="fas {{ $classIcon }}"></i>
        </div>
        <div>
            <p class="text-[10px] uppercase tracking-[0.25em] font-bold mb-2" style="color: {{ $themeColor }};">
                Grade {{ $class['grade_level'] }} Classroom
            </p>
            <h1 class="text-2xl md:text-4xl font-orbitron font-black text-white pr-20">{{ $class['class_name'] }}</h1>
            <div class="flex items-center gap-3 mt-5">
                <span class="text-[9px] text-slate-400 uppercase tracking-widest">Join Code</span>
                <code class="text-lg font-bold tracking-[0.2em] px-3 py-1 rounded bg-black/40" style="color: {{ $themeColor }};">
                    {{ $class['join_code'] }}
                </code>
                <button onclick="copyToClipboard('{{ $class['join_code'] }}')" class="text-slate-400 hover:text-white" title="Copy join code">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
        </div>
    </div>
</header>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
    <div class="portal-frame !p-5 border-l-2 border-cyan-500">
        <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold">Students</p>
        <p class="text-2xl font-orbitron mt-1">{{ count($members) }}</p>
    </div>
    <div class="portal-frame !p-5 border-l-2 border-green-500">
        <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold">Assigned / Active</p>
        <p class="text-2xl font-orbitron mt-1">{{ count($openSessions) }}</p>
    </div>
    <div class="portal-frame !p-5 border-l-2 border-slate-500">
        <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold">Ended Quizzes</p>
        <p class="text-2xl font-orbitron mt-1">{{ count($endedSessions) }}</p>
    </div>
</div>

@if($mismatchedMembers > 0)
    <div class="portal-frame !p-5 mb-8 border-l-4 border-red-500 bg-red-500/5">
        <p class="text-sm text-red-300 font-bold"><i class="fas fa-exclamation-triangle mr-2"></i> Grade mismatch detected</p>
        <p class="text-xs text-slate-400 mt-2">
            {{ $mismatchedMembers }} existing {{ Str::plural('student', $mismatchedMembers) }} joined before grade enforcement.
            Remove mismatched students so every member is Grade {{ $class['grade_level'] }}.
        </p>
    </div>
@endif

<section class="mb-10">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-lg font-orbitron font-bold uppercase">Assigned & <span class="text-green-400">Active Quizzes</span></h2>
            <p class="text-xs text-slate-500 mt-1">Start quizzes here and share the VR code with students.</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
            <a href="/teacher/quizzes?class_id={{ $class['id'] }}" class="btn-rect-secondary !py-2 !px-4 text-center sm:!w-auto">
                <i class="fas fa-scroll mr-2"></i> Assign My Quiz
            </a>
            <a href="/teacher/quiz-library?class_id={{ $class['id'] }}&grade={{ $class['grade_level'] }}"
               class="btn-rect-primary !py-2 !px-4 text-center sm:!w-auto">
                <i class="fas fa-book-open mr-2"></i> Browse Library
            </a>
        </div>
    </div>

    <div class="space-y-4">
        @forelse($openSessions as $session)
            @php $isActive = ($session['status'] ?? 'waiting') === 'active'; @endphp
            <article class="portal-frame !p-5 border-l-4 {{ $isActive ? 'border-green-500' : 'border-yellow-500' }}">
                <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-5">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded {{ $isActive ? 'bg-green-500/10 text-green-400' : 'bg-yellow-500/10 text-yellow-400' }}">
                                {{ $isActive ? 'Active' : 'Assigned' }}
                            </span>
                            <span class="text-[9px] text-slate-500 uppercase">{{ $session['time_limit'] }} sec/question</span>
                        </div>
                        <h3 class="font-bold text-lg text-white truncate">{{ $session['topic'] }}</h3>
                        <div class="flex items-center gap-2 mt-3">
                            <span class="text-[9px] text-slate-500 uppercase tracking-widest">VR Code</span>
                            <code class="text-lg text-cyan-400 font-black tracking-[0.2em]">{{ $session['room_code'] }}</code>
                            <button onclick="copyToClipboard('{{ $session['room_code'] }}')" class="text-slate-500 hover:text-white"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 w-full xl:w-auto">
                        <button onclick='openLobby(@json($class["id"]), @json($session["id"]), @json($session["topic"]), @json($session["room_code"]))'
                                class="btn-rect-secondary !py-2 !px-3 !text-[9px]">
                            <i class="fas fa-users mr-1"></i> Lobby
                        </button>
                        @if(!$isActive)
                            <button onclick='openQuizAction(@json($class["id"]), @json($session["id"]), "start", @json($session["topic"]))'
                                    class="btn-rect-secondary !py-2 !px-3 !text-[9px] !border-green-500/30 text-green-400">
                                <i class="fas fa-play mr-1"></i> Start Quiz
                            </button>
                        @endif
                        <button onclick='openQuizAction(@json($class["id"]), @json($session["id"]), "end", @json($session["topic"]))'
                                class="btn-rect-secondary !py-2 !px-3 !text-[9px] !border-red-500/30 text-red-400">
                            <i class="fas fa-stop mr-1"></i> End Quiz
                        </button>
                        <button onclick='openResults(@json($class["id"]), @json($session["id"]), @json($session["topic"]))'
                                class="btn-rect-secondary !py-2 !px-3 !text-[9px] !border-cyan-500/30 text-cyan-400">
                            <i class="fas fa-chart-bar mr-1"></i> Analytics
                        </button>
                        <button onclick='openSessionReport(@json($session["id"]), @json($session["topic"]))'
                                class="btn-rect-secondary !py-2 !px-3 !text-[9px] !border-blue-500/30 text-blue-400">
                            <i class="fas fa-file-download mr-1"></i> Report
                        </button>
                    </div>
                </div>
            </article>
        @empty
            <div class="portal-frame !p-8 text-center text-slate-500 text-xs uppercase tracking-widest">
                No assigned or active quizzes.
            </div>
        @endforelse
    </div>
</section>

<section class="mb-10">
    <h2 class="text-lg font-orbitron font-bold uppercase mb-5">Ended <span class="text-slate-400">Quizzes</span></h2>
    <div class="space-y-3">
        @forelse($endedSessions as $session)
            <article class="portal-frame !p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4 opacity-90">
                <div>
                    <p class="text-[9px] text-slate-500 uppercase tracking-widest mb-1">Ended · Code {{ $session['room_code'] }}</p>
                    <h3 class="font-bold text-white">{{ $session['topic'] }}</h3>
                    <p class="text-[10px] text-slate-500 mt-2">
                        {{ $session['analytics']['attempts'] }} attempts · {{ $session['analytics']['average'] }}% average
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-2 w-full lg:w-auto">
                    <button onclick='openResults(@json($class["id"]), @json($session["id"]), @json($session["topic"]))'
                            class="btn-rect-secondary !py-2 !px-4 !text-[9px] text-cyan-400">
                        <i class="fas fa-chart-bar mr-1"></i> Analytics
                    </button>
                    <button onclick='openSessionReport(@json($session["id"]), @json($session["topic"]))'
                            class="btn-rect-secondary !py-2 !px-4 !text-[9px] text-blue-400">
                        <i class="fas fa-file-download mr-1"></i> Report
                    </button>
                </div>
            </article>
        @empty
            <div class="portal-frame !p-8 text-center text-slate-500 text-xs uppercase tracking-widest">No ended quizzes yet.</div>
        @endforelse
    </div>
</section>

<section class="mb-10">
    <h2 class="text-lg font-orbitron font-bold uppercase mb-5">Class <span class="text-yellow-400">Leaderboard</span></h2>
    <p class="text-xs text-slate-500 mb-4">Ranked by average quiz accuracy, then total correct answers. Trophies are not used.</p>
    <div class="portal-frame !p-5 overflow-x-auto">
        <table class="w-full min-w-[560px] text-left">
            <thead class="text-[10px] text-slate-500 uppercase border-b border-white/10"><tr><th class="pb-4">Rank</th><th class="pb-4">Student</th><th class="pb-4">Quizzes</th><th class="pb-4">Avg Accuracy</th><th class="pb-4">Total Correct</th></tr></thead>
            <tbody class="text-sm">
                @forelse($leaderboard as $row)
                    <tr class="border-b border-white/5">
                        <td class="py-4 font-mono text-yellow-400">#{{ $row['rank'] }}</td>
                        <td class="py-4 font-bold">{{ $row['name'] }}</td>
                        <td class="py-4 text-slate-400">{{ $row['quizzes'] }}</td>
                        <td class="py-4 {{ $row['average'] >= 75 ? 'text-green-400' : 'text-red-400' }} font-bold">{{ $row['average'] }}%</td>
                        <td class="py-4 text-cyan-400">{{ $row['correct'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500 text-xs uppercase">No student quiz results yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-lg font-orbitron font-bold uppercase">Class <span class="text-yellow-400">Roster</span></h2>
            <p class="text-xs text-slate-500 mt-1">Only you can remove students from this class.</p>
        </div>
        <div class="flex gap-2">
            <a href="/teacher/report/classroom/{{ $class['id'] }}?format=pdf" class="btn-rect-secondary !py-2 !px-4 !w-auto text-center"><i class="fas fa-file-pdf mr-1"></i> PDF</a>
            <a href="/teacher/report/classroom/{{ $class['id'] }}?format=csv" class="btn-rect-secondary !py-2 !px-4 !w-auto text-center"><i class="fas fa-file-csv mr-1"></i> CSV</a>
        </div>
    </div>
    <div class="portal-frame !p-5 overflow-x-auto">
        <table class="w-full min-w-[650px] text-left">
            <thead class="text-[10px] text-slate-500 uppercase border-b border-white/10">
                <tr><th class="pb-4">Student</th><th class="pb-4">Email</th><th class="pb-4">Grade</th><th class="pb-4 text-right">Action</th></tr>
            </thead>
            <tbody class="text-sm">
                @forelse($members as $member)
                    @php
                        $student = $member['profiles'] ?? [];
                        $studentId = $student['id'] ?? '';
                        $studentName = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
                    @endphp
                    <tr class="border-b border-white/5 {{ $member['grade_mismatch'] ? 'bg-red-500/5' : '' }}">
                        <td class="py-4 font-bold">{{ $student['last_name'] ?? 'Unknown' }}, {{ $student['first_name'] ?? 'Unknown' }}</td>
                        <td class="py-4 text-slate-400">{{ $student['email'] ?? 'N/A' }}</td>
                        <td class="py-4 {{ $member['grade_mismatch'] ? 'text-red-400 font-bold' : 'text-cyan-400' }}">
                            Grade {{ $student['grade_level'] ?? 'N/A' }}
                            @if($member['grade_mismatch']) <span class="text-[9px] uppercase ml-1">Mismatch</span> @endif
                        </td>
                        <td class="py-4 text-right">
                            <button onclick='openRemoveStudent(@json($class["id"]), @json($studentId), @json($studentName))'
                                    class="text-red-400 hover:text-white text-[10px] uppercase font-bold">
                                <i class="fas fa-user-minus mr-1"></i> Remove
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-slate-500 text-xs uppercase">No students have joined yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection

@section('modals')
<div id="liveLobbyModal" class="modal-overlay hidden">
    <div class="portal-frame !p-6 md:!p-8 w-full max-w-2xl text-left border-purple-500/30">
        <div class="flex justify-between items-start border-b border-white/10 pb-4 mb-6">
            <div>
                <h3 id="lobby-title" class="font-orbitron font-bold uppercase text-lg text-purple-400">Live Lobby</h3>
                <p class="text-[10px] text-slate-500 mt-2">VR Code: <span id="lobby-code" class="text-white font-mono font-bold text-base ml-1"></span></p>
            </div>
            <button onclick="closeLobby()" class="text-slate-500 hover:text-white"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="overflow-y-auto max-h-80 border border-white/5 rounded bg-black/30">
            <table class="w-full"><tbody id="lobby-tbody" class="text-sm"></tbody></table>
        </div>
        <button onclick="closeLobby()" class="btn-rect-secondary mt-6">Close</button>
    </div>
</div>

<div id="viewResultsModal" class="modal-overlay hidden">
    <div class="portal-frame !p-6 md:!p-8 w-full max-w-3xl text-left border-cyan-500/30">
        <div class="flex justify-between items-center border-b border-white/10 pb-4 mb-6">
            <h3 id="results-modal-title" class="font-orbitron font-bold uppercase text-lg text-cyan-400">Quiz Analytics</h3>
            <button onclick="closeModal('viewResultsModal')" class="text-slate-500 hover:text-white"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="overflow-x-auto max-h-96">
            <table class="w-full min-w-[600px] text-left">
                <thead class="text-[10px] text-slate-500 uppercase border-b border-white/10">
                    <tr><th class="pb-4">Student</th><th class="pb-4">Score</th><th class="pb-4">Accuracy</th><th class="pb-4">Date</th></tr>
                </thead>
                <tbody id="results-tbody" class="text-sm"></tbody>
            </table>
        </div>
        <button onclick="closeModal('viewResultsModal')" class="btn-rect-secondary mt-6">Close</button>
    </div>
</div>

<div id="quizActionModal" class="modal-overlay hidden">
    <div class="portal-frame !p-9 w-full max-w-sm text-center border-yellow-500/40">
        <i id="quiz-action-icon" class="fas fa-play text-4xl text-green-400 mb-4"></i>
        <h3 id="quiz-action-title" class="font-orbitron font-bold uppercase text-white">Start Quiz?</h3>
        <p id="quiz-action-topic" class="text-xs text-slate-400 mt-3 mb-8"></p>
        <button id="confirmQuizAction" class="btn-rect-primary">Confirm</button>
        <button onclick="closeModal('quizActionModal')" class="text-[10px] font-bold mt-4 uppercase text-slate-500">Cancel</button>
    </div>
</div>

<div id="quizReportModal" class="modal-overlay hidden">
    <div class="portal-frame !p-8 w-full max-w-sm text-center border-blue-500/30">
        <i class="fas fa-file-download text-4xl text-blue-400 mb-4"></i>
        <h3 class="font-orbitron font-bold uppercase">Quiz <span class="text-blue-400">Report</span></h3>
        <p id="quiz-report-topic" class="text-xs text-slate-500 my-5"></p>
        <div class="space-y-3">
            <a id="quiz-report-pdf" class="btn-rect-primary block" href="#"><i class="fas fa-file-pdf mr-2"></i> PDF</a>
            <a id="quiz-report-csv" class="btn-rect-secondary block" href="#"><i class="fas fa-file-csv mr-2"></i> CSV</a>
            <button onclick="closeModal('quizReportModal')" class="text-[10px] text-slate-500 uppercase font-bold">Cancel</button>
        </div>
    </div>
</div>

<div id="removeStudentModal" class="modal-overlay hidden">
    <div class="portal-frame !p-9 w-full max-w-sm text-center border-red-500/50">
        <i class="fas fa-user-minus text-4xl text-red-500 mb-4"></i>
        <h3 class="font-orbitron font-bold uppercase">Remove Student?</h3>
        <p id="remove-student-name" class="text-xs text-slate-400 mt-3 mb-8"></p>
        <form id="removeStudentForm" method="POST">
            @csrf
            @method('DELETE')
            <button class="btn-rect-primary !bg-red-600 !text-white">Remove Student</button>
        </form>
        <button onclick="closeModal('removeStudentModal')" class="text-[10px] font-bold mt-4 uppercase text-slate-500">Cancel</button>
    </div>
</div>

@include('teacher.partials.logout-modal')
@endsection

@push('scripts')
<script src="{{ asset('js/teacher-classroom.js') }}"></script>
@endpush
