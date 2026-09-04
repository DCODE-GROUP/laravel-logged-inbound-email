<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Feature;

use Dcodegroup\LaravelLoggedInboundEmail\Jobs\DefaultProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Tests\TestCase;
use Illuminate\Support\Facades\Bus;

class MailgunInboundWebhookTest extends TestCase
{
    public function test_rejects_when_signing_key_not_configured(): void
    {
        config(['inbound-email.providers.mailgun.signing_key' => '']);

        Bus::fake();

        $ts = (string) time();
        $this->post('/webhooks/inbound/mailgun', $this->validMailgunPayload($ts, 'tok', $this->mailgunSignature($ts, 'tok', 'test-mailgun-key')))
            ->assertForbidden();

        Bus::assertNothingDispatched();
    }

    public function test_rejects_invalid_signature(): void
    {
        Bus::fake();

        $ts = (string) time();
        $this->post('/webhooks/inbound/mailgun', $this->validMailgunPayload($ts, 'tok', 'not-the-signature'))
            ->assertForbidden();

        Bus::assertNothingDispatched();
    }

    public function test_rejects_stale_timestamp(): void
    {
        Bus::fake();

        $old = (string) (time() - 400);
        $sig = $this->mailgunSignature($old, 'tok', 'test-mailgun-key');

        $this->post('/webhooks/inbound/mailgun', $this->validMailgunPayload($old, 'tok', $sig))
            ->assertForbidden();

        Bus::assertNothingDispatched();
    }

    public function test_dispatches_job_with_normalized_payload(): void
    {
        Bus::fake();

        $ts = (string) time();
        $token = 'abc';
        $sig = $this->mailgunSignature($ts, $token, 'test-mailgun-key');

        $this->post('/webhooks/inbound/mailgun', $this->validMailgunPayload($ts, $token, $sig))
            ->assertOk();

        Bus::assertDispatched(DefaultProcessInboundEmailJob::class, function (DefaultProcessInboundEmailJob $job): bool {
            return $job->message['provider'] === 'mailgun'
                && ($job->message['subject'] ?? null) === 'Hello'
                && ($job->message['text'] ?? null) === 'Test body';
        });
    }
}
