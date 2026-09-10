<?php

use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Dcodegroup\LaravelLoggedInboundEmail\Jobs\ProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('rejects when webhook secret configured and header missing', function (): void {
    config(['inbound-email.providers.mailpit.webhook_secret' => 'pit-secret']);

    Bus::fake();

    $this->postJson('/webhooks/inbound/mailpit', ['ID' => 'abc-1'])
        ->assertForbidden();

    Bus::assertNothingDispatched();
    $this->assertVerificationFailedRowRecorded();
});

it('rejects when webhook secret does not match', function (): void {
    config(['inbound-email.providers.mailpit.webhook_secret' => 'pit-secret']);

    Bus::fake();

    $this->postJson('/webhooks/inbound/mailpit', ['ID' => 'abc-1'], [
        'HTTP_X_WEBHOOK_SECRET' => 'wrong',
    ])->assertForbidden();

    Bus::assertNothingDispatched();
    $this->assertVerificationFailedRowRecorded();
});

it('fetches message from api and dispatches job', function (): void {
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

    Bus::assertDispatched(ProcessInboundEmailJob::class, function (ProcessInboundEmailJob $job): bool {
        $m = $job->inboundEmail;

        return $m->provider === 'mailpit'
            && $m->subject === 'Mailpit subject'
            && $m->text_content === 'Plain text'
            && data_get($m->metadata, 'mailpit_id') === 'mp-1';
    });

    expect(InboundEmail::count())->toBe(1);

    $inboundEmail = InboundEmail::sole();
    expect($inboundEmail->status)->toBe(InboundEmailStatus::Received)
        ->and($inboundEmail->payload)->toBe(json_encode(['ID' => 'mp-1'], JSON_THROW_ON_ERROR))
        ->and($inboundEmail->provider)->toBe('mailpit')
        ->and($inboundEmail->subject)->toBe('Mailpit subject')
        ->and($inboundEmail->text_content)->toBe('Plain text')
        ->and($inboundEmail->html_content)->toBe('<p>HTML</p>')
        ->and($inboundEmail->from)->toBe(['email' => 'from@local.test', 'name' => 'From'])
        ->and($inboundEmail->to)->toBe([['email' => 'to@local.test', 'name' => 'To']]);
});

it('accepts alternate id casing in webhook payload', function (): void {
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

    Bus::assertDispatched(ProcessInboundEmailJob::class);
});
