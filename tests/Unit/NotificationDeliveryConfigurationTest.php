<?php

namespace Tests\Unit;

use App\Services\AdminPushService;
use App\Services\NotificationDeliveryService;
use App\Services\SupabaseService;
use Mockery;
use Tests\TestCase;

class NotificationDeliveryConfigurationTest extends TestCase
{
    public function test_production_rejects_a_log_only_mailer(): void
    {
        $this->useProductionEnvironment();
        config([
            'mail.default' => 'log',
            'mail.from.address' => 'notifications@mathmetaverse.space',
        ]);

        $this->assertNotNull($this->service()->emailConfigurationIssue());
    }

    public function test_production_accepts_an_external_smtp_mailer(): void
    {
        $this->useProductionEnvironment();
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'notifications@mathmetaverse.space',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => 'smtp.mail-provider.test',
                'port' => 587,
            ],
        ]);

        $this->assertNull($this->service()->emailConfigurationIssue());
    }

    public function test_production_rejects_a_failover_that_can_silently_log_mail(): void
    {
        $this->useProductionEnvironment();
        config([
            'mail.default' => 'failover',
            'mail.from.address' => 'notifications@mathmetaverse.space',
            'mail.mailers.failover' => [
                'transport' => 'failover',
                'mailers' => ['smtp', 'log'],
            ],
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => 'smtp.mail-provider.test',
            ],
            'mail.mailers.log' => ['transport' => 'log'],
        ]);

        $this->assertNotNull($this->service()->emailConfigurationIssue());
    }

    private function useProductionEnvironment(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
    }

    private function service(): NotificationDeliveryService
    {
        return new NotificationDeliveryService(
            Mockery::mock(SupabaseService::class),
            Mockery::mock(AdminPushService::class),
        );
    }
}
