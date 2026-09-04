<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Feature;

use Dcodegroup\LaravelLoggedInboundEmail\Jobs\DefaultProcessInboundEmailJob;
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

        Bus::assertDispatched(DefaultProcessInboundEmailJob::class, function (DefaultProcessInboundEmailJob $job): bool {
            return $job->orgAlias === 'acme-corp'
                && $job->message['provider'] === 'mailgun'
                && ($job->message['subject'] ?? null) === 'Hello';
        });
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
}
