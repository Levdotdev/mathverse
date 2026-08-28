<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SupabaseService;
use Barryvdh\DomPDF\Facade\Pdf;

class TeacherController extends Controller
{
    public function __construct(private SupabaseService $supabase) {}

    public function index()
    {
        $user    = session('supabase_user');
        $token   = session('supabase_token');

        if (!$user || !$token) {
            return redirect('/')->with('error', 'Please log in first.');
        }

        $ownedQuizzes = $this->supabase->adminSelect(
            'quizzes',
            'id',
            ['teacher_id' => $user['id']]
        );
        $quizCount = count($ownedQuizzes);

        // Classes owned by this teacher
        $allClasses = $this->supabase->adminSelect('classes', '*', ['teacher_id' => $user['id']]);
        usort($allClasses, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

        foreach ($allClasses as &$class) {
            $class['customization'] = $this->supabase->adminSelect(
                'class_customizations',
                '*',
                ['class_id' => $class['id']]
            )[0] ?? [
                'theme_color' => '#f59e0b',
                'icon' => 'chalkboard',
                'banner_pattern' => 'grid',
            ];
        }
        unset($class);

        $classes = array_values(array_filter($allClasses, fn (array $class): bool => empty($class['archived_at'])));
        $archivedClasses = array_values(array_filter($allClasses, fn (array $class): bool => !empty($class['archived_at'])));
        $activeClassIds = array_column($classes, 'id');

        $memberships = empty($activeClassIds) ? [] : $this->supabase->adminSelect(
            'class_members',
            'student_id,class_id',
            ['class_id' => ['operator' => 'in', 'value' => '(' . implode(',', $activeClassIds) . ')']]
        );
        $studentCount = count(array_unique(array_column($memberships, 'student_id')));

        $recentQuizzes = empty($activeClassIds) ? [] : $this->supabase->adminSelect(
            'quiz_sessions',
            '*',
            [
                'teacher_id' => $user['id'],
                'class_id' => ['operator' => 'in', 'value' => '(' . implode(',', $activeClassIds) . ')'],
                'order' => 'created_at.desc',
                'limit' => 5,
            ]
        );

        return view('teacher.dashboard', compact(
            'user', 'studentCount', 'quizCount',
            'recentQuizzes', 'classes', 'archivedClasses'
        ));
    }

    // ── Profile ───────────────────────────────────────────

    public function updateProfile(Request $request)
    {
        if ($avatarSizeError = $this->rejectOversizedAvatar($request, '/teacher/dashboard?section=profile')) {
            return $avatarSizeError;
        }

        $user  = session('supabase_user');
        $token = session('supabase_token');
        $userId = $user['id'];

        // ── UPDATE BASIC INFO
        $this->supabase->update('profiles', [
            'first_name'  => $request->first_name,
            'last_name'   => $request->last_name,
            'grade_level' => 0,
        ], ['id' => $userId], $token);

        // ── UPLOAD AVATAR (USE SAME USER ID)
        $avatarUrl = null;

        if ($request->hasFile('avatar')) {
            $this->supabase->deleteAvatarByUrl($user['avatar_url'] ?? null);
            $avatarUrl = $this->supabase->uploadAvatar($userId, $request->file('avatar'));

            if ($avatarUrl) {
                $this->supabase->updateProfile($userId, [
                    'avatar_url' => $avatarUrl
                ]);
            }
        }

        // ── UPDATE SESSION
        $updated = session('supabase_user');
        $updated['first_name']  = $request->first_name;
        $updated['last_name']   = $request->last_name;
        $updated['grade_level'] = 0;

        if ($avatarUrl) {
            $updated['avatar_url'] = $avatarUrl;
        }

        session(['supabase_user' => $updated]);

        return redirect('/teacher/dashboard?section=profile')->with('success', 'Profile updated successfully!');
    }

    public function stats()
    {
        $user = session('supabase_user');

        $classes = $this->supabase->adminSelect(
            'classes',
            'id,class_name,archived_at',
            ['teacher_id' => $user['id'], 'archived_at' => ['operator' => 'is', 'value' => 'null']]
        );
        $classIds = array_column($classes, 'id');
        $quizzes = empty($classIds) ? [] : $this->supabase->adminSelect(
            'quiz_sessions',
            'id,topic,created_at,class_id',
            ['class_id' => ['operator' => 'in', 'value' => '(' . implode(',', $classIds) . ')']]
        );
        $allResults = $this->firstAttempts($this->supabase->adminSelect(
            'quiz_results', 'correct_answers,total_questions,created_at,session_id,student_id', ['order' => 'created_at.asc']
        ));

        // Filter results to only this teacher's quizzes
        $quizIds    = array_column($quizzes, 'id');
        $allResults = array_filter($allResults, fn($r) => in_array($r['session_id'], $quizIds));
        $allResults = array_values($allResults);

        // Average accuracy for each non-archived class.
        $classAccuracy = [];
        foreach ($classes as $class) {
            $sessionIds = array_column(array_values(array_filter(
                $quizzes,
                fn (array $quiz): bool => $quiz['class_id'] === $class['id']
            )), 'id');
            $classResults = array_values(array_filter(
                $allResults,
                fn (array $result): bool => in_array($result['session_id'], $sessionIds, true)
            ));
            $accuracies = array_map(fn (array $result): float => ($result['total_questions'] ?? 0) > 0
                ? (($result['correct_answers'] ?? 0) / $result['total_questions']) * 100
                : 0, $classResults);
            $classAccuracy[] = [
                'class_name' => $class['class_name'],
                'accuracy' => !empty($accuracies) ? round(array_sum($accuracies) / count($accuracies), 1) : 0,
                'attempts' => count($classResults),
            ];
        }

        // Attempts per day (last 14 days)
        $attemptsPerDay = [];
        for ($i = 13; $i >= 0; $i--) {
            $date  = date('Y-m-d', strtotime("-{$i} days"));
            $label = date('M d',   strtotime("-{$i} days"));
            $count = count(array_filter($allResults, fn($r) => str_starts_with($r['created_at'], $date)));
            $attemptsPerDay[] = ['date' => $label, 'count' => $count];
        }

        // Score distribution
        $distribution = [0, 0, 0, 0];
        foreach ($allResults as $r) {
            if ($r['total_questions'] === 0) continue;
            $pct = ($r['correct_answers'] / $r['total_questions']) * 100;
            if      ($pct <= 25) $distribution[0]++;
            elseif  ($pct <= 50) $distribution[1]++;
            elseif  ($pct <= 75) $distribution[2]++;
            else                 $distribution[3]++;
        }

        $totalAttempts = count($allResults);
        $avgAccuracy   = $totalAttempts > 0
            ? round(array_sum(array_map(fn($r) =>
                $r['total_questions'] > 0 ? ($r['correct_answers'] / $r['total_questions']) * 100 : 0,
                $allResults)) / $totalAttempts, 1)
            : 0;

        return response()->json([
            'classAccuracy'  => $classAccuracy,
            'attemptsPerDay' => $attemptsPerDay,
            'distribution'   => $distribution,
            'totalAttempts'  => $totalAttempts,
            'totalQuizzes'   => count($quizzes),
            'avgAccuracy'    => $avgAccuracy,
        ]);
    }

    public function reportQuizPerformance(Request $request)
    {
        $user    = session('supabase_user');
        $format  = $request->query('format', 'pdf');

        $quizzes    = $this->supabase->adminSelect('quiz_sessions', 'id,topic,room_code,created_at,class_id', ['teacher_id' => $user['id']]);
        $quizzes    = array_values(array_filter($quizzes, fn ($quiz) => !empty($quiz['class_id'])));
        $allResults = $this->firstAttempts($this->supabase->adminSelect(
            'quiz_results', 'correct_answers,total_questions,created_at,session_id,student_id', ['order' => 'created_at.asc']
        ));

        $quizIds    = array_column($quizzes, 'id');
        $allResults = array_values(array_filter($allResults, fn($r) => in_array($r['session_id'], $quizIds)));

        $rows = [];
        foreach ($quizzes as $q) {
            $qResults = array_values(array_filter($allResults, fn($r) => $r['session_id'] === $q['id']));
            $attempts = count($qResults);
            $avgAcc   = 0;
            $passed   = 0;

            if ($attempts > 0) {
                $avgAcc = round(array_sum(array_map(fn($r) =>
                    $r['total_questions'] > 0 ? ($r['correct_answers'] / $r['total_questions']) * 100 : 0,
                    $qResults)) / $attempts, 1);
                $passed = count(array_filter($qResults, fn($r) =>
                    $r['total_questions'] > 0 && ($r['correct_answers'] / $r['total_questions']) >= 0.75));
            }

            $rows[] = [
                'topic'     => $q['topic'],
                'room_code' => $q['room_code'],
                'attempts'  => $attempts,
                'avg_acc'   => $avgAcc,
                'pass_rate' => $attempts > 0 ? round(($passed / $attempts) * 100, 1) : 0,
                'date'      => \Carbon\Carbon::parse($q['created_at'])->format('M d, Y'),
            ];
        }

        if ($format === 'csv') {
            return $this->downloadCsv($rows,
                ['Topic', 'Room Code', 'Attempts', 'Avg Accuracy %', 'Pass Rate %', 'Date Created'],
                ['topic', 'room_code', 'attempts', 'avg_acc', 'pass_rate', 'date'],
                'quiz-performance-report'
            );
        }

        $pdf = Pdf::loadView('reports.quiz-performance', [
            'rows'      => $rows,
            'teacher'   => $user,
            'generated' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('quiz-performance-report.pdf');
    }

    public function reportStudentProgress(Request $request)
    {
        $user   = session('supabase_user');
        $format = $request->query('format', 'pdf');

        // Get all students from teacher's classes
        $classes    = $this->supabase->adminSelect('classes', 'id,class_name', ['teacher_id' => $user['id']]);
        $classIds   = array_column($classes, 'id');
        $members    = $this->supabase->adminSelect('class_members', 'student_id,class_id');
        $members    = array_filter($members, fn($m) => in_array($m['class_id'], $classIds));
        $studentIds = array_unique(array_column(array_values($members), 'student_id'));

        $classSessions = empty($classIds)
            ? []
            : $this->supabase->adminSelect(
                'quiz_sessions',
                'id',
                ['class_id' => ['operator' => 'in', 'value' => '(' . implode(',', $classIds) . ')']]
            );
        $classSessionIds = array_column($classSessions, 'id');

        $allResults = $this->firstAttempts($this->supabase->adminSelect(
            'quiz_results',
            'correct_answers,total_questions,student_id,session_id,created_at',
            ['order' => 'created_at.asc']
        ));
        $allResults = array_values(array_filter(
            $allResults,
            fn ($result) => in_array($result['session_id'], $classSessionIds, true)
        ));

        $rows = [];
        foreach ($studentIds as $sid) {
            $profile  = $this->supabase->adminSelect('profiles', 'first_name,last_name,grade_level,trophies', ['id' => $sid]);
            $p        = $profile[0] ?? null;
            if (!$p) continue;

            $sResults = array_values(array_filter($allResults, fn($r) => $r['student_id'] === $sid));
            $attempts = count($sResults);
            $avgAcc   = 0;

            if ($attempts > 0) {
                $avgAcc = round(array_sum(array_map(fn($r) =>
                    $r['total_questions'] > 0 ? ($r['correct_answers'] / $r['total_questions']) * 100 : 0,
                    $sResults)) / $attempts, 1);
            }

            $rows[] = [
                'name'        => ($p['last_name'] ?? '') . ', ' . ($p['first_name'] ?? ''),
                'grade'       => 'Grade ' . ($p['grade_level'] ?? 'N/A'),
                'quizzes'     => $attempts,
                'avg_acc'     => $avgAcc,
                'trophies'    => $p['trophies'] ?? 0,
            ];
        }

        usort($rows, fn($a, $b) => $b['avg_acc'] <=> $a['avg_acc']);

        if ($format === 'csv') {
            return $this->downloadCsv($rows,
                ['Student Name', 'Grade Level', 'Quizzes Taken', 'Avg Accuracy %', 'Trophies'],
                ['name', 'grade', 'quizzes', 'avg_acc', 'trophies'],
                'student-progress-report'
            );
        }

        $pdf = Pdf::loadView('reports.student-progress', [
            'rows'      => $rows,
            'teacher'   => $user,
            'generated' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('student-progress-report.pdf');
    }

    public function reportClasses(Request $request)
    {
        $user   = session('supabase_user');
        $format = $request->query('format', 'pdf');

        $classes = $this->supabase->adminSelect('classes', '*', ['teacher_id' => $user['id']]);
        $members = $this->supabase->adminSelect('class_members', 'student_id,class_id');

        $rows = [];
        foreach ($classes as $c) {
            $classMembers = array_values(array_filter($members, fn($m) => $m['class_id'] === $c['id']));
            $studentNames = [];

            foreach ($classMembers as $m) {
                $profile = $this->supabase->adminSelect('profiles', 'first_name,last_name', ['id' => $m['student_id']]);
                if (!empty($profile[0])) {
                    $p = $profile[0];
                    $studentNames[] = ($p['last_name'] ?? '') . ', ' . ($p['first_name'] ?? '');
                }
            }

            $rows[] = [
                'class_name' => $c['class_name'],
                'join_code'  => $c['join_code'],
                'students'   => count($classMembers),
                'roster'     => implode(' | ', $studentNames),
                'created'    => \Carbon\Carbon::parse($c['created_at'])->format('M d, Y'),
            ];
        }

        if ($format === 'csv') {
            return $this->downloadCsv($rows,
                ['Class Name', 'Join Code', 'Total Students', 'Roster', 'Date Created'],
                ['class_name', 'join_code', 'students', 'roster', 'created'],
                'classes-report'
            );
        }

        $pdf = Pdf::loadView('reports.classes', [
            'rows'      => $rows,
            'teacher'   => $user,
            'generated' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('classes-report.pdf');
    }

    private function firstAttempts(array $results): array
    {
        $first = [];
        foreach ($results as $result) {
            $sessionId = $result['session_id'] ?? null;
            $studentId = $result['student_id'] ?? null;
            if (!$sessionId || !$studentId) {
                continue;
            }
            $first[$sessionId . ':' . $studentId] ??= $result;
        }

        return array_values($first);
    }

    private function downloadCsv(array $rows, array $headers, array $keys, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $headers, $keys) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn($k) => $row[$k] ?? '', $keys));
            }
            fclose($out);
        }, "{$filename}.csv", ['Content-Type' => 'text/csv']);
    }

    public function reportSingleQuiz(Request $request, string $id)
    {
        $format = $request->query('format', 'pdf');
        $user   = session('supabase_user');

        // Quiz details
        $quiz = $this->supabase->adminSelect('quiz_sessions', '*', ['id' => $id]);
        $quiz = $quiz[0] ?? null;

        if (!$quiz || ($quiz['teacher_id'] ?? null) !== $user['id'] || empty($quiz['class_id'])) {
            return back()->with('error', 'Quiz not found.');
        }

        // Questions
        $questions = $this->supabase->adminSelect('questions', '*', ['session_id' => $id]);

        // Results with student profiles
        $results = $this->firstAttempts($this->supabase->adminSelect(
            'quiz_results',
            'correct_answers,total_questions,created_at,student_id,session_id',
            ['session_id' => $id, 'order' => 'created_at.asc']
        ));

        // Get student names
        $rows = [];
        foreach ($results as $r) {
            $profile = $this->supabase->adminSelect('profiles', 'first_name,last_name,grade_level', ['id' => $r['student_id']]);
            $p       = $profile[0] ?? null;
            $accuracy = $r['total_questions'] > 0
                ? round(($r['correct_answers'] / $r['total_questions']) * 100, 1)
                : 0;

            $rows[] = [
                'name'     => $p ? ($p['last_name'] . ', ' . $p['first_name']) : 'Unknown',
                'grade'    => $p ? 'Grade ' . ($p['grade_level'] ?? 'N/A') : 'N/A',
                'score'    => $r['correct_answers'] . ' / ' . $r['total_questions'],
                'accuracy' => $accuracy,
                'status'   => $accuracy >= 75 ? 'Passed' : 'Failed',
                'date'     => \Carbon\Carbon::parse($r['created_at'])->format('M d, Y h:i A'),
            ];
        }

        usort($rows, fn($a, $b) => $b['accuracy'] <=> $a['accuracy']);

        $totalAttempts = count($rows);
        $avgAccuracy   = $totalAttempts > 0
            ? round(array_sum(array_column($rows, 'accuracy')) / $totalAttempts, 1)
            : 0;
        $passed  = count(array_filter($rows, fn($r) => $r['accuracy'] >= 75));
        $failed  = count(array_filter($rows, fn($r) => $r['accuracy'] < 75));

        $summary = [
            'topic'         => $quiz['topic'],
            'room_code'     => $quiz['room_code'],
            'total_questions' => count($questions),
            'total_attempts'  => $totalAttempts,
            'avg_accuracy'    => $avgAccuracy,
            'passed'          => $passed,
            'failed'          => $failed,
            'pass_rate'       => $totalAttempts > 0 ? round(($passed / $totalAttempts) * 100, 1) : 0,
            'created'         => \Carbon\Carbon::parse($quiz['created_at'])->format('M d, Y'),
            'teacher'         => ($user['last_name'] ?? '') . ', ' . ($user['first_name'] ?? ''),
        ];

        if ($format === 'csv') {
            // CSV has two sections — summary then results
            return response()->streamDownload(function () use ($summary, $rows, $questions) {
                $out = fopen('php://output', 'w');

                // Summary section
                fputcsv($out, ['Quiz Performance Report']);
                fputcsv($out, ['Topic',           $summary['topic']]);
                fputcsv($out, ['Room Code',        $summary['room_code']]);
                fputcsv($out, ['Total Questions',  $summary['total_questions']]);
                fputcsv($out, ['Total Attempts',   $summary['total_attempts']]);
                fputcsv($out, ['Avg Accuracy',     $summary['avg_accuracy'] . '%']);
                fputcsv($out, ['Pass Rate',        $summary['pass_rate'] . '%']);
                fputcsv($out, ['Date Created',     $summary['created']]);
                fputcsv($out, []);

                // Questions section
                fputcsv($out, ['Questions']);
                fputcsv($out, ['#', 'Question', 'Correct Answer']);
                foreach ($questions as $i => $q) {
                    fputcsv($out, [$i + 1, $q['question'], $q['correct_answer']]);
                }
                fputcsv($out, []);

                // Results section
                fputcsv($out, ['Student Results']);
                fputcsv($out, ['Rank', 'Student Name', 'Grade', 'Score', 'Accuracy', 'Status', 'Date Taken']);
                foreach ($rows as $i => $r) {
                    fputcsv($out, [$i + 1, $r['name'], $r['grade'], $r['score'], $r['accuracy'] . '%', $r['status'], $r['date']]);
                }

                fclose($out);
            }, "quiz-{$quiz['room_code']}-report.csv", ['Content-Type' => 'text/csv']);
        }

        $pdf = Pdf::loadView('reports.single-quiz', compact('summary', 'rows', 'questions'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("quiz-{$quiz['room_code']}-report.pdf");
    }

    public function reportSingleClassroom(Request $request, string $id)
    {
        $format = $request->query('format', 'pdf');
        $user   = session('supabase_user');

        // Class details
        $class = $this->supabase->adminSelect('classes', '*', ['id' => $id]);
        $class = $class[0] ?? null;

        if (!$class || ($class['teacher_id'] ?? null) !== $user['id']) {
            return back()->with('error', 'Class not found.');
        }

        // Members
        $members = $this->supabase->adminSelect('class_members', 'student_id,joined_at', ['class_id' => $id]);

        // Only results produced by quiz sessions assigned to this class.
        $classSessions = $this->supabase->adminSelect('quiz_sessions', 'id', ['class_id' => $id]);
        $classSessionIds = array_column($classSessions, 'id');
        $allResults = $this->firstAttempts($this->supabase->adminSelect(
            'quiz_results',
            'correct_answers,total_questions,student_id,session_id,created_at',
            ['order' => 'created_at.asc']
        ));
        $allResults = array_values(array_filter(
            $allResults,
            fn ($result) => in_array($result['session_id'], $classSessionIds, true)
        ));

        $rows = [];
        foreach ($members as $m) {
            $profile = $this->supabase->adminSelect(
                'profiles', 'first_name,last_name,grade_level,trophies,level',
                ['id' => $m['student_id']]
            );
            $p = $profile[0] ?? null;
            if (!$p) continue;

            $sResults = array_values(array_filter($allResults, fn($r) => $r['student_id'] === $m['student_id']));
            $attempts = count($sResults);
            $avgAcc   = 0;

            if ($attempts > 0) {
                $avgAcc = round(array_sum(array_map(fn($r) =>
                    $r['total_questions'] > 0
                        ? ($r['correct_answers'] / $r['total_questions']) * 100
                        : 0,
                    $sResults)) / $attempts, 1);
            }

            $rows[] = [
                'name'     => ($p['last_name'] ?? '') . ', ' . ($p['first_name'] ?? ''),
                'grade'    => 'Grade ' . ($p['grade_level'] ?? 'N/A'),
                'level'    => $p['level'] ?? 1,
                'trophies' => $p['trophies'] ?? 0,
                'quizzes'  => $attempts,
                'avg_acc'  => $avgAcc,
                'joined'   => isset($m['joined_at'])
                    ? \Carbon\Carbon::parse($m['joined_at'])->format('M d, Y')
                    : 'N/A',
            ];
        }

        usort($rows, fn($a, $b) => $b['avg_acc'] <=> $a['avg_acc']);

        $summary = [
            'class_name'     => $class['class_name'],
            'join_code'      => $class['join_code'],
            'total_students' => count($rows),
            'avg_accuracy'   => count($rows) > 0
                ? round(array_sum(array_column($rows, 'avg_acc')) / count($rows), 1)
                : 0,
            'created'        => \Carbon\Carbon::parse($class['created_at'])->format('M d, Y'),
            'teacher'        => ($user['last_name'] ?? '') . ', ' . ($user['first_name'] ?? ''),
        ];

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($summary, $rows) {
                $out = fopen('php://output', 'w');

                fputcsv($out, ['Classroom Report']);
                fputcsv($out, ['Class Name',      $summary['class_name']]);
                fputcsv($out, ['Join Code',        $summary['join_code']]);
                fputcsv($out, ['Total Students',   $summary['total_students']]);
                fputcsv($out, ['Avg Accuracy',     $summary['avg_accuracy'] . '%']);
                fputcsv($out, ['Teacher',          $summary['teacher']]);
                fputcsv($out, ['Date Created',     $summary['created']]);
                fputcsv($out, []);

                fputcsv($out, ['Student Roster']);
                fputcsv($out, ['Rank', 'Student Name', 'Grade', 'Level', 'Trophies', 'Quizzes Taken', 'Avg Accuracy', 'Date Joined']);
                foreach ($rows as $i => $r) {
                    fputcsv($out, [
                        $i + 1,
                        $r['name'],
                        $r['grade'],
                        $r['level'],
                        $r['trophies'],
                        $r['quizzes'],
                        $r['avg_acc'] . '%',
                        $r['joined'],
                    ]);
                }

                fclose($out);
            }, "classroom-{$class['join_code']}-report.csv", ['Content-Type' => 'text/csv']);
        }

        $pdf = Pdf::loadView('reports.single-classroom', compact('summary', 'rows'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("classroom-{$class['join_code']}-report.pdf");
    }
}
