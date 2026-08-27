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

        foreach ($classes as &$class) {
            $class['customization'] = $this->supabase->adminSelect(
                'class_customizations',
                '*',
                ['class_id' => $class['id']]
            )[0] ?? [
                'theme_color' => '#22c55e',
                'icon' => 'chalkboard',
                'banner_pattern' => 'grid',
            ];
        }
        unset($class);

        return view('student.dashboard', compact('user', 'profile', 'rank', 'leaderboard', 'quizHistory', 'classes'));
    }

    public function updateProfile(Request $request)
    {
        if ($avatarSizeError = $this->rejectOversizedAvatar($request, '/student/dashboard?section=profile')) {
            return $avatarSizeError;
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'grade_level' => 'required|integer|between:1,6',
        ]);

        $user  = session('supabase_user');
        $token = session('supabase_token');
        $userId = $user['id'];
        $newGrade = (int) $validated['grade_level'];

        if ($newGrade !== (int) ($user['grade_level'] ?? 0)) {
            $memberships = $this->supabase->adminSelect(
                'class_members',
                'class_id',
                ['student_id' => $userId]
            );

            foreach ($memberships as $membership) {
                $class = $this->supabase->adminSelect(
                    'classes',
                    'class_name,grade_level',
                    ['id' => $membership['class_id']]
                )[0] ?? null;

                if ($class && (int) $class['grade_level'] !== $newGrade) {
                    return redirect('/student/dashboard?section=profile')->with(
                        'error',
                        "Ask your teacher to remove you from {$class['class_name']} before changing to Grade {$newGrade}."
                    );
                }
            }
        }

        // ── UPDATE BASIC INFO
        $profileUpdated = $this->supabase->update('profiles', [
            'first_name'  => $validated['first_name'],
            'last_name'   => $validated['last_name'],
            'grade_level' => $newGrade,
        ], ['id' => $userId], $token);

        if (!isset($profileUpdated[0]['id'])) {
            return redirect('/student/dashboard?section=profile')
                ->with('error', 'The profile could not be updated. Your current class grade must remain matched.');
        }

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
        $updated['first_name']  = $validated['first_name'];
        $updated['last_name']   = $validated['last_name'];
        $updated['grade_level'] = $newGrade;

        if ($avatarUrl) {
            $updated['avatar_url'] = $avatarUrl;
        }

        session(['supabase_user' => $updated]);

        return redirect('/student/dashboard?section=profile')->with('success', 'Profile updated successfully!');
    }
}
