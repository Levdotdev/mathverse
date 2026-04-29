<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SupabaseService;

class AuthController extends Controller
{
    public function __construct(private SupabaseService $supabase) {}

    public function showLogin()
    {
        // If already logged in, redirect to correct dashboard
        if ($user = session('supabase_user')) {
            return $this->redirectByRole($user['role']);
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {

        $result = $this->supabase->signIn($request->email, $request->password);

        if (isset($result['error']) || !isset($result['access_token'])) {
            return back()->with('error', $result['error_description'] ?? 'Login failed.');
        }

        // Fetch profile to get role
        $profiles = $this->supabase->select('profiles', '*', ['id' => $result['user']['id']], $result['access_token']);
        $profile  = $profiles[0] ?? null;

        if (!$profile) {
            return back()->with('error', 'Profile not found.');
        }

        if ($profile['role'] === 'pending_teacher') {
            return back()->with('error', 'Your teacher account is pending admin approval.');
        }

        // Store user info and token in session
        session([
            'supabase_token' => $result['access_token'],
            'supabase_user'  => array_merge($profile, ['email' => $result['user']['email']]),
        ]);

        return $this->redirectByRole($profile['role']);
    }

    public function register(Request $request)
    {
        if ($request->password !== $request->password_confirmation) {
            return back()->with('error', 'Passwords do not match.');
        }

        $authResult = $this->supabase->signUp($request->email, $request->password, $request->role, $request->first_name, $request->last_name);

        if (isset($authResult['error'])) {
            return back()->with('error', $authResult['msg'] ?? 'Registration failed.');
        }

        $userId = $authResult['user']['id'] ?? null;

        return redirect('/')->with('success', 'Registered! Please verify your email then log in.');
    }

    public function forgotPassword(Request $request)
    {
        $this->supabase->resetPassword($request->email);
        return back()->with('success', 'Recovery link sent.');
    }

    public function logout(Request $request)
    {
        session()->forget(['supabase_token', 'supabase_user']);
        return redirect('/');
    }

    private function redirectByRole(string $role)
    {
        return match($role) {
            'admin'   => redirect('/admin/dashboard'),
            'teacher' => redirect('/teacher/dashboard'),
            default   => redirect('/student/dashboard'),
        };
    }

    public function updatePassword(Request $request)
    {

        if ($request->password !== $request->password_confirmation) {
            return back()->with('error', 'Passwords do not match.');
        }

        $response = $this->supabase->updatePassword($request->token, $request->password);

        return back()->with('success', 'Password updated!');
    }
}