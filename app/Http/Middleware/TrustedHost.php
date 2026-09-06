<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustedHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower(rtrim($request->getHost(), '.'));

        if (!$this->isAllowed($host)) {
            abort(400, 'Invalid host.');
        }

        return $next($request);
    }

    private function isAllowed(string $host): bool
    {
        foreach ($this->allowedHosts() as $allowedHost) {
            if ($host === $allowedHost) {
                return true;
            }

            if (str_starts_with($allowedHost, '*.')
                && str_ends_with($host, substr($allowedHost, 1))
                && $host !== substr($allowedHost, 2)
            ) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function allowedHosts(): array
    {
        $configured = config('app.trusted_hosts', []);
        $hosts = is_array($configured) ? $configured : [];
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '') {
            $hosts[] = $appHost;
        }

        if (!app()->isProduction()) {
            $hosts = array_merge($hosts, ['localhost', '127.0.0.1', '::1']);
        }

        return array_values(array_unique(array_filter(array_map(
            static function (mixed $value): ?string {
                $candidate = strtolower(rtrim(trim((string) $value), '.'));

                return preg_match('/^(?:\*\.)?[a-z0-9.-]+$|^::1$/', $candidate) === 1
                    ? $candidate
                    : null;
            },
            $hosts
        ))));
    }
}
