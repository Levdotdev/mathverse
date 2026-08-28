<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\Request;

class StudentClassController extends Controller
{
    public function __construct(private SupabaseService $supabase) {}

    public function join(Request $request)
    {
        $validated = $request->validate(['join_code' => 'required|string|alpha_num|size:6']);
        $user = session('supabase_user');
        $joinCode = strtoupper(trim($validated['join_code']));
        $class = $this->supabase->adminSelect(
            'classes', 'id,class_name,grade_level,archived_at', ['join_code' => $joinCode]
        )[0] ?? null;

        if (!$class || !empty($class['archived_at'])) {
            return redirect('/student/dashboard?section=class')->with('error', 'That class code is invalid or archived.');
        }

        $profile = $this->supabase->adminSelect('profiles', 'id,grade_level', ['id' => $user['id']])[0] ?? $user;
        $studentGrade = (int) ($profile['grade_level'] ?? 0);
        $classGrade = (int) $class['grade_level'];
        if ($studentGrade !== $classGrade) {
            return redirect('/student/dashboard?section=class')->with(
                'error',
                "{$class['class_name']} is Grade {$classGrade}, but your profile is Grade {$studentGrade}."
            );
        }

        $existing = $this->supabase->adminSelect('class_members', 'student_id', [
            'class_id' => $class['id'], 'student_id' => $user['id'],
        ]);
        if (!empty($existing)) {
            return redirect("/student/classes/{$class['id']}")->with('error', 'You already belong to this class.');
        }

        $joined = $this->supabase->insert('class_members', [
            'student_id' => $user['id'], 'class_id' => $class['id'],
        ], session('supabase_token'));

        return isset($joined[0]['student_id'])
            ? redirect("/student/classes/{$class['id']}")->with('success', 'Successfully joined the class.')
            : redirect('/student/dashboard?section=class')->with('error', 'The class could not be joined.');
    }

    public function show(string $id)
    {
        $user = session('supabase_user');
        $membership = $this->membership($id, $user['id']);
        if (!$membership) {
            return redirect('/student/dashboard?section=class')->with('error', 'You do not have access to that class.');
        }

        $class = $this->supabase->adminSelect('classes', '*', ['id' => $id])[0] ?? null;
        if (!$class || !empty($class['archived_at'])) {
            return redirect('/student/dashboard?section=class')->with('error', 'This class has been archived by the teacher.');
        }

        $customization = $this->supabase->adminSelect(
            'class_customizations', '*', ['class_id' => $id]
        )[0] ?? ['theme_color' => '#22c55e', 'icon' => 'chalkboard', 'banner_pattern' => 'grid'];

        $sessions = $this->supabase->adminSelect('quiz_sessions', '*', ['class_id' => $id, 'order' => 'created_at.desc']);
        $sessionIds = array_column($sessions, 'id');
        $results = $this->supabase->adminSelect(
            'quiz_results',
            'session_id,correct_answers,total_questions,created_at',
            ['student_id' => $user['id'], 'order' => 'created_at.asc']
        );
        $resultMap = [];
        foreach ($results as $result) {
            if (in_array($result['session_id'], $sessionIds, true)) {
                $resultMap[$result['session_id']] ??= $result;
            }
        }

        foreach ($sessions as &$session) {
            $session['result'] = $resultMap[$session['id']] ?? null;
            if ($session['result']) {
                $total = (int) ($session['result']['total_questions'] ?? 0);
                $session['result']['accuracy'] = $total > 0
                    ? round(((int) $session['result']['correct_answers'] / $total) * 100, 1)
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
                && (empty($membership['joined_at']) || strtotime($session['created_at']) >= strtotime($membership['joined_at']))
        ));

        $accuracies = array_values(array_filter(array_map(
            fn (array $session) => $session['result']['accuracy'] ?? null,
            $endedSessions
        ), fn ($accuracy): bool => $accuracy !== null));

        $analytics = [
            'attempts' => count($accuracies),
            'missed' => count($endedSessions) - count($accuracies),
            'passed' => count(array_filter($accuracies, fn (float $accuracy): bool => $accuracy >= 75)),
            'failed' => count(array_filter($accuracies, fn (float $accuracy): bool => $accuracy < 75)),
            'average' => !empty($accuracies) ? round(array_sum($accuracies) / count($accuracies), 1) : 0,
            'best' => !empty($accuracies) ? max($accuracies) : 0,
        ];

        $leaderboard = $this->classLeaderboard($id, $sessionIds);

        return view('student.classes.show', compact(
            'user', 'class', 'customization', 'openSessions', 'endedSessions', 'analytics', 'leaderboard'
        ));
    }

