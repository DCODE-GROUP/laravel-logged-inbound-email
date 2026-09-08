<?php

use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Dcodegroup\LaravelLoggedInboundEmail\Jobs\ProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Illuminate\Support\Facades\Bus;

/**
 * @return array{body: string, signature: string}
 */
function signedPostmarkBody(): array
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
        'signature' => base64_encode(hash_hmac('sha256', $body, 'test-postmark-secret', true)),
    ];
}

it('rejects when webhook secret not configured', function (): void {
    config(['inbound-email.providers.postmark.webhook_secret' => '']);

    Bus::fake();

    $signed = signedPostmarkBody();
    $this->call('POST', '/webhooks/inbound/postmark', [], [], [], [
        'HTTP_X_POSTMARK_SIGNATURE' => $signed['signature'],
        'CONTENT_TYPE' => 'application/json',
    ], $signed['body'])->assertForbidden();

    Bus::assertNothingDispatched();
    $this->assertVerificationFailedRowRecorded();
});

it('rejects invalid signature', function (): void {
    Bus::fake();

    $signed = signedPostmarkBody();
    $this->call('POST', '/webhooks/inbound/postmark', [], [], [], [
        'HTTP_X_POSTMARK_SIGNATURE' => 'dGVzdA==',
        'CONTENT_TYPE' => 'application/json',
    ], $signed['body'])->assertForbidden();

    Bus::assertNothingDispatched();
    $this->assertVerificationFailedRowRecorded();
});

it('dispatches job with addresses and bodies', function (): void {
    Bus::fake();

    $signed = signedPostmarkBody();

    $this->call('POST', '/webhooks/inbound/postmark', [], [], [], [
        'HTTP_X_POSTMARK_SIGNATURE' => $signed['signature'],
        'CONTENT_TYPE' => 'application/json',
    ], $signed['body'])->assertOk();

    Bus::assertDispatched(ProcessInboundEmailJob::class, function (ProcessInboundEmailJob $job): bool {
        $m = $job->message;

        return ($m['provider'] ?? null) === 'postmark'
            && ($m['subject'] ?? null) === 'Postmark subject'
            && ($m['text'] ?? null) === 'Plain'
            && ($m['metadata']['postmark_message_id'] ?? null) === 'pm-1';
    });

    expect(InboundEmail::count())->toBe(1);

    $inboundEmail = InboundEmail::sole();
    expect($inboundEmail->status)->toBe(InboundEmailStatus::Received)
        ->and($inboundEmail->payload)->toBe($signed['body'])
        ->and($inboundEmail->provider)->toBe('postmark')
        ->and($inboundEmail->subject)->toBe('Postmark subject')
        ->and($inboundEmail->text_content)->toBe('Plain')
        ->and($inboundEmail->message_id)->toBe('pm-1');
});
