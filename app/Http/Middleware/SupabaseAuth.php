<?php

namespace App\Http\Middleware;

use App\Services\SupabaseService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupabaseAuth
{
    public function __construct(private SupabaseService $supabase) {}

    public function handle(Request $request, Closure $next, string $role = ''): mixed
    {
        $user = session('supabase_user');

        if (!$user) {
            return redirect('/')->with('error', 'Please log in first.');
        }

        try {
            $currentProfile = $this->supabase->adminSelect(
                'profiles',
                'id,role,first_name,last_name,email,avatar_url,grade_level,suspended_at,leaderboard_alias,show_on_leaderboard,auth_sessions_invalid_before',
                ['id' => $user['id']]
            )[0] ?? null;
        } catch (\Throwable $exception) {
            Log::warning('Authenticated profile verification failed.', [
                'user_id' => $user['id'] ?? null,
                'exception' => $exception::class,
            ]);
            $this->invalidateSession($request);

            return redirect('/')->with('error', 'MathVerse could not verify your session. Please sign in again.');
        }

        if (!$currentProfile) {
            $this->invalidateSession($request);
            return redirect('/')->with('error', 'Your account is no longer available.');
        }

        if (!empty($currentProfile['suspended_at'])) {
            $this->invalidateSession($request);
            return redirect('/')->with('error', 'Your account is suspended. Contact an administrator.');
        }

        if ($this->sessionPredatesPasswordChange(
            $request->session()->get('supabase_authenticated_at'),
            $currentProfile['auth_sessions_invalid_before'] ?? null
        )) {
            $this->invalidateSession($request);

            return redirect('/')->with('error', 'Your password changed. Please sign in again.');
        }

        $currentRole = $currentProfile['role'] ?? null;
        if (!is_string($currentRole)
            || !in_array($currentRole, ['student', 'teacher', 'admin'], true)
        ) {
            $this->invalidateSession($request);

            return redirect('/')->with(
                'error',
                'Your account is not authorized to use a dashboard. Contact an administrator.'
            );
        }

        $user = array_merge($user, $currentProfile);
        session(['supabase_user' => $user]);

        if ($role && ($user['role'] ?? '') !== $role) {
            // Redirect to their correct dashboard
            $userRole = $user['role'] ?? '';
            if ($userRole === 'student')  return redirect('/student/dashboard');
            if ($userRole === 'teacher')  return redirect('/teacher/dashboard');
            if ($userRole === 'admin')    return redirect('/admin/dashboard');

            return redirect('/')->with('error', 'Access denied.');
        }

        $pendingTeacherCount = 0;
        $pendingReportCount = 0;
        $notifications = [];
        $unreadNotificationCount = 0;
        try {
            if (($user['role'] ?? '') === 'admin') {
                $pendingTeacherCount = $this->supabase->adminCount('profiles', [
                    'role' => 'pending_teacher',
                ]);
                $pendingReportCount = $this->supabase->adminCount('quiz_reports', [
                    'status' => 'pending',
                ]);
                view()->share([
                    'adminPendingTeacherCount' => $pendingTeacherCount,
                    'adminPendingReportCount' => $pendingReportCount,
                ]);
            }

            // These updates and notification reads are useful but must not turn
            // a temporary delivery failure into a broken authenticated page.
            $this->supabase->adminRpc('advance_quiz_session_schedule');
            $this->supabase->adminRpc('generate_upcoming_quiz_notifications', [
                'p_user_id' => $user['id'],
            ]);

            $notifications = $this->supabase->adminSelect(
                'notifications',
                'id,type,title,message,action_url,data,read_at,created_at',
                [
                    'user_id' => $user['id'],
                    'order' => 'created_at.desc',
                    'limit' => 12,
                ]
            );
            $unreadNotificationCount = $this->supabase->adminCount('notifications', [
                'user_id' => $user['id'],
                'read_at' => ['operator' => 'is', 'value' => 'null'],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Non-critical dashboard preparation failed.', [
                'user_id' => $user['id'] ?? null,
                'exception' => $exception::class,
            ]);
        }
        view()->share(compact('notifications', 'unreadNotificationCount'));

        $response = $next($request);

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($request->isMethod('GET')
            && $response->getStatusCode() === 200
            && str_contains($contentType, 'text/html')) {
            $route = $request->route();
            $routeName = $route?->getName();
            $routeUri = $route?->uri() ?? ltrim($request->path(), '/');
            try {
                $this->supabase->audit($user, 'page.viewed', 'page', $routeName ?: $routeUri, [
                    // Query strings may carry search terms or other private
                    // context, so page-view audit records retain only the path.
                    'path' => '/' . ltrim(mb_substr($request->path(), 0, 1000), '/'),
                    'route' => $routeName,
                    'status' => $response->getStatusCode(),
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Page-view audit could not be recorded.', [
                    'user_id' => $user['id'] ?? null,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $response;
    }

    private function invalidateSession(Request $request): void
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function sessionPredatesPasswordChange(mixed $authenticatedAt, mixed $invalidBefore): bool
    {
        if (!is_string($invalidBefore) || trim($invalidBefore) === '') {
            return false;
        }

        if (!is_string($authenticatedAt) || trim($authenticatedAt) === '') {
            return true;
        }

        try {
            return CarbonImmutable::parse($authenticatedAt)
                ->lt(CarbonImmutable::parse($invalidBefore));
        } catch (\Throwable) {
            return true;
        }
    }
}
