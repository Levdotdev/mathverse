@extends('layouts.dashboard')

@section('title', $quiz['topic'] . ' Review')
@section('sidebar-subtitle', 'Instructional Hub')
@section('mobile-title', 'Quiz Review')

@section('sidebar-nav')
    @include('teacher.partials.sidebar-nav', ['activePage' => 'library'])
@endsection

@section('dashboard-content')
<button type="button" onclick="openModal('cancelSharedQuizModal')"
   class="inline-block text-xs text-slate-400 hover:text-white font-bold uppercase mb-6">
    <i class="fas fa-arrow-left mr-2"></i> Back to Shared Library
</button>

<header class="portal-frame !p-6 md:!p-8 mb-7 border-l-4 border-blue-500">
    <p class="text-[10px] text-blue-400 uppercase tracking-widest font-bold">Shared by {{ $creatorName }}</p>
    <h1 class="text-2xl font-orbitron font-bold mt-2">Review and use this quiz</h1>
    <p class="text-xs text-slate-400 mt-3">Edit and save a personal copy, with the option to assign it to matching classes. The shared original will stay unchanged.</p>
</header>

@php
    $selectedClassIds = old('class_ids', $preferredClassId ? [$preferredClassId] : []);
    $initialQuestions = old('questions', $questionsForForm);
@endphp

