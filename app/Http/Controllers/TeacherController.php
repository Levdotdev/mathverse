<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\SupabaseService;

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

        // Dashboard metrics
        $allStudents = $this->supabase->adminSelect('profiles', 'id', ['role' => 'student']);
        $studentCount = count($allStudents);

        $allQuizzes = $this->supabase->adminSelect('quiz_sessions', '*', ['teacher_id' => $user['id']]);
        usort($allQuizzes, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
        $quizCount    = count($allQuizzes);
        $recentQuizzes = array_slice($allQuizzes, 0, 5);

        // Full student list
        $students = $this->supabase->adminSelect(
            'profiles',
            'id,email,username,last_name,grade_level, first_name',
            ['role' => 'student']
        );

        // Classes owned by this teacher
        $classes = $this->supabase->adminSelect('classes', '*', ['teacher_id' => $user['id']]);
        usort($classes, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

        return view('teacher.dashboard', compact(
            'user', 'studentCount', 'quizCount',
            'recentQuizzes', 'allQuizzes', 'students', 'classes'
        ));
    }

    // ── Quiz CRUD ─────────────────────────────────────────

    public function storeQuiz(Request $request)
    {
        $user  = session('supabase_user');
        $token = session('supabase_token');

        $result = $this->supabase->insert('quiz_sessions', [
            'teacher_id'  => $user['id'],
            'topic'       => $request->topic,
            'room_code'   => $request->room_code,
            'class_id'    => $request->class_id ?: null,
            'max_members' => (int) $request->max_members,
            'is_active'   => true,
            'status'      => 'waiting',
        ], $token);

        $sessionId = $result[0]['id'] ?? null;
        if ($sessionId && $request->has('questions')) {
            $this->saveQuestions($sessionId, $request->input('questions'));
        }

        return redirect('/teacher/dashboard?section=quiz-creator')
            ->with('success', 'Quiz created successfully!');
    }

    public function updateQuiz(Request $request, string $id)
    {
        $token = session('supabase_token');

        $this->supabase->update('quiz_sessions', [
            'topic'       => $request->topic,
            'room_code'   => $request->room_code,
            'class_id'    => $request->class_id ?: null,
            'max_members' => (int) $request->max_members,
        ], ['id' => $id], $token);

        // Delete old questions then re-insert
        $this->supabase->delete('questions', ['session_id' => $id], $token);

        if ($request->has('questions')) {
            $this->saveQuestions($id, $request->input('questions'));
        }

        return redirect('/teacher/dashboard?section=quiz-creator')
            ->with('success', 'Quiz updated successfully!');
    }

    public function deleteQuiz(string $id)
    {
        $token = session('supabase_token');
        $this->supabase->delete('quiz_sessions', ['id' => $id], $token);
        return redirect('/teacher/dashboard?section=quiz-creator')
            ->with('success', 'Quiz deleted.');
    }

    public function quizResults(string $id)
    {
        // Returns JSON for the modal fetch call
        $results = $this->supabase->adminSelect(
            'quiz_results',
            'correct_answers,total_questions,created_at,profiles(first_name,last_name,email)',
            ['session_id' => $id]
        );

        usort($results, fn($a, $b) => ($b['correct_answers'] ?? 0) - ($a['correct_answers'] ?? 0));
        return response()->json($results);
    }

    // ── Class CRUD ────────────────────────────────────────

    public function createClass(Request $request)
    {
        $user  = session('supabase_user');
        $token = session('supabase_token');

        $joinCode = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));

        $this->supabase->insert('classes', [
            'teacher_id' => $user['id'],
            'class_name' => $request->class_name,
            'join_code'  => $joinCode,
        ], $token);

        return redirect('/teacher/dashboard?section=classes')
            ->with('success', "Class created! Join code: {$joinCode}");
    }

    public function deleteClass(string $id)
    {
        $token = session('supabase_token');
        $this->supabase->delete('classes', ['id' => $id], $token);
        return redirect('/teacher/dashboard?section=classes')
            ->with('success', 'Class deleted.');
    }

    public function classRoster(string $id)
    {
        $members = $this->supabase->adminSelect(
            'class_members',
            'student_id,profiles(id,username,last_name,email,first_name)',
            ['class_id' => $id]
        );
        return response()->json($members);
    }

    public function removeStudent(string $classId, string $studentId)
    {
        Http::withHeaders([
            'apikey'        => config('services.supabase.anon_key'),
            'Authorization' => 'Bearer ' . config('services.supabase.service_key'),
        ])->withQueryParameters([
            'student_id' => "eq.{$studentId}",
            'class_id'   => "eq.{$classId}",
        ])->delete(config('services.supabase.url') . '/rest/v1/class_members');

        return response()->json(['success' => true]);
    }

    // ── Lobby ─────────────────────────────────────────────

    public function lobbyParticipants(string $sessionId)
    {
        $participants = $this->supabase->adminSelect(
            'quiz_participants',
            'student_id,profiles(first_name,last_name,level)',
            ['session_id' => $sessionId]
        );
        return response()->json($participants);
    }

    public function startQuiz(string $id)
    {
        $token = session('supabase_token');
        $this->supabase->update('quiz_sessions', ['status' => 'active'], ['id' => $id], $token);
        return response()->json(['success' => true]);
    }

    public function endQuiz(string $id)
    {
        $token = session('supabase_token');
        $this->supabase->update('quiz_sessions', ['status' => 'completed'], ['id' => $id], $token);
        return response()->json(['success' => true]);
    }

    // ── Profile ───────────────────────────────────────────

    public function updateProfile(Request $request)
    {
        $user  = session('supabase_user');
        $token = session('supabase_token');

        $this->supabase->update('profiles', [
            'username'    => $request->first_name,
            'last_name'   => $request->last_name,
            'grade_level' => (int) $request->grade_level,
        ], ['id' => $user['id']], $token);

        $updated = session('supabase_user');
        $updated['username']  = $request->first_name;
        $updated['last_name'] = $request->last_name;
        session(['supabase_user' => $updated]);

        if ($request->filled('new_password') || $request->filled('new_password_confirmation')) {
            if (!$request->filled('current_password')) {
                return back()->with('error', 'Current password is required.');
            }
            if ($request->new_password !== $request->new_password_confirmation) {
                return back()->with('error', 'New passwords do not match.');
            }
            $check = $this->supabase->signIn($user['email'], $request->current_password);
            if (isset($check['error'])) {
                return back()->with('error', 'Current password is incorrect.');
            }
            Http::withHeaders([
                'apikey'        => config('services.supabase.anon_key'),
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json',
            ])->put(config('services.supabase.url') . '/auth/v1/user', [
                'password' => $request->new_password,
            ]);
        }

        return redirect('/teacher/dashboard?section=profile')
            ->with('success', 'Profile updated successfully!');
    }

    // ── Private helpers ───────────────────────────────────

    private function saveQuestions(string $sessionId, array $questions): void
    {
        $token = session('supabase_token');
        $rows  = [];

        foreach ($questions as $q) {
            if (empty(trim($q['question'] ?? ''))) continue;
            $opts = $q['options'] ?? ['', '', '', ''];
            $correctIndex = (int)($q['correct'] ?? 0);
            $rows[] = [
                'session_id'     => $sessionId,
                'grade'          => 1,
                'type'           => 'multiple_choice',
                'question'       => $q['question'],
                'choice1'        => $opts[0] ?? '',
                'choice2'        => $opts[1] ?? '',
                'choice3'        => $opts[2] ?? '',
                'choice4'        => $opts[3] ?? '',
                'choice5'        => '',
                'choice6'        => '',
                'correct_answer' => $opts[$correctIndex] ?? '',
            ];
        }

        if (!empty($rows)) {
            $this->supabase->insert('questions', $rows, $token);
        }
    }

    public function stats()
    {
        $user  = session('supabase_user');
        $token = session('supabase_token');

        // All quizzes by this teacher
        $quizzes = $this->supabase->adminSelect(
            'quiz_sessions', 'id,topic,created_at,room_code',
            ['teacher_id' => $user['id']]
        );

        // All results for this teacher's quizzes
        $allResults = [];
        foreach ($quizzes as $q) {
            $results = $this->supabase->adminSelect(
                'quiz_results',
                'correct_answers,total_questions,created_at,session_id',
                ['session_id' => $q['id']]
            );
            foreach ($results as $r) {
                $r['topic'] = $q['topic'];
                $allResults[] = $r;
            }
        }

        // Per-quiz average accuracy
        $quizAccuracy = [];
        foreach ($quizzes as $q) {
            $qResults = array_filter($allResults, fn($r) => $r['session_id'] === $q['id']);
            if (count($qResults) === 0) continue;
            $avg = array_sum(array_map(fn($r) =>
                $r['total_questions'] > 0
                    ? ($r['correct_answers'] / $r['total_questions']) * 100
                    : 0,
                $qResults
            )) / count($qResults);
            $quizAccuracy[] = [
                'topic'    => $q['topic'],
                'accuracy' => round($avg, 1),
                'attempts' => count($qResults),
            ];
        }

        // Attempts per day (last 14 days)
        $attemptsPerDay = [];
        for ($i = 13; $i >= 0; $i--) {
            $date  = date('Y-m-d', strtotime("-{$i} days"));
            $label = date('M d', strtotime("-{$i} days"));
            $count = count(array_filter($allResults, fn($r) =>
                str_starts_with($r['created_at'], $date)
            ));
            $attemptsPerDay[] = ['date' => $label, 'count' => $count];
        }

        // Score distribution (0-25%, 26-50%, 51-75%, 76-100%)
        $distribution = [0, 0, 0, 0];
        foreach ($allResults as $r) {
            if ($r['total_questions'] === 0) continue;
            $pct = ($r['correct_answers'] / $r['total_questions']) * 100;
            if ($pct <= 25)       $distribution[0]++;
            elseif ($pct <= 50)   $distribution[1]++;
            elseif ($pct <= 75)   $distribution[2]++;
            else                  $distribution[3]++;
        }

        return response()->json([
            'quizAccuracy'   => $quizAccuracy,
            'attemptsPerDay' => $attemptsPerDay,
            'distribution'   => $distribution,
            'totalAttempts'  => count($allResults),
            'totalQuizzes'   => count($quizzes),
            'avgAccuracy'    => count($allResults) > 0
                ? round(array_sum(array_map(fn($r) =>
                    $r['total_questions'] > 0
                        ? ($r['correct_answers'] / $r['total_questions']) * 100
                        : 0,
                    $allResults)) / count($allResults), 1)
                : 0,
        ]);
    }
}