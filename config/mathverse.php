<?php

return [
    'deployment_commit' => env(
        'MATHVERSE_COMMIT',
        env('RENDER_GIT_COMMIT', env('RAILWAY_GIT_COMMIT_SHA', env('VERCEL_GIT_COMMIT_SHA', env('GITHUB_SHA'))))
    ),
    'health' => [
        'supabase_warning_ms' => (int) env('HEALTH_SUPABASE_WARNING_MS', 800),
        'supabase_critical_ms' => (int) env('HEALTH_SUPABASE_CRITICAL_MS', 2500),
        'scheduler_warning_seconds' => (int) env('HEALTH_SCHEDULER_WARNING_SECONDS', 180),
        'scheduler_critical_seconds' => (int) env('HEALTH_SCHEDULER_CRITICAL_SECONDS', 600),
        'audit_pending_critical_seconds' => (int) env('HEALTH_AUDIT_PENDING_CRITICAL_SECONDS', 120),
    ],
];
