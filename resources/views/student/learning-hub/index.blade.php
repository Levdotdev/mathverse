@extends('layouts.dashboard')

@section('title', 'Learning Hub')
@section('sidebar-subtitle', 'Student Game Hub')
@section('mobile-title', 'Learning Hub')

@section('sidebar-nav')
    @include('student.partials.sidebar-nav')
@endsection

@section('dashboard-content')
<div class="max-w-7xl mx-auto">
    <header class="flex flex-col lg:flex-row lg:items-end justify-between gap-5 mb-7 border-b border-white/10 pb-5">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.35em] text-purple-400 mb-2">Autonomous Learning System</p>
            <h2 class="text-2xl md:text-4xl font-orbitron font-black uppercase">
                MathVerse <span class="text-cyan-400">Adventure</span>
            </h2>
            <p class="text-sm text-slate-400 mt-3 max-w-2xl">
                MathVerse chooses your next challenge, adapts to every answer, and helps you recover from mistakes automatically.
            </p>
        </div>
        <div class="flex items-center gap-3 text-right">
            <div>
                <p class="text-[9px] uppercase tracking-widest text-slate-500">Current Rank</p>
                <p class="font-orbitron font-black text-lg text-cyan-400">Level {{ $hub['level'] }}</p>
            </div>
            <div class="w-12 h-12 rounded-full border border-cyan-400/40 bg-cyan-400/10 flex items-center justify-center text-cyan-400">
                <i class="fas fa-user-astronaut text-xl"></i>
            </div>
        </div>
    </header>

    @if(!$hub['configured'])
        <div class="portal-frame !p-6 mb-7 border-l-4 border-yellow-500">
            <div class="flex items-start gap-4">
                <i class="fas fa-triangle-exclamation text-yellow-400 text-xl mt-1"></i>
                <div>
                    <h3 class="font-orbitron font-bold uppercase text-yellow-400">Learning Hub update required</h3>
                    <p class="text-xs text-slate-400 mt-2">
                        Install the September 1 autonomous Learning Hub database update before students begin practicing.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <section class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-7" aria-label="Learning progress">
        <div class="portal-frame !p-5 border-b-2 border-cyan-500">
            <p class="text-[9px] uppercase font-bold tracking-widest text-slate-500">Overall Mastery</p>
            <p class="text-2xl font-orbitron font-black mt-2">{{ $hub['average_mastery'] }}%</p>
            <div class="h-1.5 bg-white/5 rounded-full overflow-hidden mt-3">
                <div class="h-full bg-cyan-400 rounded-full" style="width: {{ $hub['average_mastery'] }}%"></div>
            </div>
        </div>
        <div class="portal-frame !p-5 border-b-2 border-orange-500">
            <p class="text-[9px] uppercase font-bold tracking-widest text-slate-500">Practice Streak</p>
            <p class="text-2xl font-orbitron font-black mt-2">{{ $hub['streak'] }} <span class="text-sm text-orange-400">days</span></p>
            <p class="text-[9px] text-slate-500 mt-3">A rest day will not erase earned mastery.</p>
        </div>
        <div class="portal-frame !p-5 border-b-2 border-purple-500">
            <p class="text-[9px] uppercase font-bold tracking-widest text-slate-500">Skills Mastered</p>
            <p class="text-2xl font-orbitron font-black mt-2">{{ $hub['mastered_count'] }}<span class="text-sm text-purple-400">/{{ count($hub['skills']) }}</span></p>
            <p class="text-[9px] text-slate-500 mt-3">Grade {{ $hub['grade'] }} learning path</p>
        </div>
        <div class="portal-frame !p-5 border-b-2 border-yellow-500">
            <p class="text-[9px] uppercase font-bold tracking-widest text-slate-500">Experience</p>
            <p class="text-2xl font-orbitron font-black mt-2">{{ number_format($hub['xp']) }} <span class="text-sm text-yellow-400">XP</span></p>
            <div class="h-1.5 bg-white/5 rounded-full overflow-hidden mt-3">
                <div class="h-full bg-yellow-400 rounded-full" style="width: {{ round(($hub['xp_in_level'] / $hub['xp_per_level']) * 100) }}%"></div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-[1.4fr_0.8fr] gap-6 mb-7">
        <article class="portal-frame !p-7 md:!p-9 relative overflow-hidden learning-hero-card">
            <div class="absolute -right-12 -top-12 w-52 h-52 rounded-full bg-cyan-500/10 blur-3xl pointer-events-none"></div>
            <div class="relative z-10 max-w-2xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/5 px-3 py-1.5 text-[9px] uppercase font-bold tracking-widest text-cyan-400 mb-5">
                    <i class="fas fa-wand-magic-sparkles"></i> Recommended Next
                </div>
                <p class="text-xs text-slate-500 uppercase tracking-widest">{{ $hub['recommended']['world'] }}</p>
                <h3 class="font-orbitron text-2xl md:text-3xl font-black mt-2 text-white">
                    {{ $hub['recommended']['title'] }}
                </h3>
                <div class="flex flex-wrap items-center gap-3 mt-4 text-xs">
                    <span class="px-3 py-1.5 rounded bg-white/5 text-slate-300">{{ $hub['recommended']['status'] }}</span>
                    <span class="px-3 py-1.5 rounded bg-white/5 text-slate-300">Difficulty {{ $hub['recommended']['difficulty'] }}/5</span>
                    <span class="px-3 py-1.5 rounded bg-white/5 text-slate-300">{{ $hub['recommended']['mastery'] }}% mastery</span>
                </div>
                <p class="text-sm text-slate-400 mt-5 mb-7">
                    Your next five-question mission is selected from your current level, unfinished skills, and scheduled reviews.
                </p>
                @if($hub['configured'])
                    <a href="/student/learning-hub/practice?mode={{ $hub['active_session']['mode'] ?? 'adventure' }}"
                       class="btn-rect-primary !w-auto inline-flex items-center justify-center px-8 py-4">
                        <i class="fas fa-play mr-2"></i>
                        {{ $hub['active_session'] ? 'Continue Adventure' : 'Begin Adventure' }}
                    </a>
                @else
                    <button type="button" disabled class="btn-rect-primary !w-auto px-8 py-4 opacity-40 cursor-not-allowed">
                        Adventure Offline
                    </button>
                @endif
            </div>
        </article>

        <article class="portal-frame !p-7 border-purple-500/40">
            <div class="flex items-center justify-between gap-4 mb-5">
                <div>
                    <p class="text-[9px] uppercase font-bold tracking-widest text-purple-400">Today's Mission</p>
                    <h3 class="font-orbitron font-bold text-lg mt-1">Daily Quest</h3>
                </div>
                <div class="w-12 h-12 rounded-lg border border-purple-500/30 bg-purple-500/10 flex items-center justify-center text-purple-400">
                    <i class="fas fa-calendar-check text-xl"></i>
                </div>
            </div>
            <div class="flex justify-between text-xs mb-2">
                <span class="text-slate-400">Problems completed</span>
                <span class="font-mono font-bold text-white">{{ min($hub['daily_answered'], $hub['daily_goal']) }}/{{ $hub['daily_goal'] }}</span>
            </div>
            <div class="h-3 bg-white/5 rounded-full overflow-hidden border border-white/5">
                <div class="h-full bg-gradient-to-r from-purple-500 to-cyan-400 rounded-full transition-all" style="width: {{ $hub['daily_percent'] }}%"></div>
            </div>
            <p class="text-xs text-slate-500 mt-4">
                {{ $hub['daily_percent'] >= 100 ? 'Daily quest complete. You can keep solving for more mastery and XP.' : 'Complete ten adaptive problems. MathVerse chooses every question for you.' }}
            </p>
            @if($hub['configured'])
                <a href="/student/learning-hub/practice?mode=daily" class="btn-rect-secondary block text-center mt-6 !py-3">
                    {{ $hub['daily_percent'] >= 100 ? 'Keep Practicing' : 'Start Daily Quest' }}
                </a>
            @endif
        </article>
    </section>

    <section class="mb-8">
        <div class="flex items-center justify-between gap-4 mb-4">
            <div>
                <p class="text-[9px] uppercase tracking-[0.3em] font-bold text-slate-500">Choose your mission</p>
                <h3 class="font-orbitron font-bold uppercase text-lg mt-1">Practice Modes</h3>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="{{ $hub['configured'] ? '/student/learning-hub/practice?mode=adventure' : '#' }}" class="portal-frame !p-6 learning-mode-card border-cyan-500/30 {{ !$hub['configured'] ? 'pointer-events-none opacity-50' : '' }}">
                <i class="fas fa-infinity text-2xl text-cyan-400"></i>
                <h4 class="font-orbitron font-bold mt-4">Endless Adventure</h4>
                <p class="text-xs text-slate-500 mt-2">A continuous adaptive path through every Grade {{ $hub['grade'] }} skill.</p>
            </a>
            <a href="{{ $hub['configured'] ? '/student/learning-hub/practice?mode=daily' : '#' }}" class="portal-frame !p-6 learning-mode-card border-purple-500/30 {{ !$hub['configured'] ? 'pointer-events-none opacity-50' : '' }}">
                <i class="fas fa-bullseye text-2xl text-purple-400"></i>
                <h4 class="font-orbitron font-bold mt-4">Daily Quest</h4>
                <p class="text-xs text-slate-500 mt-2">Ten focused questions with an automatic goal and immediate rewards.</p>
            </a>
            <a href="{{ $hub['configured'] ? '/student/learning-hub/practice?mode=review' : '#' }}" class="portal-frame !p-6 learning-mode-card border-orange-500/30 {{ !$hub['configured'] ? 'pointer-events-none opacity-50' : '' }}">
                <i class="fas fa-screwdriver-wrench text-2xl text-orange-400"></i>
                <h4 class="font-orbitron font-bold mt-4">Weak Skill Rescue</h4>
                <p class="text-xs text-slate-500 mt-2">Automatically repairs the skills with your lowest current mastery.</p>
            </a>
        </div>
    </section>

    <section>
        <div class="mb-4">
            <p class="text-[9px] uppercase tracking-[0.3em] font-bold text-slate-500">Grade {{ $hub['grade'] }} map</p>
            <h3 class="font-orbitron font-bold uppercase text-lg mt-1">Skill Worlds</h3>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach($hub['skills'] as $skill)
                <article class="portal-frame !p-5" style="border-color: {{ $skill['color'] }}55;">
                    <div class="flex items-start justify-between gap-3">
                        <div class="w-11 h-11 rounded-lg flex items-center justify-center" style="color: {{ $skill['color'] }}; background: {{ $skill['color'] }}18; border: 1px solid {{ $skill['color'] }}44;">
                            <i class="fas {{ $skill['icon'] }}"></i>
                        </div>
                        <span class="text-[9px] uppercase font-bold tracking-wider text-slate-500">Lv. {{ $skill['difficulty'] }}</span>
                    </div>
                    <p class="text-[9px] uppercase tracking-widest mt-5" style="color: {{ $skill['color'] }};">{{ $skill['world'] }}</p>
                    <h4 class="font-bold text-white mt-1 min-h-12">{{ $skill['title'] }}</h4>
                    <div class="flex justify-between text-[10px] mt-4 mb-2">
                        <span class="text-slate-500">{{ $skill['status'] }}</span>
                        <span class="font-mono text-white">{{ $skill['mastery'] }}%</span>
                    </div>
                    <div class="h-1.5 bg-white/5 rounded-full overflow-hidden">
                        <div class="h-full rounded-full" style="width: {{ $skill['mastery'] }}%; background: {{ $skill['color'] }};"></div>
                    </div>
                    <p class="text-[9px] text-slate-600 mt-3">
                        {{ $skill['attempts'] > 0 ? $skill['attempts'].' attempts · '.$skill['accuracy'].'% accuracy' : 'Waiting for your first mission' }}
                    </p>
                </article>
            @endforeach
        </div>
    </section>

    <div class="mt-7 text-center text-[10px] text-slate-600 uppercase tracking-widest">
        <i class="fas fa-heart-pulse mr-2 text-cyan-500"></i>
        Progress saves after every answer. MathVerse will suggest a rest after a healthy practice session.
    </div>
</div>
@endsection

@section('modals')
    @include('student.partials.logout-modal')
@endsection
