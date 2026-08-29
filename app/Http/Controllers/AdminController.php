<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\SupabaseService;
use Barryvdh\DomPDF\Facade\Pdf;

class AdminController extends Controller
{
    public function __construct(private SupabaseService $supabase) {}

    public function index(Request $request)
    {
        $user = session('supabase_user');

        $selectedGrade = (int) $request->query('student_grade', $request->query('grade', 0));
        $selectedGrade = ($selectedGrade >= 1 && $selectedGrade <= 6) ? $selectedGrade : 0;
        $studentSearch = $this->registrySearch($request->query('student_search', ''));
        $teacherSearch = $this->registrySearch($request->query('teacher_search', ''));
        $studentSort = $this->registrySort((string) $request->query('student_sort', 'name_asc'));
        $teacherSort = $this->registrySort((string) $request->query('teacher_sort', 'name_asc'));
        $studentPage = max(1, (int) $request->query('student_page', 1));
        $teacherPage = max(1, (int) $request->query('teacher_page', 1));
        $perPage = 25;

        $studentFilters = ['role' => 'student', 'order' => $this->registryOrder($studentSort)];
        if ($selectedGrade !== 0) {
            $studentFilters['grade_level'] = $selectedGrade;
        }
        if ($studentSearch !== '') {
            $studentFilters['or'] = $this->registryOrFilter($studentSearch);
        }

        $teacherFilters = ['role' => 'teacher', 'order' => $this->registryOrder($teacherSort)];
        if ($teacherSearch !== '') {
            $teacherFilters['or'] = $this->registryOrFilter($teacherSearch);
        }

        $studentResult = $this->supabase->adminSelectPage(
            'profiles', '*', $studentFilters, $perPage, ($studentPage - 1) * $perPage
        );
        $teacherResult = $this->supabase->adminSelectPage(
            'profiles', '*', $teacherFilters, $perPage, ($teacherPage - 1) * $perPage
        );

        $students = $studentResult['data'];
        $teachers = $teacherResult['data'];
        $studentTotal = $studentResult['total'];
        $teacherTotal = $teacherResult['total'];
        $studentPages = max(1, (int) ceil($studentTotal / $perPage));
        $teacherPages = max(1, (int) ceil($teacherTotal / $perPage));

        if ($studentPage > $studentPages && $studentTotal > 0) {
            return redirect()->to($request->fullUrlWithQuery(['student_page' => $studentPages, 'section' => 'students']));
        }
        if ($teacherPage > $teacherPages && $teacherTotal > 0) {
            return redirect()->to($request->fullUrlWithQuery(['teacher_page' => $teacherPages, 'section' => 'teachers']));
        }

        $totalStudents = $this->supabase->adminCount('profiles', ['role' => 'student']);
        $totalTeachers = $this->supabase->adminCount('profiles', ['role' => 'teacher']);
        $totalPending = $this->supabase->adminCount('profiles', ['role' => 'pending_teacher']);
        $totalUsers = $totalStudents + $totalTeachers + $totalPending;
        $totalQuizzes = $this->supabase->adminCount('quizzes');
        $pendingTeachers = $this->supabase->adminSelect(
            'profiles', '*', ['role' => 'pending_teacher', 'order' => 'created_at.asc']
        );
        $pendingReportCount = $this->supabase->adminCount('quiz_reports', ['status' => 'pending']);

        $auditPage = max(1, (int) $request->query('audit_page', 1));
        $auditResult = $this->supabase->adminSelectPage(
            'audit_logs', '*', ['order' => 'created_at.desc'], 30, ($auditPage - 1) * 30
        );
        $auditLogs = $auditResult['data'];
        $auditTotal = $auditResult['total'];
        $auditPages = max(1, (int) ceil($auditTotal / 30));
        if ($auditPage > $auditPages && $auditTotal > 0) {
            return redirect()->to($request->fullUrlWithQuery([
                'audit_page' => $auditPages,
                'section' => 'audit',
            ]));
        }
        $actorIds = array_values(array_unique(array_filter(array_column($auditLogs, 'actor_id'))));
        $actors = empty($actorIds) ? [] : $this->supabase->adminSelect(
            'profiles', 'id,first_name,last_name',
            ['id' => ['operator' => 'in', 'value' => '(' . implode(',', $actorIds) . ')']]
        );
        $actorNames = [];
        foreach ($actors as $actor) {
            $actorNames[$actor['id']] = trim(
                ($actor['first_name'] ?? '') . ' ' . ($actor['last_name'] ?? '')
            ) ?: 'Administrator';
        }
        foreach ($auditLogs as &$log) {
            $log['actor_name'] = $actorNames[$log['actor_id'] ?? ''] ?? 'System';
        }
        unset($log);

        return view('admin.dashboard', compact(
            'user', 'students', 'teachers', 'selectedGrade', 'totalUsers', 'totalTeachers',
            'totalStudents', 'totalQuizzes', 'pendingTeachers', 'studentSearch', 'teacherSearch',
            'studentSort', 'teacherSort', 'studentPage', 'teacherPage', 'studentPages',
            'teacherPages', 'studentTotal', 'teacherTotal', 'auditLogs', 'auditPage',
            'auditPages', 'auditTotal', 'pendingReportCount'
        ));
    }

