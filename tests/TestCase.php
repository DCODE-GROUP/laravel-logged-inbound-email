<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests;

use Dcodegroup\LaravelLoggedInboundEmail\InboundEmailServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Svix\Webhook;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            InboundEmailServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('inbound-email.providers.mailgun.signing_key', 'test-mailgun-key');
        $app['config']->set('inbound-email.providers.postmark.webhook_secret', 'test-postmark-secret');
        $app['config']->set('inbound-email.providers.mailpit.base_url', 'http://127.0.0.1:8825');
        $app['config']->set('inbound-email.providers.resend.webhook_secret', 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw');
        $app['config']->set('inbound-email.providers.resend.api_key', 're_test_api_key');
        $app['config']->set('inbound-email.providers.resend.api_base_url', 'https://api.resend.com');
    }

    protected function mailgunSignature(string $timestamp, string $token, string $signingKey): string
    {
        return hash_hmac('sha256', $timestamp.$token, $signingKey);
    }

    protected function postmarkSignature(string $body, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $body, $secret, true));
    }

    /**
     * Minimal valid Mailgun webhook fields (signature verified).
     *
     * @return array<string, string>
     */
    protected function validMailgunPayload(string $timestamp, string $token, string $signature): array
    {
        return [
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => $signature,
            'subject' => 'Hello',
            'from' => 'from@example.com',
            'sender' => 'from@example.com',
            'recipient' => 'to@example.com',
            'body-plain' => 'Test body',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{body: string, headers: array<string, string>}
     */
    protected function resendSignedRequest(array $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $secret = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';
        $wh = new Webhook($secret);
        $msgId = 'msg_resend_test';
        $timestamp = (string) time();
        $signature = $wh->sign($msgId, $timestamp, $body);

        return [
            'body' => $body,
            'headers' => [
                'HTTP_SVIX_ID' => $msgId,
                'HTTP_SVIX_TIMESTAMP' => $timestamp,
                'HTTP_SVIX_SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
        ];
    }
}
