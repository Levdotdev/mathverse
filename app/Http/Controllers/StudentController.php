<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(private SupabaseService $supabase) {}

    public function index()
    {
        $user = session('supabase_user');
        $token = session('supabase_token');
        $profile = $this->supabase->adminSelect('profiles', '*', ['id' => $user['id']])[0] ?? $user;
        $gradeLevel = (int) ($profile['grade_level'] ?? 0);

        // Global ranking is intentionally scoped to the student's current grade.
        $leaderboard = $this->supabase->adminSelect(
            'profiles',
            'id,first_name,last_name,trophies,level,grade_level',
            ['role' => 'student', 'grade_level' => $gradeLevel]
        );
        usort($leaderboard, fn (array $a, array $b): int =>
            (($b['trophies'] ?? 0) <=> ($a['trophies'] ?? 0))
            ?: (($b['level'] ?? 1) <=> ($a['level'] ?? 1))
            ?: strcmp(($a['last_name'] ?? '') . ($a['first_name'] ?? ''), ($b['last_name'] ?? '') . ($b['first_name'] ?? ''))
        );

        $rank = 'N/A';
        foreach ($leaderboard as $index => $student) {
            if ($student['id'] === $user['id']) {
                $rank = $index + 1;
                break;
            }
        }

        $memberships = $this->supabase->select(
            'class_members',
            'class_id,joined_at',
            ['student_id' => $user['id']],
            $token
        );
        $membershipMap = [];
        $allClasses = [];
        foreach ($memberships as $membership) {
            $class = $this->supabase->adminSelect('classes', '*', ['id' => $membership['class_id']])[0] ?? null;
            if (!$class) {
                continue;
            }
            $membershipMap[$class['id']] = $membership['joined_at'] ?? null;
            $allClasses[$class['id']] = $class;
        }

        $classIds = array_keys($allClasses);
        $endedSessions = empty($classIds) ? [] : $this->supabase->adminSelect(
            'quiz_sessions',
            'id,class_id,topic,room_code,status,created_at',
            [
                'class_id' => ['operator' => 'in', 'value' => '(' . implode(',', $classIds) . ')'],
                'status' => 'completed',
                'order' => 'created_at.desc',
            ]
        );
        $endedSessions = array_values(array_filter($endedSessions, function (array $session) use ($membershipMap): bool {
            $joinedAt = $membershipMap[$session['class_id']] ?? null;
            return !$joinedAt || strtotime($session['created_at']) >= strtotime($joinedAt);
        }));

        $results = $this->supabase->adminSelect(
            'quiz_results',
            'id,session_id,correct_answers,total_questions,created_at',
            ['student_id' => $user['id'], 'order' => 'created_at.asc']
        );
        $firstResultMap = [];
        foreach ($results as $result) {
            $firstResultMap[$result['session_id']] ??= $result;
        }

        $sessionMap = array_column($endedSessions, null, 'id');
        $quizHistory = [];
        foreach ($firstResultMap as $sessionId => $result) {
            $session = $sessionMap[$sessionId] ?? null;
            if (!$session) {
                continue;
            }
            $result['quiz_sessions'] = $session;
            $quizHistory[] = $result;
        }
        usort($quizHistory, fn (array $a, array $b): int => strtotime($b['created_at']) <=> strtotime($a['created_at']));

        $missedQuizzes = [];
        foreach ($endedSessions as $session) {
            if (isset($firstResultMap[$session['id']])) {
                continue;
            }
            $session['class_name'] = $allClasses[$session['class_id']]['class_name'] ?? 'Class';
            $missedQuizzes[] = $session;
        }

        $accuracies = [];
        foreach ($quizHistory as &$history) {
            $total = (int) ($history['total_questions'] ?? 0);
            $history['accuracy'] = $total > 0
                ? round(((int) $history['correct_answers'] / $total) * 100, 1)
                : 0;
            $accuracies[] = $history['accuracy'];
        }
        unset($history);

        $studentAnalytics = [
            'ended' => count($endedSessions),
            'taken' => count($quizHistory),
            'missed' => count($missedQuizzes),
            'passed' => count(array_filter($accuracies, fn (float $accuracy): bool => $accuracy >= 75)),
            'failed' => count(array_filter($accuracies, fn (float $accuracy): bool => $accuracy < 75)),
            'average' => !empty($accuracies) ? round(array_sum($accuracies) / count($accuracies), 1) : 0,
            'best' => !empty($accuracies) ? max($accuracies) : 0,
        ];

        $classes = array_values(array_filter($allClasses, fn (array $class): bool => empty($class['archived_at'])));
        foreach ($classes as &$class) {
            $class['customization'] = $this->supabase->adminSelect(
                'class_customizations', '*', ['class_id' => $class['id']]
            )[0] ?? ['theme_color' => '#22c55e', 'icon' => 'chalkboard', 'banner_pattern' => 'grid'];
        }
        unset($class);

        return view('student.dashboard', compact(
            'user', 'profile', 'rank', 'leaderboard', 'quizHistory', 'missedQuizzes',
            'studentAnalytics', 'classes', 'gradeLevel'
        ));
    }

    public function updateProfile(Request $request)
    {
        if ($avatarSizeError = $this->rejectOversizedAvatar($request, '/student/dashboard?section=profile')) {
            return $avatarSizeError;
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'grade_level' => 'required|integer|between:1,6',
        ]);

        $user = session('supabase_user');
        $token = session('supabase_token');
        $userId = $user['id'];
        $newGrade = (int) $validated['grade_level'];

        if ($newGrade !== (int) ($user['grade_level'] ?? 0)) {
            $memberships = $this->supabase->adminSelect('class_members', 'class_id', ['student_id' => $userId]);
            foreach ($memberships as $membership) {
                $class = $this->supabase->adminSelect(
                    'classes', 'class_name,grade_level,archived_at', ['id' => $membership['class_id']]
                )[0] ?? null;
                if ($class && empty($class['archived_at']) && (int) $class['grade_level'] !== $newGrade) {
                    return redirect('/student/dashboard?section=profile')->with(
                        'error',
                        "Ask your teacher to remove or archive {$class['class_name']} before changing to Grade {$newGrade}."
                    );
                }
            }
        }

        $profileUpdated = $this->supabase->update('profiles', [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'grade_level' => $newGrade,
        ], ['id' => $userId], $token);

        if (!isset($profileUpdated[0]['id'])) {
            return redirect('/student/dashboard?section=profile')
                ->with('error', 'The profile could not be updated. Your active class grades must remain matched.');
        }

        $avatarUrl = null;
        if ($request->hasFile('avatar')) {
            $this->supabase->deleteAvatarByUrl($user['avatar_url'] ?? null);
            $avatarUrl = $this->supabase->uploadAvatar($userId, $request->file('avatar'));
            if ($avatarUrl) {
                $this->supabase->updateProfile($userId, ['avatar_url' => $avatarUrl]);
            }
        }

        $updated = session('supabase_user');
        $updated['first_name'] = $validated['first_name'];
        $updated['last_name'] = $validated['last_name'];
        $updated['grade_level'] = $newGrade;
        if ($avatarUrl) {
            $updated['avatar_url'] = $avatarUrl;
        }
        session(['supabase_user' => $updated]);

        return redirect('/student/dashboard?section=profile')->with('success', 'Profile updated successfully!');
    }
}
