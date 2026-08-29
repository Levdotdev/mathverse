<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdminPushService
{
    public function send(string $title, string $body, string $url, string $tag): bool
    {
        $supabaseUrl = rtrim((string) config('services.supabase.url'), '/');
        $serviceKey = (string) config('services.supabase.service_key');
        $publicKey = (string) config('services.web_push.public_key');
        $functionUrl = (string) config('services.web_push.function_url');

        if ($functionUrl === '' && $supabaseUrl !== '') {
            $functionUrl = $supabaseUrl . '/functions/v1/send-admin-push';
        }

        if ($functionUrl === '' || $serviceKey === '' || $publicKey === '') {
            return false;
        }

        try {
            $response = Http::timeout(12)->withHeaders([
                'apikey' => $serviceKey,
                'Authorization' => "Bearer {$serviceKey}",
                'Content-Type' => 'application/json',
            ])->post($functionUrl, [
                'title' => mb_substr($title, 0, 100),
                'body' => mb_substr($body, 0, 240),
                'url' => $url,
                'tag' => mb_substr($tag, 0, 100),
            ]);

            if (!$response->successful()) {
                Log::warning('Admin browser push failed.', [
                    'status' => $response->status(),
                    'response' => mb_substr($response->body(), 0, 500),
                ]);
            }

            return $response->successful();
        } catch (\Throwable $exception) {
            Log::warning('Admin browser push could not be sent.', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
