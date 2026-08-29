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
            'id,first_name,last_name,trophies,level,grade_level,leaderboard_alias,show_on_leaderboard',
            ['role' => 'student', 'grade_level' => $gradeLevel, 'suspended_at' => ['operator' => 'is', 'value' => 'null']]
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
        foreach ($leaderboard as &$student) {
            $firstName = trim((string) ($student['first_name'] ?? '')) ?: 'Student';
            $lastInitial = mb_substr(trim((string) ($student['last_name'] ?? '')), 0, 1);
            $student['display_name'] = trim((string) ($student['leaderboard_alias'] ?? ''))
                ?: ($lastInitial !== '' ? "{$firstName} {$lastInitial}." : $firstName);
            if (!($student['show_on_leaderboard'] ?? true) && $student['id'] !== $user['id']) {
                $student['display_name'] = 'Anonymous Student';
            }
        }
        unset($student);

        $memberships = $this->supabase->select(
            'class_members',
            'class_id,joined_at',
            ['student_id' => $user['id']],
            $token
        );
        $membershipMap = [];
        foreach ($memberships as $membership) {
            $membershipMap[$membership['class_id']] = $membership['joined_at'] ?? null;
        }
        $membershipClassIds = array_keys($membershipMap);
        $classRows = empty($membershipClassIds) ? [] : $this->supabase->adminSelect(
            'classes', '*', [
                'id' => ['operator' => 'in', 'value' => '(' . implode(',', $membershipClassIds) . ')'],
            ]
        );
        $allClasses = array_column($classRows, null, 'id');

        $classIds = array_keys($allClasses);
        $endedSessions = empty($classIds) ? [] : $this->supabase->adminSelect(
            'quiz_sessions',
            'id,class_id,topic,room_code,status,retake_mode,created_at,ended_at',
            [
                'class_id' => ['operator' => 'in', 'value' => '(' . implode(',', $classIds) . ')'],
                'status' => ['operator' => 'in', 'value' => '(completed,active)'],
                'order' => 'created_at.desc',
            ]
        );
        $endedSessionIds = array_column($endedSessions, 'id');
        $eligibilityRows = empty($endedSessionIds) ? [] : $this->supabase->adminSelect(
            'quiz_session_students', 'session_id,eligibility_status,allowed_attempts,excuse_reason,retake_due_at', [
                'student_id' => $user['id'],
                'session_id' => ['operator' => 'in', 'value' => '(' . implode(',', $endedSessionIds) . ')'],
            ]
        );
        $eligibilityMap = array_column($eligibilityRows, null, 'session_id');

        $attemptRows = empty($endedSessionIds) ? [] : $this->supabase->adminSelect(
            'quiz_results', 'session_id', [
                'student_id' => $user['id'],
                'session_id' => ['operator' => 'in', 'value' => '(' . implode(',', $endedSessionIds) . ')'],
            ]
        );
        $attemptCounts = [];
        foreach ($attemptRows as $attempt) {
            $attemptCounts[$attempt['session_id']] = ($attemptCounts[$attempt['session_id']] ?? 0) + 1;
        }
        $endedSessions = array_values(array_filter(
            $endedSessions,
            function (array $session) use ($eligibilityMap, $attemptCounts): bool {
                $eligibility = $eligibilityMap[$session['id']] ?? null;
                if (!$eligibility) {
                    return false;
                }
                if (($session['status'] ?? '') === 'completed') {
                    return true;
                }

                return (bool) ($session['retake_mode'] ?? false)
                    && (
                        ($attemptCounts[$session['id']] ?? 0) >= (int) ($eligibility['allowed_attempts'] ?? 0)
                        || (
                            !empty($eligibility['retake_due_at'])
                            && now()->gte(\Carbon\Carbon::parse($eligibility['retake_due_at']))
                        )
                    );
            }
        ));
        foreach ($endedSessions as &$session) {
            $session['eligibility'] = $eligibilityMap[$session['id']];
        }
        unset($session);

        $results = $this->supabase->adminSelect(
            'quiz_results',
            'id,session_id,correct_answers,total_questions,created_at',
            ['student_id' => $user['id'], 'is_counted' => true, 'order' => 'created_at.asc']
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
            if (($session['eligibility']['eligibility_status'] ?? '') === 'excused') {
                continue;
            }
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
            'excused' => count(array_filter(
                $endedSessions,
                fn (array $session): bool => ($session['eligibility']['eligibility_status'] ?? '') === 'excused'
            )),
            'passed' => count(array_filter($accuracies, fn (float $accuracy): bool => $accuracy >= 75)),
            'failed' => count(array_filter($accuracies, fn (float $accuracy): bool => $accuracy < 75)),
            'average' => !empty($accuracies) ? round(array_sum($accuracies) / count($accuracies), 1) : 0,
            'best' => !empty($accuracies) ? max($accuracies) : 0,
        ];

        $classes = array_values(array_filter($allClasses, fn (array $class): bool => empty($class['archived_at'])));
        $activeClassIds = array_column($classes, 'id');
        $customizationRows = empty($activeClassIds) ? [] : $this->supabase->adminSelect(
            'class_customizations', '*', [
                'class_id' => ['operator' => 'in', 'value' => '(' . implode(',', $activeClassIds) . ')'],
            ]
        );
        $customizationMap = array_column($customizationRows, null, 'class_id');
        foreach ($classes as &$class) {
            $class['customization'] = $customizationMap[$class['id']]
                ?? ['theme_color' => '#22c55e', 'icon' => 'chalkboard', 'banner_pattern' => 'grid'];
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
            'leaderboard_alias' => 'nullable|string|min:2|max:30',
            'show_on_leaderboard' => 'nullable|boolean',
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
            'leaderboard_alias' => trim((string) ($validated['leaderboard_alias'] ?? '')) ?: null,
            'show_on_leaderboard' => $request->boolean('show_on_leaderboard'),
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
        $updated['leaderboard_alias'] = trim((string) ($validated['leaderboard_alias'] ?? '')) ?: null;
        $updated['show_on_leaderboard'] = $request->boolean('show_on_leaderboard');
        if ($avatarUrl) {
            $updated['avatar_url'] = $avatarUrl;
        }
        session(['supabase_user' => $updated]);

        $this->supabase->audit($updated, 'profile.updated', 'profile', $userId, [
            'grade_before' => $user['grade_level'] ?? null,
            'grade_after' => $newGrade,
            'avatar_changed' => $avatarUrl !== null,
        ]);

        return redirect('/student/dashboard?section=profile')->with('success', 'Profile updated successfully!');
    }
}
