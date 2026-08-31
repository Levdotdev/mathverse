<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private SupabaseService $supabase) {}

    public function read(Request $request, string $id): RedirectResponse
    {
        $user = session('supabase_user');
        $notification = $this->supabase->adminSelect(
            'notifications',
            'id,action_url,read_at',
            ['id' => $id, 'user_id' => $user['id']]
        )[0] ?? null;

        if (!$notification) {
            return back()->with('error', 'That notification is no longer available.');
        }

        if (empty($notification['read_at'])) {
            $this->supabase->adminUpdate(
                'notifications',
                ['read_at' => now()->toIso8601String()],
                ['id' => $id, 'user_id' => $user['id']]
            );
        }

        $actionUrl = (string) ($notification['action_url'] ?? '');
        if ($request->boolean('follow') && preg_match('#^/(?!/)#', $actionUrl) === 1) {
            return redirect($actionUrl);
        }

        return back();
    }

    public function readAll(): RedirectResponse
    {
        $user = session('supabase_user');
        $this->supabase->adminUpdate(
            'notifications',
            ['read_at' => now()->toIso8601String()],
            [
                'user_id' => $user['id'],
                'read_at' => ['operator' => 'is', 'value' => 'null'],
            ]
        );

        return back()->with('success', 'All notifications marked as read.');
    }
}
