<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\SupabaseService;
use Barryvdh\DomPDF\Facade\Pdf;

class AdminController extends Controller
{
    public function __construct(private SupabaseService $supabase) {}

    public function index()
    {
        $user     = session('supabase_user');
        $profiles = $this->supabase->adminSelect('profiles', '*', ['order' => 'role.asc']);

        $totalUsers     = count($profiles) - 1;
        $totalTeachers  = count(array_filter($profiles, fn($p) => $p['role'] === 'teacher'));
        $totalStudents  = count(array_filter($profiles, fn($p) => $p['role'] === 'student'));
        $pendingTeachers = array_values(array_filter($profiles, fn($p) => $p['role'] === 'pending_teacher'));

        $quizCountResp = $this->supabase->adminSelect('quizzes', 'id');
        $totalQuizzes  = count($quizCountResp);

        return view('admin.dashboard', compact(
            'user', 'profiles', 'totalUsers', 'totalTeachers',
            'totalStudents', 'totalQuizzes', 'pendingTeachers'
        ));
    }

    public function updateUser(Request $request, string $id)
    {
        $this->supabase->adminUpdate('profiles', [
            'username' => $request->username,
            'role'     => $request->role,
        ], ['id' => $id]);
    }

    public function deleteUser(string $id)
    {
        // Delete from auth.users — this cascades to profiles automatically
        $response = Http::withHeaders([
            'apikey'        => config('services.supabase.anon_key'),
            'Authorization' => 'Bearer ' . config('services.supabase.service_key'),
            'Content-Type'  => 'application/json',
        ])->delete(config('services.supabase.url') . "/auth/v1/admin/users/{$id}");

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'error'   => $response->json()['msg'] ?? 'Failed to delete user.'
            ], 500);
        }

        return redirect('/admin/dashboard?section=user-lists')
            ->with('success', 'User deleted.');
    }

    public function approveTeacher(string $id)
    {
        $this->supabase->adminUpdate('profiles', ['role' => 'teacher'], ['id' => $id]);
        return redirect('/admin/dashboard?section=role-verify')
            ->with('success', 'Teacher approved!');
    }

    public function denyTeacher(string $id)
    {
        $response = Http::withHeaders([
            'apikey'        => config('services.supabase.anon_key'),
            'Authorization' => 'Bearer ' . config('services.supabase.service_key'),
            'Content-Type'  => 'application/json',
        ])->delete(config('services.supabase.url') . "/auth/v1/admin/users/{$id}");

        if ($response->failed()) {
            return redirect('/admin/dashboard?section=role-verify')
                ->with('error', 'Failed to reject application.');
        }

        return redirect('/admin/dashboard?section=role-verify')
            ->with('success', 'Application rejected.');
    }

    public function updateProfile(Request $request)
    {
        if ($avatarSizeError = $this->rejectOversizedAvatar($request, '/admin/dashboard?section=profile')) {
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

        return redirect('/admin/dashboard?section=profile')->with('success', 'Profile updated successfully!');
    }

    public function stats()
    {
        // All 3 calls happen as fast as possible — no loops making extra calls
        $allResults = $this->supabase->adminSelect('quiz_results', 'correct_answers,total_questions,created_at,session_id');
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
        $allResults = $this->supabase->adminSelect('quiz_results', 'correct_answers,total_questions,student_id');

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
            'total_users'    => count($profiles) - 1,
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
