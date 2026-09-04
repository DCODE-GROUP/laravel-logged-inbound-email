<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Feature;

use Dcodegroup\LaravelLoggedInboundEmail\Jobs\DefaultProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Tests\TestCase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

class ResendInboundWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * @return array<string, mixed>
     */
    private function emailReceivedPayload(string $emailId = 'email-uuid-1'): array
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

    public function test_rejects_when_webhook_secret_not_configured(): void
    {
        config(['inbound-email.providers.resend.webhook_secret' => '']);

        Bus::fake();

        $signed = $this->resendSignedRequest($this->emailReceivedPayload());

        $this->call('POST', '/webhooks/inbound/resend', [], [], [], $signed['headers'], $signed['body'])
            ->assertForbidden();

        Bus::assertNothingDispatched();
    }

    public function test_rejects_invalid_signature(): void
    {
        Bus::fake();

        $signed = $this->resendSignedRequest($this->emailReceivedPayload());
        $signed['headers']['HTTP_SVIX_SIGNATURE'] = 'v1,invalid';

        $this->call('POST', '/webhooks/inbound/resend', [], [], [], $signed['headers'], $signed['body'])
            ->assertForbidden();

        Bus::assertNothingDispatched();
    }

    public function test_acknowledges_non_email_received_events_without_dispatching_job(): void
    {
        Bus::fake();

        $signed = $this->resendSignedRequest([
            'type' => 'email.sent',
            'created_at' => '2026-02-22T23:41:12.126Z',
            'data' => ['email_id' => 'email-uuid-1'],
        ]);

        $this->call('POST', '/webhooks/inbound/resend', [], [], [], $signed['headers'], $signed['body'])
            ->assertOk();

        Bus::assertNothingDispatched();
    }

    public function test_fetches_email_from_api_and_dispatches_job(): void
    {
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

        $signed = $this->resendSignedRequest($this->emailReceivedPayload());

        $this->call('POST', '/webhooks/inbound/resend', [], [], [], $signed['headers'], $signed['body'])
            ->assertOk();

        Bus::assertDispatched(DefaultProcessInboundEmailJob::class, function (DefaultProcessInboundEmailJob $job): bool {
            $m = $job->message;

            return ($m['provider'] ?? null) === 'resend'
                && ($m['subject'] ?? null) === 'Resend subject'
                && ($m['text'] ?? null) === 'Plain text body'
                && ($m['html'] ?? null) === '<p>HTML body</p>'
                && ($m['metadata']['resend_email_id'] ?? null) === 'email-uuid-1';
        });
    }

    public function test_fetches_attachments_via_api(): void
    {
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

        $signed = $this->resendSignedRequest($this->emailReceivedPayload('email-uuid-2'));

        $this->call('POST', '/webhooks/inbound/resend', [], [], [], $signed['headers'], $signed['body'])
            ->assertOk();

        Bus::assertDispatched(DefaultProcessInboundEmailJob::class, function (DefaultProcessInboundEmailJob $job): bool {
            $m = $job->message;
            $attachments = $m['attachments'] ?? [];

            return ($m['provider'] ?? null) === 'resend'
                && count($attachments) === 1
                && ($attachments[0]['filename'] ?? null) === 'doc.pdf'
                && ($attachments[0]['content_base64'] ?? null) === base64_encode('pdf-bytes');
        });
    }

    public function test_returns_bad_request_when_api_fetch_fails(): void
    {
        Bus::fake();

        Http::fake([
            'api.resend.com/emails/receiving/email-uuid-1' => Http::response(['error' => 'not found'], 404),
        ]);

        $signed = $this->resendSignedRequest($this->emailReceivedPayload());

        $this->call('POST', '/webhooks/inbound/resend', [], [], [], $signed['headers'], $signed['body'])
            ->assertBadRequest();

        Bus::assertNothingDispatched();
    }
}
