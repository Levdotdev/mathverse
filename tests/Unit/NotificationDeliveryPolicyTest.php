<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class NotificationDeliveryPolicyTest extends TestCase
{
    private string $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $path = dirname(__DIR__, 2)
            . '/database/supabase/2026_08_31_notification_delivery_policy_followup.sql';
        $contents = file_get_contents($path);
        $this->assertNotFalse($contents, 'The notification policy migration must be readable.');
        $this->migration = $contents;
    }

    public function test_completed_auth_security_events_are_not_queued_for_web_push(): void
    {
        $this->assertMatchesRegularExpression(
            "/if new\\.type in \\([\\s\\S]*'password_changed'[\\s\\S]*'email_changed'[\\s\\S]*\\) then\\s+return new;/",
            $this->migration
        );
    }

    public function test_every_database_accepted_quiz_result_is_an_email_event(): void
    {
        $this->assertMatchesRegularExpression(
            "/when new\\.type in \\([\\s\\S]*'quiz_result_recorded'[\\s\\S]*\\) then 'email'/",
            $this->migration
        );
    }

    public function test_due_soon_window_is_thirty_minutes(): void
    {
        $this->assertStringContainsString("Quiz due within 30 minutes", $this->migration);
        $this->assertMatchesRegularExpression(
            "/due_at between[\\s\\S]{0,140}interval '30 minutes'/",
            $this->migration
        );
    }
}
