<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdminPushService
{
    public function send(string $title, string $body, string $url, string $tag): bool
    {
        // Keeping this call recipient-free preserves the existing broadcast to
        // every administrator for teacher registrations and quiz reports.
        return $this->dispatch($title, $body, $url, $tag);
    }

    public function sendToUser(
        string $userId,
        string $title,
        string $body,
        string $url,
        string $tag
    ): bool
    {
        return $this->dispatch($title, $body, $url, $tag, [$userId]);
    }

    private function dispatch(
        string $title,
        string $body,
        string $url,
        string $tag,
        ?array $userIds = null
    ): bool
    {
        $supabaseUrl = rtrim((string) config('services.supabase.url'), '/');
        $serviceKey = (string) config('services.supabase.service_key');
        $publicKey = (string) config('services.web_push.public_key');
        $functionUrl = (string) config('services.web_push.function_url');
        $authSecret = (string) config('services.web_push.auth_secret');

        if ($functionUrl === '' && $supabaseUrl !== '') {
            $functionUrl = $supabaseUrl . '/functions/v1/send-admin-push';
        }

        if ($functionUrl === '' || $serviceKey === '' || $publicKey === '' || $authSecret === '') {
            Log::warning('MathVerse browser push is not fully configured.');
            return false;
        }

        try {
            $payload = [
                'title' => mb_substr($title, 0, 100),
                'body' => mb_substr($body, 0, 240),
                'url' => $url,
                'tag' => mb_substr($tag, 0, 100),
            ];
            if ($userIds !== null) {
                $payload['user_ids'] = array_values(array_unique($userIds));
            }

            $response = Http::timeout(12)->withHeaders([
                'apikey' => $serviceKey,
                'X-MathVerse-Push-Secret' => $authSecret,
                'Content-Type' => 'application/json',
            ])->post($functionUrl, $payload);

            if (!$response->successful()) {
                Log::warning('MathVerse browser push failed.', [
                    'status' => $response->status(),
                    'response' => mb_substr($response->body(), 0, 500),
                ]);
            } else {
                $result = $response->json();
                if (is_array($result) && (int) ($result['failed'] ?? 0) > 0) {
                    Log::warning('One or more MathVerse browser pushes were rejected.', [
                        'sent' => (int) ($result['sent'] ?? 0),
                        'failed' => (int) ($result['failed'] ?? 0),
                        'expired' => (int) ($result['expired'] ?? 0),
                        'total' => (int) ($result['total'] ?? 0),
                    ]);
                }
            }

            $result = $response->json();

            return $response->successful()
                && (!is_array($result) || (int) ($result['failed'] ?? 0) === 0);
        } catch (\Throwable $exception) {
            Log::warning('MathVerse browser push could not be sent.', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
