<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Feature;

use Dcodegroup\LaravelLoggedInboundEmail\Contracts\EmailBasedTenantResolver;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\Tests\TestCase;

class ReverseLocalPartTenantResolver implements EmailBasedTenantResolver
{
    /**
     * @param  array<int, array{email: string, name: ?string}>  $recipients
     */
    public function resolve(array $recipients): ?string
    {
        $email = $recipients[0]['email'] ?? null;

        if (! is_string($email) || $email === '') {
            return null;
        }

        $localPart = strstr($email, '@', true);
        $localPart = $localPart === false ? $email : $localPart;

        return strrev($localPart);
    }
}

class EmailBasedTenantDiscoveryTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('inbound-email.email_based_tenancy_enabled', true);
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
        config(['inbound-email.email_based_tenancy_enabled' => false]);

        $ts = (string) time();
        $token = 'abc';
        $sig = $this->mailgunSignature($ts, $token, 'test-mailgun-key');
        $payload = $this->validMailgunPayload($ts, $token, $sig);
        $payload['recipient'] = 'acme-corp+support@example.com';

        $this->post('/webhooks/inbound/mailgun', $payload)->assertOk();

        self::assertNull(InboundEmail::sole()->organization_alias);
    }

    public function test_custom_tenant_resolver_config_is_used(): void
    {
        config(['inbound-email.tenant_resolver' => ReverseLocalPartTenantResolver::class]);

        $ts = (string) time();
        $token = 'abc';
        $sig = $this->mailgunSignature($ts, $token, 'test-mailgun-key');
        $payload = $this->validMailgunPayload($ts, $token, $sig);
        $payload['recipient'] = 'acme@example.com';

        $this->post('/webhooks/inbound/mailgun', $payload)->assertOk();

        self::assertSame('emca', InboundEmail::sole()->organization_alias);
    }

    public function test_tenant_resolver_config_not_implementing_contract_throws(): void
    {
        $this->withoutExceptionHandling();

        config(['inbound-email.tenant_resolver' => self::class]);

        $ts = (string) time();
        $token = 'abc';
        $sig = $this->mailgunSignature($ts, $token, 'test-mailgun-key');
        $payload = $this->validMailgunPayload($ts, $token, $sig);
        $payload['recipient'] = 'acme-corp+support@example.com';

        $this->expectException(\RuntimeException::class);

        $this->post('/webhooks/inbound/mailgun', $payload);
    }
}
