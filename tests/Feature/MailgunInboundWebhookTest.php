<?php

use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Dcodegroup\LaravelLoggedInboundEmail\Jobs\ProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmailAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

it('rejects when signing key not configured', function (): void {
    config(['inbound-email.providers.mailgun.signing_key' => '']);

    Bus::fake();

    $ts = (string) time();
    $this->post('/webhooks/inbound/mailgun', $this->validMailgunPayload($ts, 'tok', $this->mailgunSignature($ts, 'tok', 'test-mailgun-key')))
        ->assertForbidden();

    Bus::assertNothingDispatched();
    $this->assertVerificationFailedRowRecorded();
});

it('rejects invalid signature', function (): void {
    Bus::fake();

    $ts = (string) time();
    $this->post('/webhooks/inbound/mailgun', $this->validMailgunPayload($ts, 'tok', 'not-the-signature'))
        ->assertForbidden();

    Bus::assertNothingDispatched();
    $this->assertVerificationFailedRowRecorded();
});

it('rejects stale timestamp', function (): void {
    Bus::fake();

    $old = (string) (time() - 400);
    $sig = $this->mailgunSignature($old, 'tok', 'test-mailgun-key');

    $this->post('/webhooks/inbound/mailgun', $this->validMailgunPayload($old, 'tok', $sig))
        ->assertForbidden();

    Bus::assertNothingDispatched();
    $this->assertVerificationFailedRowRecorded();
});

it('dispatches job with normalized payload', function (): void {
    Bus::fake();

    $ts = (string) time();
    $token = 'abc';
    $sig = $this->mailgunSignature($ts, $token, 'test-mailgun-key');
    $payload = $this->validMailgunPayload($ts, $token, $sig);

    $this->post('/webhooks/inbound/mailgun', $payload)
        ->assertOk();

    Bus::assertDispatched(ProcessInboundEmailJob::class, function (ProcessInboundEmailJob $job): bool {
        return $job->message['provider'] === 'mailgun'
            && ($job->message['subject'] ?? null) === 'Hello'
            && ($job->message['text'] ?? null) === 'Test body';
    });

    expect(InboundEmail::count())->toBe(1);

    $inboundEmail = InboundEmail::sole();
    expect($inboundEmail->status)->toBe(InboundEmailStatus::Received)
        ->and($inboundEmail->payload)->toBe(json_encode($payload))
        ->and($inboundEmail->provider)->toBe('mailgun')
        ->and($inboundEmail->subject)->toBe('Hello')
        ->and($inboundEmail->text_content)->toBe('Test body')
        ->and($inboundEmail->from)->toBe(['email' => 'from@example.com', 'name' => null])
        ->and($inboundEmail->to)->toBe([['email' => 'to@example.com', 'name' => null]])
        ->and($inboundEmail->received_at)->not->toBeNull()
        ->and($inboundEmail->organization_alias)->toBeNull()
        ->and($inboundEmail->tenant_id)->toBeNull();
});

it('stores attachments on the configured disk', function (): void {
    Bus::fake();
    Storage::fake('inbound-attachments');
    config(['inbound-email.attachments.disk' => 'inbound-attachments']);

    $ts = (string) time();
    $token = 'abc';
    $sig = $this->mailgunSignature($ts, $token, 'test-mailgun-key');
    $payload = $this->validMailgunPayload($ts, $token, $sig);
    $payload['attachment-count'] = '1';
    $payload['attachment-1'] = UploadedFile::fake()->createWithContent('invoice.pdf', 'pdf-file-content');

    $this->post('/webhooks/inbound/mailgun', $payload)
        ->assertOk();

    expect(InboundEmailAttachment::count())->toBe(1);

    $attachment = InboundEmailAttachment::sole();
    $inboundEmail = InboundEmail::sole();

    expect($attachment->inbound_email_id)->toBe($inboundEmail->id)
        ->and($attachment->filename)->toBe('invoice.pdf')
        ->and($attachment->disk)->toBe('inbound-attachments')
        ->and($attachment->size)->toBe(strlen('pdf-file-content'));

    Storage::disk('inbound-attachments')->assertExists($attachment->path);
    expect(Storage::disk('inbound-attachments')->get($attachment->path))->toBe('pdf-file-content');

    $storedPayload = json_decode($inboundEmail->payload, true);
    expect($storedPayload['attachment-1']['filename'])->toBe('invoice.pdf')
        ->and(base64_decode($storedPayload['attachment-1']['content_base64'], true))->toBe('pdf-file-content');
});
