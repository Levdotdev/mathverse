<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SupabaseService
{
    private const MAX_AVATAR_SIZE_BYTES = 2 * 1024 * 1024;

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

        return $response->json() ?? [];
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
        if (!$file || !$file->isValid()) return null;

        $size = $file->getSize();
        if (!is_int($size) || $size > self::MAX_AVATAR_SIZE_BYTES) return null;

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

        return $this->responseRows($response);
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
        return $this->responseRows($response);
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

        return $this->responseRows($response);
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
        return $this->responseRows($response);
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
        $params = $this->buildAdminSelectParams($query, $filters);

        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
        ])->get("{$this->url}/rest/v1/{$table}", $params);

        return $this->responseRows($response);
    }

    /**
     * Run a server-side paginated PostgREST query and return its exact total.
     * Operator filters use: ['operator' => 'ilike', 'value' => '*fractions*'].
     */
    public function adminSelectPage(
        string $table,
        string $query = '*',
        array $filters = [],
        int $limit = 24,
        int $offset = 0
    ): array {
        $params = $this->buildAdminSelectParams($query, $filters);
        $params['limit'] = max(1, min($limit, 100));
        $params['offset'] = max(0, $offset);

        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
            'Prefer'        => 'count=exact',
        ])->get("{$this->url}/rest/v1/{$table}", $params);

        $total = 0;
        $contentRange = (string) $response->header('Content-Range');
        if (preg_match('/\/(\d+)$/', $contentRange, $matches)) {
            $total = (int) $matches[1];
        }

        $rows = $this->responseRows($response);

        return [
            'data'  => $rows,
            'total' => $total,
        ];
    }

    private function buildAdminSelectParams(string $query, array $filters): array
    {
        $params = ['select' => $query];
        $allowedOperators = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'like', 'ilike', 'is', 'in', 'not.is'];

        foreach ($filters as $column => $value) {
            if (in_array($column, ['order', 'limit', 'offset'], true)) {
                $params[$column] = $value;
                continue;
            }

            if (is_array($value) && isset($value['operator'], $value['value'])) {
                $operator = (string) $value['operator'];
                if (!in_array($operator, $allowedOperators, true)) {
                    throw new \InvalidArgumentException("Unsupported Supabase filter operator: {$operator}");
                }

                $params[$column] = $operator . '.' . $value['value'];
                continue;
            }

            $params[$column] = "eq.{$value}";
        }

        return $params;
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

        return $this->responseRows($response);
    }

    public function adminInsert(string $table, array $data): array
    {
        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ])->post("{$this->url}/rest/v1/{$table}", $data);

        return $this->responseRows($response);
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

    private function responseRows($response): array
    {
        if (!$response->successful()) {
            return [];
        }

        $rows = $response->json();

        return is_array($rows) && array_is_list($rows) ? $rows : [];
    }
}
