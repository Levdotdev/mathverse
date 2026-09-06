<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedHostTest extends TestCase
{
    public function test_an_untrusted_host_header_is_rejected(): void
    {
        $this->withoutVite();

        $response = $this->withHeader('Host', 'attacker.example')
            ->get('/');

        $response->assertStatus(400);
    }

    public function test_the_configured_application_host_is_allowed(): void
    {
        $this->withoutVite();

        $response = $this->withHeader('Host', 'localhost')
            ->get('/');

        $response->assertOk();
    }
}
