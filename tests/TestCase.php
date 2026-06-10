<?php

namespace Touqeershafi\LaravelInboundEmail\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Touqeershafi\LaravelInboundEmail\InboundEmailServiceProvider;

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
}
