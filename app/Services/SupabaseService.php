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
        ?int $grade_level = null,
        ?string $redirectTo = null
    ): array {
        $request = Http::withHeaders([
            'apikey'       => $this->anonKey,
            'Content-Type' => 'application/json',
        ]);
        if ($redirectTo !== null) {
            $request = $request->withQueryParameters(['redirect_to' => $redirectTo]);
        }

        $response = $request->post("{$this->url}/auth/v1/signup", [
            'email'    => $email,
            'password' => $password,
            'data' => [
                'role'        => $role,
                'first_name'  => $first_name,
                'last_name'   => $last_name,
                'grade_level' => $grade_level,
            ],
        ]);

        return $this->authResponse($response);
    }

    public function getUserByEmail(string $email): array
    {
        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
        ])->get("{$this->url}/auth/v1/admin/users", [
            'email' => $email
        ]);

        return $response->json() ?? [];
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

    public function resetPassword(string $email, ?string $redirectTo = null): array
    {
        $request = Http::withHeaders([
            'apikey'       => $this->anonKey,
            'Content-Type' => 'application/json',
        ]);
        if ($redirectTo !== null) {
            $request = $request->withQueryParameters(['redirect_to' => $redirectTo]);
        }

        $response = $request->post("{$this->url}/auth/v1/recover", [
            'email' => $email,
        ]);

        return $this->authResponse($response);
    }

    public function updateAuthUser(
        string $token,
        array $attributes,
        ?string $redirectTo = null
    ): array {
        $request = Http::withHeaders([
            'apikey' => $this->anonKey,
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
        ]);
        if ($redirectTo !== null) {
            $request = $request->withQueryParameters(['redirect_to' => $redirectTo]);
        }

        return $this->authResponse(
            $request->put("{$this->url}/auth/v1/user", $attributes)
        );
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
        return $this->adminSelectResult($table, $query, $filters)['data'];
    }

    public function adminSelectResult(string $table, string $query = '*', array $filters = []): array
    {
        $params = $this->buildAdminSelectParams($query, $filters);

        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
        ])->get("{$this->url}/rest/v1/{$table}", $params);

        if (!$response->successful()) {
            $message = $this->databaseErrorMessage($response);

            return [
                'data' => [],
                'error' => $message ?: "Database query on {$table} failed with status {$response->status()}.",
                'status' => $response->status(),
            ];
        }

        return [
            'data' => $this->responseRows($response),
            'error' => null,
            'status' => $response->status(),
        ];
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

    /**
     * Paginate rows with non-null priority values first without requiring a
     * generated database column. Each partition keeps true server-side
     * pagination and uses the supplied secondary ordering.
     */
    public function adminSelectPrioritizedPage(
        string $table,
        string $query,
        array $filters,
        string $priorityColumn,
        string $secondaryOrder,
        int $limit = 24,
        int $offset = 0
    ): array {
        unset($filters['order'], $filters['limit'], $filters['offset']);

        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $priorityFilters = $filters;
        $priorityFilters[$priorityColumn] = ['operator' => 'not.is', 'value' => 'null'];
        $priorityFilters['order'] = $secondaryOrder;

        $remainingFilters = $filters;
        $remainingFilters[$priorityColumn] = ['operator' => 'is', 'value' => 'null'];
        $remainingFilters['order'] = $secondaryOrder;

        $priorityTotal = $this->adminCount($table, $priorityFilters);
        $remainingTotal = $this->adminCount($table, $remainingFilters);
        $rows = [];

        if ($offset < $priorityTotal) {
            $priorityLimit = min($limit, $priorityTotal - $offset);
            $priorityPage = $this->adminSelectPage(
                $table,
                $query,
                $priorityFilters,
                $priorityLimit,
                $offset
            );
            $rows = $priorityPage['data'];
        }

        $remainingSlots = $limit - count($rows);
        if ($remainingSlots > 0) {
            $remainingOffset = max(0, $offset - $priorityTotal);
            if ($remainingOffset < $remainingTotal) {
                $remainingPage = $this->adminSelectPage(
                    $table,
                    $query,
                    $remainingFilters,
                    $remainingSlots,
                    $remainingOffset
                );
                $rows = array_merge($rows, $remainingPage['data']);
            }
        }

        return [
            'data' => $rows,
            'total' => $priorityTotal + $remainingTotal,
        ];
    }

    public function adminCount(string $table, array $filters = []): int
    {
        $params = $this->buildAdminSelectParams('id', $filters);
        $params['limit'] = 1;

        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
            'Prefer'        => 'count=exact',
        ])->get("{$this->url}/rest/v1/{$table}", $params);

        if (!$response->successful()) {
            return 0;
        }

        $contentRange = (string) $response->header('Content-Range');
        return preg_match('/\/(\d+)$/', $contentRange, $matches)
            ? (int) $matches[1]
            : 0;
    }

    private function buildAdminSelectParams(string $query, array $filters): array
    {
        $params = ['select' => $query];
        $allowedOperators = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'like', 'ilike', 'is', 'in', 'not.is'];

        foreach ($filters as $column => $value) {
            if (in_array($column, ['order', 'limit', 'offset', 'or', 'and'], true)) {
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
        $query = $this->buildAdminSelectParams('*', $filters);
        unset($query['select'], $query['order'], $query['limit'], $query['offset']);

        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ])->withQueryParameters($query)
          ->patch("{$this->url}/rest/v1/{$table}", $data);

        return $this->responseRows($response);
    }

    public function adminInsert(string $table, array $data): array
    {
        return $this->adminInsertResult($table, $data)['data'];
    }

    public function adminInsertResult(string $table, array $data): array
    {
        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ])->post("{$this->url}/rest/v1/{$table}", $data);

        if (!$response->successful()) {
            $message = $this->databaseErrorMessage($response);

            return [
                'data' => [],
                'error' => $message ?: "Database insert into {$table} failed with status {$response->status()}.",
                'status' => $response->status(),
            ];
        }

        return [
            'data' => $this->responseRows($response),
            'error' => null,
            'status' => $response->status(),
        ];
    }

    public function adminDeleteResult(string $table, array $filters): array
    {
        $query = http_build_query(
            array_map(fn($v) => "eq.$v", $filters)
        );

        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
            'Prefer'        => 'return=representation',
        ])->delete("{$this->url}/rest/v1/{$table}?{$query}");

        if (!$response->successful()) {
            return [
                'data' => [],
                'error' => $this->databaseErrorMessage($response)
                    ?: "Database delete from {$table} failed with status {$response->status()}.",
                'status' => $response->status(),
            ];
        }

        return [
            'data' => $this->responseRows($response),
            'error' => null,
            'status' => $response->status(),
        ];
    }

    public function adminUpsert(string $table, array $data, string $onConflict): array
    {
        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
            'Content-Type'  => 'application/json',
            'Prefer'        => 'resolution=merge-duplicates,return=representation',
        ])->post("{$this->url}/rest/v1/{$table}?on_conflict=" . urlencode($onConflict), $data);

        return $this->responseRows($response);
    }

    public function adminRpc(string $function, array $arguments = []): array
    {
        return $this->adminRpcResult($function, $arguments)['data'];
    }

    public function adminRpcResult(string $function, array $arguments = []): array
    {
        $response = Http::withHeaders([
            'apikey'        => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
            'Content-Type'  => 'application/json',
        ])->post("{$this->url}/rest/v1/rpc/{$function}", $arguments);

        if (!$response->successful()) {
            $message = $this->databaseErrorMessage($response);

            return [
                'data' => [],
                'error' => $message ?: "Database function {$function} failed with status {$response->status()}.",
                'status' => $response->status(),
            ];
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            return [
                'data' => [],
                'error' => "Database function {$function} returned an invalid response.",
                'status' => $response->status(),
            ];
        }

        return [
            'data' => array_is_list($payload) ? $payload : [$payload],
            'error' => null,
            'status' => $response->status(),
        ];
    }

    public function setAuthUserSuspended(string $userId, bool $suspended): bool
    {
        $response = Http::withHeaders([
            'apikey' => $this->serviceKey,
            'Authorization' => "Bearer {$this->serviceKey}",
            'Content-Type' => 'application/json',
        ])->put("{$this->url}/auth/v1/admin/users/{$userId}", [
            'ban_duration' => $suspended ? '876000h' : 'none',
        ]);

        return $response->successful();
    }

    public function audit(
        array $actor,
        string $action,
        string $targetType,
        string|int|null $targetId = null,
        array $metadata = []
    ): bool {
        $created = $this->adminInsert('audit_logs', [
            'actor_id' => $actor['id'] ?? null,
            'actor_role' => $actor['role'] ?? null,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId === null ? null : (string) $targetId,
            'metadata' => $metadata,
        ]);

        return isset($created[0]['id']);
    }

    public function adminDelete(string $table, array $filters): bool
    {
        return $this->adminDeleteResult($table, $filters)['error'] === null;
    }

    public function updatePassword(string $token, string $password): array
    {
        return $this->updateAuthUser($token, ['password' => $password]);
    }

    private function authResponse($response): array
    {
        $data = $response->json();
        $data = is_array($data) ? $data : [];

        return [
            'successful' => $response->successful(),
            'data' => $data,
            'error' => $response->successful()
                ? null
                : ($data['error_description'] ?? $data['msg'] ?? $data['message'] ?? $data['error'] ?? 'Authentication request failed.'),
            'status' => $response->status(),
        ];
    }

    private function responseRows($response): array
    {
        if (!$response->successful()) {
            return [];
        }

        $rows = $response->json();

        return is_array($rows) && array_is_list($rows) ? $rows : [];
    }

    private function databaseErrorMessage($response): ?string
    {
        $error = $response->json();
        if (!is_array($error)) {
            $body = trim((string) $response->body());
            return $body !== '' ? $body : null;
        }

        $parts = array_values(array_unique(array_filter(array_map(
            fn ($value): string => trim((string) $value),
            [
                $error['message'] ?? null,
                $error['details'] ?? null,
                $error['hint'] ?? null,
                $error['code'] ?? null,
            ]
        ))));

        return $parts !== [] ? implode(' | ', $parts) : null;
    }
}
