@extends('layouts.dashboard')

@section('title', 'Quiz Version History')
@section('sidebar-border', 'border-red-500/20')
@section('sidebar-subtitle', 'System Administrator')
@section('sidebar-subtitle-color', 'text-red-500/60')
@section('accent-color', 'text-red-500')
@section('mobile-title', 'Quiz History')

@section('sidebar-nav')
    @include('admin.partials.sidebar-nav', ['activePage' => 'quizzes'])
@endsection

@section('dashboard-content')
<a href="/admin/quizzes" class="inline-block text-xs text-slate-400 hover:text-white font-bold uppercase mb-6">
    <i class="fas fa-arrow-left mr-2"></i> Back to My Quizzes
</a>

<header class="portal-frame !p-6 md:!p-8 mb-7 border-l-4 border-cyan-500">
    <p class="text-[10px] text-cyan-400 uppercase tracking-widest font-bold">Version History</p>
    <h1 class="text-2xl font-orbitron font-bold mt-2">{{ $quiz['topic'] }}</h1>
    <p class="text-xs text-slate-400 mt-3">Current version: {{ (int) ($quiz['version'] ?? 1) }}. Older versions are read-only snapshots saved before each update.</p>
</header>

<div class="space-y-4">
    @forelse($versions as $version)
        @php
            $snapshotQuestions = $version['questions'] ?? [];
            if (is_string($snapshotQuestions)) {
                $snapshotQuestions = json_decode($snapshotQuestions, true) ?: [];
            }
        @endphp
        <details class="portal-frame !p-5 group">
            <summary class="cursor-pointer list-none flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <span class="text-cyan-400 font-orbitron font-bold">Version {{ $version['version'] }}</span>
                    <span class="text-xs text-slate-500 ml-2">{{ $version['topic'] }}</span>
                </div>
                <div class="flex items-center gap-3 text-[9px] uppercase font-bold text-slate-500">
                    <span>Grade {{ $version['grade_level'] }}</span>
                    <span>{{ count($snapshotQuestions) }} questions</span>
                    <span>{{ ucfirst($version['visibility'] ?? 'shared') }}</span>
                    <span>{{ \Carbon\Carbon::parse($version['created_at'])->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</span>
                    <i class="fas fa-chevron-down group-open:rotate-180 transition-transform"></i>
                </div>
            </summary>
            <div class="mt-5 pt-5 border-t border-white/10 space-y-4">
                @foreach($snapshotQuestions as $index => $question)
                    @php
                        $choices = array_values(array_filter([
                            $question['choice1'] ?? null,
                            $question['choice2'] ?? null,
                            $question['choice3'] ?? null,
                            $question['choice4'] ?? null,
                            $question['choice5'] ?? null,
                            $question['choice6'] ?? null,
                        ], fn ($choice) => $choice !== null && $choice !== ''));
                        $storedAnswer = (string) ($question['correct_answer'] ?? '');
                        $correctIndex = ctype_digit($storedAnswer) ? (int) $storedAnswer : array_search($storedAnswer, $choices, true);
                    @endphp
                    <div class="rounded border border-white/5 bg-black/20 p-4">
                        <p class="text-[9px] text-cyan-400 uppercase font-bold mb-2">Question {{ $index + 1 }}</p>
                        <p class="text-sm text-white font-bold">{{ $question['question'] ?? 'Question unavailable' }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3">
                            @foreach($choices as $choiceIndex => $choice)
                                <div class="rounded border px-3 py-2 text-xs {{ $choiceIndex === $correctIndex ? 'border-green-500/50 bg-green-500/10 text-green-300' : 'border-white/5 text-slate-400' }}">
                                    @if($choiceIndex === $correctIndex)<i class="fas fa-check mr-2"></i>@endif{{ $choice }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </details>
    @empty
        <div class="portal-frame !p-10 text-center text-slate-500 text-xs uppercase">No previous versions yet. A snapshot is created when you update this quiz.</div>
    @endforelse
</div>
@endsection

@section('modals')
<div id="logoutModal" class="modal-overlay hidden">
    <div class="portal-frame !p-10 w-full max-w-xs text-center border-red-500/30">
        <i class="fas fa-power-off text-4xl text-red-500 mb-4"></i>
        <h3 class="font-orbitron font-bold mb-6 uppercase">End Admin Session?</h3>
        <form method="POST" action="/logout">@csrf<button class="btn-rect-primary !bg-red-600 !text-white">Confirm Logout</button></form>
        <button onclick="closeModal('logoutModal')" class="text-[10px] font-bold mt-4 uppercase text-slate-500">Cancel</button>
    </div>
</div>
@endsection
