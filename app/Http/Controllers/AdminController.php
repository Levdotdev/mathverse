<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\SupabaseService;

class AdminController extends Controller
{
    public function __construct(private SupabaseService $supabase) {}

    public function index()
    {
        $user     = session('supabase_user');
        $profiles = $this->supabase->adminSelect('profiles', '*', ['order' => 'role.asc']);

        $totalUsers     = count($profiles);
        $totalTeachers  = count(array_filter($profiles, fn($p) => $p['role'] === 'teacher'));
        $totalStudents  = count(array_filter($profiles, fn($p) => $p['role'] === 'student'));
        $pendingTeachers = array_values(array_filter($profiles, fn($p) => $p['role'] === 'pending_teacher'));

        $quizCountResp = $this->supabase->adminSelect('quiz_sessions', 'id');
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
        $this->supabase->adminDelete('profiles', ['id' => $id]);
    }

    public function approveTeacher(string $id)
    {
        $this->supabase->adminUpdate('profiles', ['role' => 'teacher'], ['id' => $id]);
        return redirect('/admin/dashboard?section=role-verify')
            ->with('success', 'Teacher approved!');
    }

    public function denyTeacher(string $id)
    {
        $this->supabase->adminDelete('profiles', ['id' => $id]);
        return redirect('/admin/dashboard?section=role-verify')
            ->with('error', 'Application rejected.');
    }

    public function updateProfile(Request $request)
    {
        $user  = session('supabase_user');
        $token = session('supabase_token');

        if ($request->filled('new_password')) {
            if (!$request->filled('current_password')) {
                return redirect('/admin/dashboard?section=profile')
                    ->with('error', 'Current password is required.');
            }
            if ($request->new_password !== $request->new_password_confirmation) {
                return redirect('/admin/dashboard?section=profile')
                    ->with('error', 'Passwords do not match.');
            }
            $check = $this->supabase->signIn($user['email'], $request->current_password);
            if (isset($check['error'])) {
                return redirect('/admin/dashboard?section=profile')
                    ->with('error', 'Current password is incorrect.');
            }
            Http::withHeaders([
                'apikey'        => config('services.supabase.anon_key'),
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json',
            ])->put(config('services.supabase.url') . '/auth/v1/user', [
                'password' => $request->new_password,
            ]);
        }

        return redirect('/admin/dashboard?section=profile')
            ->with('success', 'Password updated!');
    }

    public function stats()
    {
        // All 3 calls happen as fast as possible — no loops making extra calls
        $allResults = $this->supabase->adminSelect('quiz_results', 'correct_answers,total_questions,created_at,session_id');
        $quizzes    = $this->supabase->adminSelect('quiz_sessions', 'id,topic,teacher_id,created_at');
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
}