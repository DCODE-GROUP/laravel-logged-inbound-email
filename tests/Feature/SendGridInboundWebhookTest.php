<?php

use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Dcodegroup\LaravelLoggedInboundEmail\Jobs\ProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Illuminate\Support\Facades\Bus;

it('accepts request when verification key not configured', function (): void {
    config(['inbound-email.providers.sendgrid.verification_key' => null]);

    Bus::fake();

    $payload = [
        'from' => 'sender@example.com',
        'to' => 'receiver@example.com',
        'subject' => 'SG subject',
        'text' => 'Hello SendGrid',
    ];

    $this->post('/webhooks/inbound/sendgrid', $payload)->assertOk();

    Bus::assertDispatched(ProcessInboundEmailJob::class, function (ProcessInboundEmailJob $job): bool {
        $m = $job->inboundEmail;

        return $m->provider === 'sendgrid'
            && $m->subject === 'SG subject'
            && $m->text_content === 'Hello SendGrid';
    });

    expect(InboundEmail::count())->toBe(1);

    $inboundEmail = InboundEmail::sole();
    expect($inboundEmail->status)->toBe(InboundEmailStatus::Received)
        ->and($inboundEmail->payload)->toBe(json_encode($payload))
        ->and($inboundEmail->provider)->toBe('sendgrid')
        ->and($inboundEmail->subject)->toBe('SG subject')
        ->and($inboundEmail->text_content)->toBe('Hello SendGrid');
});

it('rejects when verification key set but signature headers missing', function (): void {
    config(['inbound-email.providers.sendgrid.verification_key' => 'sg-secret']);

    Bus::fake();

    $this->post('/webhooks/inbound/sendgrid', [
        'from' => 'a@b.com',
        'to' => 'c@d.com',
        'subject' => 'X',
    ])->assertForbidden();

    Bus::assertNothingDispatched();
    $this->assertVerificationFailedRowRecorded();
});

it('accepts when verification headers match', function (): void {
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

    Bus::assertDispatched(ProcessInboundEmailJob::class);
});

it('includes raw email in metadata when present', function (): void {
    config(['inbound-email.providers.sendgrid.verification_key' => null]);

    Bus::fake();

    $raw = "From: x@y.com\r\nTo: z@y.com\r\nSubject: Raw\r\n\r\nBody";

    $this->post('/webhooks/inbound/sendgrid', [
        'from' => 'x@y.com',
        'subject' => 'Raw',
        'text' => 'Body',
        'email' => $raw,
    ])->assertOk();

    Bus::assertDispatched(ProcessInboundEmailJob::class, function (ProcessInboundEmailJob $job) use ($raw): bool {
        return data_get($job->inboundEmail->metadata, 'raw_email') === $raw;
    });
});
