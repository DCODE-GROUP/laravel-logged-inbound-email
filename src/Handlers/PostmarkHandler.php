<?php

namespace Touqeershafi\LaravelInboundEmail\Handlers;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Touqeershafi\LaravelInboundEmail\Contracts\InboundWebhookHandler;
use Touqeershafi\LaravelInboundEmail\InboundMessage;
use Touqeershafi\LaravelInboundEmail\Support\AddressParser;
use Touqeershafi\LaravelInboundEmail\Support\ReadsInboundProviderConfig;

class PostmarkHandler implements InboundWebhookHandler
{
    use ReadsInboundProviderConfig;

    public function verify(Request $request): void
    {
        $secret = $this->inboundProviderSettings($request, 'postmark')['webhook_secret'] ?? null;
        if (! is_string($secret) || $secret === '') {
            throw new AccessDeniedHttpException('Postmark webhook secret is not configured.');
        }

        $signature = $request->header('X-Postmark-Signature');
        if (! is_string($signature) || $signature === '') {
            throw new AccessDeniedHttpException('Missing Postmark signature header.');
        }

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        if (! hash_equals($expected, $signature)) {
            throw new AccessDeniedHttpException('Invalid Postmark signature.');
        }
    }

    public function toInboundMessage(Request $request): ?InboundMessage
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $from = $this->mailboxToAddress($payload['FromFull'] ?? null)
            ?? AddressParser::parseOne($payload['From'] ?? null);

        $toFull = $this->mailboxListToAddresses($payload['ToFull'] ?? null);
        $to = $toFull !== [] ? $toFull : AddressParser::parseList($payload['To'] ?? null);

        $ccFull = $this->mailboxListToAddresses($payload['CcFull'] ?? null);
        $cc = $ccFull !== [] ? $ccFull : AddressParser::parseList($payload['Cc'] ?? null);

        $bccFull = $this->mailboxListToAddresses($payload['BccFull'] ?? null);
        $bcc = $bccFull !== [] ? $bccFull : AddressParser::parseList($payload['Bcc'] ?? null);

        return new InboundMessage(
            provider: 'postmark',
            from: $from,
            to: $to,
            cc: $cc,
            bcc: $bcc,
            replyTo: isset($payload['ReplyTo']) ? AddressParser::parseOne((string) $payload['ReplyTo']) : null,
            subject: isset($payload['Subject']) ? (string) $payload['Subject'] : null,
            text: isset($payload['TextBody']) ? (string) $payload['TextBody'] : null,
            html: isset($payload['HtmlBody']) ? (string) $payload['HtmlBody'] : null,
            attachments: $this->parseAttachments($payload),
            headers: $this->parseHeaders($payload),
            metadata: [
                'postmark_message_id' => $payload['MessageID'] ?? null,
                'mailbox_hash' => $payload['MailboxHash'] ?? null,
            ],
        );
    }

    /**
     * Parse Postmark's structured "Full" address object.
     *
     * @return array{email: string, name: ?string}|null
     */
    private function mailboxToAddress(mixed $full): ?array
    {
        if (! is_array($full)) {
            return null;
        }

        $email = isset($full['Email']) ? (string) $full['Email'] : '';
        if ($email === '') {
            return null;
        }

        return [
            'email' => $email,
            'name' => isset($full['Name']) && $full['Name'] !== '' ? (string) $full['Name'] : null,
        ];
    }

    /**
     * Parse Postmark's structured address list.
     *
     * @return array<int, array{email: string, name: ?string}>
     */
    private function mailboxListToAddresses(mixed $list): array
    {
        if (! is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $item) {
            $addr = $this->mailboxToAddress($item);
            if ($addr !== null) {
                $out[] = $addr;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{filename: string, content_type: ?string, content_base64: string}>
     */
    private function parseAttachments(array $payload): array
    {
        $out = [];

        foreach ($payload['Attachments'] ?? [] as $att) {
            if (! is_array($att)) {
                continue;
            }

            $out[] = [
                'filename' => isset($att['Name']) ? (string) $att['Name'] : 'attachment',
                'content_type' => isset($att['ContentType']) ? (string) $att['ContentType'] : null,
                'content_base64' => isset($att['Content']) ? (string) $att['Content'] : '',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function parseHeaders(array $payload): array
    {
        $out = [];

        foreach ($payload['Headers'] ?? [] as $h) {
            if (is_array($h) && isset($h['Name'], $h['Value']) && is_string($h['Name']) && is_string($h['Value'])) {
                $out[$h['Name']] = $h['Value'];
            }
        }

        return $out;
    }
}
