<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Feature;

use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Dcodegroup\LaravelLoggedInboundEmail\Jobs\DefaultProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\Tests\TestCase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

class MailpitInboundWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_rejects_when_webhook_secret_configured_and_header_missing(): void
    {
        config(['inbound-email.providers.mailpit.webhook_secret' => 'pit-secret']);

        Bus::fake();

        $this->postJson('/webhooks/inbound/mailpit', ['ID' => 'abc-1'])
            ->assertForbidden();

        Bus::assertNothingDispatched();
        self::assertSame(0, InboundEmail::count());
    }

    public function test_rejects_when_webhook_secret_does_not_match(): void
    {
        config(['inbound-email.providers.mailpit.webhook_secret' => 'pit-secret']);

        Bus::fake();

        $this->postJson('/webhooks/inbound/mailpit', ['ID' => 'abc-1'], [
            'HTTP_X_WEBHOOK_SECRET' => 'wrong',
        ])->assertForbidden();

        Bus::assertNothingDispatched();
        self::assertSame(0, InboundEmail::count());
    }

    public function test_fetches_message_from_api_and_dispatches_job(): void
    {
        config(['inbound-email.providers.mailpit.webhook_secret' => '']);

        Bus::fake();

        Http::fake([
            '127.0.0.1:8825/api/v1/message/mp-1' => Http::response([
                'From' => ['Email' => 'from@local.test', 'Name' => 'From'],
                'To' => [['Email' => 'to@local.test', 'Name' => 'To']],
                'Cc' => [],
                'Bcc' => [],
                'Subject' => 'Mailpit subject',
                'Text' => 'Plain text',
                'HTML' => '<p>HTML</p>',
                'Attachments' => [],
                'Headers' => [],
            ], 200),
        ]);

        $this->postJson('/webhooks/inbound/mailpit', ['ID' => 'mp-1'])->assertOk();

        Bus::assertDispatched(DefaultProcessInboundEmailJob::class, function (DefaultProcessInboundEmailJob $job): bool {
            $m = $job->message;

            return ($m['provider'] ?? null) === 'mailpit'
                && ($m['subject'] ?? null) === 'Mailpit subject'
                && ($m['text'] ?? null) === 'Plain text'
                && ($m['metadata']['mailpit_id'] ?? null) === 'mp-1';
        });

        self::assertSame(1, InboundEmail::count());

        $inboundEmail = InboundEmail::sole();
        self::assertSame(InboundEmailStatus::Received, $inboundEmail->status);
        self::assertSame(json_encode(['ID' => 'mp-1'], JSON_THROW_ON_ERROR), $inboundEmail->payload);
        self::assertSame('mailpit', $inboundEmail->provider);
        self::assertSame('Mailpit subject', $inboundEmail->subject);
        self::assertSame('Plain text', $inboundEmail->text_content);
        self::assertSame('<p>HTML</p>', $inboundEmail->html_content);
        self::assertSame(['email' => 'from@local.test', 'name' => 'From'], $inboundEmail->from);
        self::assertSame([['email' => 'to@local.test', 'name' => 'To']], $inboundEmail->to);
    }

    public function test_accepts_alternate_id_casing_in_webhook_payload(): void
    {
        config(['inbound-email.providers.mailpit.webhook_secret' => '']);

        Bus::fake();

        Http::fake([
            '127.0.0.1:8825/api/v1/message/mp-2' => Http::response([
                'From' => ['Email' => 'a@b.com'],
                'To' => [['Email' => 'c@d.com']],
                'Subject' => 'Alt',
                'Text' => 'T',
                'HTML' => '',
                'Attachments' => [],
                'Headers' => [],
            ], 200),
        ]);

        $this->postJson('/webhooks/inbound/mailpit', ['id' => 'mp-2'])->assertOk();

        Bus::assertDispatched(DefaultProcessInboundEmailJob::class);
    }
}
