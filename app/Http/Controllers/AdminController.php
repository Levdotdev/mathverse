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

        return back()->with('success', 'User role updated!');
    }

    public function deleteUser(string $id)
    {
        $this->supabase->adminDelete('profiles', ['id' => $id]);
        return back()->with('success', 'User successfully deleted!');
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
                return back()->with('error', 'Current password is required.');
            }
            if ($request->new_password !== $request->new_password_confirmation) {
                return back()->with('error', 'Passwords do not match.');
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

        return back()->with('success', 'Password updated!');
    }
}