<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Feature;

use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Dcodegroup\LaravelLoggedInboundEmail\Jobs\DefaultProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\Tests\TestCase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SesInboundWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'inbound-email.providers.ses.allow_sns_message_without_signature' => true,
        ]);

        Http::preventStrayRequests();
    }

    /**
     * @param  array<string, mixed>  $inner
     * @return array<string, mixed>
     */
    private function snsNotificationEnvelope(array $inner): array
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

    public function test_subscription_confirmation_does_not_dispatch_job(): void
    {
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
        self::assertSame(0, InboundEmail::count());
    }

    public function test_notification_with_malformed_inner_json_marks_row_failed(): void
    {
        Bus::fake();

        $envelope = $this->snsNotificationEnvelope(['placeholder' => true]);
        $envelope['Message'] = 'not valid json';

        $this->postJson('/webhooks/inbound/ses', $envelope)->assertBadRequest();

        Bus::assertNothingDispatched();

        $inboundEmail = InboundEmail::sole();
        self::assertSame(InboundEmailStatus::Failed, $inboundEmail->status);
        self::assertNotEmpty($inboundEmail->error);
        self::assertSame('ses', $inboundEmail->provider);
    }

    public function test_notification_with_base64_content_dispatches_job(): void
    {
        Bus::fake();

        $rawMime = "From: from@example.com\r\nTo: to@example.com\r\nSubject: SES line\r\n\r\nHello SES";

        $inner = [
            'notificationType' => 'Received',
            'mail' => [
                'messageId' => 'ses-message-id-99',
            ],
            'content' => base64_encode($rawMime),
        ];

        $envelope = $this->snsNotificationEnvelope($inner);
        $this->postJson('/webhooks/inbound/ses', $envelope)->assertOk();

        Bus::assertDispatched(DefaultProcessInboundEmailJob::class, function (DefaultProcessInboundEmailJob $job): bool {
            $m = $job->message;

            return ($m['provider'] ?? null) === 'ses'
                && ($m['metadata']['ses_message_id'] ?? null) === 'ses-message-id-99'
                && str_contains((string) ($m['text'] ?? ''), 'Hello SES');
        });

        self::assertSame(1, InboundEmail::count());

        $inboundEmail = InboundEmail::sole();
        self::assertSame(InboundEmailStatus::Received, $inboundEmail->status);
        self::assertSame('ses', $inboundEmail->provider);
        self::assertStringContainsString('Hello SES', (string) $inboundEmail->text_content);
        self::assertSame('ses-message-id-99', $inboundEmail->message_id);
        self::assertNotNull($inboundEmail->received_at);
        self::assertSame(json_encode($envelope, JSON_THROW_ON_ERROR), $inboundEmail->payload);
    }

    public function test_s3_action_loads_raw_mime_from_configured_disk(): void
    {
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

        $this->postJson('/webhooks/inbound/ses', $this->snsNotificationEnvelope($inner))->assertOk();

        Bus::assertDispatched(DefaultProcessInboundEmailJob::class, function (DefaultProcessInboundEmailJob $job): bool {
            $m = $job->message;

            return ($m['provider'] ?? null) === 'ses'
                && ($m['metadata']['ses_message_id'] ?? null) === 'ses-s3-1'
                && str_contains((string) ($m['text'] ?? ''), 'S3 body');
        });
    }
}
