<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SupabaseService;

class StudentController extends Controller
{
    public function __construct(private SupabaseService $supabase) {}

    public function index()
    {
        $user    = session('supabase_user');
        $token   = session('supabase_token');
        $profile = $user;

        // Global leaderboard
        $leaderboard = $this->supabase->adminSelect(
            'profiles', 'id,first_name,last_name,trophies,level',
            ['role' => 'student']
        );
        usort($leaderboard, fn($a, $b) => ($b['trophies'] ?? 0) - ($a['trophies'] ?? 0));

        $rank = 'N/A';
        foreach ($leaderboard as $i => $p) {
            if ($p['id'] === $user['id']) { $rank = $i + 1; break; }
        }

        // Quiz history
        $quizHistory = $this->supabase->select(
            'quiz_results',
            'correct_answers,total_questions,created_at,quiz_sessions(topic)',
            ['student_id' => $user['id']],
            $token
        );
        usort($quizHistory, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

        // Enrolled classes
        $memberships = $this->supabase->select('class_members', 'class_id', ['student_id' => $user['id']], $token);
        $classes     = [];
        foreach ($memberships as $m) {
            $result  = $this->supabase->adminSelect('classes', '*', ['id' => $m['class_id']]);
            $classes = array_merge($classes, $result);
        }

        return view('student.dashboard', compact('user', 'profile', 'rank', 'leaderboard', 'quizHistory', 'classes'));
    }

    public function joinClass(Request $request)
    {
        $user  = session('supabase_user');
        $token = session('supabase_token');

        $classes = $this->supabase->adminSelect('classes', 'id', ['join_code' => strtoupper($request->join_code)]);
        if (empty($classes)) {
            return redirect('/student/dashboard?section=class')
            ->with('error', 'Invalid Join Code.');
        }

        $this->supabase->insert('class_members', [
            'student_id' => $user['id'],
            'class_id'   => $classes[0]['id'],
        ], $token);

        return redirect('/student/dashboard?section=class')
            ->with('success', 'Successfully joined class!');
    }

    public function leaveClass(Request $request)
    {
        $user  = session('supabase_user');
        $token = session('supabase_token');

        // Supabase REST needs both filters — we'll use a raw multi-filter call
        \Illuminate\Support\Facades\Http::withHeaders([
            'apikey'        => config('services.supabase.anon_key'),
            'Authorization' => "Bearer {$token}",
        ])->withQueryParameters([
            'student_id' => "eq.{$user['id']}",
            'class_id'   => "eq.{$request->class_id}",
        ])->delete(config('services.supabase.url') . '/rest/v1/class_members');

        return redirect('/student/dashboard?section=class')
            ->with('success', 'Left the class.');
    }

    public function classRoster(string $classId)
    {
        $members = $this->supabase->adminSelect(
            'class_members',
            'student_id,profiles(first_name,last_name,level)',
            ['class_id' => $classId]
        );

        $roster = array_map(fn($m) => $m['profiles'] ?? [], $members);
        return response()->json(array_filter($roster));
    }

    public function updateProfile(Request $request)
    {
        if ($avatarSizeError = $this->rejectOversizedAvatar($request, '/student/dashboard?section=profile')) {
            return $avatarSizeError;
        }

        $user  = session('supabase_user');
        $token = session('supabase_token');
        $userId = $user['id'];

        // ── UPDATE BASIC INFO
        $this->supabase->update('profiles', [
            'first_name'  => $request->first_name,
            'last_name'   => $request->last_name,
            'grade_level' => (int) $request->grade_level,
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
        $updated['grade_level'] = $request->grade_level;

        if ($avatarUrl) {
            $updated['avatar_url'] = $avatarUrl;
        }

        session(['supabase_user' => $updated]);

        return redirect('/student/dashboard?section=profile')->with('success', 'Profile updated successfully!');
    }
}