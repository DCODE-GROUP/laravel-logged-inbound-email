<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Support;

use OpenSSLAsymmetricKey;

/**
 * Verifies Amazon SNS message signatures (SignatureVersion 1, SHA1withRSA).
 *
 * @see https://docs.aws.amazon.com/sns/latest/dg/sns-verify-signature-of-message.html
 */
final class SnsMessageVerifier
{
    /**
     * @param  array<string, mixed>  $message
     */
    public function verify(array $message): bool
    {
        if (($message['SignatureVersion'] ?? null) !== '1') {
            return false;
        }

        $signature = $message['Signature'] ?? null;
        $signingCertUrl = $message['SigningCertURL'] ?? null;

        if (! is_string($signature) || ! is_string($signingCertUrl)) {
            return false;
        }

        if (! $this->isValidSigningCertUrl($signingCertUrl)) {
            return false;
        }

        $cert = $this->downloadCertificate($signingCertUrl);
        if ($cert === null) {
            return false;
        }

        $stringToSign = $this->buildStringToSign($message);
        if ($stringToSign === null) {
            return false;
        }

        $decoded = base64_decode($signature, true);
        if ($decoded === false) {
            return false;
        }

        $pubKey = openssl_pkey_get_public($cert);
        if (! $pubKey instanceof OpenSSLAsymmetricKey) {
            return false;
        }

        return openssl_verify($stringToSign, $decoded, $pubKey, OPENSSL_ALGO_SHA1) === 1;
    }

    private function isValidSigningCertUrl(string $url): bool
    {
        /** @var array<string, string>|false $parts */
        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        $host = $parts['host'] ?? '';

        return str_starts_with($host, 'sns.') && str_ends_with($host, '.amazonaws.com');
    }

    private function downloadCertificate(string $url): ?string
    {
        $context = stream_context_create(['http' => ['timeout' => 5]]);

        $cert = @file_get_contents($url, false, $context);

        return is_string($cert) && $cert !== '' ? $cert : null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function buildStringToSign(array $message): ?string
    {
        $type = (string) ($message['Type'] ?? '');

        $fields = match ($type) {
            'Notification' => ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'],
            'SubscriptionConfirmation' => ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'],
            'UnsubscribeConfirmation' => ['Message', 'MessageId', 'UnsubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'],
            default => null,
        };

        if ($fields === null) {
            return null;
        }

        $result = '';
        foreach ($fields as $field) {
            if (isset($message[$field])) {
                $result .= "{$field}\n{$message[$field]}\n";
            }
        }

        return $result;
    }
}
