<?php

namespace App\Support;

final class SupabaseAuthError
{
    public static function loginMessage(array $response): string
    {
        $details = mb_strtolower(implode(' ', array_filter([
            $response['code'] ?? null,
            $response['error'] ?? null,
            $response['error_code'] ?? null,
            $response['error_description'] ?? null,
            $response['msg'] ?? null,
            $response['message'] ?? null,
        ], fn ($value): bool => is_scalar($value) && trim((string) $value) !== '')));

        if (str_contains($details, 'email_not_confirmed')
            || str_contains($details, 'email not confirmed')) {
            return 'Please confirm your email address before signing in.';
        }

        if (str_contains($details, 'invalid_credentials')
            || str_contains($details, 'invalid login credentials')) {
            return 'The email or password is incorrect.';
        }

        if (str_contains($details, 'over_request_rate_limit')
            || str_contains($details, 'too many request')
            || str_contains($details, 'rate limit')) {
            return 'Too many sign-in attempts. Please wait a moment and try again.';
        }

        if (str_contains($details, 'user_banned')
            || str_contains($details, 'user is banned')) {
            return 'This account is suspended. Contact a MathVerse administrator.';
        }

        if (str_contains($details, 'captcha')) {
            return 'The security check could not be verified. Please refresh and try again.';
        }

        return 'We could not sign you in right now. Please check your details and try again.';
    }
}