    public function deleteUser(string $id)
    {
        $profile = $this->supabase->adminSelect(
            'profiles', 'role,first_name,last_name,email', ['id' => $id]
        )[0] ?? null;
        if (!$profile || !in_array($profile['role'], ['student', 'teacher'], true)) {
            return redirect('/admin/dashboard')->with('error', 'Only student and teacher accounts can be deleted here.');
        }
        $section = $profile['role'] === 'student' ? 'students' : 'teachers';

        // Delete from auth.users — this cascades to profiles automatically
        $response = Http::withHeaders([
            'apikey'        => config('services.supabase.anon_key'),
            'Authorization' => 'Bearer ' . config('services.supabase.service_key'),
            'Content-Type'  => 'application/json',
        ])->delete(config('services.supabase.url') . "/auth/v1/admin/users/{$id}");

        if ($response->failed()) {
            return redirect("/admin/dashboard?section={$section}")
                ->with('error', $response->json()['msg'] ?? 'Failed to delete user.');
        }

        $this->supabase->audit(session('supabase_user'), 'user.deleted', 'profile', $id, [
            'role' => $profile['role'],
            'name' => trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')),
            'email' => $profile['email'] ?? null,
        ]);

        return redirect("/admin/dashboard?section={$section}")
            ->with('success', 'User deleted.');
    }

    public function approveTeacher(string $id)
    {
        $profile = $this->supabase->adminSelect(
            'profiles', 'id,role,first_name,last_name,email', ['id' => $id]
        )[0] ?? null;
        if (!$profile || ($profile['role'] ?? '') !== 'pending_teacher') {
            return redirect('/admin/dashboard?section=role-verify')
                ->with('error', 'Only a pending teacher application can be approved.');
        }

        $updated = $this->supabase->adminUpdate(
            'profiles', ['role' => 'teacher'], ['id' => $id, 'role' => 'pending_teacher']
        );
        if (!isset($updated[0]['id'])) {
            return redirect('/admin/dashboard?section=role-verify')
                ->with('error', 'The teacher application could not be approved.');
        }

        $this->supabase->audit(session('supabase_user'), 'teacher.approved', 'profile', $id, [
            'name' => trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')),
            'email' => $profile['email'] ?? null,
        ]);
        return redirect('/admin/dashboard?section=role-verify')
            ->with('success', 'Teacher approved!');
    }

    public function denyTeacher(string $id)
    {
        $profile = $this->supabase->adminSelect(
            'profiles', 'id,role,first_name,last_name,email', ['id' => $id]
        )[0] ?? null;
        if (!$profile || ($profile['role'] ?? '') !== 'pending_teacher') {
            return redirect('/admin/dashboard?section=role-verify')
                ->with('error', 'Only a pending teacher application can be rejected.');
        }

        $response = Http::withHeaders([
            'apikey'        => config('services.supabase.anon_key'),
            'Authorization' => 'Bearer ' . config('services.supabase.service_key'),
            'Content-Type'  => 'application/json',
        ])->delete(config('services.supabase.url') . "/auth/v1/admin/users/{$id}");

        if ($response->failed()) {
            return redirect('/admin/dashboard?section=role-verify')
                ->with('error', 'Failed to reject application.');
        }

        $this->supabase->audit(session('supabase_user'), 'teacher.rejected', 'profile', $id, [
            'name' => trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')),
            'email' => $profile['email'] ?? null,
        ]);

        return redirect('/admin/dashboard?section=role-verify')
            ->with('success', 'Application rejected.');
    }

