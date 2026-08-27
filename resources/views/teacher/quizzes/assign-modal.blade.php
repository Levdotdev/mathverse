<div id="assignQuizModal" class="modal-overlay hidden" data-default-class="{{ $preferredClassId ?? '' }}">
    <div class="portal-frame !p-8 w-full max-w-md text-left border-purple-500/30">
        <div class="text-center mb-6">
            <i class="fas fa-chalkboard-teacher text-4xl text-purple-400 mb-4"></i>
            <h3 class="font-orbitron font-bold uppercase text-white">Assign <span class="text-purple-400">Quiz</span></h3>
            <p id="assign-quiz-topic" class="text-sm text-slate-300 mt-2"></p>
            <p class="text-[10px] text-purple-300 mt-1">Grade <span id="assign-quiz-grade">—</span></p>
        </div>

        <form id="assignQuizForm" method="POST" class="space-y-5">
            @csrf
            <input type="hidden" name="return_to_class" value="{{ $preferredClassId ?? '' }}">
            <div class="form-group">
                <label class="input-label">Matching Class</label>
                <select name="class_id" id="assign-class-select"
                        class="input-mobile-ultra !pl-4 bg-slate-900 text-white" required>
                    <option value="">Select a class</option>
                    @foreach($classes as $class)
                        <option value="{{ $class['id'] }}" data-grade="{{ $class['grade_level'] }}">
                            Grade {{ $class['grade_level'] }} · {{ $class['class_name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="input-label">Time Limit Per Question</label>
                <div class="relative">
                    <i class="fas fa-stopwatch input-icon"></i>
                    <input type="number" name="time_limit" value="20" min="5" max="300"
                           class="input-mobile-ultra" required>
                </div>
                <p class="text-[9px] text-slate-500 mt-1">Choose 5–300 seconds. This applies only to this class assignment.</p>
            </div>
            <p id="assign-no-class" class="hidden text-xs text-red-400 text-center">
                You do not have a class with this grade level yet.
            </p>
            <button type="submit" id="assign-quiz-submit"
                    class="btn-rect-primary !bg-purple-600 !text-white uppercase text-xs">
                Assign to Class
            </button>
        </form>
        <button onclick="closeModal('assignQuizModal')"
                class="text-[10px] font-bold mt-4 uppercase text-slate-500 block w-full text-center">Cancel</button>
    </div>
</div>
