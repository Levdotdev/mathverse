@extends('layouts.dashboard')

@section('title', 'Student Portal')
@section('sidebar-subtitle', 'Student Game Hub')
@section('mobile-title', 'Student Hub')

@section('sidebar-nav')
<button onclick="showSection('stats')"  class="nav-link active w-full" id="btn-stats">
    <i class="fas fa-chart-line mr-3 w-5 text-cyan-400"></i> My Stats
</button>
<button onclick="showSection('ranking')" class="nav-link w-full" id="btn-ranking">
    <i class="fas fa-trophy mr-3 w-5 text-yellow-500"></i> Ranking
</button>
<button onclick="showSection('class')"  class="nav-link w-full" id="btn-class">
    <i class="fas fa-chalkboard mr-3 w-5 text-green-400"></i> My Class
</button>
<button onclick="showSection('profile')" class="nav-link w-full" id="btn-profile">
    <i class="fas fa-user-circle mr-3 w-5 text-blue-400"></i> Profile
</button>
@endsection

@section('dashboard-content')

{{-- STATS SECTION --}}
<section id="sec-stats" class="content-section">
    <h2 class="text-xl md:text-2xl font-orbitron font-bold mb-6 uppercase border-b border-white/10 pb-2">
        Academic <span class="text-cyan-400">Progress</span>
    </h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
        <div class="portal-frame !p-5 border-b-2 border-cyan-500">
            <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Global Rank</p>
            <h3 class="text-2xl font-orbitron mt-1">#{{ $rank }}</h3>
        </div>
        <div class="portal-frame !p-5 border-b-2 border-purple-500">
            <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Total Trophies</p>
            <h3 class="text-2xl font-orbitron mt-1">{{ number_format($profile['trophies'] ?? 0) }}</h3>
        </div>
    </div>

    <div class="portal-frame !p-6">
        <h4 class="font-orbitron text-xs text-cyan-400 uppercase mb-6 tracking-widest">
            <i class="fas fa-history mr-2"></i> Recent Quiz Bee Performance
        </h4>
        <div class="space-y-4">
            @forelse($quizHistory as $record)
                @php
                    $accuracy = $record['total_questions'] > 0
                        ? round(($record['correct_answers'] / $record['total_questions']) * 100)
                        : 0;
                    $statusColor = $accuracy >= 75 ? 'text-green-500' : ($accuracy >= 50 ? 'text-yellow-500' : 'text-red-500');
                    $statusText  = $accuracy >= 75 ? 'Cleared' : ($accuracy >= 50 ? 'Passed' : 'Failed');
                    $topic = $record['quiz_sessions']['topic'] ?? 'Unknown Quiz';
                @endphp
                <div class="flex justify-between items-center bg-white/5 p-4 rounded border border-white/5 hover:border-cyan-500/30 transition-colors">
                    <div>
                        <p class="font-bold text-sm text-white">{{ $topic }}</p>
                        <p class="text-[10px] text-slate-500 font-mono">
                            Date: {{ \Carbon\Carbon::parse($record['created_at'])->format('M d, Y') }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-cyan-400 font-bold">{{ $record['correct_answers'] }}/{{ $record['total_questions'] }}</p>
                        <p class="text-[9px] {{ $statusColor }} font-black uppercase">{{ $statusText }} ({{ $accuracy }}%)</p>
                    </div>
                </div>
            @empty
                <p class="text-slate-500 text-xs py-4 text-center uppercase tracking-widest">
                    No quiz history yet. Play a VR Quiz Bee to see results here!
                </p>
            @endforelse
        </div>
    </div>
</section>

{{-- CLASS SECTION --}}
<section id="sec-class" class="content-section hidden">
    <h2 class="text-xl md:text-2xl font-orbitron font-bold mb-6 uppercase border-b border-white/10 pb-2">
        Virtual <span class="text-green-400">Classrooms</span>
    </h2>

    <div class="portal-frame !p-6 mb-8 flex flex-col md:flex-row items-center gap-4 justify-between border-l-4 border-green-500">
        <div>
            <h3 class="font-orbitron font-bold text-lg uppercase mb-1">Join a New Class</h3>
            <p class="text-xs text-slate-400">Enter the 6-character code from your teacher.</p>
        </div>
        <form method="POST" action="/student/join-class" class="flex w-full md:w-auto gap-2">
            @csrf
            <div class="relative w-full md:w-48">
                <i class="fas fa-key input-icon"></i>
                <input type="text" name="join_code" placeholder="Join Code"
                       class="input-mobile-ultra font-mono uppercase !pl-4 tracking-widest">
            </div>
            <button type="submit" class="btn-rect-primary !bg-green-500 !text-black !w-auto px-6">Join</button>
        </form>
    </div>

    <h4 class="font-orbitron text-xs text-green-400 uppercase mb-4 tracking-widest">
        <i class="fas fa-chalkboard mr-2"></i> My Enrolled Classes
    </h4>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
        @forelse($classes as $class)
            <div class="portal-frame !p-5 border-l-4 border-green-500 flex justify-between items-center hover:bg-white/5 transition-all">
                <div>
                    <h4 class="font-bold text-white text-lg">{{ $class['class_name'] }}</h4>
                    <p class="text-[10px] text-slate-500 uppercase mt-1 tracking-widest">
                        Code: <span class="text-cyan-400 font-mono">{{ $class['join_code'] }}</span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <button onclick="openRosterModal('{{ $class['id'] }}', '{{ addslashes($class['class_name']) }}')"
                            class="text-cyan-400 text-[10px] font-bold uppercase border border-cyan-500/30 bg-cyan-500/10 px-3 py-2 rounded">
                        <i class="fas fa-users mr-1"></i> Roster
                    </button>
                    <form method="POST" action="/student/leave-class" onsubmit="return confirm('Leave this class?')">
                        @csrf
                        <input type="hidden" name="class_id" value="{{ $class['id'] }}">
                        <button type="submit" class="text-red-500 text-[10px] font-bold uppercase border border-red-500/30 bg-red-500/10 px-3 py-2 rounded">
                            Leave
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="portal-frame !p-6 text-center text-slate-500 text-xs uppercase tracking-widest sm:col-span-2">
                You have not joined any classes yet.
            </div>
        @endforelse
    </div>
</section>

{{-- RANKING SECTION --}}
<section id="sec-ranking" class="content-section hidden">
    <div class="portal-frame !p-6 md:!p-8">
        <h2 class="text-xl font-orbitron font-bold mb-6 uppercase">
            Global <span class="text-cyan-400">Ranking</span>
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="text-slate-500 text-[10px] uppercase border-b border-white/10">
                    <tr>
                        <th class="pb-4">Rank</th>
                        <th class="pb-4">Student</th>
                        <th class="pb-4">Level</th>
                        <th class="pb-4">Trophies</th>
                    </tr>
                </thead>
                <tbody class="text-sm font-rajdhani">
                    @foreach($leaderboard as $i => $p)
                        @php $isMe = $p['id'] === $profile['id']; @endphp
                        <tr class="border-b border-white/5 {{ $isMe ? 'bg-cyan-400/5' : '' }}">
                            <td class="py-4 font-mono {{ $isMe ? 'text-cyan-400' : '' }}">#{{ $i + 1 }}</td>
                            <td class="py-4 {{ $isMe ? 'font-bold' : '' }}">
                                {{ $p['last_name'] ?? 'Unknown' }}, {{ $p['username'] ?? 'Unknown' }}
                                {{ $isMe ? '(You)' : '' }}
                            </td>
                            <td class="py-4">Level {{ $p['level'] ?? 1 }}</td>
                            <td class="py-4 {{ $isMe ? 'font-bold' : '' }}">
                                {{ number_format($p['trophies'] ?? 0) }}
                                <i class="fas fa-trophy text-yellow-500 ml-1"></i>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

{{-- PROFILE SECTION --}}
<section id="sec-profile" class="content-section hidden">
    <div class="portal-frame !p-6 md:!p-10">
        <h2 class="text-xl font-orbitron font-bold mb-10 uppercase">
            <i class="fas fa-user-circle mr-2"></i> Account <span class="text-cyan-400">Profile</span>
        </h2>
        <form method="POST" action="/student/profile" class="space-y-6 max-w-2xl mx-auto">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="form-group">
                    <label class="input-label">Email Address</label>
                    <div class="relative">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" value="{{ $user['email'] }}" readonly
                               class="input-mobile-ultra !bg-white/5 text-slate-400">
                    </div>
                </div>
                <div class="form-group">
                    <label class="input-label">Grade Level</label>
                    <div class="relative">
                        <i class="fas fa-graduation-cap input-icon"></i>
                        <select name="grade_level" class="input-mobile-ultra bg-slate-900 text-white">
                            @for($g = 1; $g <= 12; $g++)
                                <option value="{{ $g }}" {{ ($profile['grade_level'] ?? 1) == $g ? 'selected' : '' }}>
                                    Grade {{ $g }}
                                </option>
                            @endfor
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="input-label">First Name</label>
                    <div class="relative">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="first_name" value="{{ $profile['username'] ?? '' }}"
                               class="input-mobile-ultra">
                    </div>
                </div>
                <div class="form-group">
                    <label class="input-label">Last Name</label>
                    <div class="relative">
                        <i class="fas fa-id-card input-icon"></i>
                        <input type="text" name="last_name" value="{{ $profile['last_name'] ?? '' }}"
                               class="input-mobile-ultra">
                    </div>
                </div>
                <div class="form-group sm:col-span-2 border-t border-white/10 pt-4 mt-2">
                    <label class="input-label text-orange-400">Current Password (required for changes)</label>
                    <div class="relative">
                        <i class="fas fa-unlock-alt input-icon"></i>
                        <input type="password" id="s-curr-pass" name="current_password"
                               class="input-mobile-ultra pr-12" placeholder="Enter current password">
                        <button type="button" onclick="tglPass('s-curr-pass','s-ico-curr')"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
                            <i id="s-ico-curr" class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="input-label">New Password</label>
                    <div class="relative">
                        <i class="fas fa-key input-icon"></i>
                        <input type="password" id="s-new-pass" name="new_password"
                               class="input-mobile-ultra pr-12" placeholder="••••••••">
                        <button type="button" onclick="tglPass('s-new-pass','s-ico-new')"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
                            <i id="s-ico-new" class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="input-label">Confirm Password</label>
                    <div class="relative">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="s-conf-pass" name="new_password_confirmation"
                               class="input-mobile-ultra pr-12" placeholder="••••••••">
                        <button type="button" onclick="tglPass('s-conf-pass','s-ico-conf')"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
                            <i id="s-ico-conf" class="fas fa-eye-slash"></i>
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

@section('modals')
{{-- Class Roster Modal --}}
<div id="viewClassRosterModal" class="modal-overlay hidden">
    <div class="portal-frame !p-6 md:!p-8 w-full max-w-2xl text-left border-green-500/30">
        <div class="flex justify-between items-center border-b border-white/10 pb-4 mb-6">
            <h3 class="font-orbitron font-bold uppercase text-lg text-green-400" id="roster-modal-title">Classmates</h3>
            <button onclick="closeModal('viewClassRosterModal')" class="text-slate-500 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="overflow-x-auto max-h-96">
            <table class="w-full text-left min-w-[400px]">
                <thead class="text-slate-500 text-[10px] uppercase border-b border-white/5">
                    <tr><th class="pb-4">Student Name</th><th class="pb-4 text-right">Level</th></tr>
                </thead>
                <tbody id="roster-tbody" class="text-sm font-rajdhani text-white"></tbody>
            </table>
        </div>
        <button onclick="closeModal('viewClassRosterModal')" class="btn-rect-secondary mt-6 w-full text-xs">
            Close Panel
        </button>
    </div>
</div>
@endsection

@push('scripts')
<script>
async function openRosterModal(classId, className) {
    document.getElementById('roster-modal-title').innerText = className + ' - Classmates';
    const tbody = document.getElementById('roster-tbody');
    tbody.innerHTML = '<tr><td colspan="2" class="text-center py-8 text-slate-500"><i class="fas fa-circle-notch fa-spin text-2xl"></i></td></tr>';
    openModal('viewClassRosterModal');

    const res  = await fetch(`/student/class-roster/${classId}`);
    const data = await res.json();

    if (!data.length) {
        tbody.innerHTML = '<tr><td colspan="2" class="text-center py-6 text-slate-500 text-xs uppercase">No classmates found.</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(m => `
        <tr class="border-b border-white/5 hover:bg-white/5">
            <td class="py-4 font-bold"><i class="fas fa-user-graduate text-slate-500 mr-2"></i>
                ${m.last_name ?? 'Unknown'}, ${m.username ?? 'Unknown'}
            </td>
            <td class="py-4 text-cyan-400 font-mono text-right">Level ${m.level ?? 1}</td>
        </tr>
    `).join('');
}
</script>
@endpush