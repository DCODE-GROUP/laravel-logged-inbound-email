<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Handlers;

use Dcodegroup\LaravelLoggedInboundEmail\Contracts\InboundWebhookHandler;
use Dcodegroup\LaravelLoggedInboundEmail\InboundMessage;
use Dcodegroup\LaravelLoggedInboundEmail\Support\ReadsInboundProviderConfig;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MailpitHandler implements InboundWebhookHandler
{
    use ReadsInboundProviderConfig;

    public function verify(Request $request): void
    {
        $secret = $this->inboundProviderSettings($request, 'mailpit')['webhook_secret'] ?? null;
        if (! is_string($secret) || $secret === '') {
            return;
        }

        $header = $request->header('X-Webhook-Secret');
        if (! is_string($header) || ! hash_equals($secret, $header)) {
            throw new AccessDeniedHttpException('Invalid Mailpit webhook secret.');
        }
    }

    public function toInboundMessage(Request $request): ?InboundMessage
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $id = $this->extractMessageId($payload);
        if ($id === null) {
            throw new BadRequestHttpException('Mailpit webhook payload has no message ID.');
        }

        $data = $this->fetchMessageFromApi($id, $request);

        return $this->buildMessage($data, $id);
    }

    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractMessageId(array $payload): ?string
    {
        $id = $payload['ID'] ?? $payload['Id'] ?? $payload['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchMessageFromApi(string $id, Request $request): array
    {
        $settings = $this->inboundProviderSettings($request, 'mailpit');
        $base = rtrim((string) ($settings['base_url'] ?? 'http://127.0.0.1:8025'), '/');
        $token = $settings['api_token'] ?? null;

        $pending = Http::baseUrl($base)
            ->accept('application/json')
            ->timeout(10);

        if (is_string($token) && $token !== '') {
            $pending = $pending->withToken($token);
        }

        try {
            $response = $pending->get("/api/v1/message/{$id}");
            $response->throw();
        } catch (RequestException $e) {
            throw new BadRequestHttpException('Failed to fetch Mailpit message: '.$e->getMessage());
        }

        /** @var array<string, mixed>|null $data */
        $data = $response->json();
        if (! is_array($data)) {
            throw new BadRequestHttpException('Invalid Mailpit API response.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildMessage(array $data, string $id): InboundMessage
    {
        return new InboundMessage(
            provider: 'mailpit',
            from: $this->parseContact($data['From'] ?? null),
            to: $this->contactList($data['To'] ?? null),
            cc: $this->contactList($data['Cc'] ?? null),
            bcc: $this->contactList($data['Bcc'] ?? null),
            replyTo: $this->parseContact($data['ReplyTo'] ?? null),
            subject: isset($data['Subject']) ? (string) $data['Subject'] : null,
            text: isset($data['Text']) ? (string) $data['Text'] : null,
            html: isset($data['HTML']) ? (string) $data['HTML'] : null,
            attachments: $this->parseAttachments($data['Attachments'] ?? null),
            headers: $this->parseHeaders($data['Headers'] ?? null),
            metadata: ['mailpit_id' => $id],
        );
    }

    /**
     * @param  array<int, mixed>|mixed|null  $list
     * @return array<int, array{email: string, name: ?string}>
     */
    private function contactList(mixed $list): array
    {
        $items = $this->asList($list);
        $out = [];

        foreach ($items as $item) {
            $contact = $this->parseContact($item);
            if ($contact !== null) {
                $out[] = $contact;
            }
        }

        return $out;
    }

    /**
     * @return array<int, mixed>
     */
    private function asList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return array_is_list($value) ? $value : [$value];
        }

        return [$value];
    }

    /**
     * @return array{email: string, name: ?string}|null
     */
    private function parseContact(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            if (preg_match('/^(?P<name>.*?)\s*<(?P<email>[^>]+)>$/', trim($value), $m)) {
                $name = trim($m['name'], " \t\"'");

                return [
                    'email' => trim($m['email']),
                    'name' => $name !== '' ? $name : null,
                ];
            }

            $trimmed = trim($value);

            return $trimmed !== '' ? ['email' => $trimmed, 'name' => null] : null;
        }

        if (is_array($value)) {
            $email = (string) ($value['Address'] ?? $value['Email'] ?? '');
            if ($email === '') {
                return null;
            }

            return [
                'email' => $email,
                'name' => isset($value['Name']) && $value['Name'] !== '' ? (string) $value['Name'] : null,
            ];
        }

        return null;
    }

    /**
     * @return array<int, array{filename: string, content_type: ?string, content_base64: string}>
     */
    private function parseAttachments(mixed $attachments): array
    {
        $out = [];

        foreach ($this->asList($attachments) as $att) {
            if (! is_array($att)) {
                continue;
            }

            $content = $att['Content'] ?? '';

            $out[] = [
                'filename' => isset($att['Filename']) ? (string) $att['Filename'] : 'attachment',
                'content_type' => isset($att['ContentType']) ? (string) $att['ContentType'] : null,
                'content_base64' => is_string($content) ? $content : base64_encode((string) $content),
            ];
        }

        return $out;
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