    public function review(string $classId, string $sessionId)
    {
        $user = session('supabase_user');
        $membership = $this->membership($classId, $user['id']);
        if (!$membership) {
            return redirect('/student/dashboard?section=class')->with('error', 'You do not have access to that quiz.');
        }

        $class = $this->supabase->adminSelect('classes', '*', ['id' => $classId])[0] ?? null;
        $session = $this->supabase->adminSelect('quiz_sessions', '*', [
            'id' => $sessionId, 'class_id' => $classId, 'status' => 'completed',
        ])[0] ?? null;
        if (!$class || !$session) {
            return redirect('/student/dashboard?section=stats')->with('error', 'Only ended quizzes can be reviewed.');
        }
        if (!empty($membership['joined_at']) && strtotime($session['created_at']) < strtotime($membership['joined_at'])) {
            return redirect('/student/dashboard?section=stats')->with('error', 'That quiz ended before you joined the class.');
        }

        $result = $this->supabase->adminSelect(
            'quiz_results',
            'correct_answers,total_questions,created_at',
            ['session_id' => $sessionId, 'student_id' => $user['id'], 'order' => 'created_at.asc', 'limit' => 1]
        )[0] ?? null;
        if ($result) {
            $total = (int) ($result['total_questions'] ?? 0);
            $result['accuracy'] = $total > 0 ? round(((int) $result['correct_answers'] / $total) * 100, 1) : 0;
            $result['status'] = $result['accuracy'] >= 75 ? 'Passed' : 'Failed';
        }

        $questions = $this->supabase->adminSelect('questions', '*', ['session_id' => $sessionId, 'order' => 'id.asc']);
        foreach ($questions as &$question) {
            $choices = array_values(array_filter([
                $question['choice1'] ?? null, $question['choice2'] ?? null,
                $question['choice3'] ?? null, $question['choice4'] ?? null,
                $question['choice5'] ?? null, $question['choice6'] ?? null,
            ], fn ($choice): bool => $choice !== null && $choice !== ''));
            $storedAnswer = (string) ($question['correct_answer'] ?? '');
            $answerIndex = ctype_digit($storedAnswer) ? (int) $storedAnswer : -1;
            $question['choices'] = $choices;
            if ($answerIndex < 0 || !isset($choices[$answerIndex])) {
                $matchedIndex = array_search($storedAnswer, $choices, true);
                $answerIndex = $matchedIndex === false ? -1 : $matchedIndex;
            }
            $question['correct_index'] = $answerIndex;
            $question['correct_text'] = $answerIndex >= 0 ? $choices[$answerIndex] : $storedAnswer;
        }
        unset($question);

        return view('student.classes.review', compact('user', 'class', 'session', 'result', 'questions'));
    }

    private function membership(string $classId, string $studentId): ?array
    {
        return $this->supabase->adminSelect('class_members', 'student_id,joined_at', [
            'class_id' => $classId, 'student_id' => $studentId,
        ])[0] ?? null;
    }

    private function classLeaderboard(string $classId, array $sessionIds): array
    {
        if (empty($sessionIds)) {
            return [];
        }

        $members = $this->supabase->adminSelect(
            'class_members',
            'student_id,profiles(first_name,last_name)',
            ['class_id' => $classId]
        );
        $results = $this->supabase->adminSelect(
            'quiz_results',
            'session_id,student_id,correct_answers,total_questions,created_at',
            ['session_id' => ['operator' => 'in', 'value' => '(' . implode(',', $sessionIds) . ')'], 'order' => 'created_at.asc']
        );
        $firstResults = [];
        foreach ($results as $result) {
            $firstResults[$result['session_id'] . ':' . $result['student_id']] ??= $result;
        }

        $rows = [];
        foreach ($members as $member) {
            $studentResults = array_values(array_filter(
                $firstResults,
                fn (array $result): bool => $result['student_id'] === $member['student_id']
            ));
            if (empty($studentResults)) {
                continue;
            }
            $accuracies = array_map(fn (array $result): float => ($result['total_questions'] ?? 0) > 0
                ? (($result['correct_answers'] ?? 0) / $result['total_questions']) * 100
                : 0, $studentResults);
            $profile = $member['profiles'] ?? [];
            $firstName = trim((string) ($profile['first_name'] ?? '')) ?: 'Student';
            $lastInitial = mb_substr(trim((string) ($profile['last_name'] ?? '')), 0, 1);
            $rows[] = [
                'student_id' => $member['student_id'],
                'name' => $lastInitial !== '' ? "{$firstName} {$lastInitial}." : $firstName,
                'average' => round(array_sum($accuracies) / count($accuracies), 1),
                'quizzes' => count($studentResults),
                'correct' => array_sum(array_column($studentResults, 'correct_answers')),
            ];
        }

        usort($rows, fn (array $a, array $b): int =>
            ($b['average'] <=> $a['average'])
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
}
