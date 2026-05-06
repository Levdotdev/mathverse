<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SupabaseService
{
    private string $url;
    private string $anonKey;
    private string $serviceKey;

    public function __construct()
    {
        $this->url        = config('services.supabase.url');
        $this->anonKey    = config('services.supabase.anon_key');
        $this->serviceKey = config('services.supabase.service_key');
    }

    // ── Auth ──────────────────────────────────────────────

    public function signIn(string $email, string $password): array
    {
        $response = Http::withHeaders([
            'apikey'       => $this->anonKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/token?grant_type=password", [
            'email'    => $email,
            'password' => $password,
        ]);

        return $response->json();
    }

    public function signUp(
        string $email,
        string $password,
        string $role,
        string $first_name,
        string $last_name,
        ?int $grade_level = null
    ): array {
        $response = Http::withHeaders([
            'apikey'       => $this->anonKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/signup", [
            'email'    => $email,
            'password' => $password,
            'data' => [
                'role'        => $role,
                'first_name'  => $first_name,
                'last_name'   => $last_name,
                'grade_level' => $grade_level,
            ],
        ]);

        return $response->json();
    }

    public function getUserByEmail(string $email): array
    {
        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
        ])->get("{$this->url}/auth/v1/admin/users", [
            'email' => $email
        ]);

        return $response->json();
    }

    public function uploadAvatar(string $userId, $file): ?string
    {
        if (!$file) return null;

        $ext      = $file->getClientOriginalExtension();
        $mime     = $file->getMimeType();
        $content  = file_get_contents($file->getRealPath());

        $path = "avatars/{$userId}_" . time() . ".{$ext}";

        $upload = Http::withHeaders([
            'apikey'        => $this->anonKey,
            'Authorization' => "Bearer {$this->serviceKey}",
            'Content-Type'  => $mime,
            'x-upsert'      => 'true',
        ])->withBody($content, $mime)
          ->post("{$this->url}/storage/v1/object/{$path}");

        if ($upload->successful()) {
            return "{$this->url}/storage/v1/object/public/{$path}";
        }

        return null;
    }

    public function deleteAvatarByUrl(?string $avatarUrl): bool
    {
        if (!$avatarUrl) return false;

        $parts = explode('/object/public/', $avatarUrl);

        if (count($parts) < 2) return false;

        $path = $parts[1];

        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
        ])->delete("{$this->url}/storage/v1/object/{$path}");

        return $response->successful();
    }

    public function updateProfile(string $userId, array $data): array
    {
        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ])->patch("{$this->url}/rest/v1/profiles?id=eq.{$userId}", $data);

        return $response->json() ?? [];
    }

    public function resetPassword(string $email): array
    {
        $response = Http::withHeaders([
            'apikey'       => $this->anonKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/recover", [
            'email' => $email,
        ]);

        return $response->json();
    }

    // ── Database (uses JWT token from logged-in user) ─────

    public function select(string $table, string $query = '*', array $filters = [], ?string $token = null): array
    {
        $key = $token ?? $this->anonKey;

        $request = Http::withHeaders([
            'apikey'        => $this->anonKey,
            'Authorization' => "Bearer {$key}",
        ])->withQueryParameters(['select' => $query]);

        foreach ($filters as $column => $value) {
            $request = $request->withQueryParameters([$column => "eq.{$value}"]);
        }

        $response = $request->get("{$this->url}/rest/v1/{$table}");
        return $response->json() ?? [];
    }

    public function insert(string $table, array $data, ?string $token = null): array
    {
        $key = $token ?? $this->anonKey;

        $response = Http::withHeaders([
            'apikey'        => $this->anonKey,
            'Authorization' => "Bearer {$key}",
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ])->post("{$this->url}/rest/v1/{$table}", $data);

        return $response->json() ?? [];
    }

    public function update(string $table, array $data, array $filters, ?string $token = null): array
    {
        $key = $token ?? $this->anonKey;

        $request = Http::withHeaders([
            'apikey'        => $this->anonKey,
            'Authorization' => "Bearer {$key}",
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ]);

        foreach ($filters as $column => $value) {
            $request = $request->withQueryParameters([$column => "eq.{$value}"]);
        }

        $response = $request->patch("{$this->url}/rest/v1/{$table}", $data);
        return $response->json() ?? [];
    }

    public function delete(string $table, array $filters): bool
    {
        $request = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
        ]);

        foreach ($filters as $column => $value) {
            $request = $request->withQueryParameters([
                $column => "eq.{$value}"
            ]);
        }

        $response = $request->delete("{$this->url}/rest/v1/{$table}");

        return $response->successful();
    }

    // ── Admin (bypasses RLS entirely) ─────────────────────

    public function adminSelect(string $table, string $query = '*', array $filters = []): array
    {
        $params = ['select' => $query];

        foreach ($filters as $column => $value) {
            if ($column === 'order') {
                $params['order'] = $value;
            } else {
                $params[$column] = "eq.{$value}";
            }
        }

        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
        ])->get("{$this->url}/rest/v1/{$table}", $params);

        return $response->json() ?? [];
    }

    public function adminUpdate(string $table, array $data, array $filters): array
    {
        $query = http_build_query(
            array_map(fn($v) => "eq.$v", $filters)
        );

        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ])->patch("{$this->url}/rest/v1/{$table}?{$query}", $data);

        return $response->json();
    }

    public function adminDelete(string $table, array $filters): bool
    {
        $query = http_build_query(
            array_map(fn($v) => "eq.$v", $filters)
        );

        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
        ])->delete("{$this->url}/rest/v1/{$table}?{$query}");

        return $response->successful();
    }

    public function updatePassword(string $token, string $password): array
    {
        $response = Http::withHeaders([
            'apikey'        => $this->anonKey,
            'Authorization' => "Bearer {$token}",
            'Content-Type'  => 'application/json',
        ])->put("{$this->url}/auth/v1/user", [
            'password' => $password,
        ]);

        return $response->json();
    }
}