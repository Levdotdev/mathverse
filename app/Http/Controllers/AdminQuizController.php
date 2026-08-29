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
        $verifiedOnly = $request->boolean('verified');
        $reportedOnly = $request->boolean('reported');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 24;

        $filters = [
            'teacher_id' => ['operator' => 'neq', 'value' => $user['id']],
            'visibility' => 'shared',
            'order' => 'verified_at.desc.nullslast,grade_level.asc,created_at.desc',
        ];
        if ($grade !== null) {
            $filters['grade_level'] = $grade;
        }
        if ($safeSearch !== '') {
            $filters['topic'] = ['operator' => 'ilike', 'value' => "*{$safeSearch}*"];
        }

        if ($verifiedOnly) {
            $filters['verified_at'] = ['operator' => 'not.is', 'value' => 'null'];
        }

        if ($reportedOnly) {
            $pendingReports = $this->supabase->adminSelect(
                'quiz_reports', 'quiz_id', ['status' => 'pending']
            );
            $reportedQuizIds = array_values(array_unique(array_filter(
                array_column($pendingReports, 'quiz_id')
            )));
            if (empty($reportedQuizIds)) {
                $result = ['data' => [], 'total' => 0];
            } else {
                $filters['id'] = [
                    'operator' => 'in',
                    'value' => '(' . implode(',', $reportedQuizIds) . ')',
                ];
            }
        }

        $result ??= $this->supabase->adminSelectPage(
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
        $reportCounts = $this->reportCounts(array_column($quizzes, 'id'));
        $quizzesByGrade = array_fill(1, 6, []);

        foreach ($quizzes as &$quiz) {
            $quiz['creator_name'] = $creatorNames[$quiz['teacher_id']] ?? 'MathVerse User';
            $quiz['question_count'] = $questionCounts[$quiz['id']] ?? 0;
            $quiz['pending_report_count'] = $reportCounts[$quiz['id']] ?? 0;
            $quizzesByGrade[(int) $quiz['grade_level']][] = $quiz;
        }
        unset($quiz);

        return view('admin.quizzes.library', compact(
            'user', 'quizzesByGrade', 'search', 'grade', 'page', 'total', 'totalPages',
            'verifiedOnly', 'reportedOnly'
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
            'visibility' => $validated['visibility'],
            'verified_at' => now()->toIso8601String(),
            'verified_by' => $user['id'],
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

        $message = $validated['visibility'] === 'shared'
            ? 'Admin quiz created and shared with teachers.'
            : 'Private admin quiz created.';

        return redirect('/admin/quizzes')->with('success', $message);
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
        $currentVersion = max(1, (int) ($quiz['version'] ?? 1));
        $snapshot = $this->preserveVersionSnapshot(
            $quiz,
            $oldQuestions,
            $currentVersion,
            $user['id']
        );
        if (!$snapshot) {
            return redirect('/admin/quizzes')->with('error', 'The current quiz version could not be preserved.');
        }

        $updated = $this->supabase->adminUpdate('quizzes', [
            'topic' => trim($validated['topic']),
            'grade_level' => (int) $validated['grade_level'],
            'visibility' => $validated['visibility'],
            'version' => $currentVersion + 1,
            'verified_at' => now()->toIso8601String(),
            'verified_by' => $user['id'],
            'updated_at' => now()->toIso8601String(),
        ], ['id' => $id, 'teacher_id' => $user['id']]);

        if (!isset($updated[0]['id'])) {
            if ($snapshot['created']) {
                $this->supabase->adminDelete('quiz_versions', ['id' => $snapshot['row']['id']]);
            }
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
                'visibility' => $quiz['visibility'] ?? 'shared',
                'version' => $currentVersion,
                'verified_at' => $quiz['verified_at'] ?? null,
                'verified_by' => $quiz['verified_by'] ?? null,
                'updated_at' => $quiz['updated_at'] ?? $quiz['created_at'],
            ], ['id' => $id, 'teacher_id' => $user['id']]);
            $this->supabase->adminDelete('quiz_questions', ['quiz_id' => $id]);
            $this->restoreTemplateQuestions($oldQuestions);
            if ($snapshot['created']) {
                $this->supabase->adminDelete('quiz_versions', ['id' => $snapshot['row']['id']]);
            }

            return redirect('/admin/quizzes')
                ->with('error', 'The new questions could not be saved, so the previous quiz was restored.');
        }

        $this->supabase->audit($user, 'quiz.edited', 'quiz', $id, [
            'creator_id' => $quiz['teacher_id'] ?? null,
            'topic_before' => $quiz['topic'] ?? null,
            'topic_after' => trim($validated['topic']),
            'version_before' => $currentVersion,
            'version_after' => $currentVersion + 1,
        ]);

        return redirect('/admin/quizzes')
            ->with('success', 'Quiz updated. Existing class assignments were not changed.');
    }

    public function review(string $id)
    {
        $user = session('supabase_user');
        $quiz = $this->supabase->adminSelect('quizzes', '*', ['id' => $id])[0] ?? null;

        if (!$quiz || ($quiz['visibility'] ?? 'shared') !== 'shared') {
            return redirect('/admin/quiz-library')->with('error', 'Quiz not found.');
        }

        if (($quiz['teacher_id'] ?? null) === $user['id']) {
            return redirect('/admin/quizzes')->with('error', 'Open your quiz from My Quizzes to edit it.');
        }

        $questionRows = $this->supabase->adminSelect(
            'quiz_questions',
            '*',
            ['quiz_id' => $id, 'order' => 'position.asc']
        );
        $questions = $this->reviewQuestions($questionRows);
        $questionsForForm = $this->questionsForForm($questionRows);
        $creatorNames = $this->creatorNames([$quiz['teacher_id']]);
        $creatorName = $creatorNames[$quiz['teacher_id']] ?? 'MathVerse User';
        $reports = $this->supabase->adminSelect(
            'quiz_reports', '*', ['quiz_id' => $id, 'order' => 'created_at.desc']
        );
        $reporterNames = $this->creatorNames(array_column($reports, 'reporter_id'));
        $questionLabels = [];
        foreach ($questions as $index => $question) {
            if (!empty($question['id'])) {
                $questionLabels[$question['id']] = 'Question ' . ($question['position'] ?? $index + 1)
                    . ': ' . \Illuminate\Support\Str::limit($question['question'] ?? '', 80);
            }
        }
        foreach ($reports as &$report) {
            $report['reporter_name'] = $reporterNames[$report['reporter_id'] ?? ''] ?? 'Former user';
            $report['question_label'] = $questionLabels[$report['question_id'] ?? ''] ?? null;
        }
        unset($report);

        return view('admin.quizzes.review', compact(
            'user', 'quiz', 'questions', 'questionsForForm', 'creatorName', 'reports'
        ));
    }

    public function updateReviewed(Request $request, string $id)
    {
        $admin = session('supabase_user');
        $quiz = $this->supabase->adminSelect('quizzes', '*', [
            'id' => $id,
            'visibility' => 'shared',
        ])[0] ?? null;

        if (!$quiz || ($quiz['teacher_id'] ?? null) === ($admin['id'] ?? null)) {
            return redirect('/admin/quiz-library')
                ->with('error', 'That teacher quiz is no longer available for review.');
        }

        $validated = $this->validateQuiz($request);
        $oldQuestions = $this->supabase->adminSelect(
            'quiz_questions', '*', ['quiz_id' => $id, 'order' => 'position.asc']
        );
        $currentVersion = max(1, (int) ($quiz['version'] ?? 1));
        $snapshot = $this->preserveVersionSnapshot(
            $quiz,
            $oldQuestions,
            $currentVersion,
            $admin['id']
        );
        if (!$snapshot) {
            return back()->withInput()->with('error', 'The current quiz version could not be preserved.');
        }

        $updated = $this->supabase->adminUpdate('quizzes', [
            'topic' => trim($validated['topic']),
            'grade_level' => (int) $validated['grade_level'],
            'visibility' => $validated['visibility'],
            'version' => $currentVersion + 1,
            'verified_at' => null,
            'verified_by' => null,
            'updated_at' => now()->toIso8601String(),
        ], ['id' => $id]);
        if (!isset($updated[0]['id'])) {
            if ($snapshot['created']) {
                $this->supabase->adminDelete('quiz_versions', ['id' => $snapshot['row']['id']]);
            }
            return back()->withInput()->with('error', 'The teacher quiz could not be updated.');
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
                'visibility' => $quiz['visibility'] ?? 'shared',
                'version' => $currentVersion,
                'verified_at' => $quiz['verified_at'] ?? null,
                'verified_by' => $quiz['verified_by'] ?? null,
                'updated_at' => $quiz['updated_at'] ?? $quiz['created_at'],
            ], ['id' => $id]);
            $this->supabase->adminDelete('quiz_questions', ['quiz_id' => $id]);
            $this->restoreTemplateQuestions($oldQuestions);
            if ($snapshot['created']) {
                $this->supabase->adminDelete('quiz_versions', ['id' => $snapshot['row']['id']]);
            }

            return back()->withInput()
                ->with('error', 'The new questions could not be saved, so the previous quiz was restored.');
        }

        $this->supabase->audit($admin, 'quiz.edited', 'quiz', $id, [
            'creator_id' => $quiz['teacher_id'] ?? null,
            'topic_before' => $quiz['topic'] ?? null,
            'topic_after' => trim($validated['topic']),
            'version_before' => $currentVersion,
            'version_after' => $currentVersion + 1,
            'admin_review_edit' => true,
        ]);

        return redirect("/admin/quiz-library/{$id}/review")
            ->with('success', 'Teacher quiz updated. Existing class assignments were not changed.');
    }

    public function toggleVerified(string $id)
    {
        $admin = session('supabase_user');
        $quiz = $this->supabase->adminSelect('quizzes', '*', [
            'id' => $id, 'visibility' => 'shared',
        ])[0] ?? null;
        if (!$quiz) {
            return back()->with('error', 'Only a shared quiz can be verified.');
        }

        $creator = $this->supabase->adminSelect('profiles', 'role', [
            'id' => $quiz['teacher_id'],
        ])[0] ?? null;
        if (($creator['role'] ?? '') === 'admin' && !empty($quiz['verified_at'])) {
            return back()->with('error', 'Admin-created quizzes are always verified.');
        }

        $verify = empty($quiz['verified_at']);
        $updated = $this->supabase->adminUpdate('quizzes', [
            'verified_at' => $verify ? now()->toIso8601String() : null,
            'verified_by' => $verify ? $admin['id'] : null,
        ], ['id' => $id]);
        if (!isset($updated[0]['id'])) {
            return back()->with('error', 'The verification status could not be changed.');
        }

        $this->supabase->audit(
            $admin,
            $verify ? 'quiz.verified' : 'quiz.verification_removed',
            'quiz',
            $id,
            ['topic' => $quiz['topic'] ?? null]
        );

        return back()->with('success', $verify ? 'Quiz marked as verified.' : 'Verification removed.');
    }

    public function resolveReport(Request $request, string $id, string $reportId)
    {
        $validated = $request->validate(['status' => 'required|in:reviewed,dismissed']);
        $report = $this->supabase->adminSelect('quiz_reports', '*', [
            'id' => $reportId, 'quiz_id' => $id,
        ])[0] ?? null;
        if (!$report || ($report['status'] ?? '') !== 'pending') {
            return back()->with('error', 'That report has already been handled or no longer exists.');
        }

        $admin = session('supabase_user');
        $updated = $this->supabase->adminUpdate('quiz_reports', [
            'status' => $validated['status'],
            'reviewed_by' => $admin['id'],
            'reviewed_at' => now()->toIso8601String(),
        ], ['id' => $reportId, 'quiz_id' => $id, 'status' => 'pending']);
        if (!isset($updated[0]['id'])) {
            return back()->with('error', 'The report could not be updated.');
        }

        $this->supabase->audit($admin, 'quiz_report.' . $validated['status'], 'quiz_report', $reportId, [
            'quiz_id' => $id,
            'reason' => $report['reason'] ?? null,
        ]);

        return back()->with('success', 'Quiz report marked as ' . $validated['status'] . '.');
    }

    public function versions(string $id)
    {
        $user = session('supabase_user');
        $quiz = $this->supabase->adminSelect('quizzes', '*', ['id' => $id])[0] ?? null;
        $isOwnQuiz = $quiz && ($quiz['teacher_id'] ?? null) === $user['id'];
        if (!$quiz || (!$isOwnQuiz && ($quiz['visibility'] ?? '') !== 'shared')) {
            return redirect('/admin/quiz-library')->with('error', 'Quiz not found.');
        }
        $versions = $this->supabase->adminSelect(
            'quiz_versions', '*', ['quiz_id' => $id, 'order' => 'version.desc']
        );
        $creatorNames = $this->creatorNames([$quiz['teacher_id']]);
        $creatorName = $creatorNames[$quiz['teacher_id']] ?? 'MathVerse User';
        return view('admin.quizzes.versions', compact(
            'user', 'quiz', 'versions', 'isOwnQuiz', 'creatorName'
        ));
    }

    public function restoreVersion(string $id, int $version)
    {
        $admin = session('supabase_user');
        $quiz = $this->supabase->adminSelect('quizzes', '*', ['id' => $id])[0] ?? null;
        $isOwnQuiz = $quiz && ($quiz['teacher_id'] ?? null) === $admin['id'];
        if (!$quiz || (!$isOwnQuiz && ($quiz['visibility'] ?? '') !== 'shared')) {
            return redirect('/admin/quiz-library')->with('error', 'Quiz not found.');
        }

        $restored = $this->supabase->adminRpc('restore_quiz_version', [
            'p_quiz_id' => $id,
            'p_version' => $version,
            'p_actor_id' => $admin['id'],
        ]);
        if (!isset($restored[0]['quiz_id'])) {
            return redirect("/admin/quizzes/{$id}/versions")
                ->with('error', 'That quiz version could not be restored.');
        }

        $this->supabase->audit($admin, 'quiz.version_restored', 'quiz', $id, [
            'creator_id' => $quiz['teacher_id'] ?? null,
            'restored_version' => $version,
            'discarded_after_version' => $version,
        ]);

        return redirect("/admin/quizzes/{$id}/versions")
            ->with('success', "Version {$version} restored. Later versions were removed.");
    }

    public function destroy(Request $request, string $id)
    {
        $destination = $this->quizDestination($request);
        $quiz = $this->supabase->adminSelect(
            'quizzes', 'id,topic,teacher_id,visibility', ['id' => $id]
        )[0] ?? null;
        if (!$quiz) {
            return redirect($destination)->with('error', 'Quiz not found.');
        }

        $admin = session('supabase_user');
        $isOwnQuiz = ($quiz['teacher_id'] ?? null) === ($admin['id'] ?? null);
        if (!$isOwnQuiz && ($quiz['visibility'] ?? 'shared') !== 'shared') {
            return redirect($destination)->with('error', 'A private quiz can only be deleted by its creator.');
        }

        if (!$this->supabase->adminDelete('quizzes', ['id' => $id])) {
            return redirect($destination)->with('error', 'The quiz could not be deleted.');
        }

        $this->supabase->audit($admin, 'quiz.deleted', 'quiz', $id, [
            'topic' => $quiz['topic'] ?? null,
            'creator_id' => $quiz['teacher_id'] ?? null,
            'visibility' => $quiz['visibility'] ?? null,
        ]);

        $message = $isOwnQuiz
            ? 'Your quiz was deleted. Existing class assignments were preserved.'
            : 'Quiz removed from the shared library. Existing class assignments were preserved.';

        return redirect($destination)->with('success', $message);
    }

    private function validateQuiz(Request $request): array
    {
        return $request->validate([
            'topic' => 'required|string|max:150',
            'grade_level' => 'required|integer|between:1,6',
            'visibility' => 'required|in:private,shared',
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

    private function preserveVersionSnapshot(
        array $quiz,
        array $questions,
        int $version,
        string $actorId
    ): ?array {
        $existing = $this->supabase->adminSelect('quiz_versions', '*', [
            'quiz_id' => $quiz['id'],
            'version' => $version,
        ])[0] ?? null;
        if ($existing) {
            return ['row' => $existing, 'created' => false];
        }

        $created = $this->supabase->adminInsert('quiz_versions', [
            'quiz_id' => $quiz['id'],
            'version' => $version,
            'topic' => $quiz['topic'],
            'grade_level' => (int) $quiz['grade_level'],
            'visibility' => $quiz['visibility'] ?? 'shared',
            'questions' => $questions,
            'created_by' => $actorId,
        ])[0] ?? null;

        return $created ? ['row' => $created, 'created' => true] : null;
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
                'id' => $question['id'] ?? null,
                'position' => $question['position'] ?? null,
                'question' => $question['question'],
                'choices' => $choices,
                'correct_index' => $correctIndex,
            ];
        }, $questions);
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
        $verified = $request->boolean('verified');
        $reported = $request->boolean('reported');
        $query = array_filter([
            'search' => $search,
            'grade' => ($grade >= 1 && $grade <= 6) ? $grade : null,
            'verified' => $verified ? 1 : null,
            'reported' => $reported ? 1 : null,
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

    private function reportCounts(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return [];
        }
        $reports = $this->supabase->adminSelect('quiz_reports', 'quiz_id', [
            'quiz_id' => ['operator' => 'in', 'value' => '(' . implode(',', $ids) . ')'],
            'status' => 'pending',
        ]);
        $counts = [];
        foreach ($reports as $report) {
            $counts[$report['quiz_id']] = ($counts[$report['quiz_id']] ?? 0) + 1;
        }
        return $counts;
    }
}
