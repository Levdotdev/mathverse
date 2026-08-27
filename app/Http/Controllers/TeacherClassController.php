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

        $customization = $this->customization($id);
        $members = $this->supabase->adminSelect(
            'class_members',
            'student_id,joined_at,profiles(id,username,last_name,email,first_name,grade_level,level)',
            ['class_id' => $id, 'order' => 'joined_at.asc']
        );

        $mismatchedMembers = 0;
        foreach ($members as &$member) {
            $member['grade_mismatch'] = (int) ($member['profiles']['grade_level'] ?? 0)
                !== (int) $class['grade_level'];
            if ($member['grade_mismatch']) {
                $mismatchedMembers++;
            }
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

        return view('teacher.classes.show', compact(
            'user', 'class', 'customization', 'members', 'mismatchedMembers',
            'openSessions', 'endedSessions'
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
        if (!$this->ownedClass($id, $user['id'])) {
            return redirect('/teacher/dashboard?section=classes')->with('error', 'Class not found.');
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
        if (!$this->ownedSession($classId, $sessionId)) {
            return response()->json(['message' => 'Quiz session not found.'], 404);
        }

        $results = $this->supabase->adminSelect(
            'quiz_results',
            'correct_answers,total_questions,created_at,profiles(first_name,last_name,email)',
            ['session_id' => $sessionId]
        );
        usort($results, fn ($a, $b) => ($b['correct_answers'] ?? 0) <=> ($a['correct_answers'] ?? 0));

        return response()->json($results);
    }

    public function start(string $classId, string $sessionId)
    {
        $session = $this->ownedSession($classId, $sessionId);
        if (!$session || ($session['status'] ?? 'waiting') !== 'waiting') {
            return response()->json(['message' => 'Only an assigned quiz can be started.'], 422);
        }

        $updated = $this->supabase->update('quiz_sessions', [
            'status' => 'active',
            'is_active' => true,
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
        ], ['id' => $sessionId], session('supabase_token'));

        return isset($updated[0]['id'])
            ? response()->json(['success' => true])
            : response()->json(['message' => 'The quiz could not be ended.'], 500);
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
            'session_id,correct_answers,total_questions',
            ['session_id' => ['operator' => 'in', 'value' => '(' . implode(',', $sessionIds) . ')']]
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

        foreach ($grouped as &$item) {
            $item['average'] = $item['attempts'] > 0
                ? round($item['accuracy_sum'] / $item['attempts'], 1)
                : 0;
            unset($item['accuracy_sum']);
        }
        unset($item);

        return $grouped;
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
