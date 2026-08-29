<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

        $this->supabase->audit($profile, 'user.logged_in', 'profile', $profile['id'], [
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 250),
        ]);

        return $this->redirectByRole($profile['role']);
    }

    public function register(Request $request)
    {
        if ($avatarSizeError = $this->rejectOversizedAvatar($request)) {
            return $avatarSizeError;
        }

        if ($request->password !== $request->password_confirmation) {
            return back()->with('error', 'Passwords do not match.');
        }

        $gradeLevel = $request->role === 'student'
            ? (int) $request->grade_level
            : null;

        $auth = $this->supabase->signUp(
            $request->email,
            $request->password,
            $request->role,
            $request->first_name,
            $request->last_name,
            $gradeLevel
        );

        if (isset($auth['error'])) {
            return back()->with('error', $auth['body'] ?? 'Signup failed.');
        }

        // ── STEP 2: GET USER ID (FIX FOR NULL TOKEN ISSUE)
        $userData = $this->supabase->getUserByEmail($request->email);

        $userId = $userData['users'][0]['id'] ?? null;

        if (!$userId) {
            return back()->with('error', 'User created but ID not found.');
        }

        // ── STEP 3: UPLOAD AVATAR
        $avatarUrl = $this->supabase->uploadAvatar($userId, $request->file('avatar'));

        // ── STEP 4: UPDATE PROFILE
        if ($avatarUrl) {
            $this->supabase->updateProfile($userId, [
                'avatar_url' => $avatarUrl
            ]);
        }

        return redirect('/')
            ->with('success', 'Registered successfully! Please verify your email.');
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

    public function changePassword(Request $request)
    {
        $user  = session('supabase_user');
        $token = session('supabase_token');

            $role = session('supabase_user')['role'];

            if ($role === 'student') {
                $redirectProfile = '/student/dashboard?section=password';
            } elseif ($role === 'teacher') {
                $redirectProfile = '/teacher/dashboard?section=password';
            } else {
                $redirectProfile = '/admin/dashboard?section=password';
            }

            if ($request->new_password !== $request->new_password_confirmation) {
                return redirect($redirectProfile)->with('error', 'New passwords do not match.');
            }

            $check = $this->supabase->signIn($user['email'], $request->current_password);

            if (!isset($check['access_token'])) {
                return redirect($redirectProfile)->with('error', 'Current password is incorrect.');
            }

            $newToken = $check['access_token'];

            \Illuminate\Support\Facades\Http::withHeaders([
                'apikey'        => config('services.supabase.anon_key'),
                'Authorization' => "Bearer {$newToken}",
                'Content-Type'  => 'application/json',
            ])->put(config('services.supabase.url') . '/auth/v1/user', [
                'password' => $request->new_password,
            ]);
            return back()->with('success', 'Password changed successfully!');
    }

    public function updatePassword(Request $request)
    {
        if ($request->password !== $request->password_confirmation) {
            return back()->with('error', 'Passwords do not match.');
        }

        if (strlen($request->password) < 6) {
            return back()->with('error', 'Password must be at least 6 characters.');
        }

        $token_hash = $request->token;
        $type       = 'recovery'; // always recovery for password reset

        if (!$token_hash) {
            return back()->with('error', 'Invalid or missing reset token.');
        }

        // Step 1 — Verify the token_hash to get a session
        $session = Http::withHeaders([
            'apikey'       => config('services.supabase.anon_key'),
            'Content-Type' => 'application/json',
        ])->post(config('services.supabase.url') . '/auth/v1/verify', [
            'token_hash' => $token_hash,
            'type'       => $type,
        ]);

        $sessionData = $session->json();

        // Debug — remove after fixing
        \Log::info('Supabase verify response:', $sessionData);

        if ($session->failed() || !isset($sessionData['access_token'])) {
            return back()->with('error', 'Invalid or expired reset link. Please request a new one. Error: ' . ($sessionData['error_description'] ?? $sessionData['msg'] ?? 'Unknown'));
        }

        $access_token = $sessionData['access_token'];

        // Step 2 — Update password
        $response = Http::withHeaders([
            'apikey'        => config('services.supabase.anon_key'),
            'Authorization' => "Bearer {$access_token}",
            'Content-Type'  => 'application/json',
        ])->put(config('services.supabase.url') . '/auth/v1/user', [
            'password' => $request->password,
        ]);

        if ($response->failed()) {
            return back()->with('error', 'Failed to update password: ' . $response->json()['msg'] ?? 'Unknown error');
        }

        return redirect('/')->with('success', 'Password updated! Please log in.');
    }
}
