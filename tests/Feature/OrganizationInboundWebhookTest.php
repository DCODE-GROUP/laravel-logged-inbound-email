<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Feature;

use Dcodegroup\LaravelLoggedInboundEmail\Jobs\ProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\Tests\TestCase;
use Illuminate\Support\Facades\Bus;

class OrganizationInboundWebhookTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('inbound-email.organization_in_route', true);
    }

    public function test_dispatches_job_with_org_alias_separate_from_message(): void
    {
        Bus::fake();

        $ts = (string) time();
        $token = 'abc';
        $sig = $this->mailgunSignature($ts, $token, 'test-mailgun-key');

        $this->post('/webhooks/inbound/acme-corp/mailgun', $this->validMailgunPayload($ts, $token, $sig))
            ->assertOk();

        Bus::assertDispatched(ProcessInboundEmailJob::class, function (ProcessInboundEmailJob $job): bool {
            return $job->orgAlias === 'acme-corp'
                && $job->message['provider'] === 'mailgun'
                && ($job->message['subject'] ?? null) === 'Hello';
        });

        $inboundEmail = InboundEmail::sole();

        self::assertSame('acme-corp', $inboundEmail->organization_alias);
        self::assertNull($inboundEmail->tenant_id);
    }

    public function test_unknown_provider_returns_404(): void
    {
        $this->post('/webhooks/inbound/acme/unknown-provider')->assertNotFound();
    }

    public function test_invalid_org_alias_segment_returns_404(): void
    {
        // Pattern requires first character alphanumeric (cannot start with hyphen).
        $this->post('/webhooks/inbound/-bad/mailgun', [])->assertNotFound();
    }

    public function test_route_alias_wins_over_disagreeing_plus_addressed_recipient(): void
    {
        config(['inbound-email.email_based_tenancy_enabled' => true]);

        $ts = (string) time();
        $token = 'abc';
        $sig = $this->mailgunSignature($ts, $token, 'test-mailgun-key');
        $payload = $this->validMailgunPayload($ts, $token, $sig);
        $payload['recipient'] = 'other-tenant+support@example.com';

        $this->post('/webhooks/inbound/acme-corp/mailgun', $payload)->assertOk();

        self::assertSame('acme-corp', InboundEmail::sole()->organization_alias);
    }
}
