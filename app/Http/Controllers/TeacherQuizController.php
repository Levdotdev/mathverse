<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\Request;

class TeacherQuizController extends Controller
{
    public function __construct(private SupabaseService $supabase) {}

    public function index(Request $request)
    {
        $user = session('supabase_user');
        [$search, $grade, $safeSearch] = $this->quizFilters($request);
        $filters = [
            'teacher_id' => $user['id'],
            'order' => 'grade_level.asc,created_at.desc',
        ];

        if ($grade !== null) {
            $filters['grade_level'] = $grade;
        }
        if ($safeSearch !== '') {
            $filters['topic'] = ['operator' => 'ilike', 'value' => "*{$safeSearch}*"];
        }

        $quizzes = $this->supabase->adminSelect(
            'quizzes',
            '*',
            $filters
        );

        $questionCounts = $this->questionCounts(array_column($quizzes, 'id'));
        foreach ($quizzes as &$quiz) {
            $quiz['question_count'] = $questionCounts[$quiz['id']] ?? 0;
        }
        unset($quiz);

        $classes = $this->teacherClasses($user['id']);
        $preferredClassId = $this->preferredClassId($request, $classes);

        return view('teacher.quizzes.index', compact(
            'user', 'quizzes', 'classes', 'preferredClassId', 'search', 'grade'
        ));
    }

    public function library(Request $request)
    {
        $user = session('supabase_user');
        [$search, $grade, $safeSearch] = $this->quizFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 24;

        $filters = [
            'teacher_id' => ['operator' => 'neq', 'value' => $user['id']],
            'order'      => 'grade_level.asc,created_at.desc',
        ];

        if ($grade !== null) {
            $filters['grade_level'] = $grade;
        }

        if ($safeSearch !== '') {
            $filters['topic'] = ['operator' => 'ilike', 'value' => "*{$safeSearch}*"];
        }

        $result = $this->supabase->adminSelectPage(
            'quizzes',
            '*',
            $filters,
            $perPage,
            ($page - 1) * $perPage
        );

        $quizzes = $result['data'];
        $total = $result['total'];
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages && $total > 0) {
            return redirect()->to($request->fullUrlWithQuery(['page' => $totalPages]));
        }

        $creatorNames = $this->creatorNames(array_column($quizzes, 'teacher_id'));
        $questionCounts = $this->questionCounts(array_column($quizzes, 'id'));
        $quizzesByGrade = array_fill(1, 6, []);

        foreach ($quizzes as &$quiz) {
            $quiz['creator_name'] = $creatorNames[$quiz['teacher_id']] ?? 'MathVerse Teacher';
            $quiz['question_count'] = $questionCounts[$quiz['id']] ?? 0;
            $quizzesByGrade[(int) $quiz['grade_level']][] = $quiz;
        }
        unset($quiz);

        $classes = $this->teacherClasses($user['id']);
        $preferredClassId = $this->preferredClassId($request, $classes);

