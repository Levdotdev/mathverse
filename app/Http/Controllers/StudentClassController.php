<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\Request;

class StudentClassController extends Controller
{
    public function __construct(private SupabaseService $supabase) {}

    public function join(Request $request)
    {
        $validated = $request->validate([
            'join_code' => 'required|string|alpha_num|size:6',
        ]);

        $user = session('supabase_user');
        $token = session('supabase_token');
        $joinCode = strtoupper(trim($validated['join_code']));
        $class = $this->supabase->adminSelect(
            'classes',
            'id,class_name,grade_level',
            ['join_code' => $joinCode]
        )[0] ?? null;

        if (!$class) {
            return redirect('/student/dashboard?section=class')->with('error', 'Invalid class join code.');
        }

        $profile = $this->supabase->adminSelect(
            'profiles',
            'id,grade_level',
            ['id' => $user['id']]
        )[0] ?? $user;
        $studentGrade = (int) ($profile['grade_level'] ?? 0);
        $classGrade = (int) $class['grade_level'];

        if ($studentGrade !== $classGrade) {
            return redirect('/student/dashboard?section=class')->with(
                'error',
                "{$class['class_name']} is Grade {$classGrade}, but your profile is Grade {$studentGrade}."
            );
        }

        $existing = $this->supabase->adminSelect('class_members', 'student_id', [
            'class_id' => $class['id'],
            'student_id' => $user['id'],
        ]);
        if (!empty($existing)) {
            return redirect("/student/classes/{$class['id']}")->with('error', 'You already belong to this class.');
        }

        $joined = $this->supabase->insert('class_members', [
            'student_id' => $user['id'],
            'class_id' => $class['id'],
        ], $token);

        if (!isset($joined[0]['student_id'])) {
            return redirect('/student/dashboard?section=class')
                ->with('error', 'The class could not be joined. Confirm that your profile grade matches the class grade.');
        }

        return redirect("/student/classes/{$class['id']}")->with('success', 'Successfully joined the class.');
    }

    public function show(string $id)
    {
        $user = session('supabase_user');
        $membership = $this->supabase->adminSelect('class_members', 'student_id', [
            'class_id' => $id,
            'student_id' => $user['id'],
        ])[0] ?? null;

        if (!$membership) {
            return redirect('/student/dashboard?section=class')
                ->with('error', 'You do not have access to that class.');
        }

        $class = $this->supabase->adminSelect('classes', '*', ['id' => $id])[0] ?? null;
        if (!$class) {
            return redirect('/student/dashboard?section=class')->with('error', 'Class not found.');
        }

        $customization = $this->supabase->adminSelect(
            'class_customizations',
            '*',
            ['class_id' => $id]
        )[0] ?? [
            'theme_color' => '#22c55e',
            'icon' => 'chalkboard',
            'banner_pattern' => 'grid',
        ];

        $sessions = $this->supabase->adminSelect(
            'quiz_sessions',
            '*',
            ['class_id' => $id, 'order' => 'created_at.desc']
        );

        $results = $this->supabase->adminSelect(
            'quiz_results',
            'session_id,correct_answers,total_questions,created_at',
            ['student_id' => $user['id'], 'order' => 'created_at.desc']
        );
        $sessionIds = array_flip(array_column($sessions, 'id'));
        $results = array_values(array_filter(
            $results,
            fn (array $result): bool => isset($sessionIds[$result['session_id']])
        ));

        $resultMap = [];
        foreach ($results as $result) {
            if (!isset($resultMap[$result['session_id']])) {
                $resultMap[$result['session_id']] = $result;
            }
        }

        foreach ($sessions as &$session) {
            $session['result'] = $resultMap[$session['id']] ?? null;
            if ($session['result']) {
                $total = (int) ($session['result']['total_questions'] ?? 0);
                $session['result']['accuracy'] = $total > 0
                    ? round(((int) $session['result']['correct_answers'] / $total) * 100)
                    : 0;
            }
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

        $accuracies = array_values(array_filter(array_map(
            fn (array $session) => $session['result']['accuracy'] ?? null,
            $endedSessions
        ), fn ($accuracy) => $accuracy !== null));

        $analytics = [
            'attempts' => count($accuracies),
            'average' => !empty($accuracies) ? round(array_sum($accuracies) / count($accuracies), 1) : 0,
            'best' => !empty($accuracies) ? max($accuracies) : 0,
        ];

        return view('student.classes.show', compact(
            'user', 'class', 'customization', 'openSessions', 'endedSessions', 'analytics'
        ));
    }
}
