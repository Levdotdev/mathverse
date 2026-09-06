<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SecuritySourceTest extends TestCase
{
    public function test_blade_templates_do_not_use_inline_event_handlers(): void
    {
        foreach ($this->bladeFiles() as $path) {
            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $this->assertDoesNotMatchRegularExpression(
                '/\son[a-z]+\s*=/i',
                $contents,
                "Inline browser event handler found in {$path}."
            );
        }
    }

    public function test_every_literal_blade_script_tag_has_a_csp_nonce(): void
    {
        foreach ($this->bladeFiles() as $path) {
            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            preg_match_all('/<script\b[^>]*>/i', $contents, $matches);

            foreach ($matches[0] as $tag) {
                $this->assertStringContainsString(
                    'nonce=',
                    strtolower($tag),
                    "Script without a CSP nonce found in {$path}: {$tag}"
                );
            }
        }
    }

    public function test_action_argument_attributes_use_html_safe_json(): void
    {
        foreach ($this->bladeFiles() as $path) {
            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $this->assertDoesNotMatchRegularExpression(
                '/data-action-args\s*=\s*([\'\"])@json\s*\(/i',
                $contents,
                "Unsafe JSON directive found in an HTML attribute in {$path}."
            );
        }
    }

    public function test_dynamic_layout_titles_are_escaped(): void
    {
        $appLayout = $this->projectPath('resources/views/layouts/app.blade.php');
        $dashboardLayout = $this->projectPath('resources/views/layouts/dashboard.blade.php');

        $this->assertStringContainsString(
            "{{ trim(\$__env->yieldContent('title', 'Academic Portal')) }}",
            (string) file_get_contents($appLayout)
        );
        $this->assertStringContainsString(
            "{{ trim(\$__env->yieldContent('mobile-title')) }}",
            (string) file_get_contents($dashboardLayout)
        );
    }

    public function test_security_definer_migration_revokes_public_execution(): void
    {
        $migration = (string) file_get_contents($this->projectPath(
            'database/supabase/2026_09_05_security_hardening.sql'
        ));

        $this->assertStringContainsString('revoke create on schema public', $migration);
        $this->assertStringContainsString('public_table_hardening', $migration);
        $this->assertStringContainsString('public_view_hardening', $migration);
        $this->assertStringContainsString('public_sequence_hardening', $migration);
        $this->assertStringContainsString('public_function_hardening', $migration);
        $this->assertStringContainsString(
            'alter table public.%I enable row level security',
            $migration
        );
        $this->assertStringContainsString(
            'revoke all privileges on table public.%I from public, anon',
            $migration
        );
        $this->assertStringContainsString(
            'alter view public.%I set (security_invoker = true)',
            $migration
        );
        $this->assertStringContainsString(
            'revoke all privileges on table public.%I from authenticated',
            $migration
        );
        $this->assertStringContainsString(
            'alter default privileges for role postgres in schema public',
            $migration
        );
        $this->assertStringContainsString(
            'revoke all privileges on tables from public, anon, authenticated',
            $migration
        );
        $this->assertStringContainsString(
            'revoke all privileges on sequence public.%I from public, anon',
            $migration
        );
        $this->assertStringContainsString(
            'revoke all privileges on sequences from public, anon, authenticated',
            $migration
        );
        $this->assertStringContainsString(
            'revoke all on function %I.%I(%s) from public, anon',
            $migration
        );
        $this->assertStringContainsString(
            'from public, anon, authenticated, service_role',
            $migration
        );
        $this->assertStringContainsString('procedure.prosecdef', $migration);
        $this->assertStringContainsString('set search_path = pg_catalog, public', $migration);
        $this->assertStringContainsString(
            'from public, anon, authenticated',
            $migration
        );
        $this->assertStringContainsString('to service_role', $migration);
    }

    public function test_profile_roles_and_sensitive_columns_are_server_controlled(): void
    {
        $migration = (string) file_get_contents($this->projectPath(
            'database/supabase/2026_09_05_security_hardening.sql'
        ));

        $this->assertStringContainsString('profiles_role_boundary', $migration);
        $this->assertStringContainsString("new.role::text not in ('student', 'pending_teacher')", $migration);
        $this->assertStringContainsString("request_role = 'service_role'", $migration);
        $this->assertStringContainsString(
            'revoke insert, delete, truncate, references, trigger on table public.profiles',
            $migration
        );
        $this->assertStringContainsString(
            'revoke update on table public.profiles from public, anon, authenticated',
            $migration
        );
        $this->assertStringContainsString('profile_column_hardening', $migration);
        $this->assertStringContainsString('server_managed_column_hardening', $migration);
        $this->assertStringContainsString('public.class_members,', $migration);
        $this->assertStringContainsString('public.quiz_session_students', $migration);
        $this->assertStringNotContainsString(
            'on table public.profiles to authenticated',
            $migration
        );
        $this->assertStringContainsString('auth_sessions_invalid_before', $migration);
        $this->assertStringContainsString(
            'auth_users_mathverse_session_invalidation',
            $migration
        );
        $this->assertStringContainsString('push_subscriptions_owner_immutable', $migration);
        $this->assertStringContainsString(
            'new.user_id is distinct from old.user_id',
            $migration
        );
    }

    public function test_password_changes_invalidate_older_application_sessions(): void
    {
        $controller = (string) file_get_contents($this->projectPath(
            'app/Http/Controllers/AuthController.php'
        ));
        $middleware = (string) file_get_contents($this->projectPath(
            'app/Http/Middleware/SupabaseAuth.php'
        ));

        $this->assertStringContainsString("'supabase_authenticated_at'", $controller);
        $this->assertStringContainsString('sessionPredatesPasswordChange(', $middleware);
        $this->assertStringContainsString('auth_sessions_invalid_before', $middleware);
    }

    public function test_reset_page_removes_credentials_from_browser_visible_urls(): void
    {
        $resetView = (string) file_get_contents($this->projectPath(
            'resources/views/auth/reset.blade.php'
        ));

        $this->assertStringContainsString("fragmentParams.get('token_hash')", $resetView);
        $this->assertStringContainsString("cleanUrl.hash = ''", $resetView);
        foreach (['token', 'token_hash', 'access_token', 'refresh_token', 'code'] as $parameter) {
            $this->assertStringContainsString(
                "'{$parameter}'",
                $resetView,
                "The reset page does not scrub {$parameter} from its visible URL."
            );
        }
    }

    public function test_production_requires_https_for_generated_application_urls(): void
    {
        $provider = (string) file_get_contents($this->projectPath(
            'app/Providers/AppServiceProvider.php'
        ));
        $appConfig = (string) file_get_contents($this->projectPath('config/app.php'));

        $this->assertStringContainsString("\$appScheme !== 'https'", $provider);
        $this->assertStringContainsString(
            'Production APP_URL must be the HTTPS MathVerse root URL.',
            $provider
        );
        $this->assertStringContainsString('Production APP_DEBUG must be false.', $provider);
        $this->assertStringContainsString("config('session.encrypt')", $provider);
        $this->assertStringContainsString("config('session.secure')", $provider);
        $this->assertStringContainsString("config('session.http_only')", $provider);
        $this->assertStringContainsString("URL::forceScheme('https')", $provider);
        $this->assertStringContainsString(
            "? 'https://mathmetaverse.space'",
            $appConfig
        );
    }

    public function test_sensitive_rate_limits_include_identity_and_network_budgets(): void
    {
        $provider = (string) file_get_contents($this->projectPath(
            'app/Providers/AppServiceProvider.php'
        ));
        $routes = (string) file_get_contents($this->projectPath('routes/web.php'));

        foreach ([
            'login-email:',
            'login-ip:',
            'register-email:',
            'register-ip:',
            'recovery-email:',
            'recovery-ip:',
            'reset-token:',
            'reset-ip:',
            "RateLimiter::for('class-join'",
            'class-join-user:',
            'class-join-ip:',
        ] as $expected) {
            $this->assertStringContainsString($expected, $provider);
        }

        $this->assertStringContainsString("middleware('throttle:class-join')", $routes);
    }

    public function test_profile_form_helper_cannot_change_server_controlled_fields(): void
    {
        $service = (string) file_get_contents($this->projectPath(
            'app/Services/SupabaseService.php'
        ));

        $this->assertStringContainsString('PROFILE_FORM_COLUMNS', $service);
        $this->assertStringContainsString(
            "!in_array(\$column, self::PROFILE_FORM_COLUMNS, true)",
            $service
        );
        $this->assertStringContainsString(
            'The profile update contains a server-controlled field.',
            $service
        );
        $this->assertStringNotContainsString('getUserByEmail', $service);
    }

    public function test_rollback_archives_are_not_exposed_through_the_data_api(): void
    {
        $migration = (string) file_get_contents($this->projectPath(
            'database/supabase/2026_09_05_security_hardening.sql'
        ));

        $this->assertStringContainsString(
            "strpos(relation.relname, 'rollback_') = 1",
            $migration
        );
        $this->assertStringContainsString(
            'alter table public.%I enable row level security',
            $migration
        );
        $this->assertStringContainsString(
            'revoke all privileges on table public.%I from public, anon, authenticated, service_role',
            $migration
        );
    }

    public function test_every_created_public_table_enables_row_level_security(): void
    {
        $sql = $this->allSupabaseSql();
        preg_match_all(
            '/create\s+table\s+(?:if\s+not\s+exists\s+)?public\.([a-z_][a-z0-9_]*)/i',
            $sql,
            $createdMatches
        );
        preg_match_all(
            '/alter\s+table\s+(?:if\s+exists\s+)?public\.([a-z_][a-z0-9_]*)\s+enable\s+row\s+level\s+security/i',
            $sql,
            $rlsMatches
        );

        $created = array_values(array_unique($createdMatches[1]));
        $rlsEnabled = array_values(array_unique($rlsMatches[1]));
        $missing = array_values(array_diff($created, $rlsEnabled));
        sort($missing);

        $this->assertSame([], $missing, 'Tables without RLS: ' . implode(', ', $missing));
    }

    public function test_every_rollback_archive_revokes_all_data_api_roles(): void
    {
        $sql = $this->allSupabaseSql();
        preg_match_all(
            '/create\s+table\s+(?:if\s+not\s+exists\s+)?public\.(rollback_[a-z0-9_]+)/i',
            $sql,
            $archiveMatches
        );

        foreach (array_unique($archiveMatches[1]) as $table) {
            $this->assertMatchesRegularExpression(
                '/revoke\s+all\s+privileges\s+on\s+table\s+public\.'
                    . preg_quote($table, '/')
                    . '\s+from\s+public,\s*anon,\s*authenticated,\s*service_role/i',
                $sql,
                "Rollback archive {$table} still has a Data API grant."
            );
        }
    }

    public function test_security_definer_allowlist_covers_every_forward_migration_function(): void
    {
        $migrationDirectory = $this->projectPath('database/supabase');
        $hardeningPath = $migrationDirectory . '/2026_09_05_security_hardening.sql';
        $hardening = (string) file_get_contents($hardeningPath);
        preg_match_all("/'([a-z_][a-z0-9_]*)'/i", $hardening, $allowlistMatches);
        $allowlist = array_values(array_unique($allowlistMatches[1]));

        $privilegedFunctions = [];
        foreach (glob($migrationDirectory . '/*.sql') ?: [] as $path) {
            if ($path === $hardeningPath || str_ends_with($path, '_rollback.sql')) {
                continue;
            }

            $contents = (string) file_get_contents($path);
            $chunks = preg_split(
                '/(?=create\s+or\s+replace\s+function\s+public\.)/i',
                $contents,
                -1,
                PREG_SPLIT_NO_EMPTY
            ) ?: [];

            foreach ($chunks as $chunk) {
                if (stripos($chunk, 'security definer') === false
                    || preg_match(
                        '/^create\s+or\s+replace\s+function\s+public\.([a-z_][a-z0-9_]*)/i',
                        ltrim($chunk),
                        $nameMatch
                    ) !== 1
                ) {
                    continue;
                }

                $privilegedFunctions[] = $nameMatch[1];
            }
        }

        $missing = array_values(array_diff(array_unique($privilegedFunctions), $allowlist));
        sort($missing);
        $this->assertSame([], $missing, 'Missing privileged functions: ' . implode(', ', $missing));
    }

    public function test_recovery_tokens_are_delivered_in_a_url_fragment(): void
    {
        $template = (string) file_get_contents($this->projectPath(
            'supabase/email-templates/reset-password.html'
        ));
        $resetView = (string) file_get_contents($this->projectPath(
            'resources/views/auth/reset.blade.php'
        ));

        $this->assertStringContainsString('#token_hash={{ .TokenHash }}', $template);
        $this->assertStringNotContainsString('?token_hash={{ .TokenHash }}', $template);
        $this->assertStringContainsString('window.location.hash', $resetView);
        $this->assertStringNotContainsString("params.get('token_hash')", $resetView);
        $this->assertStringNotContainsString("old('token')", $resetView);
        $this->assertStringContainsString('password_recovery_token', $resetView);
        $this->assertStringContainsString("cleanUrl.hash = '';", $resetView);
    }

    public function test_answer_keys_require_the_whole_quiz_session_to_be_completed(): void
    {
        $controller = (string) file_get_contents($this->projectPath(
            'app/Http/Controllers/StudentClassController.php'
        ));

        $guardPosition = strpos(
            $controller,
            "if ((\$session['status'] ?? '') !== 'completed')"
        );
        $questionLoadPosition = strpos(
            $controller,
            "adminSelect('questions', '*', ['session_id' => \$sessionId"
        );

        $this->assertNotFalse($guardPosition);
        $this->assertNotFalse($questionLoadPosition);
        $this->assertLessThan($questionLoadPosition, $guardPosition);
    }

    public function test_push_function_limits_streamed_request_bodies_before_json_decoding(): void
    {
        $function = (string) file_get_contents($this->projectPath(
            'supabase/functions/send-admin-push/index.ts'
        ));

        $this->assertStringContainsString('readLimitedText(request.body, MAX_REQUEST_BYTES)', $function);
        $this->assertStringContainsString('totalBytes > maximumBytes', $function);
        $this->assertStringNotContainsString('await request.text()', $function);
        $this->assertStringNotContainsString('await request.json()', $function);
    }

    /** @return list<string> */
    private function bladeFiles(): array
    {
        $root = $this->projectPath('resources/views');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        $files = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function allSupabaseSql(): string
    {
        $sql = '';
        foreach (glob($this->projectPath('database/supabase/*.sql')) ?: [] as $path) {
            $sql .= "\n" . (string) file_get_contents($path);
        }

        return $sql;
    }

    private function projectPath(string $path): string
    {
        return dirname(__DIR__, 2) . '/' . $path;
    }
}
