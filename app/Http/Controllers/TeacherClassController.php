<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\Request;

class TeacherClassController extends Controller
{
    private const THEME_COLORS = [
        '#f59e0b', '#06b6d4', '#8b5cf6', '#22c55e', '#ec4899', '#3b82f6',
    ];

    private const ICONS = ['chalkboard', 'calculator', 'rocket', 'atom', 'shapes', 'gamepad'];

    private const PATTERNS = ['grid', 'stars', 'circuit', 'waves', 'plain'];

    public function __construct(private SupabaseService $supabase) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_name' => 'required|string|max:100',
            'grade_level' => 'required|integer|between:1,6',
        ]);

        $user = session('supabase_user');
        $token = session('supabase_token');
        $joinCode = $this->generateJoinCode();

        $created = $this->supabase->insert('classes', [
            'teacher_id' => $user['id'],
            'class_name' => trim($validated['class_name']),
            'join_code' => $joinCode,
            'grade_level' => (int) $validated['grade_level'],
        ], $token);

        $classId = $created[0]['id'] ?? null;
        if (!$classId) {
            return redirect('/teacher/dashboard?section=classes')
                ->with('error', 'The class could not be created. Run the new Supabase migration first.');
        }

        $this->supabase->insert('class_customizations', [
            'class_id' => $classId,
            'theme_color' => '#f59e0b',
            'icon' => 'chalkboard',
            'banner_pattern' => 'grid',
        ], $token);

        return redirect("/teacher/classes/{$classId}")
            ->with('success', "Class created. Join code: {$joinCode}");
    }

    public function show(string $id)
    {
        $user = session('supabase_user');
        $class = $this->ownedClass($id, $user['id']);
        if (!$class) {
            return redirect('/teacher/dashboard?section=classes')->with('error', 'Class not found.');
        }

        $this->expirePastDueSessions($id, $user);

        $customization = $this->customization($id);
        $members = $this->supabase->adminSelect(
            'class_members',
            'student_id,joined_at,profiles(id,username,last_name,email,first_name,grade_level,level)',
            ['class_id' => $id, 'order' => 'joined_at.asc']
        );
        $accommodations = $this->supabase->adminSelect(
            'class_member_accommodations', '*', ['class_id' => $id]
        );
        $accommodationMap = array_column($accommodations, null, 'student_id');

        $mismatchedMembers = 0;
        foreach ($members as &$member) {
            $member['grade_mismatch'] = (int) ($member['profiles']['grade_level'] ?? 0)
                !== (int) $class['grade_level'];
            if ($member['grade_mismatch']) {
                $mismatchedMembers++;
            }
            $member['accommodation'] = $accommodationMap[$member['student_id']] ?? [
                'additional_time_seconds' => 0,
                'notes' => null,
            ];
        }
        unset($member);

        $sessions = $this->supabase->adminSelect(
            'quiz_sessions',
            '*',
            ['class_id' => $id, 'teacher_id' => $user['id'], 'order' => 'created_at.desc']
        );

        $analytics = $this->sessionAnalytics(array_column($sessions, 'id'));
        foreach ($sessions as &$session) {
            $session['analytics'] = $analytics[$session['id']] ?? [
                'attempts' => 0,
                'average' => 0,
                'eligible' => 0,
                'missed' => 0,
                'completion_rate' => 0,
            ];
        }
        unset($session);

        $openSessions = array_values(array_filter(
            $sessions,
            fn (array $session): bool => in_array($session['status'] ?? 'waiting', ['waiting', 'active'], true)
        ));
        $endedSessions = array_values(array_filter(
            $sessions,
            fn (array $session): bool => ($session['status'] ?? 'waiting') === 'completed'
        ));

        $leaderboard = $this->classLeaderboard($members, array_column($endedSessions, 'id'));

        return view('teacher.classes.show', compact(
            'user', 'class', 'customization', 'members', 'mismatchedMembers',
            'openSessions', 'endedSessions', 'leaderboard'
        ));
    }

    public function settings(string $id)
    {
        $user = session('supabase_user');
        $class = $this->ownedClass($id, $user['id']);
        if (!$class) {
            return redirect('/teacher/dashboard?section=classes')->with('error', 'Class not found.');
        }

        $customization = $this->customization($id);

        return view('teacher.classes.settings', [
            'user' => $user,
            'class' => $class,
            'customization' => $customization,
            'themeColors' => self::THEME_COLORS,
            'icons' => self::ICONS,
            'patterns' => self::PATTERNS,
        ]);
    }

    public function updateSettings(Request $request, string $id)
    {
        $validated = $request->validate([
            'class_name' => 'required|string|max:100',
            'grade_level' => 'required|integer|between:1,6',
            'theme_color' => 'required|in:' . implode(',', self::THEME_COLORS),
            'icon' => 'required|in:' . implode(',', self::ICONS),
            'banner_pattern' => 'required|in:' . implode(',', self::PATTERNS),
        ]);

        $user = session('supabase_user');
        $token = session('supabase_token');
        $class = $this->ownedClass($id, $user['id']);
        if (!$class) {
            return redirect('/teacher/dashboard?section=classes')->with('error', 'Class not found.');
        }

        $newGrade = (int) $validated['grade_level'];
        if ($newGrade !== (int) $class['grade_level']) {
            $sessions = $this->supabase->adminSelect(
                'quiz_sessions',
                'id',
                ['class_id' => $id, 'limit' => 1]
            );
            if (!empty($sessions)) {
                return redirect("/teacher/classes/{$id}/settings")
                    ->with('error', 'A class with quiz history cannot change grade level. Create a new class for the new grade.');
            }

            $members = $this->supabase->adminSelect(
                'class_members',
                'profiles(grade_level)',
                ['class_id' => $id]
            );
            $mismatch = array_filter(
                $members,
                fn (array $member): bool => (int) ($member['profiles']['grade_level'] ?? 0) !== $newGrade
            );

            if (!empty($mismatch)) {
                return redirect("/teacher/classes/{$id}/settings")
                    ->with('error', 'Remove students whose profile grade differs before changing the class grade.');
            }
        }

        $updated = $this->supabase->update('classes', [
            'class_name' => trim($validated['class_name']),
            'grade_level' => $newGrade,
        ], ['id' => $id], $token);

        if (!isset($updated[0]['id'])) {
            return redirect("/teacher/classes/{$id}/settings")
                ->with('error', 'The class details could not be updated.');
        }

        $customData = [
            'theme_color' => $validated['theme_color'],
            'icon' => $validated['icon'],
            'banner_pattern' => $validated['banner_pattern'],
            'updated_at' => now()->toIso8601String(),
        ];

        $existing = $this->supabase->adminSelect('class_customizations', 'class_id', ['class_id' => $id]);
        if (empty($existing)) {
            $customData['class_id'] = $id;
            $customizationUpdated = $this->supabase->insert('class_customizations', $customData, $token);
        } else {
            $customizationUpdated = $this->supabase->update(
                'class_customizations',
                $customData,
                ['class_id' => $id],
                $token
            );
        }

        if (!isset($customizationUpdated[0]['class_id'])) {
            return redirect("/teacher/classes/{$id}/settings")
                ->with('error', 'The class details were saved, but the visual design could not be updated.');
        }

        return redirect("/teacher/classes/{$id}/settings")->with('success', 'Class settings updated.');
    }

    public function regenerateCode(string $id)
    {
        $user = session('supabase_user');
        $token = session('supabase_token');
        $class = $this->ownedClass($id, $user['id']);
        if (!$class) {
            return redirect('/teacher/dashboard?section=classes')->with('error', 'Class not found.');
        }
        if (!empty($class['archived_at'])) {
            return redirect("/teacher/classes/{$id}/settings")->with('error', 'Archived class codes cannot be regenerated.');
        }

        $joinCode = $this->generateJoinCode();
        $updated = $this->supabase->update('classes', ['join_code' => $joinCode], ['id' => $id], $token);

        if (!isset($updated[0]['id'])) {
            return redirect("/teacher/classes/{$id}/settings")
                ->with('error', 'A new join code could not be generated.');
        }

        return redirect("/teacher/classes/{$id}/settings")
            ->with('success', "New join code generated: {$joinCode}");
    }

    public function archive(string $id)
    {
        $user = session('supabase_user');
        $class = $this->ownedClass($id, $user['id']);
        if (!$class) {
            return redirect('/teacher/dashboard?section=classes')->with('error', 'Class not found.');
        }

        if (!empty($class['archived_at'])) {
            return redirect("/teacher/classes/{$id}/settings")->with('error', 'This class is already archived.');
        }

        $updated = $this->supabase->update(
            'classes',
            ['archived_at' => now()->toIso8601String()],
            ['id' => $id],
            session('supabase_token')
        );
        if (!isset($updated[0]['id'])) {
            return redirect("/teacher/classes/{$id}/settings")
                ->with('error', 'The class could not be archived. Run the latest Supabase migration first.');
        }

        $openSessions = $this->supabase->adminSelect('quiz_sessions', 'id,status', ['class_id' => $id]);
        foreach ($openSessions as $session) {
            if (in_array($session['status'] ?? 'waiting', ['waiting', 'active'], true)) {
                $this->supabase->adminUpdate('quiz_sessions', [
                    'status' => 'completed',
                    'is_active' => false,
                    'retake_mode' => false,
                    'ended_at' => now()->toIso8601String(),
                ], ['id' => $session['id']]);
            }
        }

        $this->supabase->adminUpdate('profiles', ['class_id' => null], ['class_id' => $id]);
        $this->supabase->audit($user, 'class.archived', 'class', $id, [
            'class_name' => $class['class_name'] ?? null,
        ]);

        return redirect('/teacher/dashboard?section=classes')
            ->with('success', 'Class archived. Its history is preserved and it no longer locks student grade levels.');
    }

    public function restore(string $id)
    {
        $user = session('supabase_user');
        $class = $this->ownedClass($id, $user['id']);
        if (!$class) {
            return redirect('/teacher/dashboard?section=classes')->with('error', 'Class not found.');
        }

        if (empty($class['archived_at'])) {
            return redirect("/teacher/classes/{$id}/settings")->with('error', 'This class is already active.');
        }

        $members = $this->supabase->adminSelect('class_members', 'profiles(grade_level)', ['class_id' => $id]);
        $mismatch = array_filter($members, fn (array $member): bool =>
            (int) ($member['profiles']['grade_level'] ?? 0) !== (int) $class['grade_level']
        );
        if (!empty($mismatch)) {
            return redirect("/teacher/classes/{$id}/settings")
                ->with('error', 'Remove students whose current grade differs from this class before restoring it.');
        }

        $updated = $this->supabase->update(
            'classes',
            ['archived_at' => null],
            ['id' => $id],
            session('supabase_token')
        );

        if (!isset($updated[0]['id'])) {
            return redirect("/teacher/classes/{$id}/settings")
                ->with('error', 'The class could not be restored.');
        }

        $this->supabase->audit($user, 'class.restored', 'class', $id, [
            'class_name' => $class['class_name'] ?? null,
        ]);
        return redirect("/teacher/classes/{$id}")->with('success', 'Class restored.');
    }

    public function destroy(string $id)
    {
        $user = session('supabase_user');
        if (!$this->ownedClass($id, $user['id'])) {
            return redirect('/teacher/dashboard?section=classes')->with('error', 'Class not found.');
        }

        $sessions = $this->supabase->adminSelect('quiz_sessions', 'id', ['class_id' => $id]);
        foreach ($sessions as $session) {
            $sessionId = $session['id'];
            $this->supabase->delete('quiz_participants', ['session_id' => $sessionId]);
            $this->supabase->delete('quiz_results', ['session_id' => $sessionId]);
            $this->supabase->delete('questions', ['session_id' => $sessionId]);
            $this->supabase->delete('quiz_sessions', ['id' => $sessionId]);
        }

        $this->supabase->adminUpdate('profiles', ['class_id' => null], ['class_id' => $id]);
        $this->supabase->delete('class_members', ['class_id' => $id]);
        $this->supabase->delete('class_customizations', ['class_id' => $id]);
        if (!$this->supabase->delete('classes', ['id' => $id])) {
            return redirect("/teacher/classes/{$id}/settings")
                ->with('error', 'The class could not be fully deleted. No success was reported.');
        }

        return redirect('/teacher/dashboard?section=classes')->with('success', 'Class deleted.');
    }

    public function removeStudent(string $classId, string $studentId)
    {
        $user = session('supabase_user');
        if (!$this->ownedClass($classId, $user['id'])) {
            return redirect('/teacher/dashboard?section=classes')->with('error', 'Class not found.');
        }

        $removed = $this->supabase->adminDelete('class_members', [
            'class_id' => $classId,
            'student_id' => $studentId,
        ]);

        if (!$removed) {
            return redirect("/teacher/classes/{$classId}")
                ->with('error', 'The student could not be removed from the class.');
        }

        $this->supabase->adminUpdate('profiles', ['class_id' => null], [
            'id' => $studentId,
            'class_id' => $classId,
        ]);

        $this->supabase->audit($user, 'class.student_removed', 'profile', $studentId, [
            'class_id' => $classId,
        ]);

        return redirect("/teacher/classes/{$classId}")->with('success', 'Student removed from the class.');
    }

    public function lobby(string $classId, string $sessionId)
    {
        $session = $this->ownedSession($classId, $sessionId);
        if (!$session) {
            return response()->json(['message' => 'Quiz session not found.'], 404);
        }

        $participants = $this->supabase->adminSelect(
            'quiz_participants',
            'student_id,profiles(first_name,last_name,level)',
            ['session_id' => $sessionId]
        );

        return response()->json($participants);
    }

    public function results(string $classId, string $sessionId)
    {
        $session = $this->ownedSession($classId, $sessionId);
        if (!$session) {
            return response()->json(['message' => 'Quiz session not found.'], 404);
        }

        $results = $this->supabase->adminSelect(
            'quiz_results',
            'student_id,correct_answers,total_questions,created_at,attempt_number,is_counted',
            ['session_id' => $sessionId, 'order' => 'attempt_number.asc']
        );
        $countedResults = [];
        $attemptCounts = [];
        foreach ($results as $result) {
            $studentId = $result['student_id'];
            $attemptCounts[$studentId] = ($attemptCounts[$studentId] ?? 0) + 1;
            if ($result['is_counted'] ?? false) {
                $countedResults[$studentId] = $result;
            }
        }

        $eligibility = $this->supabase->adminSelect(
            'quiz_session_students',
            'student_id,eligibility_status,allowed_attempts,additional_time_seconds,excuse_reason,retake_due_at,profiles!quiz_session_students_student_id_fkey(first_name,last_name,email)',
            ['session_id' => $sessionId]
        );

        $rows = [];
        foreach ($eligibility as $item) {
            $studentId = $item['student_id'];
            $result = $countedResults[$studentId] ?? null;
            $attemptsUsed = $attemptCounts[$studentId] ?? 0;
            $retakeExpired = !empty($item['retake_due_at'])
                && now()->gte(\Carbon\Carbon::parse($item['retake_due_at']));
            $item['result'] = $result;
            $item['attempts_used'] = $attemptsUsed;
            $item['remaining_attempts'] = $retakeExpired
                ? 0
                : max(0, (int) $item['allowed_attempts'] - $attemptsUsed);
            $item['can_grant_retake'] = ($session['status'] ?? '') === 'completed'
                || (bool) ($session['retake_mode'] ?? false);
            $item['assignment_status'] = ($item['eligibility_status'] ?? '') === 'excused'
                ? 'excused'
                : ($result ? 'completed' : (
                    ($session['status'] ?? '') === 'completed' || $retakeExpired ? 'missed' : 'available'
                ));
            $rows[] = $item;
        }

        usort($rows, function (array $a, array $b): int {
            $aScore = (int) ($a['result']['correct_answers'] ?? -1);
            $bScore = (int) ($b['result']['correct_answers'] ?? -1);
            if ($aScore !== $bScore) {
                return $bScore <=> $aScore;
            }
            $aProfile = $a['profiles'] ?? [];
            $bProfile = $b['profiles'] ?? [];
            return strcmp(
                ($aProfile['last_name'] ?? '') . ($aProfile['first_name'] ?? ''),
                ($bProfile['last_name'] ?? '') . ($bProfile['first_name'] ?? '')
            );
        });

        return response()->json($rows);
    }

    public function start(string $classId, string $sessionId)
    {
        $session = $this->ownedSession($classId, $sessionId);
        if (!$session) {
            return response()->json(['message' => 'Quiz session not found.'], 404);
        }
        $class = $this->ownedClass($classId, session('supabase_user')['id']);
        if (!empty($class['archived_at'])) {
            return response()->json(['message' => 'Archived classes cannot start quizzes.'], 422);
        }
        if (($session['status'] ?? 'waiting') !== 'waiting') {
            return response()->json(['message' => 'Only an assigned quiz can be started.'], 422);
        }
        if (!empty($session['available_at']) && now()->lt(\Carbon\Carbon::parse($session['available_at']))) {
            return response()->json(['message' => 'This quiz is scheduled for a later time.'], 422);
        }
        if (!empty($session['due_at']) && now()->gte(\Carbon\Carbon::parse($session['due_at']))) {
            return response()->json(['message' => 'This quiz assignment is already past due.'], 422);
        }

        $updated = $this->supabase->update('quiz_sessions', [
            'status' => 'active',
            'is_active' => true,
            'started_at' => $session['started_at'] ?? now()->toIso8601String(),
        ], ['id' => $sessionId], session('supabase_token'));

        return isset($updated[0]['id'])
            ? response()->json(['success' => true])
            : response()->json(['message' => 'The quiz could not be started.'], 500);
    }

    public function end(string $classId, string $sessionId)
    {
        $session = $this->ownedSession($classId, $sessionId);
        if (!$session || !in_array($session['status'] ?? 'waiting', ['waiting', 'active'], true)) {
            return response()->json(['message' => 'This quiz has already ended.'], 422);
        }

        $updated = $this->supabase->update('quiz_sessions', [
            'status' => 'completed',
            'is_active' => false,
            'ended_at' => now()->toIso8601String(),
            'retake_mode' => false,
        ], ['id' => $sessionId], session('supabase_token'));

        if (!isset($updated[0]['id'])) {
            return response()->json(['message' => 'The quiz could not be ended.'], 500);
        }

        $this->supabase->audit(session('supabase_user'), 'quiz.ended', 'quiz_session', $sessionId, [
            'class_id' => $classId,
            'topic' => $session['topic'] ?? null,
        ]);
        return response()->json(['success' => true]);
    }

    public function grantRetake(Request $request, string $classId, string $sessionId, string $studentId)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'due_at' => 'nullable|date|after:now',
        ]);
        $session = $this->ownedSession($classId, $sessionId);
        if (!$session) {
            return response()->json(['message' => 'Quiz session not found.'], 404);
        }
        if (($session['status'] ?? '') !== 'completed' && !($session['retake_mode'] ?? false)) {
            return response()->json(['message' => 'End the original quiz before granting a retake.'], 422);
        }

        $dueAt = !empty($validated['due_at'])
            ? \Carbon\Carbon::parse($validated['due_at'], config('app.timezone'))->utc()->toIso8601String()
            : null;
        $teacher = session('supabase_user');
        $retake = $this->supabase->adminRpc('grant_quiz_retake', [
            'p_session_id' => $sessionId,
            'p_student_id' => $studentId,
            'p_teacher_id' => $teacher['id'],
            'p_reason' => trim($validated['reason']),
            'p_due_at' => $dueAt,
        ])[0] ?? null;

        if (!$retake || !isset($retake['new_allowed_attempts'])) {
            return response()->json(['message' => 'The retake could not be granted.'], 500);
        }

        $allowedAttempts = (int) $retake['new_allowed_attempts'];
        $dueAt = $retake['retake_due_at'] ?? $dueAt;

        $this->supabase->audit($teacher, 'quiz.retake_granted', 'profile', $studentId, [
            'session_id' => $sessionId,
            'class_id' => $classId,
            'reason' => trim($validated['reason']),
            'allowed_attempts' => $allowedAttempts,
            'due_at' => $dueAt,
        ]);

        return response()->json(['success' => true, 'message' => 'Retake granted. The quiz is active for this student.']);
    }

    public function excuseStudent(Request $request, string $classId, string $sessionId, string $studentId)
    {
        $validated = $request->validate(['reason' => 'required|string|max:500']);
        if (!$this->ownedSession($classId, $sessionId)) {
            return response()->json(['message' => 'Quiz session not found.'], 404);
        }

        $countedResult = $this->supabase->adminSelect('quiz_results', 'id', [
            'session_id' => $sessionId, 'student_id' => $studentId, 'is_counted' => true,
        ]);
        if ($countedResult) {
            return response()->json(['message' => 'A completed attempt cannot be marked excused.'], 422);
        }

        $teacher = session('supabase_user');
        $updated = $this->supabase->adminUpdate('quiz_session_students', [
            'eligibility_status' => 'excused',
            'allowed_attempts' => 0,
            'excused_at' => now()->toIso8601String(),
            'excused_by' => $teacher['id'],
            'excuse_reason' => trim($validated['reason']),
        ], ['session_id' => $sessionId, 'student_id' => $studentId]);
        if (!isset($updated[0]['student_id'])) {
            return response()->json(['message' => 'The student could not be marked excused.'], 500);
        }

        $this->supabase->audit($teacher, 'quiz.student_excused', 'profile', $studentId, [
            'session_id' => $sessionId,
            'class_id' => $classId,
            'reason' => trim($validated['reason']),
        ]);

        return response()->json(['success' => true, 'message' => 'Student marked excused for this quiz.']);
    }

    public function updateAccommodation(Request $request, string $classId, string $studentId)
    {
        $validated = $request->validate([
            'additional_time_seconds' => 'required|integer|between:0,300',
            'notes' => 'nullable|string|max:500',
        ]);
        $teacher = session('supabase_user');
        if (!$this->ownedClass($classId, $teacher['id'])) {
            return redirect('/teacher/dashboard?section=classes')->with('error', 'Class not found.');
        }
        $member = $this->supabase->adminSelect('class_members', 'student_id', [
            'class_id' => $classId, 'student_id' => $studentId,
        ]);
        if (!$member) {
            return redirect("/teacher/classes/{$classId}")->with('error', 'That student is not in this class.');
        }

        $additionalTimeSeconds = (int) $validated['additional_time_seconds'];
        $saved = $this->supabase->adminUpsert('class_member_accommodations', [
            'class_id' => $classId,
            'student_id' => $studentId,
            'additional_time_seconds' => $additionalTimeSeconds,
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
            'updated_by' => $teacher['id'],
            'updated_at' => now()->toIso8601String(),
        ], 'class_id,student_id');
        if (!isset($saved[0]['student_id'])) {
            return redirect("/teacher/classes/{$classId}")->with('error', 'The accommodation could not be saved.');
        }

        $this->supabase->audit($teacher, 'class.accommodation_updated', 'profile', $studentId, [
            'class_id' => $classId,
            'additional_time_seconds' => $additionalTimeSeconds,
        ]);

        return redirect("/teacher/classes/{$classId}")->with('success', 'Student accommodation updated.');
    }

    private function ownedClass(string $classId, string $teacherId): ?array
    {
        return $this->supabase->adminSelect('classes', '*', [
            'id' => $classId,
            'teacher_id' => $teacherId,
        ])[0] ?? null;
    }

    private function ownedSession(string $classId, string $sessionId): ?array
    {
        $user = session('supabase_user');

        return $this->supabase->adminSelect('quiz_sessions', '*', [
            'id' => $sessionId,
            'class_id' => $classId,
            'teacher_id' => $user['id'],
        ])[0] ?? null;
    }

    private function customization(string $classId): array
    {
        return $this->supabase->adminSelect(
            'class_customizations',
            '*',
            ['class_id' => $classId]
        )[0] ?? [
            'theme_color' => '#f59e0b',
            'icon' => 'chalkboard',
            'banner_pattern' => 'grid',
        ];
    }

    private function sessionAnalytics(array $sessionIds): array
    {
        $sessionIds = array_values(array_unique(array_filter($sessionIds)));
        if (empty($sessionIds)) {
            return [];
        }

        $results = $this->supabase->adminSelect(
            'quiz_results',
            'session_id,student_id,correct_answers,total_questions,created_at',
            [
                'session_id' => ['operator' => 'in', 'value' => '(' . implode(',', $sessionIds) . ')'],
                'is_counted' => true,
                'order' => 'created_at.asc',
            ]
        );

        $eligibility = $this->supabase->adminSelect(
            'quiz_session_students', 'session_id,student_id,eligibility_status', [
                'session_id' => ['operator' => 'in', 'value' => '(' . implode(',', $sessionIds) . ')'],
            ]
        );

        $grouped = [];
        foreach ($results as $result) {
            $id = $result['session_id'];
            $accuracy = ($result['total_questions'] ?? 0) > 0
                ? (($result['correct_answers'] ?? 0) / $result['total_questions']) * 100
                : 0;
            $grouped[$id]['attempts'] = ($grouped[$id]['attempts'] ?? 0) + 1;
            $grouped[$id]['accuracy_sum'] = ($grouped[$id]['accuracy_sum'] ?? 0) + $accuracy;
        }

        foreach ($eligibility as $item) {
            if (($item['eligibility_status'] ?? '') !== 'eligible') {
                continue;
            }
            $id = $item['session_id'];
            $grouped[$id]['eligible'] = ($grouped[$id]['eligible'] ?? 0) + 1;
        }

        foreach ($grouped as &$item) {
            $item['attempts'] = $item['attempts'] ?? 0;
            $item['accuracy_sum'] = $item['accuracy_sum'] ?? 0;
            $item['average'] = $item['attempts'] > 0
                ? round($item['accuracy_sum'] / $item['attempts'], 1)
                : 0;
            $item['eligible'] = $item['eligible'] ?? 0;
            $item['missed'] = max(0, $item['eligible'] - $item['attempts']);
            $item['completion_rate'] = $item['eligible'] > 0
                ? round(($item['attempts'] / $item['eligible']) * 100, 1)
                : 0;
            unset($item['accuracy_sum']);
        }
        unset($item);

        return $grouped;
    }

    private function classLeaderboard(array $members, array $sessionIds): array
    {
        $sessionIds = array_values(array_unique(array_filter($sessionIds)));
        if (empty($members)) {
            return [];
        }

        $results = empty($sessionIds) ? [] : $this->supabase->adminSelect(
            'quiz_results',
            'session_id,student_id,correct_answers,total_questions,created_at',
            [
                'session_id' => ['operator' => 'in', 'value' => '(' . implode(',', $sessionIds) . ')'],
                'is_counted' => true,
                'order' => 'created_at.asc',
            ]
        );
        $eligibility = empty($sessionIds) ? [] : $this->supabase->adminSelect(
            'quiz_session_students', 'session_id,student_id,eligibility_status', [
                'session_id' => ['operator' => 'in', 'value' => '(' . implode(',', $sessionIds) . ')'],
            ]
        );
        $resultMap = [];
        foreach ($results as $result) {
            $resultMap[$result['session_id'] . ':' . $result['student_id']] = $result;
        }

        $rows = [];
        foreach ($members as $member) {
            $profile = $member['profiles'] ?? [];
            $studentId = $profile['id'] ?? $member['student_id'];
            $studentEligibility = array_values(array_filter(
                $eligibility,
                fn (array $item): bool => $item['student_id'] === $studentId
                    && ($item['eligibility_status'] ?? '') === 'eligible'
            ));
            $studentResults = [];
            foreach ($studentEligibility as $item) {
                $key = $item['session_id'] . ':' . $studentId;
                if (isset($resultMap[$key])) {
                    $studentResults[] = $resultMap[$key];
                }
            }
            $accuracies = array_map(fn (array $result): float => ($result['total_questions'] ?? 0) > 0
                ? (($result['correct_answers'] ?? 0) / $result['total_questions']) * 100
                : 0, $studentResults);
            $eligibleCount = count($studentEligibility);
            $completedCount = count($studentResults);
            if ($eligibleCount === 0) {
                continue;
            }
            $rows[] = [
                'student_id' => $studentId,
                'name' => trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?: 'Unknown Student',
                'average' => $eligibleCount > 0 ? round(array_sum($accuracies) / $eligibleCount, 1) : 0,
                'quizzes' => $completedCount,
                'eligible' => $eligibleCount,
                'missed' => max(0, $eligibleCount - $completedCount),
                'completion_rate' => $eligibleCount > 0
                    ? round(($completedCount / $eligibleCount) * 100, 1)
                    : 0,
                'correct' => array_sum(array_column($studentResults, 'correct_answers')),
            ];
        }

        usort($rows, fn (array $a, array $b): int =>
            ($b['average'] <=> $a['average'])
            ?: ($b['completion_rate'] <=> $a['completion_rate'])
            ?: ($b['correct'] <=> $a['correct'])
            ?: ($b['quizzes'] <=> $a['quizzes'])
            ?: strcmp($a['name'], $b['name'])
        );

        foreach ($rows as $index => &$row) {
            $row['rank'] = $index + 1;
        }
        unset($row);

        return $rows;
    }

    private function expirePastDueSessions(string $classId, array $teacher): void
    {
        $sessions = $this->supabase->adminSelect('quiz_sessions', 'id,topic,due_at,status', [
            'class_id' => $classId,
            'teacher_id' => $teacher['id'],
            'due_at' => ['operator' => 'lte', 'value' => now()->utc()->toIso8601String()],
        ]);

        foreach ($sessions as $session) {
            if (!in_array($session['status'] ?? '', ['waiting', 'active'], true)) {
                continue;
            }
            $updated = $this->supabase->adminUpdate('quiz_sessions', [
                'status' => 'completed',
                'is_active' => false,
                'retake_mode' => false,
                'ended_at' => $session['due_at'] ?? now()->toIso8601String(),
            ], ['id' => $session['id'], 'teacher_id' => $teacher['id'], 'status' => $session['status']]);
            if (isset($updated[0]['id'])) {
                $this->supabase->audit($teacher, 'quiz.auto_ended', 'quiz_session', $session['id'], [
                    'class_id' => $classId,
                    'topic' => $session['topic'] ?? null,
                    'due_at' => $session['due_at'] ?? null,
                ]);
            }
        }
    }

    private function generateJoinCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }

            if (empty($this->supabase->adminSelect('classes', 'id', ['join_code' => $code]))) {
                return $code;
            }
        }

        throw new \RuntimeException('Unable to generate an available class join code.');
    }
}
