<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SupabaseAuth
{
    public function handle(Request $request, Closure $next, string $role = ''): mixed
    {
        $user = session('supabase_user');

        if (!$user) {
            return redirect('/')->with('error', 'Please log in first.');
        }

        if ($role && ($user['role'] ?? '') !== $role) {
            // Redirect to their correct dashboard
            $userRole = $user['role'] ?? '';
            if ($userRole === 'student')  return redirect('/student/dashboard');
            if ($userRole === 'teacher')  return redirect('/teacher/dashboard');
            if ($userRole === 'admin')    return redirect('/admin/dashboard');

            return redirect('/')->with('error', 'Access denied.');
        }

        return $next($request);
    }
}