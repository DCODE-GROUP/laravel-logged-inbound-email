<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Handlers;

use Dcodegroup\LaravelLoggedInboundEmail\Contracts\InboundWebhookHandler;
use Dcodegroup\LaravelLoggedInboundEmail\InboundMessage;
use Dcodegroup\LaravelLoggedInboundEmail\Support\AddressParser;
use Dcodegroup\LaravelLoggedInboundEmail\Support\ReadsInboundProviderConfig;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MailgunHandler implements InboundWebhookHandler
{
    use ReadsInboundProviderConfig;

    private const TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function verify(Request $request): void
    {
        $signingKey = $this->inboundProviderSettings($request, 'mailgun')['signing_key'] ?? null;
        if (! is_string($signingKey) || $signingKey === '') {
            throw new AccessDeniedHttpException('Mailgun signing key is not configured.');
        }

        $timestamp = $request->input('timestamp');
        $token = $request->input('token');
        $signature = $request->input('signature');

        if (! is_string($timestamp) || ! is_string($token) || ! is_string($signature)) {
            throw new AccessDeniedHttpException('Missing Mailgun signature fields.');
        }

        if (abs(time() - (int) $timestamp) > self::TIMESTAMP_TOLERANCE_SECONDS) {
            throw new AccessDeniedHttpException('Mailgun signature timestamp is too old.');
        }

        if (! hash_equals(hash_hmac('sha256', $timestamp.$token, $signingKey), $signature)) {
            throw new AccessDeniedHttpException('Invalid Mailgun signature.');
        }
    }

    public function toInboundMessage(Request $request): ?InboundMessage
    {
        $from = AddressParser::parseOne($request->input('sender') ?? $request->input('from'));
        $to = AddressParser::parseList($request->input('recipient') ?? $request->input('To'));

        $attachments = $this->parseAttachments($request);
        $headers = $this->parseHeaders($request);

        return new InboundMessage(
            provider: 'mailgun',
            from: $from,
            to: $to,
            subject: $this->stringInput($request, 'subject'),
            text: $this->stringInput($request, 'body-plain') ?? $this->stringInput($request, 'stripped-text'),
            html: $this->stringInput($request, 'body-html') ?? $this->stringInput($request, 'stripped-html'),
            attachments: $attachments,
            headers: $headers,
            rawMime: $this->stringInput($request, 'body-mime'),
            metadata: [
                'mailgun_message_id' => $request->input('Message-Id') ?? $request->input('message-id'),
            ],
        );
    }

    /**
     * @return array<int, array{filename: string, content_type: ?string, content_base64: string}>
     */
    private function parseAttachments(Request $request): array
    {
        $attachments = [];
        $count = (int) ($request->input('attachment-count') ?? 0);

        for ($i = 1; $i <= $count; $i++) {
            $file = $request->file("attachment-{$i}");
            if ($file === null || ! $file->isValid()) {
                continue;
            }

            $path = $file->getRealPath();
            if ($path === false) {
                continue;
            }

            $attachments[] = [
                'filename' => $file->getClientOriginalName() !== '' ? $file->getClientOriginalName() : "attachment-{$i}",
                'content_type' => $file->getMimeType(),
                'content_base64' => base64_encode((string) file_get_contents($path)),
            ];
        }

        return $attachments;
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(Request $request): array
    {
        $headers = [];
        $raw = $request->input('message-headers');

        if (! is_string($raw)) {
            return $headers;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $headers;
        }

        foreach ($decoded as $row) {
            if (is_array($row) && isset($row[0], $row[1]) && is_string($row[0]) && is_string($row[1])) {
                $headers[$row[0]] = $row[1];
            }
        }

        return $headers;
    }

    private function stringInput(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
