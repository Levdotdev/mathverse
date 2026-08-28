@extends('layouts.dashboard')

@section('title', 'Student Portal')
@section('sidebar-subtitle', 'Student Game Hub')
@section('mobile-title', 'Student Hub')

@section('sidebar-nav')
    @include('student.partials.sidebar-nav')
@endsection

@section('dashboard-content')

{{-- STATS SECTION --}}
<section id="sec-stats" class="content-section">
    <h2 class="text-xl md:text-2xl font-orbitron font-bold mb-6 uppercase border-b border-white/10 pb-2">
        Academic <span class="text-cyan-400">Progress</span>
    </h2>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="portal-frame !p-5 border-b-2 border-cyan-500">
            <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Grade {{ $gradeLevel }} Rank</p>
            <h3 class="text-2xl font-orbitron mt-1">#{{ $rank }}</h3>
        </div>
        <div class="portal-frame !p-5 border-b-2 border-purple-500"><p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Taken</p><h3 class="text-2xl font-orbitron mt-1">{{ $studentAnalytics['taken'] }}</h3></div>
        <div class="portal-frame !p-5 border-b-2 border-red-500"><p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Missed</p><h3 class="text-2xl font-orbitron mt-1">{{ $studentAnalytics['missed'] }}</h3></div>
        <div class="portal-frame !p-5 border-b-2 border-blue-500"><p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Average</p><h3 class="text-2xl font-orbitron mt-1">{{ $studentAnalytics['average'] }}%</h3></div>
        <div class="portal-frame !p-5 border-b-2 border-green-500"><p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Passed (75%+)</p><h3 class="text-2xl font-orbitron mt-1">{{ $studentAnalytics['passed'] }}</h3></div>
        <div class="portal-frame !p-5 border-b-2 border-orange-500"><p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Failed (&lt;75%)</p><h3 class="text-2xl font-orbitron mt-1">{{ $studentAnalytics['failed'] }}</h3></div>
        <div class="portal-frame !p-5 border-b-2 border-yellow-500"><p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Best Accuracy</p><h3 class="text-2xl font-orbitron mt-1">{{ $studentAnalytics['best'] }}%</h3></div>
        <div class="portal-frame !p-5 border-b-2 border-slate-500">
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
                    $accuracy = $record['accuracy'];
                    $statusColor = $accuracy >= 75 ? 'text-green-500' : 'text-red-500';
                    $statusText  = $accuracy >= 75 ? 'Passed' : 'Failed';
                    $topic = $record['quiz_sessions']['topic'] ?? 'Unknown Quiz';
                    $classId = $record['quiz_sessions']['class_id'] ?? null;
                    $sessionId = $record['session_id'] ?? ($record['quiz_sessions']['id'] ?? null);
                @endphp
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 bg-white/5 p-4 rounded border border-white/5 hover:border-cyan-500/30 transition-colors">
                    <div>
                        <p class="font-bold text-sm text-white">{{ $topic }}</p>
                        <p class="text-[10px] text-slate-500 font-mono">
                            Date: {{ \Carbon\Carbon::parse($record['created_at'])->format('M d, Y') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-4 sm:text-right">
                        <div>
                        <p class="text-cyan-400 font-bold">{{ $record['correct_answers'] }}/{{ $record['total_questions'] }}</p>
                        <p class="text-[9px] {{ $statusColor }} font-black uppercase">{{ $statusText }} ({{ $accuracy }}%)</p>
                        </div>
                        @if($classId && $sessionId)
                            <a href="/student/classes/{{ $classId }}/quizzes/{{ $sessionId }}/review" class="btn-rect-secondary !py-2 !px-3 !w-auto text-[9px]">Review</a>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-slate-500 text-xs py-4 text-center uppercase tracking-widest">
                    No quiz history yet. Play a VR Quiz Bee to see results here!
                </p>
            @endforelse
        </div>
    </div>

    <div class="portal-frame !p-6 mt-6 border-l-4 border-red-500">
        <h4 class="font-orbitron text-xs text-red-400 uppercase mb-5 tracking-widest"><i class="fas fa-calendar-times mr-2"></i> Missed Quizzes</h4>
        <div class="space-y-3">
            @forelse($missedQuizzes as $missed)
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-white/5 pb-3">
                    <div><p class="font-bold text-sm">{{ $missed['topic'] }}</p><p class="text-[10px] text-slate-500">{{ $missed['class_name'] }} · Ended {{ \Carbon\Carbon::parse($missed['created_at'])->format('M d, Y') }}</p></div>
                    <a href="/student/classes/{{ $missed['class_id'] }}/quizzes/{{ $missed['id'] }}/review" class="btn-rect-secondary !py-2 !px-3 !w-auto text-[9px]">Review Answers</a>
                </div>
            @empty
                <p class="text-slate-500 text-xs uppercase tracking-widest">No missed quizzes.</p>
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
        <form method="POST" action="/student/classes/join" class="flex w-full md:w-auto gap-2">
            @csrf
            <div class="relative w-full md:w-48">
                <input type="text" name="join_code" placeholder="Join Code"
                       class="input-mobile-ultra font-mono uppercase !pl-4 tracking-widest" required>
            </div>
            <button type="submit" class="btn-rect-primary !bg-green-500 !text-black !w-auto px-6">Join</button>
        </form>
    </div>

    <h4 class="font-orbitron text-xs text-green-400 uppercase mb-4 tracking-widest">
        <i class="fas fa-chalkboard mr-2"></i> My Enrolled Classes
    </h4>

    @php
        $studentClassIcons = [
            'chalkboard' => 'fa-chalkboard', 'calculator' => 'fa-calculator',
            'rocket' => 'fa-rocket', 'atom' => 'fa-atom',
            'shapes' => 'fa-shapes', 'gamepad' => 'fa-gamepad',
        ];
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-8">
        @forelse($classes as $class)
            @php
                $custom = $class['customization'];
                $color = $custom['theme_color'];
                $icon = $studentClassIcons[$custom['icon']] ?? 'fa-chalkboard';
            @endphp
            <article class="portal-frame overflow-hidden" style="border-color: {{ $color }}66;">
                <div class="p-6 flex items-center gap-4" style="background: linear-gradient(135deg, {{ $color }}28, transparent);">
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl shrink-0"
                         style="color: {{ $color }}; background: {{ $color }}22; border: 1px solid {{ $color }}55;">
                        <i class="fas {{ $icon }}"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[9px] uppercase font-bold tracking-widest" style="color: {{ $color }};">Grade {{ $class['grade_level'] }}</p>
                        <h4 class="font-bold text-white text-lg truncate">{{ $class['class_name'] }}</h4>
                    </div>
                </div>
                <div class="p-4">
                    <a href="/student/classes/{{ $class['id'] }}" class="btn-rect-primary !py-3 block text-center">
                        View Quizzes & Analytics <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </article>
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
            Grade {{ $gradeLevel }} <span class="text-cyan-400">Global Ranking</span>
        </h2>
        <p class="text-xs text-slate-500 mb-6">Only Grade {{ $gradeLevel }} students are included.</p>
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
                                {{ $p['last_name'] ?? 'Unknown' }}, {{ $p['first_name'] ?? 'Unknown' }}
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

<section id="sec-password" class="content-section hidden">
    <div class="portal-frame !p-6 md:!p-10">
        <h2 class="text-xl font-orbitron font-bold mb-10 uppercase">
            <i class="fas fa-key mr-2"></i> CHANGE <span class="text-cyan-400">PASSWORD</span>
        </h2>
        <form method="POST" action="/change-password" class="space-y-6 max-w-2xl mx-auto">
            @csrf
            <div class="form-group sm:col-span-2 mt-2">
                <label class="input-label text-orange-400">Current Password</label>
                <div class="relative">
                    <i class="fas fa-unlock-alt input-icon"></i>
                    <input type="password" id="s-curr-pass" name="current_password"
                            class="input-mobile-ultra pr-12" placeholder="Enter current password" required>
                    <button type="button" onclick="tglPass('s-curr-pass','s-ico-curr')"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
                        <i id="s-ico-curr" class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>
            <div class="form-group border-t border-white/10 pt-4 mt-2">
                <label class="input-label">New Password</label>
                <div class="relative">
                    <i class="fas fa-key input-icon"></i>
                    <input type="password" id="s-new-pass" name="new_password"
                           class="input-mobile-ultra pr-12" placeholder="••••••••" required>
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
                           class="input-mobile-ultra pr-12" placeholder="••••••••" required>
                    <button type="button" onclick="tglPass('s-conf-pass','s-ico-conf')"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
                            <i id="s-ico-conf" class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-rect-primary mt-4">
                <i class="fas fa-save mr-2"></i> Update Password
            </button>
        </form>
    </div>
</section>

{{-- PROFILE SECTION --}}
<section id="sec-profile" class="content-section hidden">
    <div class="portal-frame !p-6 md:!p-10">
        <h2 class="text-xl font-orbitron font-bold mb-10 uppercase">
            <i class="fas fa-user-circle mr-2"></i> Account <span class="text-cyan-400">Profile</span>
        </h2>
        <form method="POST" action="/student/profile" class="space-y-6 max-w-2xl mx-auto" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="input-label">Profile Picture</label>
                <div class="flex items-center gap-4">
                    {{-- Preview circle --}}
                    <div class="w-16 h-16 rounded-full border-2 border-white/10 bg-white/5 flex items-center justify-center overflow-hidden shrink-0" id="avatar-preview-wrap">
                        <img id="avatar-preview"
                            src="{{ $user['avatar_url'] ?: asset('default.png') }}"
                            class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1">
                            <label for="avatar-input"
                                class="cursor-pointer block w-full text-center border border-white/10 bg-white/5 hover:bg-white/10 transition-all rounded px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400 hover:text-white">
                                <i class="fas fa-upload mr-2"></i> Choose Photo
                            </label>
                            <input type="file" id="avatar-input" name="avatar"
                                accept="image/*" class="hidden" onchange="previewAvatar(this)">
                            <p class="text-[9px] text-slate-600 mt-1 text-center">JPG, PNG • Less than 5 MB</p>
                        </div>
                    </div>
                </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="form-group">
                    <label class="input-label">Email Address</label>
                    <div class="relative">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" value="{{ $user['email'] }}" readonly name="email"
                               class="input-mobile-ultra !bg-white/5 text-slate-400">
                    </div>
                </div>
                <div class="form-group">
                    <label class="input-label">Grade Level</label>
                    <div class="relative">
                        <i class="fas fa-graduation-cap input-icon"></i>
                        <select name="grade_level" class="input-mobile-ultra bg-slate-900 text-white">
                            @for($g = 1; $g <= 6; $g++)
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
                        <input type="text" name="first_name" value="{{ $profile['first_name'] ?? '' }}"
                               class="input-mobile-ultra" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="input-label">Last Name</label>
                    <div class="relative">
                        <i class="fas fa-id-card input-icon"></i>
                        <input type="text" name="last_name" value="{{ $profile['last_name'] ?? '' }}"
                               class="input-mobile-ultra" required>
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
@include('student.partials.logout-modal')
@endsection
