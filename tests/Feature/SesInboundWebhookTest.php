<?php

use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Dcodegroup\LaravelLoggedInboundEmail\Jobs\ProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config([
        'inbound-email.providers.ses.allow_sns_message_without_signature' => true,
    ]);

    Http::preventStrayRequests();
});

/**
 * @param  array<string, mixed>  $inner
 * @return array<string, mixed>
 */
function sesNotificationEnvelope(array $inner): array
{
    return [
        'Type' => 'Notification',
        'Message' => json_encode($inner, JSON_THROW_ON_ERROR),
        'MessageId' => 'sns-msg-1',
        'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:inbound',
        'Timestamp' => gmdate('c'),
        'SignatureVersion' => '1',
        'Signature' => 'test-signature',
        'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-test.pem',
    ];
}

it('does not dispatch a job for a subscription confirmation', function (): void {
    Bus::fake();

    Http::fake([
        'example.com/*' => Http::response('OK', 200),
    ]);

    $payload = [
        'Type' => 'SubscriptionConfirmation',
        'Message' => 'You have chosen to subscribe to the topic.',
        'SubscribeURL' => 'https://example.com/confirm-sub',
        'Token' => 'token',
        'TopicArn' => 'arn:aws:sns:us-east-1:123:topic',
        'MessageId' => 'sub-1',
        'Timestamp' => gmdate('c'),
        'SignatureVersion' => '1',
        'Signature' => 'sig',
        'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
    ];

    $this->postJson('/webhooks/inbound/ses', $payload)->assertOk();

    Bus::assertNothingDispatched();
    expect(InboundEmail::count())->toBe(1);
});

it('marks the row failed when the inner json is malformed', function (): void {
    Bus::fake();

    $envelope = sesNotificationEnvelope(['placeholder' => true]);
    $envelope['Message'] = 'not valid json';

    $this->postJson('/webhooks/inbound/ses', $envelope)->assertBadRequest();

    Bus::assertNothingDispatched();

    $inboundEmail = InboundEmail::sole();
    expect($inboundEmail->status)->toBe(InboundEmailStatus::Failed)
        ->and($inboundEmail->error)->not->toBeEmpty()
        ->and($inboundEmail->provider)->toBe('ses');
});

it('dispatches a job for a notification with base64 content', function (): void {
    Bus::fake();

    $rawMime = "From: from@example.com\r\nTo: to@example.com\r\nSubject: SES line\r\n\r\nHello SES";

    $inner = [
        'notificationType' => 'Received',
        'mail' => [
            'messageId' => 'ses-message-id-99',
        ],
        'content' => base64_encode($rawMime),
    ];

    $envelope = sesNotificationEnvelope($inner);
    $this->postJson('/webhooks/inbound/ses', $envelope)->assertOk();

    Bus::assertDispatched(ProcessInboundEmailJob::class, function (ProcessInboundEmailJob $job): bool {
        $m = $job->inboundEmail;

        return $m->provider === 'ses'
            && data_get($m->metadata, 'ses_message_id') === 'ses-message-id-99'
            && str_contains($m->text_content, 'Hello SES');
    });

    expect(InboundEmail::count())->toBe(1);

    $inboundEmail = InboundEmail::sole();
    expect($inboundEmail->status)->toBe(InboundEmailStatus::Received)
        ->and($inboundEmail->provider)->toBe('ses')
        ->and((string) $inboundEmail->text_content)->toContain('Hello SES')
        ->and($inboundEmail->message_id)->toBe('ses-message-id-99')
        ->and($inboundEmail->received_at)->not->toBeNull()
        ->and($inboundEmail->payload)->toBe(json_encode($envelope, JSON_THROW_ON_ERROR));
});

it('loads raw mime from the configured disk for an s3 action', function (): void {
    Bus::fake();

    Storage::fake('ses-inbound');

    config(['inbound-email.providers.ses.s3_disk' => 'ses-inbound']);

    $rawMime = "From: s3@example.com\r\nTo: recv@example.com\r\nSubject: From S3\r\n\r\nS3 body";

    Storage::disk('ses-inbound')->put('emails/key-1.eml', $rawMime);

    $inner = [
        'notificationType' => 'Received',
        'mail' => [
            'messageId' => 'ses-s3-1',
        ],
        'receipt' => [
            'action' => [
                'type' => 'S3',
                'objectKey' => 'emails/key-1.eml',
            ],
        ],
    ];

    $this->postJson('/webhooks/inbound/ses', sesNotificationEnvelope($inner))->assertOk();

    Bus::assertDispatched(ProcessInboundEmailJob::class, function (ProcessInboundEmailJob $job): bool {
        $m = $job->inboundEmail;

        return $m->provider === 'ses'
            && data_get($m->metadata, 'ses_message_id') === 'ses-s3-1'
            && str_contains($m->text_content, 'S3 body');
    });
});
