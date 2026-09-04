<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Feature;

use Dcodegroup\LaravelLoggedInboundEmail\Jobs\DefaultProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Tests\TestCase;
use Illuminate\Support\Facades\Bus;

class SendGridInboundWebhookTest extends TestCase
{
    public function test_accepts_request_when_verification_key_not_configured(): void
    {
        config(['inbound-email.providers.sendgrid.verification_key' => null]);

        Bus::fake();

        $this->post('/webhooks/inbound/sendgrid', [
            'from' => 'sender@example.com',
            'to' => 'receiver@example.com',
            'subject' => 'SG subject',
            'text' => 'Hello SendGrid',
        ])->assertOk();

        Bus::assertDispatched(DefaultProcessInboundEmailJob::class, function (DefaultProcessInboundEmailJob $job): bool {
            $m = $job->message;

            return ($m['provider'] ?? null) === 'sendgrid'
                && ($m['subject'] ?? null) === 'SG subject'
                && ($m['text'] ?? null) === 'Hello SendGrid';
        });
    }

    public function test_rejects_when_verification_key_set_but_signature_headers_missing(): void
    {
        config(['inbound-email.providers.sendgrid.verification_key' => 'sg-secret']);

        Bus::fake();

        $this->post('/webhooks/inbound/sendgrid', [
            'from' => 'a@b.com',
            'to' => 'c@d.com',
            'subject' => 'X',
        ])->assertForbidden();

        Bus::assertNothingDispatched();
    }

    public function test_accepts_when_verification_headers_match(): void
    {
        $key = 'sg-verify-key';
        config(['inbound-email.providers.sendgrid.verification_key' => $key]);

        Bus::fake();

        $body = 'from=a%40b.com&to=c%40d.com&subject=Signed';
        $timestamp = (string) time();
        $payload = $timestamp.$body;
        $sig = base64_encode(hash_hmac('sha256', $payload, $key, true));

        $this->call('POST', '/webhooks/inbound/sendgrid', [], [], [], [
            'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_SIGNATURE' => $sig,
            'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_TIMESTAMP' => $timestamp,
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ], $body)->assertOk();

        Bus::assertDispatched(DefaultProcessInboundEmailJob::class);
    }

    public function test_includes_raw_email_in_metadata_when_present(): void
    {
        config(['inbound-email.providers.sendgrid.verification_key' => null]);

        Bus::fake();

        $raw = "From: x@y.com\r\nTo: z@y.com\r\nSubject: Raw\r\n\r\nBody";

        $this->post('/webhooks/inbound/sendgrid', [
            'from' => 'x@y.com',
            'subject' => 'Raw',
            'text' => 'Body',
            'email' => $raw,
        ])->assertOk();

        Bus::assertDispatched(DefaultProcessInboundEmailJob::class, function (DefaultProcessInboundEmailJob $job) use ($raw): bool {
            return ($job->message['metadata']['raw_email'] ?? null) === $raw;
        });
    }
}
