<?php

use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Dcodegroup\LaravelLoggedInboundEmail\Jobs\ProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

/**
 * @return array<string, mixed>
 */
function resendEmailReceivedPayload(string $emailId = 'email-uuid-1'): array
{
    return [
        'type' => 'email.received',
        'created_at' => '2026-02-22T23:41:12.126Z',
        'data' => [
            'email_id' => $emailId,
            'from' => 'sender@example.com',
            'to' => ['recipient@example.com'],
            'subject' => 'Resend subject',
            'message_id' => '<msg@example.com>',
            'attachments' => [],
        ],
    ];
}

it('rejects when webhook secret not configured', function (): void {
    config(['inbound-email.providers.resend.webhook_secret' => '']);

    Bus::fake();

    $signed = $this->resendSignedRequest(resendEmailReceivedPayload());

    $this->call('POST', '/webhooks/inbound/resend', [], [], [], $signed['headers'], $signed['body'])
        ->assertForbidden();

    Bus::assertNothingDispatched();
    $this->assertVerificationFailedRowRecorded();
});

it('rejects invalid signature', function (): void {
    Bus::fake();

    $signed = $this->resendSignedRequest(resendEmailReceivedPayload());
    $signed['headers']['HTTP_SVIX_SIGNATURE'] = 'v1,invalid';

    $this->call('POST', '/webhooks/inbound/resend', [], [], [], $signed['headers'], $signed['body'])
        ->assertForbidden();

    Bus::assertNothingDispatched();
    $this->assertVerificationFailedRowRecorded();
});

it('acknowledges non email.received events without dispatching job', function (): void {
    Bus::fake();

    $signed = $this->resendSignedRequest([
        'type' => 'email.sent',
        'created_at' => '2026-02-22T23:41:12.126Z',
        'data' => ['email_id' => 'email-uuid-1'],
    ]);

    $this->call('POST', '/webhooks/inbound/resend', [], [], [], $signed['headers'], $signed['body'])
        ->assertOk();

    Bus::assertNothingDispatched();
    expect(InboundEmail::count())->toBe(1); // We still want to have logged the trash content
});

it('fetches email from api and dispatches job', function (): void {
    Bus::fake();

    Http::fake([
        'api.resend.com/emails/receiving/email-uuid-1' => Http::response([
            'id' => 'email-uuid-1',
            'from' => 'sender@example.com',
            'to' => ['recipient@example.com'],
            'cc' => [],
            'bcc' => [],
            'reply_to' => [],
            'subject' => 'Resend subject',
            'text' => 'Plain text body',
            'html' => '<p>HTML body</p>',
            'headers' => ['message-id' => '<msg@example.com>'],
            'message_id' => '<msg@example.com>',
            'attachments' => [],
        ], 200),
    ]);

    $signed = $this->resendSignedRequest(resendEmailReceivedPayload());

    $this->call('POST', '/webhooks/inbound/resend', [], [], [], $signed['headers'], $signed['body'])
        ->assertOk();

    Bus::assertDispatched(ProcessInboundEmailJob::class, function (ProcessInboundEmailJob $job): bool {
        $m = $job->inboundEmail;

        return $m->provider === 'resend'
            && $m->subject === 'Resend subject'
            && $m->text_content === 'Plain text body'
            && $m->html_content === '<p>HTML body</p>'
            && data_get($m->metadata, 'resend_email_id') === 'email-uuid-1';
    });

    expect(InboundEmail::count())->toBe(1);

    $inboundEmail = InboundEmail::sole();
    expect($inboundEmail->status)->toBe(InboundEmailStatus::Received)
        ->and($inboundEmail->payload)->toBe($signed['body'])
        ->and($inboundEmail->provider)->toBe('resend')
        ->and($inboundEmail->subject)->toBe('Resend subject')
        ->and($inboundEmail->text_content)->toBe('Plain text body')
        ->and($inboundEmail->html_content)->toBe('<p>HTML body</p>')
        ->and($inboundEmail->message_id)->toBe('<msg@example.com>');
});

it('fetches attachments via api', function (): void {
    Bus::fake();

    Http::fake([
        'api.resend.com/emails/receiving/email-uuid-2' => Http::response([
            'id' => 'email-uuid-2',
            'from' => 'a@example.com',
            'to' => ['b@example.com'],
            'subject' => 'With attachment',
            'text' => 'Body',
            'html' => null,
            'headers' => [],
            'attachments' => [
                [
                    'id' => 'att-1',
                    'filename' => 'doc.pdf',
                    'content_type' => 'application/pdf',
                ],
            ],
        ], 200),
        'api.resend.com/emails/receiving/email-uuid-2/attachments/att-1' => Http::response([
            'id' => 'att-1',
            'filename' => 'doc.pdf',
            'content_type' => 'application/pdf',
            'download_url' => 'https://cdn.example.test/files/doc.pdf',
        ], 200),
        'cdn.example.test/*' => Http::response('pdf-bytes', 200),
    ]);

    $signed = $this->resendSignedRequest(resendEmailReceivedPayload('email-uuid-2'));

    $this->call('POST', '/webhooks/inbound/resend', [], [], [], $signed['headers'], $signed['body'])
        ->assertOk();

    Bus::assertDispatched(ProcessInboundEmailJob::class, function (ProcessInboundEmailJob $job): bool {
        $m = $job->inboundEmail;
        $attachments = $m->attachments;

        return $m->provider === 'resend'
            && count($attachments) === 1
            && $attachments[0]->filename === 'doc.pdf';
    });
});

it('returns bad request when api fetch fails', function (): void {
    Bus::fake();

    Http::fake([
        'api.resend.com/emails/receiving/email-uuid-1' => Http::response(['error' => 'not found'], 404),
    ]);

    $signed = $this->resendSignedRequest(resendEmailReceivedPayload());

    $this->call('POST', '/webhooks/inbound/resend', [], [], [], $signed['headers'], $signed['body'])
        ->assertBadRequest();

    Bus::assertNothingDispatched();

    $inboundEmail = InboundEmail::sole();
    expect($inboundEmail->status)->toBe(InboundEmailStatus::Failed)
        ->and($inboundEmail->error)->not->toBeEmpty();
});
