<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class SystemHealthService
{
    public function __construct(private SupabaseService $supabase) {}

    public function snapshot(): array
    {
        $database = $this->databaseHealth();
        $migrations = $this->migrationHealth();
        $scheduler = $this->schedulerHealth();
        $deliveries = $this->deliveryHealth();
        $audit = $this->auditHealth();
        $queue = $this->queueHealth();
        $deployment = $this->deploymentHealth();
        $checks = compact('database', 'migrations', 'scheduler', 'deliveries', 'audit', 'queue', 'deployment');
        $statuses = array_column($checks, 'status');
        $overall = in_array('critical', $statuses, true) ? 'critical'
            : (in_array('warning', $statuses, true) ? 'warning' : 'healthy');

        return [
            'generated_at' => now()->utc()->toIso8601String(),
            'overall' => $overall,
            'issue_count' => count(array_filter($statuses, fn (string $status): bool => $status !== 'healthy')),
            'commit' => $this->deploymentCommit(),
        ] + $checks;
    }

    public function recordSchedulerHeartbeat(): bool
    {
        try {
            $rows = $this->supabase->adminUpsert('system_heartbeats', [
                'component' => 'laravel_scheduler',
                'status' => 'ok',
                'details' => ['commit' => $this->deploymentCommit(), 'environment' => app()->environment()],
                'checked_at' => now()->utc()->toIso8601String(),
            ], 'component');
            return isset($rows[0]['component']);
        } catch (\Throwable $exception) {
            Log::error('The scheduler heartbeat could not be recorded.', ['exception' => $exception::class]);
            return false;
        }
    }

    private function databaseHealth(): array
    {
        $started = hrtime(true);
        try {
            $result = $this->supabase->adminSelectResult('profiles', 'id', ['limit' => 1]);
            $latency = (int) round((hrtime(true) - $started) / 1_000_000);
            if ($result['error'] !== null) {
                return ['status' => 'critical', 'latency_ms' => $latency, 'message' => 'The primary data service did not accept a health query.'];
            }
            $warning = (int) config('mathverse.health.supabase_warning_ms', 800);
            $critical = (int) config('mathverse.health.supabase_critical_ms', 2500);
            $status = $latency >= $critical ? 'critical' : ($latency >= $warning ? 'warning' : 'healthy');
            return ['status' => $status, 'latency_ms' => $latency,
                'message' => $status === 'healthy' ? 'Primary data service responded normally.' : 'Primary data service latency is above the configured threshold.'];
        } catch (\Throwable) {
            return ['status' => 'critical', 'latency_ms' => null, 'message' => 'The primary data service is unreachable.'];
        }
    }

    private function migrationHealth(): array
    {
        $expected = $this->expectedMigrations();
        try {
            $result = $this->supabase->adminSelectResult('mathverse_schema_migrations', 'migration_key,applied_at', ['order' => 'migration_key.asc', 'limit' => 100]);
            if ($result['error'] !== null) {
                return ['status' => 'critical', 'expected' => count($expected), 'applied' => 0, 'missing' => $expected, 'message' => 'The migration registry is unavailable.'];
            }
            $applied = array_values(array_filter(array_map(fn (array $row): string => (string) ($row['migration_key'] ?? ''), $result['data'])));
            $missing = array_values(array_diff($expected, $applied));
            return [
                'status' => $missing === [] ? 'healthy' : 'critical',
                'expected' => count($expected),
                'applied' => count(array_intersect($expected, $applied)),
                'missing' => $missing,
                'message' => $missing === [] ? 'Every deployed SQL migration is registered.' : count($missing).' deployed migration(s) are not registered.',
            ];
        } catch (\Throwable) {
            return ['status' => 'critical', 'expected' => count($expected), 'applied' => 0, 'missing' => $expected, 'message' => 'Migration status could not be read.'];
        }
    }

    private function schedulerHealth(): array
    {
        try {
            $result = $this->supabase->adminSelectResult('system_heartbeats', 'component,status,details,checked_at', ['component' => 'laravel_scheduler', 'limit' => 1]);
            $row = $result['error'] === null ? ($result['data'][0] ?? null) : null;
            if (!is_array($row) || empty($row['checked_at'])) {
                return ['status' => 'critical', 'last_seen_at' => null, 'age_seconds' => null, 'message' => 'No scheduler heartbeat has been recorded.'];
            }
            $lastSeen = \App\Support\AppDate::parse($row['checked_at']);
            if ($lastSeen === null) {
                return ['status' => 'critical', 'last_seen_at' => null, 'age_seconds' => null, 'message' => 'The scheduler heartbeat timestamp is invalid.'];
            }
            $age = max(0, time() - $lastSeen->getTimestamp());
            $warning = (int) config('mathverse.health.scheduler_warning_seconds', 180);
            $critical = (int) config('mathverse.health.scheduler_critical_seconds', 600);
            $status = $age >= $critical ? 'critical' : ($age >= $warning ? 'warning' : 'healthy');
            return ['status' => $status, 'last_seen_at' => (string) $row['checked_at'], 'age_seconds' => $age,
                'message' => $status === 'healthy' ? 'Laravel scheduler heartbeat is current.' : 'Laravel scheduler heartbeat is stale.'];
        } catch (\Throwable) {
            return ['status' => 'critical', 'last_seen_at' => null, 'age_seconds' => null, 'message' => 'Scheduler health could not be read.'];
        }
    }

    private function deliveryHealth(): array
    {
        try {
            $staleBefore = now()->subMinutes(10)->utc()->toIso8601String();
            $results = [
                'email_failed' => $this->supabase->adminCountResult('notification_deliveries', ['channel' => 'email', 'status' => 'failed']),
                'push_failed' => $this->supabase->adminCountResult('notification_deliveries', ['channel' => 'web_push', 'status' => 'failed']),
                'pending' => $this->supabase->adminCountResult('notification_deliveries', ['status' => 'pending']),
                'stuck' => $this->supabase->adminCountResult('notification_deliveries', ['status' => 'sending', 'locked_at' => ['operator' => 'lt', 'value' => $staleBefore]]),
                'exhausted' => $this->supabase->adminCountResult('notification_deliveries', ['status' => 'failed', 'attempts' => ['operator' => 'gte', 'value' => 5]]),
            ];
            if (array_filter($results, fn (array $result): bool => $result['error'] !== null) !== []) {
                return ['status' => 'critical', 'counts' => [], 'recent_failures' => [], 'message' => 'Notification delivery counts could not be verified.'];
            }
            $counts = array_map(fn (array $result): int => (int) $result['count'], $results);
            $recent = $this->supabase->adminSelectResult('notification_deliveries', 'id,channel,event_type,status,attempts,last_error,updated_at', ['status' => 'failed', 'order' => 'updated_at.desc', 'limit' => 8]);
            if ($recent['error'] !== null) {
                return ['status' => 'critical', 'counts' => [], 'recent_failures' => [], 'message' => 'The notification delivery table is unavailable.'];
            }
            $failures = array_map(fn (array $row): array => [
                'id' => (string) ($row['id'] ?? ''),
                'channel' => (string) ($row['channel'] ?? 'unknown'),
                'event_type' => (string) ($row['event_type'] ?? 'unknown'),
                'attempts' => (int) ($row['attempts'] ?? 0),
                'error' => $this->safeOperationalError($row['last_error'] ?? null),
                'updated_at' => $row['updated_at'] ?? null,
            ], $recent['data'] ?? []);
            $failed = $counts['email_failed'] + $counts['push_failed'];
            $status = ($counts['exhausted'] > 0 || $counts['stuck'] > 0) ? 'critical' : ($failed > 0 ? 'warning' : 'healthy');
            return ['status' => $status, 'counts' => $counts, 'recent_failures' => $failures,
                'message' => $status === 'healthy' ? 'No failed email or browser-alert deliveries.' : 'Notification delivery requires attention.'];
        } catch (\Throwable) {
            return ['status' => 'critical', 'counts' => [], 'recent_failures' => [], 'message' => 'Notification delivery health could not be read.'];
        }
    }

    private function auditHealth(): array
    {
        try {
            $criticalBefore = now()->subSeconds((int) config('mathverse.health.audit_pending_critical_seconds', 120))->utc()->toIso8601String();
            $probe = $this->supabase->adminSelectResult('privileged_audit_outbox', 'id', ['limit' => 1]);
            if ($probe['error'] !== null) {
                return ['status' => 'critical', 'pending' => 0, 'failed' => 0, 'stale' => 0, 'message' => 'The durable audit outbox is unavailable.'];
            }
            $results = [
                'pending' => $this->supabase->adminCountResult('privileged_audit_outbox', ['status' => 'pending']),
                'failed' => $this->supabase->adminCountResult('privileged_audit_outbox', ['status' => 'failed']),
                'stale' => $this->supabase->adminCountResult('privileged_audit_outbox', ['status' => 'pending', 'created_at' => ['operator' => 'lt', 'value' => $criticalBefore]]),
            ];
            if (array_filter($results, fn (array $result): bool => $result['error'] !== null) !== []) {
                return ['status' => 'critical', 'pending' => 0, 'failed' => 0, 'stale' => 0, 'message' => 'Durable audit counts could not be verified.'];
            }
            $pending = (int) $results['pending']['count'];
            $failed = (int) $results['failed']['count'];
            $stale = (int) $results['stale']['count'];
            $status = $stale > 0 ? 'critical' : ($pending > 0 ? 'warning' : 'healthy');
            return ['status' => $status, 'pending' => $pending, 'failed' => $failed, 'stale' => $stale,
                'message' => $status === 'healthy' ? 'All privileged audit intents are finalized.' : 'One or more privileged audit intents have not been finalized.'];
        } catch (\Throwable) {
            return ['status' => 'critical', 'pending' => 0, 'failed' => 0, 'stale' => 0, 'message' => 'Durable audit health could not be read.'];
        }
    }

    private function queueHealth(): array
    {
        $connection = (string) config('queue.default', 'deferred');
        $driver = (string) config("queue.connections.{$connection}.driver", $connection);
        $state = [
            'connection' => $connection, 'driver' => $driver,
            'queued' => null, 'failed' => null,
            'requires_worker' => !in_array($driver, ['sync', 'deferred', 'background', 'null'], true),
        ];

        if (in_array($driver, ['sync', 'deferred', 'background'], true)) {
            return $state + ['status' => 'healthy', 'message' => match ($driver) {
                'sync' => 'Jobs run immediately; local queue tables and a worker are not required.',
                'background' => 'Jobs run in background processes; local queue tables and a worker are not required.',
                default => 'Optional jobs run after the response; local queue tables and a worker are not required. Email and push retries use the delivery outbox.',
            }];
        }

        if ($driver === 'null') {
            return $state + ['status' => 'critical', 'message' => 'The null queue driver discards jobs. Configure a working queue driver.'];
        }

        try {
            if ($driver === 'database') {
                $queueConnection = config("queue.connections.{$connection}.connection") ?: config('database.default');
                $queueTable = (string) config("queue.connections.{$connection}.table", 'jobs');
                if (!Schema::connection((string) $queueConnection)->hasTable($queueTable)) {
                    return $state + ['status' => 'warning', 'message' => 'The database queue is configured but its jobs table is missing. Configure persistent Laravel SQL storage and migrate it, or use QUEUE_CONNECTION=deferred when no SQL queue is intended.'];
                }
                $queued = (int) DB::connection((string) $queueConnection)->table($queueTable)->count();
            } else {
                $queued = Queue::connection($connection)->size((string) config("queue.connections.{$connection}.queue", 'default'));
            }
            $state['queued'] = $queued;
            $failed = null;
            if (in_array(config('queue.failed.driver'), ['database', 'database-uuids'], true)) {
                $failedTable = (string) config('queue.failed.table', 'failed_jobs');
                $failedConnection = config('queue.failed.database') ?: config('database.default');
                if (!Schema::connection((string) $failedConnection)->hasTable($failedTable)) {
                    return $state + ['status' => 'warning', 'message' => 'The active queue responded, but the configured SQL failed-job table is missing. Failure storage needs configuration.'];
                }
                $failed = (int) DB::connection((string) $failedConnection)->table($failedTable)->count();
            }
            $state['failed'] = $failed;
            $status = $failed > 0 ? 'critical' : ($queued > 100 ? 'warning' : 'healthy');
            return $state + ['status' => $status, 'message' => $failed > 0
                ? 'The Laravel failed-job table is not empty.'
                : ($queued > 100 ? 'The active queue has more than 100 waiting jobs.'
                    : ($failed === null ? 'The active queue responded. Failure records must be checked in its configured log or provider dashboard.' : 'The active queue responded and has no recorded failed jobs.'))];
        } catch (\Throwable) {
            return $state + ['status' => 'warning', 'message' => $driver === 'database'
                ? 'The database queue could not connect to Laravel SQL storage. Configure a persistent SQL database, or set QUEUE_CONNECTION=deferred if this Supabase-backed deployment does not use a SQL queue.'
                : 'The configured queue or its failure storage could not be inspected. Check the active driver and provider configuration.'];
        }
    }

    private function deploymentHealth(): array
    {
        $commit = $this->deploymentCommit();
        return ['status' => $commit === 'not provided' ? 'warning' : 'healthy', 'commit' => $commit,
            'message' => $commit === 'not provided' ? 'The deployed commit identifier is not configured.' : 'The deployed commit identifier is available for incident matching.'];
    }

    private function expectedMigrations(): array
    {
        $paths = glob(database_path('supabase/*.sql')) ?: [];
        $names = array_values(array_filter(array_map('basename', $paths), fn (string $name): bool => !str_ends_with($name, '_rollback.sql')));
        sort($names, SORT_STRING);
        return $names;
    }

    private function deploymentCommit(): string
    {
        $commit = trim((string) config('mathverse.deployment_commit', ''));
        return $commit !== '' ? mb_substr($commit, 0, 64) : 'not provided';
    }

    private function safeOperationalError(mixed $error): string
    {
        $message = trim(preg_replace('/\s+/', ' ', (string) $error) ?? '');
        $message = preg_replace('/https?:\/\/\S+/i', '[address removed]', $message) ?? $message;
        $message = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/', '[email removed]', $message) ?? $message;
        $message = preg_replace('/\b(token|secret|key|password)\s*[:=]\s*\S+/i', '$1=[removed]', $message) ?? $message;
        return mb_substr($message !== '' ? $message : 'Provider did not return an error message.', 0, 240);
    }
}
