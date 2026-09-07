<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Handlers;

use Dcodegroup\LaravelLoggedInboundEmail\Contracts\InboundWebhookHandler;
use Dcodegroup\LaravelLoggedInboundEmail\InboundMessage;
use Dcodegroup\LaravelLoggedInboundEmail\Support\AddressParser;
use Dcodegroup\LaravelLoggedInboundEmail\Support\ReadsInboundProviderConfig;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Svix\Exception\WebhookVerificationException;
use Svix\Webhook;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ResendHandler implements InboundWebhookHandler
{
    use ReadsInboundProviderConfig;

    public function verify(Request $request): void
    {
        $secret = $this->inboundProviderSettings($request, 'resend')['webhook_secret'] ?? null;
        if (! is_string($secret) || $secret === '') {
            throw new AccessDeniedHttpException('Resend webhook secret is not configured.');
        }

        $svixId = $request->header('svix-id');
        $svixTimestamp = $request->header('svix-timestamp');
        $svixSignature = $request->header('svix-signature');

        if (! is_string($svixId) || $svixId === ''
            || ! is_string($svixTimestamp) || $svixTimestamp === ''
            || ! is_string($svixSignature) || $svixSignature === '') {
            throw new AccessDeniedHttpException('Missing Resend webhook signature headers.');
        }

        try {
            $wh = new Webhook($secret);
            $wh->verify($request->getContent(), [
                'svix-id' => $svixId,
                'svix-timestamp' => $svixTimestamp,
                'svix-signature' => $svixSignature,
            ]);
        } catch (WebhookVerificationException) {
            throw new AccessDeniedHttpException('Invalid Resend webhook signature.');
        }
    }

    public function toInboundMessage(Request $request): ?InboundMessage
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        if (($payload['type'] ?? null) !== 'email.received') {
            return null;
        }

        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            throw new BadRequestHttpException('Resend webhook payload has no data object.');
        }

        $emailId = $data['email_id'] ?? $data['id'] ?? null;
        if (! is_string($emailId) || $emailId === '') {
            throw new BadRequestHttpException('Resend webhook payload has no email ID.');
        }

        $emailData = $this->fetchEmailFromApi($emailId, $request);

        return $this->buildMessage($emailData, $emailId, $payload, $request);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchEmailFromApi(string $emailId, Request $request): array
    {
        $settings = $this->inboundProviderSettings($request, 'resend');
        $apiKey = $settings['api_key'] ?? null;
        if (! is_string($apiKey) || $apiKey === '') {
            throw new BadRequestHttpException('Resend API key is not configured.');
        }

        $base = rtrim((string) ($settings['api_base_url'] ?? 'https://api.resend.com'), '/');

        try {
            $response = Http::baseUrl($base)
                ->accept('application/json')
                ->withToken($apiKey)
                ->timeout(15)
                ->get("/emails/receiving/{$emailId}");
            $response->throw();
        } catch (RequestException $e) {
            throw new BadRequestHttpException('Failed to fetch Resend email: '.$e->getMessage());
        }

        /** @var array<string, mixed>|null $data */
        $data = $response->json();
        if (! is_array($data)) {
            throw new BadRequestHttpException('Invalid Resend API response.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $emailData
     * @param  array<string, mixed>  $webhookPayload
     */
    private function buildMessage(
        array $emailData,
        string $emailId,
        array $webhookPayload,
        Request $request,
    ): InboundMessage {
        $replyToList = $this->addressListFromMixed($emailData['reply_to'] ?? null);
        $replyTo = $replyToList[0] ?? null;

        return new InboundMessage(
            provider: 'resend',
            from: AddressParser::parseOne($emailData['from'] ?? null),
            to: $this->addressListFromMixed($emailData['to'] ?? null),
            cc: $this->addressListFromMixed($emailData['cc'] ?? null),
            bcc: $this->addressListFromMixed($emailData['bcc'] ?? null),
            replyTo: $replyTo,
            subject: isset($emailData['subject']) ? (string) $emailData['subject'] : null,
            text: isset($emailData['text']) ? (string) $emailData['text'] : null,
            html: isset($emailData['html']) ? (string) $emailData['html'] : null,
            attachments: $this->parseAttachments($emailData, $emailId, $request),
            headers: $this->parseHeaders($emailData['headers'] ?? null),
            metadata: [
                'resend_email_id' => $emailId,
                'resend_message_id' => $emailData['message_id'] ?? ($webhookPayload['data']['message_id'] ?? null),
                'webhook_created_at' => $webhookPayload['created_at'] ?? null,
            ],
        );
    }

    /**
     * @return array<int, array{email: string, name: ?string}>
     */
    private function addressListFromMixed(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            return AddressParser::parseList($value);
        }

        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $addr = AddressParser::parseOne($item);
                if ($addr !== null) {
                    $out[] = $addr;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $emailData
     * @return array<int, array{filename: string, content_type: ?string, content_base64: string}>
     */
    private function parseAttachments(array $emailData, string $emailId, Request $request): array
    {
        $out = [];
        $attachments = $emailData['attachments'] ?? [];

        if (! is_array($attachments)) {
            return [];
        }

        foreach ($attachments as $att) {
            if (! is_array($att)) {
                continue;
            }

            $attachmentId = $att['id'] ?? null;
            if (! is_string($attachmentId) || $attachmentId === '') {
                continue;
            }

            $contentBase64 = $this->fetchAttachmentContent($emailId, $attachmentId, $request);

            $out[] = [
                'filename' => isset($att['filename']) ? (string) $att['filename'] : 'attachment',
                'content_type' => isset($att['content_type']) ? (string) $att['content_type'] : null,
                'content_base64' => $contentBase64,
            ];
        }

        return $out;
    }

    private function fetchAttachmentContent(string $emailId, string $attachmentId, Request $request): string
    {
        $settings = $this->inboundProviderSettings($request, 'resend');
        $apiKey = $settings['api_key'] ?? null;
        if (! is_string($apiKey) || $apiKey === '') {
            return '';
        }

        $base = rtrim((string) ($settings['api_base_url'] ?? 'https://api.resend.com'), '/');

        try {
            $metaResponse = Http::baseUrl($base)
                ->accept('application/json')
                ->withToken($apiKey)
                ->timeout(15)
                ->get("/emails/receiving/{$emailId}/attachments/{$attachmentId}");
            $metaResponse->throw();
        } catch (RequestException) {
            return '';
        }

        /** @var array<string, mixed>|null $meta */
        $meta = $metaResponse->json();
        $downloadUrl = is_array($meta) ? ($meta['download_url'] ?? null) : null;
        if (! is_string($downloadUrl) || $downloadUrl === '') {
            return '';
        }

        try {
            $fileResponse = Http::timeout(30)->get($downloadUrl);
            $fileResponse->throw();
        } catch (RequestException) {
            return '';
        }

        return base64_encode($fileResponse->body());
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(mixed $headers): array
    {
        if (! is_array($headers)) {
            return [];
        }

        $out = [];
        foreach ($headers as $k => $v) {
            if (is_string($k) && (is_string($v) || is_numeric($v))) {
                $out[$k] = (string) $v;
            }
        }

        return $out;
    }
}
