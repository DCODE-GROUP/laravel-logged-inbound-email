<?php

namespace Touqeershafi\LaravelInboundEmail\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use Touqeershafi\LaravelInboundEmail\Jobs\DefaultProcessInboundEmailJob;
use Touqeershafi\LaravelInboundEmail\Tests\TestCase;

class PostmarkInboundWebhookTest extends TestCase
{
    /**
     * @return array{body: string, signature: string}
     */
    private function signedPostmarkBody(): array
    {
        $body = json_encode([
            'From' => 'a@example.com',
            'To' => 'b@example.com',
            'Subject' => 'Postmark subject',
            'TextBody' => 'Plain',
            'HtmlBody' => '<p>H</p>',
            'FromFull' => ['Email' => 'a@example.com', 'Name' => 'Alice'],
            'ToFull' => [['Email' => 'b@example.com', 'Name' => 'Bob']],
            'Headers' => [],
            'Attachments' => [],
            'MessageID' => 'pm-1',
        ], JSON_THROW_ON_ERROR);

        return [
            'body' => $body,
            'signature' => $this->postmarkSignature($body, 'test-postmark-secret'),
        ];
    }

    public function test_rejects_when_webhook_secret_not_configured(): void
    {
        config(['inbound-email.providers.postmark.webhook_secret' => '']);

        Bus::fake();

        $signed = $this->signedPostmarkBody();
        $this->call('POST', '/webhooks/inbound/postmark', [], [], [], [
            'HTTP_X_POSTMARK_SIGNATURE' => $signed['signature'],
            'CONTENT_TYPE' => 'application/json',
        ], $signed['body'])->assertForbidden();

        Bus::assertNothingDispatched();
    }

    public function test_rejects_invalid_signature(): void
    {
        Bus::fake();

        $signed = $this->signedPostmarkBody();
        $this->call('POST', '/webhooks/inbound/postmark', [], [], [], [
            'HTTP_X_POSTMARK_SIGNATURE' => 'dGVzdA==',
            'CONTENT_TYPE' => 'application/json',
        ], $signed['body'])->assertForbidden();

        Bus::assertNothingDispatched();
    }

    public function test_dispatches_job_with_addresses_and_bodies(): void
    {
        Bus::fake();

        $signed = $this->signedPostmarkBody();

        $this->call('POST', '/webhooks/inbound/postmark', [], [], [], [
            'HTTP_X_POSTMARK_SIGNATURE' => $signed['signature'],
            'CONTENT_TYPE' => 'application/json',
        ], $signed['body'])->assertOk();

        Bus::assertDispatched(DefaultProcessInboundEmailJob::class, function (DefaultProcessInboundEmailJob $job): bool {
            $m = $job->message;

            return ($m['provider'] ?? null) === 'postmark'
                && ($m['subject'] ?? null) === 'Postmark subject'
                && ($m['text'] ?? null) === 'Plain'
                && ($m['metadata']['postmark_message_id'] ?? null) === 'pm-1';
        });
    }
}
