<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Feature;

use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Dcodegroup\LaravelLoggedInboundEmail\Jobs\DefaultProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmailAttachment;
use Dcodegroup\LaravelLoggedInboundEmail\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

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
        self::assertSame(json_encode($payload), $inboundEmail->payload);
        self::assertSame('mailgun', $inboundEmail->provider);
        self::assertSame('Hello', $inboundEmail->subject);
        self::assertSame('Test body', $inboundEmail->text_content);
        self::assertSame(['email' => 'from@example.com', 'name' => null], $inboundEmail->from);
        self::assertSame([['email' => 'to@example.com', 'name' => null]], $inboundEmail->to);
        self::assertNotNull($inboundEmail->received_at);
        self::assertNull($inboundEmail->organization_alias);
        self::assertNull($inboundEmail->tenant_id);
    }

    public function test_stores_attachments_on_the_configured_disk(): void
    {
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

        self::assertSame(1, InboundEmailAttachment::count());

        $attachment = InboundEmailAttachment::sole();
        $inboundEmail = InboundEmail::sole();

        self::assertSame($inboundEmail->id, $attachment->inbound_email_id);
        self::assertSame('invoice.pdf', $attachment->filename);
        self::assertSame('inbound-attachments', $attachment->disk);
        self::assertSame(strlen('pdf-file-content'), $attachment->size);

        Storage::disk('inbound-attachments')->assertExists($attachment->path);
        self::assertSame('pdf-file-content', Storage::disk('inbound-attachments')->get($attachment->path));
    }
}
