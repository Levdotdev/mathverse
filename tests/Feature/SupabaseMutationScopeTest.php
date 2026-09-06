<?php

namespace Tests\Feature;

use App\Services\SupabaseService;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class SupabaseMutationScopeTest extends TestCase
{
    public function test_production_rejects_a_plaintext_supabase_endpoint(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config()->set('services.supabase.url', 'http://project.supabase.co');
        config()->set('services.supabase.anon_key', str_repeat('a', 32));
        config()->set('services.supabase.service_key', str_repeat('b', 32));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('valid HTTPS project URL');

        new SupabaseService();
    }

    public function test_service_role_delete_refuses_an_empty_filter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Refusing an unscoped Supabase mutation.');

        app(SupabaseService::class)->adminDelete('profiles', []);
    }

    public function test_service_role_update_refuses_query_control_parameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('explicit column filters');

        app(SupabaseService::class)->adminUpdate('profiles', ['role' => 'admin'], [
            'limit' => 1,
        ]);
    }

    public function test_in_filter_rejects_postgrest_expression_injection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Supabase in-filter value.');

        app(SupabaseService::class)->adminSelect('profiles', 'id', [
            'id' => [
                'operator' => 'in',
                'value' => '(safe-id),or(role.eq.admin)',
            ],
        ]);
    }

    public function test_logical_filter_rejects_content_outside_its_clause_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Supabase logical filter expression.');

        app(SupabaseService::class)->adminSelect('profiles', 'id', [
            'or' => '(first_name.ilike.*sam*),role.eq.admin',
        ]);
    }

    public function test_expected_registry_logical_filter_remains_supported(): void
    {
        config()->set('services.supabase.url', 'https://project.supabase.co');
        Http::fake([
            'project.supabase.co/*' => Http::response([], 200),
        ]);

        $rows = app(SupabaseService::class)->adminSelect('profiles', 'id', [
            'or' => '(first_name.ilike.*Sam Lee*,last_name.ilike.*Sam Lee*,email.ilike.*Sam Lee*)',
        ]);

        $this->assertSame([], $rows);
        Http::assertSentCount(1);
    }

    public function test_avatar_cleanup_cannot_delete_another_users_file(): void
    {
        config()->set('services.supabase.url', 'https://project.supabase.co');
        Http::fake();

        $deleted = app(SupabaseService::class)->deleteAvatarByUrl(
            'https://project.supabase.co/storage/v1/object/public/avatars/'
                . '11111111-1111-4111-8111-111111111111_legacy123.png',
            '22222222-2222-4222-8222-222222222222'
        );

        $this->assertFalse($deleted);
        Http::assertNothingSent();
    }

    public function test_profile_form_helper_rejects_server_controlled_columns_before_http(): void
    {
        config()->set('services.supabase.url', 'https://project.supabase.co');
        Http::fake();

        try {
            app(SupabaseService::class)->updateProfile(
                '11111111-1111-4111-8111-111111111111',
                ['role' => 'admin']
            );
            $this->fail('A server-controlled profile field was accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The profile update contains a server-controlled field.',
                $exception->getMessage()
            );
        }

        Http::assertNothingSent();
    }

    public function test_auth_account_update_rejects_an_unsafe_bearer_token_before_http(): void
    {
        config()->set('services.supabase.url', 'https://project.supabase.co');
        Http::fake();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid authentication token.');

        try {
            app(SupabaseService::class)->updateAuthUser(
                "token\r\nX-Injected: value",
                ['password' => 'Password1!']
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_auth_account_update_rejects_unsupported_fields_before_http(): void
    {
        config()->set('services.supabase.url', 'https://project.supabase.co');
        Http::fake();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported authentication account update.');

        try {
            app(SupabaseService::class)->updateAuthUser(
                'header.payload.signature',
                ['app_metadata' => ['role' => 'admin']]
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_data_api_delete_uses_the_users_token_instead_of_the_service_role(): void
    {
        config()->set('services.supabase.url', 'https://project.supabase.co');
        config()->set('services.supabase.anon_key', 'public-anon-key');
        config()->set('services.supabase.service_key', 'private-service-role-key');
        Http::fake([
            'project.supabase.co/*' => Http::response([], 200),
        ]);

        $deleted = app(SupabaseService::class)->delete(
            'quizzes',
            ['id' => '11111111-1111-4111-8111-111111111111'],
            'user-access-token'
        );

        $this->assertTrue($deleted);
        Http::assertSent(function ($request): bool {
            return $request->method() === 'DELETE'
                && $request->hasHeader('apikey', 'public-anon-key')
                && $request->hasHeader('Authorization', 'Bearer user-access-token');
        });
    }

    public function test_data_api_methods_reject_an_unsafe_bearer_token_before_http(): void
    {
        config()->set('services.supabase.url', 'https://project.supabase.co');
        config()->set('services.supabase.anon_key', 'public-anon-key');
        Http::fake();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Data API authentication token.');

        try {
            app(SupabaseService::class)->delete(
                'quizzes',
                ['id' => '11111111-1111-4111-8111-111111111111'],
                "token\r\nX-Injected: value"
            );
        } finally {
            Http::assertNothingSent();
        }
    }
}
