<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedHostTest extends TestCase
{
    public function test_an_untrusted_host_header_is_rejected(): void
    {
        $this->withoutVite();

        $response = $this->withServerVariables([
            'HTTP_HOST' => 'attacker.example',
            'SERVER_NAME' => 'attacker.example',
        ])->get('/');

        $response->assertStatus(400);
    }

    public function test_the_configured_application_host_is_allowed(): void
    {
        $this->withoutVite();

        $response = $this->withServerVariables([
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
        ])->get('/');

        $response->assertOk();
    }
}
