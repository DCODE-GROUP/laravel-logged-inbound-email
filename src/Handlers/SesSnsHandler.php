<?php

namespace Touqeershafi\LaravelInboundEmail\Handlers;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Touqeershafi\LaravelInboundEmail\Contracts\InboundWebhookHandler;
use Touqeershafi\LaravelInboundEmail\InboundMessage;
use Touqeershafi\LaravelInboundEmail\Support\RawMimeParser;
use Touqeershafi\LaravelInboundEmail\Support\ReadsInboundProviderConfig;
use Touqeershafi\LaravelInboundEmail\Support\SnsMessageVerifier;

class SesSnsHandler implements InboundWebhookHandler
{
    use ReadsInboundProviderConfig;

    public function __construct(
        private readonly SnsMessageVerifier $snsVerifier,
    ) {}

    public function verify(Request $request): void
    {
        $payload = $this->snsPayload($request);

        $settings = $this->inboundProviderSettings($request, 'ses');
        $allowUnsigned = $settings['allow_sns_message_without_signature'] ?? false;
        $allowUnsigned = filter_var($allowUnsigned, FILTER_VALIDATE_BOOLEAN);

        if (! $allowUnsigned && ! $this->snsVerifier->verify($payload)) {
            throw new AccessDeniedHttpException('Invalid Amazon SNS signature.');
        }

        $this->confirmSubscriptionIfNeeded($payload);
    }

    public function toInboundMessage(Request $request): ?InboundMessage
    {
        $payload = $this->snsPayload($request);
        $type = (string) ($payload['Type'] ?? '');

        if ($type === 'SubscriptionConfirmation' || $type === 'UnsubscribeConfirmation') {
            return null;
        }

        if ($type !== 'Notification') {
            return null;
        }

        $innerRaw = $payload['Message'] ?? '';
        if (! is_string($innerRaw) || $innerRaw === '') {
            throw new BadRequestHttpException('SNS notification has no Message body.');
        }

        $inner = json_decode($innerRaw, true);
        if (! is_array($inner)) {
            throw new BadRequestHttpException('Invalid SES notification JSON.');
        }

        $rawMime = $this->resolveRawMime($inner, $request);
        if ($rawMime === null || $rawMime === '') {
            throw new BadRequestHttpException('Could not resolve raw MIME for SES notification.');
        }

        return $this->buildMessage($rawMime, $inner);
    }

    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function snsPayload(Request $request): array
    {
        /** @var array<string, mixed>|null $cached */
        $cached = $request->attributes->get('inbound_sns_payload');
        if (is_array($cached)) {
            return $cached;
        }

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            throw new BadRequestHttpException('Invalid SNS envelope JSON.');
        }

        $request->attributes->set('inbound_sns_payload', $data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function confirmSubscriptionIfNeeded(array $payload): void
    {
        $type = (string) ($payload['Type'] ?? '');

        $url = match ($type) {
            'SubscriptionConfirmation' => $payload['SubscribeURL'] ?? null,
            'UnsubscribeConfirmation' => $payload['UnsubscribeURL'] ?? null,
            default => null,
        };

        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        try {
            Http::get($url);
        } catch (RequestException) {
            // best-effort — do not reject the webhook if the confirmation ping fails
        }
    }

    /**
     * @param  array<string, mixed>  $inner
     */
    private function resolveRawMime(array $inner, Request $request): ?string
    {
        if (isset($inner['content']) && is_string($inner['content'])) {
            $decoded = base64_decode($inner['content'], true);
            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }

        return $this->resolveFromS3($inner, $request);
    }

    /**
     * @param  array<string, mixed>  $inner
     */
    private function resolveFromS3(array $inner, Request $request): ?string
    {
        $receipt = $inner['receipt'] ?? null;
        if (! is_array($receipt)) {
            return null;
        }

        $actions = [];
        if (isset($receipt['actions']) && is_array($receipt['actions'])) {
            $actions = $receipt['actions'];
        } elseif (isset($receipt['action']) && is_array($receipt['action'])) {
            $actions = isset($receipt['action']['type']) ? [$receipt['action']] : $receipt['action'];
        }

        foreach ($actions as $action) {
            if (! is_array($action) || ($action['type'] ?? '') !== 'S3') {
                continue;
            }

            $key = $action['objectKey'] ?? $action['key'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }

            $diskName = $this->inboundProviderSettings($request, 'ses')['s3_disk'] ?? null;
            if (! is_string($diskName) || $diskName === '') {
                throw new BadRequestHttpException(
                    'SES S3 action detected but inbound-email.providers.ses.s3_disk is not configured.'
                );
            }

            return Storage::disk($diskName)->get($key);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $inner
     */
    private function buildMessage(string $rawMime, array $inner): InboundMessage
    {
        $parsed = RawMimeParser::parse($rawMime);

        /** @var array<string, mixed> $mail */
        $mail = is_array($inner['mail'] ?? null) ? $inner['mail'] : [];

        /** @var array<string, mixed> $commonHeaders */
        $commonHeaders = is_array($mail['commonHeaders'] ?? null) ? $mail['commonHeaders'] : [];

        return new InboundMessage(
            provider: 'ses',
            from: $parsed->from,
            to: $parsed->to,
            cc: $parsed->cc,
            bcc: $parsed->bcc,
            subject: $parsed->subject ?? (isset($commonHeaders['subject']) ? (string) $commonHeaders['subject'] : null),
            text: $parsed->text,
            html: $parsed->html,
            attachments: $parsed->attachments,
            headers: $parsed->headers,
            rawMime: $rawMime,
            metadata: [
                'ses_message_id' => $mail['messageId'] ?? null,
                'ses_notification' => $inner,
            ],
        );
    }
}
