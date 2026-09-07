<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Feature;

use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\Tests\TestCase;

class PlusAddressingTenantDiscoveryTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('inbound-email.tenant_plus_addressing_enabled', true);
    }

    public function test_tenant_identifier_is_parsed_from_plus_addressed_recipient(): void
    {
        $ts = (string) time();
        $token = 'abc';
        $sig = $this->mailgunSignature($ts, $token, 'test-mailgun-key');
        $payload = $this->validMailgunPayload($ts, $token, $sig);
        $payload['recipient'] = 'acme-corp+support@example.com';

        $this->post('/webhooks/inbound/mailgun', $payload)->assertOk();

        self::assertSame('acme-corp', InboundEmail::sole()->organization_alias);
    }

    public function test_recipient_without_plus_segment_leaves_alias_null(): void
    {
        $ts = (string) time();
        $token = 'abc';
        $sig = $this->mailgunSignature($ts, $token, 'test-mailgun-key');

        $this->post('/webhooks/inbound/mailgun', $this->validMailgunPayload($ts, $token, $sig))->assertOk();

        self::assertNull(InboundEmail::sole()->organization_alias);
    }

    public function test_disabled_by_default_leaves_alias_null_even_with_plus_addressed_recipient(): void
    {
        config(['inbound-email.tenant_plus_addressing_enabled' => false]);

        $ts = (string) time();
        $token = 'abc';
        $sig = $this->mailgunSignature($ts, $token, 'test-mailgun-key');
        $payload = $this->validMailgunPayload($ts, $token, $sig);
        $payload['recipient'] = 'acme-corp+support@example.com';

        $this->post('/webhooks/inbound/mailgun', $payload)->assertOk();

        self::assertNull(InboundEmail::sole()->organization_alias);
    }
}
