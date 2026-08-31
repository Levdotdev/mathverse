<?php

namespace App\Http\Middleware;

use App\Services\SupabaseService;
use Closure;
use Illuminate\Http\Request;

class SupabaseAuth
{
    public function __construct(private SupabaseService $supabase) {}

    public function handle(Request $request, Closure $next, string $role = ''): mixed
    {
        $user = session('supabase_user');

        if (!$user) {
            return redirect('/')->with('error', 'Please log in first.');
        }

        $currentProfile = $this->supabase->adminSelect(
            'profiles',
            'id,role,first_name,last_name,email,avatar_url,grade_level,suspended_at,leaderboard_alias,show_on_leaderboard',
            ['id' => $user['id']]
        )[0] ?? null;

        if (!$currentProfile) {
            session()->forget(['supabase_token', 'supabase_user']);
            return redirect('/')->with('error', 'Your account is no longer available.');
        }

        if (!empty($currentProfile['suspended_at'])) {
            session()->forget(['supabase_token', 'supabase_user']);
            return redirect('/')->with('error', 'Your account is suspended. Contact an administrator.');
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

        // Advance scheduled starts and due dates on normal application traffic.
        // The database function is idempotent and returns an empty result before
        // the scheduling migration has been installed.
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
        view()->share(compact('notifications', 'unreadNotificationCount'));

        $response = $next($request);

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($request->isMethod('GET')
            && $response->getStatusCode() === 200
            && str_contains($contentType, 'text/html')) {
            $route = $request->route();
            $routeName = $route?->getName();
            $routeUri = $route?->uri() ?? ltrim($request->path(), '/');
            $this->supabase->audit($user, 'page.viewed', 'page', $routeName ?: $routeUri, [
                'path' => $request->getRequestUri(),
                'route' => $routeName,
                'status' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }
}