        return view('teacher.quizzes.library', compact(
            'user', 'quizzesByGrade', 'classes', 'preferredClassId',
            'search', 'grade', 'page', 'total', 'totalPages'
        ));
    }

    public function review(Request $request, string $id)
    {
        $user = session('supabase_user');
        $quiz = $this->supabase->adminSelect('quizzes', '*', ['id' => $id])[0] ?? null;

        if (!$quiz) {
            return redirect('/teacher/quiz-library')->with('error', 'Quiz not found.');
        }

        if (($quiz['teacher_id'] ?? null) === $user['id']) {
            return redirect('/teacher/quizzes')->with('error', 'Open your quiz from My Quizzes to edit it.');
        }

        $templateQuestions = $this->supabase->adminSelect(
            'quiz_questions',
            '*',
            ['quiz_id' => $id, 'order' => 'position.asc']
        );
        $questionsForForm = $this->questionsForForm($templateQuestions);
        $creatorNames = $this->creatorNames([$quiz['teacher_id']]);
        $creatorName = $creatorNames[$quiz['teacher_id']] ?? 'MathVerse Teacher';
        $classes = $this->teacherClasses($user['id']);
        $preferredClassId = $this->preferredClassId($request, $classes);

        return view('teacher.quizzes.review', compact(
            'user', 'quiz', 'questionsForForm', 'creatorName', 'classes', 'preferredClassId'
        ));
    }

    public function copyAndAssign(Request $request, string $id)
    {
        $validated = $this->validateQuiz($request);
        $assignment = $request->validate([
            'class_ids' => 'required|array|min:1|max:100',
            'class_ids.*' => 'required|uuid|distinct',
            'time_limit' => 'required|integer|between:5,300',
        ]);

        $user = session('supabase_user');
        $token = session('supabase_token');
        $sourceQuiz = $this->supabase->adminSelect('quizzes', '*', ['id' => $id])[0] ?? null;

        if (!$sourceQuiz || ($sourceQuiz['teacher_id'] ?? null) === $user['id']) {
            return redirect('/teacher/quiz-library')
                ->with('error', 'The shared quiz is no longer available.');
        }

        $classIds = array_values($assignment['class_ids']);
        $classes = $this->supabase->adminSelect('classes', '*', [
            'id' => ['operator' => 'in', 'value' => '(' . implode(',', $classIds) . ')'],
            'teacher_id' => $user['id'],
        ]);
        $classesById = [];
        foreach ($classes as $class) {
            $classesById[$class['id']] = $class;
        }

        if (count($classesById) !== count($classIds)) {
            return back()->withInput()->with('error', 'One or more selected classes are not available.');
        }

        $grade = (int) $validated['grade_level'];
        $orderedClasses = [];
        foreach ($classIds as $classId) {
            $class = $classesById[$classId];
            if (!empty($class['archived_at'])) {
                return back()->withInput()->with('error', 'Archived classes cannot receive quiz assignments.');
            }
            if ((int) $class['grade_level'] !== $grade) {
                return back()->withInput()
                    ->with('error', 'Every selected class must match the copied quiz grade level.');
            }
            $orderedClasses[] = $class;
        }

        $copy = $this->supabase->insert('quizzes', [
            'teacher_id' => $user['id'],
            'topic' => trim($validated['topic']),
            'grade_level' => $grade,
            'updated_at' => now()->toIso8601String(),
        ], $token);
        $copyId = $copy[0]['id'] ?? null;

        if (!$copyId) {
            return back()->withInput()->with('error', 'The quiz copy could not be created.');
        }

        if (!$this->saveTemplateQuestions($copyId, $validated['questions'], $grade, $token)) {
            $this->rollbackQuizCopy($copyId, []);
            return back()->withInput()->with('error', 'The quiz copy could not be saved.');
        }

        $copiedQuestions = $this->supabase->adminSelect(
            'quiz_questions',
            '*',
            ['quiz_id' => $copyId, 'order' => 'position.asc']
        );
        if (count($copiedQuestions) !== count($validated['questions'])) {
            $this->rollbackQuizCopy($copyId, []);
            return back()->withInput()->with('error', 'The copied questions could not be verified.');
        }

        $createdSessionIds = [];
        try {
            foreach ($orderedClasses as $class) {
                $session = $this->supabase->insert('quiz_sessions', [
                    'teacher_id' => $user['id'],
                    'source_quiz_id' => $copyId,
                    'topic' => trim($validated['topic']),
                    'room_code' => $this->generateRoomCode(),
                    'class_id' => $class['id'],
                    'max_members' => 60,
                    'time_limit' => (int) $assignment['time_limit'],
                    'is_active' => true,
                    'status' => 'waiting',
                ], $token);

                $sessionId = $session[0]['id'] ?? null;
                if (!$sessionId) {
                    throw new \RuntimeException('A class assignment could not be created.');
                }
                $createdSessionIds[] = $sessionId;

                if (!$this->saveSessionQuestions($sessionId, $copiedQuestions, $token)) {
                    throw new \RuntimeException('A class assignment could not copy its questions.');
                }
            }
        } catch (\Throwable $exception) {
            $this->rollbackQuizCopy($copyId, $createdSessionIds);

            return back()->withInput()->with(
                'error',
                'Nothing was assigned because one of the class assignments could not be completed.'
            );
        }

        $classCount = count($orderedClasses);
        $classLabel = $classCount === 1 ? '1 class' : "{$classCount} classes";

        return redirect('/teacher/quizzes')->with(
            'success',
            "Quiz copied to My Quizzes and assigned to {$classLabel}."
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validateQuiz($request);
        $user = session('supabase_user');
        $token = session('supabase_token');

        $created = $this->supabase->insert('quizzes', [
            'teacher_id' => $user['id'],
            'topic' => trim($validated['topic']),
            'grade_level' => (int) $validated['grade_level'],
            'updated_at' => now()->toIso8601String(),
        ], $token);

        $quizId = $created[0]['id'] ?? null;
        if (!$quizId) {
            return redirect('/teacher/quizzes')
                ->with('error', 'The quiz could not be created. Run the new Supabase migration first.');
        }

        if (!$this->saveTemplateQuestions(
            $quizId,
            $validated['questions'],
            (int) $validated['grade_level'],
            $token
        )) {
            $this->supabase->delete('quizzes', ['id' => $quizId]);

            return redirect('/teacher/quizzes')
                ->with('error', 'The questions could not be saved. Please try again.');
        }

        return redirect('/teacher/quizzes')->with('success', 'Quiz created and shared with other teachers.');
    }

    public function update(Request $request, string $id)
    {
        $user = session('supabase_user');
        $token = session('supabase_token');
        $quiz = $this->ownedQuiz($id, $user['id']);

        if (!$quiz) {
            return redirect('/teacher/quizzes')->with('error', 'You can only edit quizzes you created.');
        }

        $validated = $this->validateQuiz($request);
        $oldQuestions = $this->supabase->adminSelect(
            'quiz_questions',
            '*',
            ['quiz_id' => $id, 'order' => 'position.asc']
        );

        $updated = $this->supabase->update('quizzes', [
            'topic' => trim($validated['topic']),
            'grade_level' => (int) $validated['grade_level'],
            'updated_at' => now()->toIso8601String(),
        ], ['id' => $id], $token);

        if (!isset($updated[0]['id'])) {
            return redirect('/teacher/quizzes')->with('error', 'The quiz could not be updated.');
        }

        $this->supabase->delete('quiz_questions', ['quiz_id' => $id]);
        $saved = $this->saveTemplateQuestions(
            $id,
            $validated['questions'],
            (int) $validated['grade_level'],
            $token
        );

        if (!$saved) {
            $this->supabase->update('quizzes', [
                'topic' => $quiz['topic'],
                'grade_level' => $quiz['grade_level'],
                'updated_at' => $quiz['updated_at'] ?? $quiz['created_at'],
            ], ['id' => $id], $token);
            $this->supabase->delete('quiz_questions', ['quiz_id' => $id]);
            $this->restoreTemplateQuestions($oldQuestions, $token);

            return redirect('/teacher/quizzes')
                ->with('error', 'The new questions could not be saved, so the previous quiz was restored.');
        }

        return redirect('/teacher/quizzes')->with('success', 'Quiz updated. Existing class assignments were not changed.');
    }

    public function destroy(string $id)
    {
        $user = session('supabase_user');
        if (!$this->ownedQuiz($id, $user['id'])) {
            return redirect('/teacher/quizzes')->with('error', 'You can only delete quizzes you created.');
        }

        if (!$this->supabase->delete('quizzes', ['id' => $id])) {
            return redirect('/teacher/quizzes')->with('error', 'The quiz could not be deleted.');
        }

        return redirect('/teacher/quizzes')
            ->with('success', 'Quiz deleted. Existing class sessions and results were preserved.');
    }

    public function show(string $id)
    {
        $user = session('supabase_user');
        $quiz = $this->ownedQuiz($id, $user['id']);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found.'], 404);
        }

        $questions = $this->supabase->adminSelect(
            'quiz_questions',
            '*',
            ['quiz_id' => $id, 'order' => 'position.asc']
        );

        return response()->json(compact('quiz', 'questions'));
    }

    public function assign(Request $request, string $id)
    {
        $validated = $request->validate([
            'class_id' => 'required|uuid',
            'time_limit' => 'required|integer|between:5,300',
            'return_to_class' => 'nullable|uuid',
        ]);

        $user = session('supabase_user');
        $token = session('supabase_token');
        $quiz = $this->ownedQuiz($id, $user['id']);
        $class = $this->supabase->adminSelect('classes', '*', [
            'id' => $validated['class_id'],
            'teacher_id' => $user['id'],
        ])[0] ?? null;

        if (!$quiz || !$class) {
            return back()->with('error', 'The selected quiz or class is not available. Shared quizzes must be copied first.');
        }

        if (!empty($class['archived_at'])) {
            return back()->with('error', 'Archived classes cannot receive new quiz assignments.');
        }

        if ((int) $quiz['grade_level'] !== (int) $class['grade_level']) {
            return back()->with('error', 'A quiz can only be assigned to a class with the same grade level.');
        }

        $alreadyOpen = $this->supabase->adminSelect('quiz_sessions', 'id,status', [
            'class_id' => $class['id'],
            'source_quiz_id' => $quiz['id'],
        ]);
        $alreadyOpen = array_filter(
            $alreadyOpen,
            fn ($session) => in_array($session['status'] ?? 'waiting', ['waiting', 'active'], true)
        );

        if (!empty($alreadyOpen)) {
            return back()->with('error', 'This quiz is already assigned or active in that class.');
        }

        $templateQuestions = $this->supabase->adminSelect(
            'quiz_questions',
            '*',
            ['quiz_id' => $quiz['id'], 'order' => 'position.asc']
        );

        if (empty($templateQuestions)) {
            return back()->with('error', 'This quiz has no questions and cannot be assigned.');
        }

        $session = $this->supabase->insert('quiz_sessions', [
            'teacher_id' => $user['id'],
            'source_quiz_id' => $quiz['id'],
            'topic' => $quiz['topic'],
            'room_code' => $this->generateRoomCode(),
            'class_id' => $class['id'],
            'max_members' => 60,
            'time_limit' => (int) $validated['time_limit'],
            'is_active' => true,
            'status' => 'waiting',
        ], $token);

        $sessionId = $session[0]['id'] ?? null;
        if (!$sessionId) {
            return back()->with('error', 'The quiz could not be assigned. Please try again.');
        }

        if (!$this->saveSessionQuestions($sessionId, $templateQuestions, $token)) {
            $this->supabase->delete('questions', ['session_id' => $sessionId]);
            $this->supabase->delete('quiz_sessions', ['id' => $sessionId]);

            return back()->with('error', 'The quiz assignment was cancelled because its questions could not be copied.');
        }

        if (($validated['return_to_class'] ?? null) === $class['id']) {
            return redirect("/teacher/classes/{$class['id']}")
                ->with('success', 'Quiz assigned. Its VR code is ready for the class.');
        }

        return back()->with('success', "Quiz assigned to {$class['class_name']}.");
    }

    private function validateQuiz(Request $request): array
    {
        return $request->validate([
            'topic' => 'required|string|max:150',
            'grade_level' => 'required|integer|between:1,6',
            'questions' => 'required|array|min:1|max:100',
            'questions.*.question' => 'required|string|max:1000',
            'questions.*.options' => 'required|array|size:4',
            'questions.*.options.*' => 'required|string|max:500',
            'questions.*.correct' => 'required|integer|between:0,3',
        ]);
    }

    private function quizFilters(Request $request): array
    {
        $search = trim(mb_substr((string) $request->query('search', ''), 0, 80));
        $grade = (int) $request->query('grade', 0);
        $grade = ($grade >= 1 && $grade <= 6) ? $grade : null;
        $safeSearch = trim(str_replace(['*', '%'], '', $search));

        return [$search, $grade, $safeSearch];
    }

    private function saveTemplateQuestions(
        string $quizId,
        array $questions,
        int $grade,
        string $token
    ): bool {
        $rows = [];
        foreach (array_values($questions) as $index => $question) {
            $options = array_values($question['options']);
            $rows[] = [
                'quiz_id' => $quizId,
                'position' => $index + 1,
                'grade' => $grade,
                'type' => 'multiple_choice',
                'question' => trim($question['question']),
                'choice1' => trim($options[0]),
                'choice2' => trim($options[1]),
                'choice3' => trim($options[2]),
                'choice4' => trim($options[3]),
                'choice5' => '',
                'choice6' => '',
                'correct_answer' => (string) ((int) $question['correct']),
            ];
        }

        $saved = $this->supabase->insert('quiz_questions', $rows, $token);

        return count($saved) === count($rows);
    }

    private function restoreTemplateQuestions(array $questions, string $token): void
    {
        $rows = array_map(function (array $question): array {
            unset($question['id']);
            return $question;
        }, $questions);

        if (!empty($rows)) {
            $this->supabase->insert('quiz_questions', $rows, $token);
        }
    }

    private function questionsForForm(array $questions): array
    {
        return array_map(function (array $question): array {
            $options = [
                $question['choice1'],
                $question['choice2'],
                $question['choice3'],
                $question['choice4'],
            ];
            $correctIndex = filter_var($question['correct_answer'], FILTER_VALIDATE_INT);
            if ($correctIndex === false || $correctIndex < 0 || $correctIndex > 3) {
                $correctIndex = array_search($question['correct_answer'], $options, true);
                $correctIndex = $correctIndex === false ? 0 : $correctIndex;
            }

            return [
                'question' => $question['question'],
                'options' => $options,
                'correct' => $correctIndex,
            ];
        }, $questions);
    }

    private function rollbackQuizCopy(string $quizId, array $sessionIds): void
    {
        foreach ($sessionIds as $sessionId) {
            $this->supabase->delete('questions', ['session_id' => $sessionId]);
            $this->supabase->delete('quiz_sessions', ['id' => $sessionId]);
        }

        $this->supabase->delete('quiz_questions', ['quiz_id' => $quizId]);
        $this->supabase->delete('quizzes', ['id' => $quizId]);
    }

    private function saveSessionQuestions(string $sessionId, array $questions, string $token): bool
    {
        $rows = array_map(fn (array $question): array => [
            'session_id' => $sessionId,
            'grade' => (int) $question['grade'],
            'type' => $question['type'] ?? 'multiple_choice',
            'question' => $question['question'],
            'choice1' => $question['choice1'],
            'choice2' => $question['choice2'],
            'choice3' => $question['choice3'],
            'choice4' => $question['choice4'],
            'choice5' => $question['choice5'] ?? '',
            'choice6' => $question['choice6'] ?? '',
            'correct_answer' => (string) $question['correct_answer'],
        ], $questions);

        $saved = $this->supabase->insert('questions', $rows, $token);

        return count($saved) === count($rows);
    }

    private function ownedQuiz(string $id, string $teacherId): ?array
    {
        return $this->supabase->adminSelect('quizzes', '*', [
            'id' => $id,
            'teacher_id' => $teacherId,
        ])[0] ?? null;
    }

    private function teacherClasses(string $teacherId): array
    {
        return $this->supabase->adminSelect(
            'classes',
            'id,class_name,grade_level,archived_at',
            [
                'teacher_id' => $teacherId,
                'archived_at' => ['operator' => 'is', 'value' => 'null'],
                'order' => 'class_name.asc',
            ]
        );
    }

    private function preferredClassId(Request $request, array $classes): ?string
    {
        $requested = (string) $request->query('class_id', '');
        foreach ($classes as $class) {
            if ($class['id'] === $requested) {
                return $requested;
            }
        }

        return null;
    }

    private function creatorNames(array $teacherIds): array
    {
        $teacherIds = array_values(array_unique(array_filter($teacherIds)));
        if (empty($teacherIds)) {
            return [];
        }

        $profiles = $this->supabase->adminSelect(
            'profiles',
            'id,first_name,last_name',
            ['id' => ['operator' => 'in', 'value' => '(' . implode(',', $teacherIds) . ')']]
        );

        $names = [];
        foreach ($profiles as $profile) {
            $names[$profile['id']] = trim(
                ($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')
            ) ?: 'MathVerse Teacher';
        }

        return $names;
    }

    private function questionCounts(array $quizIds): array
    {
        $quizIds = array_values(array_unique(array_filter($quizIds)));
        if (empty($quizIds)) {
            return [];
        }

        $questions = $this->supabase->adminSelect(
            'quiz_questions',
            'quiz_id',
            ['quiz_id' => ['operator' => 'in', 'value' => '(' . implode(',', $quizIds) . ')']]
        );

        $counts = [];
        foreach ($questions as $question) {
            $quizId = $question['quiz_id'];
            $counts[$quizId] = ($counts[$quizId] ?? 0) + 1;
        }

        return $counts;
    }

    private function generateRoomCode(): string
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $code = (string) random_int(1000, 9999);
            $open = $this->supabase->adminSelect('quiz_sessions', 'id', [
                'room_code' => $code,
                'is_active' => true,
            ]);

            if (empty($open)) {
                return $code;
            }
        }

        throw new \RuntimeException('Unable to generate an available VR room code.');
    }
}
