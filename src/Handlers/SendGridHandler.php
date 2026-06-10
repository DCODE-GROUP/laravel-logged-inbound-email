<?php

namespace Touqeershafi\LaravelInboundEmail\Handlers;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Touqeershafi\LaravelInboundEmail\Contracts\InboundWebhookHandler;
use Touqeershafi\LaravelInboundEmail\InboundMessage;
use Touqeershafi\LaravelInboundEmail\Support\AddressParser;
use Touqeershafi\LaravelInboundEmail\Support\ReadsInboundProviderConfig;

class SendGridHandler implements InboundWebhookHandler
{
    use ReadsInboundProviderConfig;

    public function verify(Request $request): void
    {
        $key = $this->inboundProviderSettings($request, 'sendgrid')['verification_key'] ?? null;
        if (! is_string($key) || $key === '') {
            return;
        }

        $signature = $request->header('X-Twilio-Email-Event-Webhook-Signature')
            ?? $request->header('X-SendGrid-Signature');
        $timestamp = $request->header('X-Twilio-Email-Event-Webhook-Timestamp');

        if (! is_string($signature) || $signature === '' || ! is_string($timestamp) || $timestamp === '') {
            throw new AccessDeniedHttpException('Missing SendGrid signature headers.');
        }

        $expected = base64_encode(hash_hmac('sha256', $timestamp.$request->getContent(), $key, true));

        if (! hash_equals($expected, $signature)) {
            throw new AccessDeniedHttpException('Invalid SendGrid signature.');
        }
    }

    public function toInboundMessage(Request $request): ?InboundMessage
    {
        return $this->fromFormFields($request);
    }

    private function fromFormFields(Request $request): InboundMessage
    {
        return new InboundMessage(
            provider: 'sendgrid',
            from: AddressParser::parseOne($request->input('from')),
            to: AddressParser::parseList($request->input('to')),
            subject: $this->stringInput($request, 'subject'),
            text: $this->stringInput($request, 'text'),
            html: $this->stringInput($request, 'html'),
            attachments: $this->parseAttachments($request),
            headers: $this->parseRawHeaders($request),
            metadata: [
                'sender_ip' => $request->input('sender_ip'),
                'envelope' => $request->input('envelope'),
                'raw_email' => $request->input('email'),
            ],
        );
    }

    /**
     * @return array<int, array{filename: string, content_type: ?string, content_base64: string}>
     */
    private function parseAttachments(Request $request): array
    {
        $out = [];
        $info = $request->input('attachment-info');

        if (! is_string($info) || $info === '') {
            return $out;
        }

        $decoded = json_decode($info, true);
        if (! is_array($decoded)) {
            return $out;
        }

        foreach ($decoded as $filename => $meta) {
            if (! is_string($filename) || ! is_array($meta)) {
                continue;
            }

            $index = isset($meta['index']) ? (int) $meta['index'] : null;
            if ($index === null) {
                continue;
            }

            $file = $request->file("attachment{$index}");
            if ($file === null || ! $file->isValid()) {
                continue;
            }

            $path = $file->getRealPath();
            if ($path === false) {
                continue;
            }

            $out[] = [
                'filename' => $filename,
                'content_type' => $file->getMimeType(),
                'content_base64' => base64_encode((string) file_get_contents($path)),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function parseRawHeaders(Request $request): array
    {
        $out = [];
        $raw = $request->input('headers');

        if (! is_string($raw) || $raw === '') {
            return $out;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $out;
        }

        foreach ($decoded as $k => $v) {
            if (! is_string($k)) {
                continue;
            }

            $out[$k] = is_string($v) ? $v : (string) json_encode($v);
        }

        return $out;
    }

    private function stringInput(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