<form id="shared-quiz-copy-form" method="POST"
      action="/teacher/quiz-library/{{ $quiz['id'] }}/copy-and-assign" class="space-y-7">
    @csrf

    <section class="portal-frame !p-6 md:!p-8">
        <h2 class="font-orbitron font-bold uppercase mb-6">Quiz <span class="text-purple-400">Copy</span></h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="form-group">
                <label class="input-label">Quiz Topic</label>
                <div class="relative">
                    <i class="fas fa-tag input-icon"></i>
                    <input type="text" name="topic" id="q-topic" maxlength="150"
                           value="{{ old('topic', $quiz['topic']) }}" class="input-mobile-ultra" required>
                </div>
            </div>
            <div class="form-group">
                <label class="input-label">Grade Level</label>
                <select name="grade_level" id="q-grade"
                        class="input-mobile-ultra !pl-4 bg-slate-900 text-white" required>
                    @for($gradeOption = 1; $gradeOption <= 6; $gradeOption++)
                        <option value="{{ $gradeOption }}"
                            {{ (int) old('grade_level', $quiz['grade_level']) === $gradeOption ? 'selected' : '' }}>
                            Grade {{ $gradeOption }}
                        </option>
                    @endfor
                </select>
            </div>
        </div>
    </section>

    <section class="portal-frame !p-6 md:!p-8">
        <div class="mb-6">
            <h2 class="font-orbitron font-bold uppercase">Questions</h2>
            <p class="text-[10px] text-slate-500 mt-1">Changes apply only to your new copy.</p>
        </div>
        <div id="questions-builder" class="space-y-6"></div>
        <div class="mt-6 flex justify-end">
            <button type="button" onclick="addNewQuestion()"
                    class="btn-rect-secondary !py-2 !px-4 sm:!w-auto">
                <i class="fas fa-plus mr-2"></i> Add Question
            </button>
        </div>
    </section>

    <section class="portal-frame !p-6 md:!p-8">
        <div class="mb-6">
            <h2 class="font-orbitron font-bold uppercase">Assign to <span class="text-yellow-400">Classes</span> <span class="text-slate-600">(Optional)</span></h2>
            <p class="text-[10px] text-slate-500 mt-1">Select one or more matching classes, or leave all unchecked to save only a personal copy.</p>
        </div>

        <div id="review-class-list" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($classes as $class)
                <label data-class-option data-grade="{{ $class['grade_level'] }}" data-class-name="{{ $class['class_name'] }}"
                       class="flex items-center gap-3 p-4 rounded border border-white/10 bg-black/30 cursor-pointer hover:border-yellow-500/40 transition-colors">
                    <input type="checkbox" name="class_ids[]" value="{{ $class['id'] }}"
                           {{ in_array($class['id'], $selectedClassIds, true) ? 'checked' : '' }}
                           class="w-4 h-4 accent-yellow-500">
                    <span class="min-w-0">
                        <span class="block text-sm font-bold text-white truncate">{{ $class['class_name'] }}</span>
                        <span class="block text-[10px] text-slate-500 uppercase">Grade {{ $class['grade_level'] }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        <p id="review-no-matching-class" class="hidden text-xs text-yellow-400 text-center py-6">
            You do not have an active class with this grade level, but you can still save a copy.
        </p>

        <div id="review-time-limit" class="form-group mt-6 max-w-sm transition-opacity">
            <label class="input-label">Time Limit Per Question</label>
            <div class="relative">
                <i class="fas fa-stopwatch input-icon"></i>
                <input type="number" id="review-time-limit-input" name="time_limit" value="{{ old('time_limit', 20) }}"
                       min="5" max="300" class="input-mobile-ultra" required>
            </div>
            <p class="text-[9px] text-slate-500 mt-1">5–300 seconds for every selected class.</p>
        </div>
    </section>

    <div class="flex flex-col sm:flex-row justify-end gap-3">
        <button type="button" onclick="openModal('cancelSharedQuizModal')"
                class="btn-rect-secondary !py-3 !px-6 sm:!w-auto text-center">Cancel</button>
        <button type="submit" id="copy-assign-submit"
                class="btn-rect-primary !py-3 !px-6 sm:!w-auto">
            <i class="fas fa-copy mr-2"></i><span id="copy-assign-label">Save Copy</span>
        </button>
    </div>
</form>
@endsection

@section('modals')
    <div id="cancelSharedQuizModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="cancel-shared-quiz-title">
        <div class="portal-frame !p-8 w-full max-w-sm text-center border-red-500/40">
            <i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-4"></i>
            <h3 id="cancel-shared-quiz-title" class="font-orbitron font-bold uppercase text-white">Discard Changes?</h3>
            <p class="text-xs text-slate-400 my-5">Your edits to this quiz copy have not been saved.</p>
            <div class="flex flex-col gap-3">
                <a href="/teacher/quiz-library{{ $preferredClassId ? '?class_id=' . $preferredClassId : '' }}"
                   class="btn-rect-primary !bg-red-600 !text-white text-center">
                    <i class="fas fa-trash-alt mr-2"></i> Discard and Leave
                </a>
                <button type="button" onclick="closeModal('cancelSharedQuizModal')"
                        class="btn-rect-secondary">Keep Editing</button>
            </div>
        </div>
    </div>

    <div id="confirmSharedQuizModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="confirm-shared-quiz-title">
        <div class="portal-frame !p-8 w-full max-w-md text-center border-purple-500/40">
            <i class="fas fa-clipboard-check text-4xl text-purple-400 mb-4"></i>
            <h3 id="confirm-shared-quiz-title" class="font-orbitron font-bold uppercase text-white">Confirm Quiz Copy</h3>
            <p id="confirm-shared-quiz-summary" class="text-xs text-slate-300 mt-4"></p>

            <div class="bg-black/40 border border-white/10 rounded p-4 my-5 text-left">
                <p id="confirm-shared-quiz-meta" class="text-[10px] text-purple-300 uppercase font-bold tracking-wider"></p>
                <div id="confirm-shared-quiz-classes" class="hidden mt-4">
                    <p class="text-[9px] text-slate-500 uppercase font-bold mb-2">Selected Classes</p>
                    <ul id="confirm-shared-quiz-class-list" class="space-y-2 text-xs text-white"></ul>
                </div>
            </div>

            <div class="flex flex-col gap-3">
                <button type="button" id="confirm-shared-quiz-submit"
                        class="btn-rect-primary !bg-purple-600 !text-white">
                    <i class="fas fa-check-circle mr-2"></i><span id="confirm-shared-quiz-button-label">Save Copy</span>
                </button>
                <button type="button" onclick="closeModal('confirmSharedQuizModal')"
                        class="btn-rect-secondary">Review Again</button>
            </div>
        </div>
    </div>

    @include('teacher.partials.logout-modal')
@endsection

@push('scripts')
<script>
window.sharedQuizReviewQuestions = @json($initialQuestions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
</script>
<script src="{{ asset('js/teacher-quizzes.js') }}?v={{ filemtime(public_path('js/teacher-quizzes.js')) }}"></script>
<script src="{{ asset('js/shared-quiz-review.js') }}?v={{ filemtime(public_path('js/shared-quiz-review.js')) }}"></script>
@endpush
