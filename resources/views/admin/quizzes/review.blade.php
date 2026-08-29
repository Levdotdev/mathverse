@extends('layouts.dashboard')

@section('title', $quiz['topic'] . ' Review')
@section('sidebar-border', 'border-red-500/20')
@section('sidebar-subtitle', 'System Administrator')
@section('sidebar-subtitle-color', 'text-red-500/60')
@section('accent-color', 'text-red-500')
@section('mobile-title', 'Quiz Review')

@section('sidebar-nav')
    @include('admin.partials.sidebar-nav', ['activePage' => 'library'])
@endsection

@section('dashboard-content')
<a href="/admin/quiz-library"
   class="inline-block text-xs text-slate-400 hover:text-white font-bold uppercase mb-6">
    <i class="fas fa-arrow-left mr-2"></i> Back to Shared Library
</a>

<header class="portal-frame !p-6 md:!p-8 mb-7 border-l-4 border-blue-500">
    <p class="text-[10px] text-blue-400 uppercase tracking-widest font-bold">Read-only shared quiz</p>
    <h1 class="text-2xl font-orbitron font-bold mt-2">{{ $quiz['topic'] }}</h1>
    <div class="flex flex-wrap gap-3 mt-5 text-[10px] font-bold uppercase tracking-widest">
        <span class="px-3 py-2 rounded bg-blue-500/10 text-blue-300">Grade {{ $quiz['grade_level'] }}</span>
        <span class="px-3 py-2 rounded bg-white/5 text-slate-400">{{ count($questions) }} Questions</span>
        <span class="px-3 py-2 text-slate-500">By {{ $creatorName }}</span>
    </div>
</header>

<div class="space-y-5">
    @forelse($questions as $index => $question)
        <article class="portal-frame !p-6">
            <p class="text-[10px] text-blue-400 uppercase font-bold tracking-widest mb-3">Question {{ $index + 1 }}</p>
            <h2 class="text-lg font-bold text-white mb-5">{{ $question['question'] }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($question['choices'] as $choiceIndex => $choice)
                    @php $isCorrect = $choiceIndex === $question['correct_index']; @endphp
                    <div class="p-4 rounded border {{ $isCorrect ? 'border-green-500/50 bg-green-500/10 text-green-300' : 'border-white/5 bg-white/5 text-slate-400' }}">
                        <span class="font-bold mr-2">{{ chr(65 + $choiceIndex) }}.</span>{{ $choice }}
                        @if($isCorrect)<i class="fas fa-check-circle float-right mt-1"></i>@endif
                    </div>
                @endforeach
            </div>
        </article>
    @empty
        <div class="portal-frame !p-10 text-center text-slate-500 text-xs uppercase">
            This quiz has no questions to review.
        </div>
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
