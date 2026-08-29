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
    <p class="text-[10px] text-blue-400 uppercase tracking-widest font-bold">Administrator review and editing</p>
    <h1 class="text-2xl font-orbitron font-bold mt-2">{{ $quiz['topic'] }}</h1>
    <div class="flex flex-wrap gap-3 mt-5 text-[10px] font-bold uppercase tracking-widest">
        <span class="px-3 py-2 rounded bg-blue-500/10 text-blue-300">Grade {{ $quiz['grade_level'] }}</span>
        <span class="px-3 py-2 rounded bg-white/5 text-slate-400">{{ count($questions) }} Questions</span>
        <span class="px-3 py-2 text-slate-500">By {{ $creatorName }}</span>
        <span class="px-3 py-2 rounded bg-yellow-500/10 text-yellow-400"><i class="fas fa-star mr-1"></i>{{ number_format((float) ($quiz['rating_average'] ?? 0), 1) }} ({{ (int) ($quiz['rating_count'] ?? 0) }})</span>
        <span class="px-3 py-2 rounded bg-cyan-500/10 text-cyan-400">{{ (int) ($quiz['usage_count'] ?? 0) }} class uses</span>
        <span class="px-3 py-2 rounded bg-white/5 text-slate-400">v{{ (int) ($quiz['version'] ?? 1) }}</span>
    </div>
    <div class="flex flex-col sm:flex-row gap-3 mt-5">
        <form method="POST" action="/admin/quiz-library/{{ $quiz['id'] }}/verify">
            @csrf
            <button type="submit" class="btn-rect-secondary !py-2 !px-4 md:!w-auto {{ !empty($quiz['verified_at']) ? 'text-green-400 !border-green-500/40' : '' }}">
                <i class="fas fa-check-circle mr-2"></i>{{ !empty($quiz['verified_at']) ? 'Remove Verification' : 'Mark as Verified' }}
            </button>
        </form>
        <a href="/admin/quizzes/{{ $quiz['id'] }}/versions"
           class="btn-rect-secondary !py-2 !px-4 md:!w-auto text-center">
            <i class="fas fa-history mr-2"></i>Version History
        </a>
    </div>
</header>

<section class="portal-frame !p-6 md:!p-8 mb-7">
    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="font-orbitron font-bold uppercase">Question <span class="text-red-400">Reports</span></h2>
            <p class="text-[10px] text-slate-500 mt-1">Review teacher-submitted concerns and record a moderation outcome.</p>
        </div>
        <span class="text-[10px] text-slate-500 uppercase">{{ count($reports) }} total</span>
    </div>
    <div class="space-y-3">
        @forelse($reports as $report)
            <article class="rounded border {{ ($report['status'] ?? 'pending') === 'pending' ? 'border-red-500/30 bg-red-500/5' : 'border-white/5 bg-black/20' }} p-4">
                <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap gap-2 text-[9px] uppercase font-bold">
                            <span class="text-red-400">{{ str_replace('_', ' ', $report['reason'] ?? 'other') }}</span>
                            @if(!empty($report['question_label']))<span class="text-cyan-400">{{ $report['question_label'] }}</span>@endif
                            <span class="text-slate-500">{{ ucfirst($report['status'] ?? 'pending') }}</span>
                            <span class="text-slate-600">By {{ $report['reporter_name'] }}</span>
                        </div>
                        <p class="text-xs text-slate-300 mt-3">{{ $report['details'] ?: 'No additional details provided.' }}</p>
                        <p class="text-[9px] text-slate-600 mt-2">Submitted {{ \Carbon\Carbon::parse($report['created_at'])->format('M j, Y g:i A') }}</p>
                    </div>
                    @if(($report['status'] ?? 'pending') === 'pending')
                        <div class="flex gap-2 shrink-0">
                            <form method="POST" action="/admin/quiz-library/{{ $quiz['id'] }}/reports/{{ $report['id'] }}">
                                @csrf
                                <input type="hidden" name="status" value="reviewed">
                                <button type="submit" class="btn-rect-secondary !py-2 !px-3 !w-auto text-green-400">Reviewed</button>
                            </form>
                            <form method="POST" action="/admin/quiz-library/{{ $quiz['id'] }}/reports/{{ $report['id'] }}">
                                @csrf
                                <input type="hidden" name="status" value="dismissed">
                                <button type="submit" class="btn-rect-secondary !py-2 !px-3 !w-auto text-slate-400">Dismiss</button>
                            </form>
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <p class="text-xs text-slate-500 text-center py-5 uppercase">No reports for this quiz.</p>
        @endforelse
    </div>
