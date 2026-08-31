<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AdminPushService;
use App\Services\SupabaseService;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function __construct(
        private SupabaseService $supabase,
        private AdminPushService $adminPush
    ) {}

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
        $validated = $request->validate([
            'email' => 'required|email|max:254',
            'password' => 'required|string|max:128',
        ]);

        $result = $this->supabase->signIn($validated['email'], $validated['password']);

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

        $validated = $request->validate([
            'email' => 'required|email|max:254',
            'password' => 'required|string|min:6|max:128|confirmed',
            'role' => 'required|in:student,pending_teacher',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'grade_level' => 'nullable|required_if:role,student|integer|between:1,6',
        ]);

        $gradeLevel = $validated['role'] === 'student'
            ? (int) $validated['grade_level']
            : null;

        $auth = $this->supabase->signUp(
            $validated['email'],
            $validated['password'],
            $validated['role'],
            $validated['first_name'],
            $validated['last_name'],
            $gradeLevel,
            url('/')
        );

        if (!$auth['successful']) {
            return back()->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', $auth['error'] ?? 'Signup failed.');
        }

        // Supabase returns the new user even when email confirmation means no
        // session is issued yet. Keep the admin lookup only as a compatibility
        // fallback for older Auth responses.
        $userId = $auth['data']['user']['id'] ?? null;
        if (!$userId) {
            $userData = $this->supabase->getUserByEmail($validated['email']);
            $userId = $userData['users'][0]['id'] ?? null;
        }

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

        if ($validated['role'] === 'pending_teacher') {
            $teacherName = trim($validated['first_name'] . ' ' . $validated['last_name'])
                ?: $validated['email'];
            $this->adminPush->send(
                'Teacher verification requested',
                "{$teacherName} registered and is ready for verification.",
                '/admin/dashboard?section=role-verify',
                "teacher-verification-{$userId}"
            );
        }

        return redirect('/')
            ->with('success', 'Registered successfully! Please verify your email.');
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate(['email' => 'required|email|max:254']);
        $result = $this->supabase->resetPassword(
            $validated['email'],
            url('/reset-password')
        );
        if (!$result['successful']) {
            return back()->with('error', 'The recovery email could not be sent. Please try again later.');
        }

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
        $validated = $request->validate([
            'current_password' => 'required|string|max:128',
            'new_password' => 'required|string|min:6|max:128|confirmed',
        ]);
        $user = session('supabase_user');
        $redirect = $this->securityRedirect($user['role'] ?? 'student');
        $check = $this->supabase->signIn($user['email'], $validated['current_password']);

        if (!isset($check['access_token'])) {
            return redirect($redirect)->with('error', 'Current password is incorrect.');
        }

        $result = $this->supabase->updateAuthUser($check['access_token'], [
            'password' => $validated['new_password'],
        ]);
        if (!$result['successful']) {
            return redirect($redirect)->with('error', $result['error'] ?? 'The password could not be changed.');
        }

        session(['supabase_token' => $check['access_token']]);
        $this->supabase->audit($user, 'account.password_changed', 'profile', $user['id']);

        return redirect($redirect)->with('success', 'Password changed successfully.');
    }

    public function changeEmail(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string|max:128',
            'new_email' => 'required|email|max:254|confirmed',
        ]);
        $user = session('supabase_user');
        $redirect = $this->securityRedirect($user['role'] ?? 'student');
        $newEmail = mb_strtolower(trim($validated['new_email']));

        if (mb_strtolower((string) $user['email']) === $newEmail) {
            return redirect($redirect)->with('error', 'Enter a different email address.');
        }

        $check = $this->supabase->signIn($user['email'], $validated['current_password']);
        if (!isset($check['access_token'])) {
            return redirect($redirect)->with('error', 'Current password is incorrect.');
        }

        $result = $this->supabase->updateAuthUser(
            $check['access_token'],
            ['email' => $newEmail],
            url('/')
        );
        if (!$result['successful']) {
            return redirect($redirect)->with('error', $result['error'] ?? 'The email change could not be started.');
        }

        session(['supabase_token' => $check['access_token']]);
        $this->supabase->audit($user, 'account.email_change_requested', 'profile', $user['id'], [
            'new_email' => $newEmail,
        ]);

        return redirect($redirect)->with(
            'success',
            'Confirmation sent. Check the new email and, when secure email change is enabled, the current email too.'
        );
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6|max:128|confirmed',
            'token' => 'required|string|max:2048',
        ]);

        $token_hash = $validated['token'];
        $type       = 'recovery'; // always recovery for password reset

        // Step 1 — Verify the token_hash to get a session
        $session = Http::withHeaders([
            'apikey'       => config('services.supabase.anon_key'),
            'Content-Type' => 'application/json',
        ])->post(config('services.supabase.url') . '/auth/v1/verify', [
            'token_hash' => $token_hash,
            'type'       => $type,
        ]);

        $sessionData = $session->json();

        if ($session->failed() || !isset($sessionData['access_token'])) {
            return back()->with('error', 'Invalid or expired reset link. Please request a new one.');
        }

        $access_token = $sessionData['access_token'];

        // Step 2 — Update password
        $result = $this->supabase->updateAuthUser($access_token, [
            'password' => $validated['password'],
        ]);

        if (!$result['successful']) {
            return back()->with('error', $result['error'] ?? 'The password could not be updated.');
        }

        return redirect('/')->with('success', 'Password updated! Please log in.');
    }

    private function securityRedirect(string $role): string
    {
        return match ($role) {
            'admin' => '/admin/dashboard?section=security',
            'teacher' => '/teacher/dashboard?section=security',
            default => '/student/dashboard?section=security',
        };
    }
}
