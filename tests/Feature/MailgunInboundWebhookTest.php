<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Feature;

use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Dcodegroup\LaravelLoggedInboundEmail\Jobs\DefaultProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
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
        self::assertSame(0, InboundEmail::count());
    }

    public function test_rejects_invalid_signature(): void
    {
        Bus::fake();

        $ts = (string) time();
        $this->post('/webhooks/inbound/mailgun', $this->validMailgunPayload($ts, 'tok', 'not-the-signature'))
            ->assertForbidden();

        Bus::assertNothingDispatched();
        self::assertSame(0, InboundEmail::count());
    }

    public function test_rejects_stale_timestamp(): void
    {
        Bus::fake();

        $old = (string) (time() - 400);
        $sig = $this->mailgunSignature($old, 'tok', 'test-mailgun-key');

        $this->post('/webhooks/inbound/mailgun', $this->validMailgunPayload($old, 'tok', $sig))
            ->assertForbidden();

        Bus::assertNothingDispatched();
        self::assertSame(0, InboundEmail::count());
    }

    public function test_dispatches_job_with_normalized_payload(): void
    {
        Bus::fake();

        $ts = (string) time();
        $token = 'abc';
        $sig = $this->mailgunSignature($ts, $token, 'test-mailgun-key');
        $payload = $this->validMailgunPayload($ts, $token, $sig);

        $this->post('/webhooks/inbound/mailgun', $payload)
            ->assertOk();

        Bus::assertDispatched(DefaultProcessInboundEmailJob::class, function (DefaultProcessInboundEmailJob $job): bool {
            return $job->message['provider'] === 'mailgun'
                && ($job->message['subject'] ?? null) === 'Hello'
                && ($job->message['text'] ?? null) === 'Test body';
        });

        self::assertSame(1, InboundEmail::count());

        $inboundEmail = InboundEmail::sole();
        self::assertSame(InboundEmailStatus::Received, $inboundEmail->status);
        self::assertSame(http_build_query($payload), $inboundEmail->payload);
        self::assertSame('mailgun', $inboundEmail->provider);
        self::assertSame('Hello', $inboundEmail->subject);
        self::assertSame('Test body', $inboundEmail->text_content);
        self::assertSame(['email' => 'from@example.com', 'name' => null], $inboundEmail->from);
        self::assertSame([['email' => 'to@example.com', 'name' => null]], $inboundEmail->to);
        self::assertNotNull($inboundEmail->received_at);
    }
}
