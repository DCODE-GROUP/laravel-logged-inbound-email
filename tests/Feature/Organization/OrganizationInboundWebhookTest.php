<?php

use Dcodegroup\LaravelLoggedInboundEmail\Jobs\ProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Illuminate\Support\Facades\Bus;

it('dispatches job with org alias separate from message', function (): void {
    Bus::fake();

    $ts = (string) time();
    $token = 'abc';
    $sig = $this->mailgunSignature($ts, $token, 'test-mailgun-key');

    $this->post('/webhooks/inbound/acme-corp/mailgun', $this->validMailgunPayload($ts, $token, $sig))
        ->assertOk();

    Bus::assertDispatched(ProcessInboundEmailJob::class, function (ProcessInboundEmailJob $job): bool {
        return $job->orgAlias === 'acme-corp'
            && $job->message['provider'] === 'mailgun'
            && ($job->message['subject'] ?? null) === 'Hello';
    });

    $inboundEmail = InboundEmail::sole();

    expect($inboundEmail->organization_alias)->toBe('acme-corp')
        ->and($inboundEmail->tenant_id)->toBeNull();
});

it('returns 404 for an unknown provider', function (): void {
    $this->post('/webhooks/inbound/acme/unknown-provider')->assertNotFound();
});

it('returns 404 for an invalid org alias segment', function (): void {
    // Pattern requires first character alphanumeric (cannot start with hyphen).
    $this->post('/webhooks/inbound/-bad/mailgun', [])->assertNotFound();
});

it('lets the route alias win over a disagreeing plus-addressed recipient', function (): void {
    config(['inbound-email.email_based_tenancy_enabled' => true]);

    $ts = (string) time();
    $token = 'abc';
    $sig = $this->mailgunSignature($ts, $token, 'test-mailgun-key');
    $payload = $this->validMailgunPayload($ts, $token, $sig);
    $payload['recipient'] = 'other-tenant+support@example.com';

    $this->post('/webhooks/inbound/acme-corp/mailgun', $payload)->assertOk();

    expect(InboundEmail::sole()->organization_alias)->toBe('acme-corp');
});