</section>

@php $initialQuestions = old('questions', $questionsForForm); @endphp
<form id="admin-review-quiz-form" method="POST" action="/admin/quiz-library/{{ $quiz['id'] }}" class="space-y-7">
    @csrf
    @method('PUT')
    <input type="hidden" name="visibility" value="shared">

    <section class="portal-frame !p-6 md:!p-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="form-group">
                <label class="input-label">Quiz Topic</label>
                <div class="relative">
                    <i class="fas fa-tag input-icon"></i>
                    <input id="q-topic" type="text" name="topic" maxlength="150"
                           value="{{ old('topic', $quiz['topic']) }}" class="input-mobile-ultra" required>
                </div>
            </div>
            <div class="form-group">
                <label class="input-label">Grade Level</label>
                <select id="q-grade" name="grade_level" class="input-mobile-ultra !pl-4 bg-slate-900 text-white" required>
                    @for($gradeOption = 1; $gradeOption <= 6; $gradeOption++)
                        <option value="{{ $gradeOption }}" {{ (int) old('grade_level', $quiz['grade_level']) === $gradeOption ? 'selected' : '' }}>Grade {{ $gradeOption }}</option>
                    @endfor
                </select>
            </div>
        </div>
    </section>

    <section class="portal-frame !p-6 md:!p-8">
        <div class="mb-6">
            <h2 class="font-orbitron font-bold uppercase">Edit <span class="text-purple-400">Questions</span></h2>
            <p class="text-[10px] text-slate-500 mt-1">Existing class assignments keep their original question snapshots.</p>
        </div>
        <div id="questions-builder" class="space-y-6"></div>
        <div class="mt-6 flex justify-end">
            <button type="button" onclick="addNewQuestion()" class="btn-rect-secondary !py-2 !px-4 sm:!w-auto">
                <i class="fas fa-plus mr-2"></i>Add Question
            </button>
        </div>
    </section>

    <div class="flex flex-col sm:flex-row justify-end gap-3">
        <a href="/admin/quiz-library" class="btn-rect-secondary !py-3 !px-6 sm:!w-auto text-center">Cancel</a>
        <button type="button" onclick="openModal('confirmAdminReviewEditModal')" class="btn-rect-primary !bg-red-600 !text-white !py-3 !px-6 sm:!w-auto">
            <i class="fas fa-save mr-2"></i>Save Teacher Quiz
        </button>
    </div>
</form>
@endsection

@section('modals')
<div id="confirmAdminReviewEditModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="confirm-admin-edit-title">
    <div class="portal-frame !p-9 w-full max-w-sm text-center border-red-500/40">
        <i class="fas fa-edit text-4xl text-red-400 mb-4"></i>
        <h3 id="confirm-admin-edit-title" class="font-orbitron font-bold uppercase">Update Teacher Quiz?</h3>
        <p class="text-xs text-slate-400 my-5">This updates the shared quiz and creates a restorable version. Existing classroom assignments are unchanged.</p>
        <button type="button" onclick="closeModal('confirmAdminReviewEditModal'); document.getElementById('admin-review-quiz-form').requestSubmit()" class="btn-rect-primary !bg-red-600 !text-white">Save Changes</button>
        <button type="button" onclick="closeModal('confirmAdminReviewEditModal')" class="text-[10px] font-bold mt-4 uppercase text-slate-500">Review Again</button>
    </div>
</div>

<div id="logoutModal" class="modal-overlay hidden">
    <div class="portal-frame !p-10 w-full max-w-xs text-center border-red-500/30">
        <i class="fas fa-power-off text-4xl text-red-500 mb-4"></i>
        <h3 class="font-orbitron font-bold mb-6 uppercase">End Admin Session?</h3>
        <form method="POST" action="/logout">@csrf<button class="btn-rect-primary !bg-red-600 !text-white">Confirm Logout</button></form>
        <button onclick="closeModal('logoutModal')" class="text-[10px] font-bold mt-4 uppercase text-slate-500">Cancel</button>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.adminReviewQuestions = @json($initialQuestions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
</script>
<script src="{{ asset('js/teacher-quizzes.js') }}?v={{ filemtime(public_path('js/teacher-quizzes.js')) }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    (window.adminReviewQuestions || []).forEach((question) => {
        addQuestionBlock(question.question || '', question.options || ['', '', '', ''], Number.parseInt(question.correct, 10) || 0);
    });
    if (!document.getElementById('questions-builder')?.children.length) addNewQuestion();
});
</script>
@endpush
