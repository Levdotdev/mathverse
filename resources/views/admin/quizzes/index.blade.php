@extends('layouts.dashboard')

@section('title', 'Shared Quiz Administration')
@section('sidebar-border', 'border-red-500/20')
@section('sidebar-subtitle', 'System Administrator')
@section('sidebar-subtitle-color', 'text-red-500/60')
@section('accent-color', 'text-red-500')
@section('mobile-title', 'Shared Quizzes')

@section('sidebar-nav')
    @include('admin.partials.sidebar-nav', ['activePage' => 'quizzes'])
@endsection

@section('dashboard-content')
<div id="quiz-list-container">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-xl md:text-2xl font-orbitron font-bold uppercase">Shared Quiz <span class="text-purple-400">Database</span></h1>
            <p class="text-xs text-slate-500 mt-2">Create admin-authored quizzes or remove any quiz from the shared library. Admins cannot assign quizzes.</p>
        </div>
        <button onclick="loadQuizBuilder()" class="btn-rect-primary !py-3 sm:!w-auto px-6 !bg-red-600 !text-white">
            <i class="fas fa-plus mr-2"></i> Add Quiz
        </button>
    </div>

    <form method="GET" action="/admin/quizzes" class="portal-frame !p-4 mb-6 grid grid-cols-1 sm:grid-cols-[1fr_190px_auto] gap-3">
        <input type="search" name="search" value="{{ $search }}" placeholder="Search quiz topic..." class="input-mobile-ultra !pl-4">
        <select name="grade" class="input-mobile-ultra !pl-4 bg-slate-900 text-white">
            <option value="">All grade levels</option>
            @for($g = 1; $g <= 6; $g++)
                <option value="{{ $g }}" {{ $grade === $g ? 'selected' : '' }}>Grade {{ $g }}</option>
            @endfor
        </select>
        <button class="btn-rect-secondary !py-3 !px-5"><i class="fas fa-search mr-2"></i> Filter</button>
    </form>

    <div class="space-y-4">
        @forelse($quizzes as $quiz)
            <article class="portal-frame !p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                <div class="min-w-0">
                    <h2 class="font-bold text-lg text-white truncate">{{ $quiz['topic'] }}</h2>
                    <div class="flex flex-wrap gap-2 mt-2 text-[10px] font-bold uppercase tracking-widest">
                        <span class="text-purple-300 bg-purple-500/10 px-2 py-1 rounded">Grade {{ $quiz['grade_level'] }}</span>
                        <span class="text-slate-400 bg-white/5 px-2 py-1 rounded">{{ $quiz['question_count'] }} Questions</span>
                        <span class="{{ $quiz['owned_by_admin'] ? 'text-red-300' : 'text-cyan-400' }} px-1 py-1">By {{ $quiz['creator_name'] }}</span>
                    </div>
                </div>
                <button onclick='openDeleteQuizModal(@json($quiz["id"]), @json($quiz["topic"]))'
                        class="btn-rect-secondary !py-2 !px-4 !w-auto !border-red-500/30 text-red-400">
                    <i class="fas fa-trash-alt mr-1"></i> Delete
                </button>
            </article>
        @empty
            <div class="portal-frame !p-10 text-center text-slate-500 text-xs uppercase tracking-widest">No quizzes match these filters.</div>
        @endforelse
    </div>
</div>

<div id="quiz-editor-container" class="hidden">
    <div class="portal-frame !p-6 md:!p-8 relative">
        <button type="button" onclick="toggleQuizView('list')" class="absolute top-5 right-5 text-slate-500 hover:text-white"><i class="fas fa-times-circle text-xl"></i></button>
        <h2 id="builder-title" class="text-xl font-orbitron font-bold mb-2 uppercase">Create <span class="text-purple-400">Quiz</span></h2>
        <p class="text-xs text-slate-500 mb-8">This quiz is immediately available to teachers in the shared library.</p>
        <form id="quiz-form" method="POST" action="/admin/quizzes">
            @csrf
            <span id="method-field"></span>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-8">
                <div class="form-group">
                    <label class="input-label">Quiz Topic</label>
                    <input type="text" name="topic" id="q-topic" maxlength="150" class="input-mobile-ultra !pl-4" required>
                </div>
                <div class="form-group">
                    <label class="input-label">Grade Level</label>
                    <select name="grade_level" id="q-grade" class="input-mobile-ultra !pl-4 bg-slate-900 text-white" required>
                        @for($g = 1; $g <= 6; $g++)<option value="{{ $g }}">Grade {{ $g }}</option>@endfor
                    </select>
                </div>
            </div>
            <div id="questions-builder" class="space-y-6"></div>
            <div class="mt-8 flex flex-col sm:flex-row gap-4">
                <button type="button" onclick="addNewQuestion()" class="btn-rect-secondary flex-1"><i class="fas fa-plus mr-2"></i> Add Question</button>
                <button type="submit" id="save-quiz-btn" class="btn-rect-primary flex-1 !bg-red-600 !text-white"><i class="fas fa-save mr-2"></i> Save Quiz</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('modals')
<div id="deleteQuizModal" class="modal-overlay hidden">
    <div class="portal-frame !p-10 w-full max-w-sm text-center border-red-500/50">
        <i class="fas fa-trash-alt text-4xl text-red-500 mb-4"></i>
        <h3 class="font-orbitron font-bold uppercase">Delete Shared Quiz?</h3>
        <p id="delete-quiz-topic" class="text-xs text-slate-400 my-4"></p>
        <p class="text-[10px] text-slate-500 mb-8">Existing class assignments and results remain available.</p>
        <form id="deleteQuizForm" method="POST">@csrf @method('DELETE')
            <button class="btn-rect-primary !bg-red-600 !text-white">Delete Quiz</button>
        </form>
        <button onclick="closeModal('deleteQuizModal')" class="text-[10px] font-bold mt-4 uppercase text-slate-500">Cancel</button>
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
<script>window.quizRoutesBasePath = '/admin/quizzes';</script>
<script src="{{ asset('js/teacher-quizzes.js') }}"></script>
@if($errors->any())<script>document.addEventListener('DOMContentLoaded', () => loadQuizBuilder());</script>@endif
@endpush
