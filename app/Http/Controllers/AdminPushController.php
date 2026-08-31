<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPushController extends Controller
{
    public function __construct(private SupabaseService $supabase) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|url|max:2048',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string|max:500',
            'keys.auth' => 'required|string|max:500',
        ]);
        $user = session('supabase_user');

        $saved = $this->supabase->adminUpsert('push_subscriptions', [
            'user_id' => $user['id'],
            'endpoint' => $validated['endpoint'],
            'p256dh' => $validated['keys']['p256dh'],
            'auth' => $validated['keys']['auth'],
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'updated_at' => now()->toIso8601String(),
        ], 'endpoint');

        if (!isset($saved[0]['id'])) {
            return response()->json([
                'message' => 'The browser-alert subscription could not be saved. Run the documented database updates first.',
            ], 503);
        }

        return response()->json(['message' => 'Browser alerts are enabled on this device.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|url|max:2048',
        ]);
        $user = session('supabase_user');

        $deleted = $this->supabase->adminDelete('push_subscriptions', [
            'endpoint' => $validated['endpoint'],
            'user_id' => $user['id'],
        ]);

        return $deleted
            ? response()->json(['message' => 'Browser alerts are disabled on this device.'])
            : response()->json(['message' => 'The push subscription could not be removed.'], 503);
    }
}