    public function suspendUser(Request $request, string $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'return_section' => 'nullable|in:students,teachers',
        ]);
        $profile = $this->manageableProfile($id);
        $section = $validated['return_section'] ?? (($profile['role'] ?? '') === 'teacher' ? 'teachers' : 'students');

        if (!$profile) {
            return redirect("/admin/dashboard?section={$section}")
                ->with('error', 'Only student and teacher accounts can be suspended.');
        }
        if (!empty($profile['suspended_at'])) {
            return redirect("/admin/dashboard?section={$section}")
                ->with('error', 'That account is already suspended.');
        }

        $admin = session('supabase_user');
        if (!$this->supabase->setAuthUserSuspended($id, true)) {
            return redirect("/admin/dashboard?section={$section}")
                ->with('error', 'Supabase Auth could not suspend that account. No profile changes were made.');
        }
        $updated = $this->supabase->adminUpdate('profiles', [
            'suspended_at' => now()->toIso8601String(),
            'suspended_by' => $admin['id'],
            'suspension_reason' => trim($validated['reason']),
        ], ['id' => $id, 'role' => $profile['role']]);

        if (!isset($updated[0]['id'])) {
            $this->supabase->setAuthUserSuspended($id, false);
            return redirect("/admin/dashboard?section={$section}")
                ->with('error', 'The account could not be suspended.');
        }

        $this->supabase->audit($admin, 'user.suspended', 'profile', $id, [
            'role' => $profile['role'],
            'reason' => trim($validated['reason']),
        ]);

        return redirect("/admin/dashboard?section={$section}")
            ->with('success', 'Account suspended. Its data was preserved.');
    }

    public function restoreUser(Request $request, string $id)
    {
        $profile = $this->manageableProfile($id);
        $section = $request->input('return_section') === 'teachers' ? 'teachers' : 'students';
        if (!$profile || empty($profile['suspended_at'])) {
            return redirect("/admin/dashboard?section={$section}")
                ->with('error', 'That account is not suspended.');
        }

        if (!$this->supabase->setAuthUserSuspended($id, false)) {
            return redirect("/admin/dashboard?section={$section}")
                ->with('error', 'Supabase Auth could not restore that account. It remains suspended.');
        }
        $updated = $this->supabase->adminUpdate('profiles', [
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ], ['id' => $id, 'role' => $profile['role']]);

        if (!isset($updated[0]['id'])) {
            $this->supabase->setAuthUserSuspended($id, true);
            return redirect("/admin/dashboard?section={$section}")
                ->with('error', 'The account could not be restored.');
        }

        $this->supabase->audit(session('supabase_user'), 'user.restored', 'profile', $id, [
            'role' => $profile['role'],
        ]);

        return redirect("/admin/dashboard?section={$section}")
            ->with('success', 'Account restored.');
    }

    public function updateProfile(Request $request)
    {
        if ($avatarSizeError = $this->rejectOversizedAvatar($request, '/admin/dashboard?section=profile')) {
            return $avatarSizeError;
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
        ]);

        $user  = session('supabase_user');
        $token = session('supabase_token');
        $userId = $user['id'];

        // ── UPDATE BASIC INFO
        $profileUpdated = $this->supabase->update('profiles', [
            'first_name'  => $validated['first_name'],
            'last_name'   => $validated['last_name'],
            'grade_level' => 0,
        ], ['id' => $userId], $token);
        if (!isset($profileUpdated[0]['id'])) {
            return redirect('/admin/dashboard?section=profile')
                ->with('error', 'The profile could not be updated.');
        }

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
        $updated['first_name']  = $validated['first_name'];
        $updated['last_name']   = $validated['last_name'];
        $updated['grade_level'] = 0;

        if ($avatarUrl) {
            $updated['avatar_url'] = $avatarUrl;
        }

        session(['supabase_user' => $updated]);

        $this->supabase->audit($updated, 'profile.updated', 'profile', $userId, [
            'avatar_changed' => $avatarUrl !== null,
        ]);

        return redirect('/admin/dashboard?section=profile')->with('success', 'Profile updated successfully!');
    }

    public function stats()
    {
        // All 3 calls happen as fast as possible — no loops making extra calls
        $allResults = $this->supabase->adminSelect(
            'quiz_results',
            'correct_answers,total_questions,created_at,session_id,student_id',
            ['is_counted' => true, 'order' => 'created_at.asc']
        );
        $quizzes    = $this->supabase->adminSelect('quizzes', 'id,topic,teacher_id,created_at');
        $profiles   = $this->supabase->adminSelect('profiles', 'id,role,created_at');

        // Registrations per day
        $registrationsPerDay = [];
        for ($i = 13; $i >= 0; $i--) {
            $date  = date('Y-m-d', strtotime("-{$i} days"));
            $label = date('M d',   strtotime("-{$i} days"));
            $count = count(array_filter($profiles, fn($p) => str_starts_with($p['created_at'] ?? '', $date)));
            $registrationsPerDay[] = ['date' => $label, 'count' => $count];
        }

        // Attempts per day
        $attemptsPerDay = [];
        for ($i = 13; $i >= 0; $i--) {
            $date  = date('Y-m-d', strtotime("-{$i} days"));
            $label = date('M d',   strtotime("-{$i} days"));
            $count = count(array_filter($allResults, fn($r) => str_starts_with($r['created_at'] ?? '', $date)));
            $attemptsPerDay[] = ['date' => $label, 'count' => $count];
        }

        // Role breakdown
        $roleBreakdown = [
            'students' => count(array_filter($profiles, fn($p) => $p['role'] === 'student')),
            'teachers' => count(array_filter($profiles, fn($p) => $p['role'] === 'teacher')),
            'pending'  => count(array_filter($profiles, fn($p) => $p['role'] === 'pending_teacher')),
        ];

        // Score distribution
        $distribution = [0, 0, 0, 0];
        foreach ($allResults as $r) {
            if (($r['total_questions'] ?? 0) === 0) continue;
            $pct = ($r['correct_answers'] / $r['total_questions']) * 100;
            if      ($pct <= 25) $distribution[0]++;
            elseif  ($pct <= 50) $distribution[1]++;
            elseif  ($pct <= 75) $distribution[2]++;
            else                 $distribution[3]++;
        }

        $totalAttempts = count($allResults);
        $avgAccuracy   = $totalAttempts > 0
            ? round(array_sum(array_map(fn($r) =>
                ($r['total_questions'] ?? 0) > 0
                    ? ($r['correct_answers'] / $r['total_questions']) * 100 : 0,
                $allResults)) / $totalAttempts, 1)
            : 0;

        return response()->json([
            'registrationsPerDay' => $registrationsPerDay,
            'attemptsPerDay'      => $attemptsPerDay,
            'roleBreakdown'       => $roleBreakdown,
            'distribution'        => $distribution,
            'totalAttempts'       => $totalAttempts,
            'totalQuizzes'        => count($quizzes),
            'totalUsers'          => count($profiles),
            'avgAccuracy'         => $avgAccuracy,
        ]);
    }

    public function reportStudents(Request $request)
    {
        $format   = $request->query('format', 'pdf');
        $profiles = $this->supabase->adminSelect('profiles', '*', ['role' => 'student']);

        $rows = array_map(fn($p) => [
            'name'     => ($p['last_name'] ?? '') . ', ' . ($p['first_name'] ?? ''),
            'email'    => $p['email'] ?? '',
            'grade'    => $p['grade_level'] ? 'Grade ' . $p['grade_level'] : 'N/A',
            'trophies' => $p['trophies'] ?? 0,
            'level'    => $p['level'] ?? 1,
            'joined'   => isset($p['created_at'])
                ? \Carbon\Carbon::parse($p['created_at'])->format('M d, Y')
                : 'N/A',
        ], $profiles);

        usort($rows, fn($a, $b) => strcmp($a['name'], $b['name']));

        if ($format === 'csv') {
            return $this->downloadCsv($rows,
                ['Name', 'Email', 'Grade', 'Level', 'Trophies', 'Date Joined'],
                ['name', 'email', 'grade', 'level', 'trophies', 'joined'],
                'student-registry-report'
            );
        }

        $pdf = Pdf::loadView('reports.students', [
            'rows'      => $rows,
            'generated' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('student-registry-report.pdf');
    }

    public function reportTeachers(Request $request)
    {
        $format   = $request->query('format', 'pdf');
        $profiles = $this->supabase->adminSelect('profiles', '*', ['role' => 'teacher']);

        // Get quiz count per teacher
        $allQuizzes = $this->supabase->adminSelect('quizzes', 'id,teacher_id');

        $rows = array_map(function($p) use ($allQuizzes) {
            $quizCount = count(array_filter($allQuizzes, fn($q) => $q['teacher_id'] === $p['id']));
            return [
                'name'       => ($p['last_name'] ?? '') . ', ' . ($p['first_name'] ?? ''),
                'email'      => $p['email'] ?? '',
                'grade'      => $p['grade_level'] ? 'Grade ' . $p['grade_level'] : 'N/A',
                'quizzes'    => $quizCount,
                'joined'     => isset($p['created_at'])
                    ? \Carbon\Carbon::parse($p['created_at'])->format('M d, Y')
                    : 'N/A',
            ];
        }, $profiles);

        usort($rows, fn($a, $b) => strcmp($a['name'], $b['name']));

        if ($format === 'csv') {
            return $this->downloadCsv($rows,
                ['Name', 'Email', 'Grade', 'Quizzes Created', 'Date Joined'],
                ['name', 'email', 'grade', 'quizzes', 'joined'],
                'teacher-registry-report'
            );
        }

        $pdf = Pdf::loadView('reports.teachers', [
            'rows'      => $rows,
            'generated' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('teacher-registry-report.pdf');
    }

    public function reportSummary(Request $request)
    {
        $format     = $request->query('format', 'pdf');
        $profiles   = $this->supabase->adminSelect('profiles', '*');
        $quizzes    = $this->supabase->adminSelect('quizzes', 'id');
        $allResults = $this->supabase->adminSelect(
            'quiz_results',
            'correct_answers,total_questions,student_id,session_id,created_at',
            ['is_counted' => true, 'order' => 'created_at.asc']
        );

        $students = array_values(array_filter($profiles, fn($p) => $p['role'] === 'student'));
        $teachers = array_values(array_filter($profiles, fn($p) => $p['role'] === 'teacher'));
        $pending  = array_values(array_filter($profiles, fn($p) => $p['role'] === 'pending_teacher'));

        $totalAttempts = count($allResults);
        $avgAccuracy   = 0;
        if ($totalAttempts > 0) {
            $avgAccuracy = round(array_sum(array_map(fn($r) =>
                $r['total_questions'] > 0 ? ($r['correct_answers'] / $r['total_questions']) * 100 : 0,
                $allResults)) / $totalAttempts, 1);
        }

        // Top 10 students by trophies
        usort($students, fn($a, $b) => ($b['trophies'] ?? 0) - ($a['trophies'] ?? 0));
        $top10 = array_slice($students, 0, 10);
        $top10 = array_map(fn($s) => [
            'name'     => ($s['last_name'] ?? '') . ', ' . ($s['first_name'] ?? ''),
            'grade'    => 'Grade ' . ($s['grade_level'] ?? 'N/A'),
            'trophies' => $s['trophies'] ?? 0,
        ], $top10);

        $summary = [
            'total_users'    => count($students) + count($teachers) + count($pending),
            'total_students' => count($students),
            'total_teachers' => count($teachers),
            'total_pending'  => count($pending),
            'total_quizzes'  => count($quizzes),
            'total_attempts' => $totalAttempts,
            'avg_accuracy'   => $avgAccuracy . '%',
            'generated'      => now()->format('M d, Y h:i A'),
        ];

        if ($format === 'csv') {
            $summaryRows = [
                ['Metric', 'Value'],
                ['Total Users',    $summary['total_users']],
                ['Total Students', $summary['total_students']],
                ['Total Teachers', $summary['total_teachers']],
                ['Pending Teachers', $summary['total_pending']],
                ['Total Quizzes',  $summary['total_quizzes']],
                ['Total Attempts', $summary['total_attempts']],
                ['Avg Accuracy',   $summary['avg_accuracy']],
            ];
            return response()->streamDownload(function () use ($summaryRows, $top10) {
                $out = fopen('php://output', 'w');
                foreach ($summaryRows as $row) fputcsv($out, $row);
                fputcsv($out, []);
                fputcsv($out, ['Top 10 Students by Trophies']);
                fputcsv($out, ['Name', 'Grade', 'Trophies']);
                foreach ($top10 as $s) fputcsv($out, [$s['name'], $s['grade'], $s['trophies']]);
                fclose($out);
            }, 'platform-summary.csv', ['Content-Type' => 'text/csv']);
        }

        $pdf = Pdf::loadView('reports.summary', compact('summary', 'top10'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('platform-summary-report.pdf');
    }

    private function manageableProfile(string $id): ?array
    {
        $profile = $this->supabase->adminSelect(
            'profiles',
            'id,role,first_name,last_name,email,suspended_at,suspension_reason',
            ['id' => $id]
        )[0] ?? null;

        return $profile && in_array($profile['role'] ?? '', ['student', 'teacher'], true)
            ? $profile
            : null;
    }

    private function registrySearch(mixed $value): string
    {
        $search = trim(mb_substr((string) $value, 0, 80));
        return trim(str_replace(['*', '%', ',', '(', ')'], '', $search));
    }

    private function registryOrFilter(string $search): string
    {
        return '(first_name.ilike.*' . $search
            . '*,last_name.ilike.*' . $search
            . '*,email.ilike.*' . $search . '*)';
    }

    private function registrySort(string $sort): string
    {
        return in_array($sort, ['name_asc', 'name_desc', 'newest', 'oldest'], true)
            ? $sort
            : 'name_asc';
    }

    private function registryOrder(string $sort): string
    {
        return match ($sort) {
            'name_desc' => 'last_name.desc,first_name.desc',
            'newest' => 'created_at.desc',
            'oldest' => 'created_at.asc',
            default => 'last_name.asc,first_name.asc',
        };
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
}
