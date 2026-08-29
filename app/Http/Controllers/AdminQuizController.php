<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\Request;

class AdminQuizController extends Controller
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

        $quizzes = $this->supabase->adminSelect('quizzes', '*', $filters);
        $questionCounts = $this->questionCounts(array_column($quizzes, 'id'));

        foreach ($quizzes as &$quiz) {
            $quiz['question_count'] = $questionCounts[$quiz['id']] ?? 0;
        }
        unset($quiz);

        return view('admin.quizzes.index', compact('user', 'quizzes', 'search', 'grade'));
    }

    public function library(Request $request)
    {
        $user = session('supabase_user');
        [$search, $grade, $safeSearch] = $this->quizFilters($request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 24;

        $filters = [
            'teacher_id' => ['operator' => 'neq', 'value' => $user['id']],
            'order' => 'grade_level.asc,created_at.desc',
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
            $quiz['creator_name'] = $creatorNames[$quiz['teacher_id']] ?? 'MathVerse User';
            $quiz['question_count'] = $questionCounts[$quiz['id']] ?? 0;
            $quizzesByGrade[(int) $quiz['grade_level']][] = $quiz;
        }
        unset($quiz);

        return view('admin.quizzes.library', compact(
            'user', 'quizzesByGrade', 'search', 'grade', 'page', 'total', 'totalPages'
        ));
    }

    public function store(Request $request)
    {
        $validated = $this->validateQuiz($request);
        $user = session('supabase_user');

        $created = $this->supabase->adminInsert('quizzes', [
            'teacher_id' => $user['id'],
            'topic' => trim($validated['topic']),
            'grade_level' => (int) $validated['grade_level'],
            'updated_at' => now()->toIso8601String(),
        ]);

        $quizId = $created[0]['id'] ?? null;
        if (!$quizId) {
            return redirect('/admin/quizzes')->with('error', 'The quiz could not be created.');
        }

        if (!$this->saveTemplateQuestions(
            $quizId,
            $validated['questions'],
            (int) $validated['grade_level']
        )) {
            $this->supabase->adminDelete('quizzes', ['id' => $quizId]);
            return redirect('/admin/quizzes')->with('error', 'The quiz questions could not be saved.');
        }

        return redirect('/admin/quizzes')->with('success', 'Admin quiz created in the shared library.');
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

    public function update(Request $request, string $id)
    {
        $user = session('supabase_user');
        $quiz = $this->ownedQuiz($id, $user['id']);

        if (!$quiz) {
            return redirect('/admin/quizzes')->with('error', 'You can only edit quizzes you created.');
        }

        $validated = $this->validateQuiz($request);
        $oldQuestions = $this->supabase->adminSelect(
            'quiz_questions',
            '*',
            ['quiz_id' => $id, 'order' => 'position.asc']
        );

        $updated = $this->supabase->adminUpdate('quizzes', [
            'topic' => trim($validated['topic']),
            'grade_level' => (int) $validated['grade_level'],
            'updated_at' => now()->toIso8601String(),
        ], ['id' => $id, 'teacher_id' => $user['id']]);

        if (!isset($updated[0]['id'])) {
            return redirect('/admin/quizzes')->with('error', 'The quiz could not be updated.');
        }

        $this->supabase->adminDelete('quiz_questions', ['quiz_id' => $id]);
        if (!$this->saveTemplateQuestions(
            $id,
            $validated['questions'],
            (int) $validated['grade_level']
        )) {
            $this->supabase->adminUpdate('quizzes', [
                'topic' => $quiz['topic'],
                'grade_level' => $quiz['grade_level'],
                'updated_at' => $quiz['updated_at'] ?? $quiz['created_at'],
            ], ['id' => $id, 'teacher_id' => $user['id']]);
            $this->supabase->adminDelete('quiz_questions', ['quiz_id' => $id]);
            $this->restoreTemplateQuestions($oldQuestions);

            return redirect('/admin/quizzes')
                ->with('error', 'The new questions could not be saved, so the previous quiz was restored.');
        }

        return redirect('/admin/quizzes')
            ->with('success', 'Quiz updated. Existing class assignments were not changed.');
    }

    public function review(string $id)
    {
        $user = session('supabase_user');
        $quiz = $this->supabase->adminSelect('quizzes', '*', ['id' => $id])[0] ?? null;

        if (!$quiz) {
            return redirect('/admin/quiz-library')->with('error', 'Quiz not found.');
        }

        if (($quiz['teacher_id'] ?? null) === $user['id']) {
            return redirect('/admin/quizzes')->with('error', 'Open your quiz from My Quizzes to edit it.');
        }

        $questions = $this->reviewQuestions($this->supabase->adminSelect(
            'quiz_questions',
            '*',
            ['quiz_id' => $id, 'order' => 'position.asc']
        ));
        $creatorNames = $this->creatorNames([$quiz['teacher_id']]);
        $creatorName = $creatorNames[$quiz['teacher_id']] ?? 'MathVerse User';

        return view('admin.quizzes.review', compact('user', 'quiz', 'questions', 'creatorName'));
    }

    public function destroy(Request $request, string $id)
    {
        $destination = $this->quizDestination($request);
        $quiz = $this->supabase->adminSelect('quizzes', 'id', ['id' => $id])[0] ?? null;
        if (!$quiz) {
            return redirect($destination)->with('error', 'Quiz not found.');
        }

        if (!$this->supabase->adminDelete('quizzes', ['id' => $id])) {
            return redirect($destination)->with('error', 'The quiz could not be deleted.');
        }

        return redirect($destination)
            ->with('success', 'Quiz removed from the shared library. Existing class assignments were preserved.');
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

    private function saveTemplateQuestions(string $quizId, array $questions, int $grade): bool
    {
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

        $saved = $this->supabase->adminInsert('quiz_questions', $rows);

        return count($saved) === count($rows);
    }

    private function restoreTemplateQuestions(array $questions): void
    {
        $rows = array_map(function (array $question): array {
            unset($question['id']);
            return $question;
        }, $questions);

        if (!empty($rows)) {
            $this->supabase->adminInsert('quiz_questions', $rows);
        }
    }

    private function reviewQuestions(array $questions): array
    {
        return array_map(function (array $question): array {
            $choices = [
                $question['choice1'],
                $question['choice2'],
                $question['choice3'],
                $question['choice4'],
            ];
            $correctIndex = filter_var($question['correct_answer'], FILTER_VALIDATE_INT);
            if ($correctIndex === false || $correctIndex < 0 || $correctIndex > 3) {
                $correctIndex = array_search($question['correct_answer'], $choices, true);
                $correctIndex = $correctIndex === false ? 0 : $correctIndex;
            }

            return [
                'question' => $question['question'],
                'choices' => $choices,
                'correct_index' => $correctIndex,
            ];
        }, $questions);
    }

    private function ownedQuiz(string $id, string $adminId): ?array
    {
        return $this->supabase->adminSelect('quizzes', '*', [
            'id' => $id,
            'teacher_id' => $adminId,
        ])[0] ?? null;
    }

    private function quizFilters(Request $request): array
    {
        $search = trim(mb_substr((string) $request->query('search', ''), 0, 80));
        $grade = (int) $request->query('grade', 0);
        $grade = ($grade >= 1 && $grade <= 6) ? $grade : null;
        $safeSearch = trim(str_replace(['*', '%'], '', $search));

        return [$search, $grade, $safeSearch];
    }

    private function quizDestination(Request $request): string
    {
        $base = $request->input('return_to') === 'library'
            ? '/admin/quiz-library'
            : '/admin/quizzes';
        $search = trim(mb_substr((string) $request->input('search', ''), 0, 80));
        $grade = (int) $request->input('grade', 0);
        $page = max(1, (int) $request->input('page', 1));
        $query = array_filter([
            'search' => $search,
            'grade' => ($grade >= 1 && $grade <= 6) ? $grade : null,
            'page' => $page > 1 ? $page : null,
        ], fn ($value) => $value !== null && $value !== '');

        return $base . ($query === [] ? '' : '?' . http_build_query($query));
    }

    private function creatorNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return [];
        }

        $profiles = $this->supabase->adminSelect(
            'profiles',
            'id,first_name,last_name,role',
            ['id' => ['operator' => 'in', 'value' => '(' . implode(',', $ids) . ')']]
        );

        $names = [];
        foreach ($profiles as $profile) {
            $name = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?: 'MathVerse User';
            $names[$profile['id']] = ($profile['role'] ?? '') === 'admin' ? "{$name} (Admin)" : $name;
        }

        return $names;
    }

    private function questionCounts(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return [];
        }

        $questions = $this->supabase->adminSelect(
            'quiz_questions',
            'quiz_id',
            ['quiz_id' => ['operator' => 'in', 'value' => '(' . implode(',', $ids) . ')']]
        );

        $counts = [];
        foreach ($questions as $question) {
            $counts[$question['quiz_id']] = ($counts[$question['quiz_id']] ?? 0) + 1;
        }

        return $counts;
    }
}
