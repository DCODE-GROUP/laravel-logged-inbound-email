<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Feature;

use Dcodegroup\LaravelLoggedInboundEmail\Tests\TestCase;

class InboundRoutesTest extends TestCase
{
    public function test_unknown_provider_returns_404(): void
    {
        $this->post('/webhooks/inbound/unknown-provider')->assertNotFound();
    }
}
