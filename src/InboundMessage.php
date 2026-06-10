<?php

namespace Touqeershafi\LaravelInboundEmail;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

/**
 * Immutable, serialization-safe representation of a received inbound email.
 *
 * All address entries follow the shape `['email' => string, 'name' => ?string]`.
 * Attachment entries follow `['filename' => string, 'content_type' => ?string, 'content_base64' => string]`.
 *
 * @implements Arrayable<string, mixed>
 */
class InboundMessage implements Arrayable, Jsonable
{
    /**
     * @param  array{email: string, name: ?string}|null  $from
     * @param  array<int, array{email: string, name: ?string}>  $to
     * @param  array<int, array{email: string, name: ?string}>  $cc
     * @param  array<int, array{email: string, name: ?string}>  $bcc
     * @param  array{email: string, name: ?string}|null  $replyTo
     * @param  array<int, array{filename: string, content_type: ?string, content_base64: string}>  $attachments
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $provider,
        public readonly ?array $from,
        public readonly array $to,
        public readonly array $cc = [],
        public readonly array $bcc = [],
        public readonly ?array $replyTo = null,
        public readonly ?string $subject = null,
        public readonly ?string $text = null,
        public readonly ?string $html = null,
        public readonly array $attachments = [],
        public readonly array $headers = [],
        public readonly ?string $rawMime = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Re-hydrate from the array snapshot stored in the queue job.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            provider: (string) ($data['provider'] ?? ''),
            from: is_array($data['from'] ?? null) ? $data['from'] : null,
            to: is_array($data['to'] ?? null) ? $data['to'] : [],
            cc: is_array($data['cc'] ?? null) ? $data['cc'] : [],
            bcc: is_array($data['bcc'] ?? null) ? $data['bcc'] : [],
            replyTo: is_array($data['reply_to'] ?? null) ? $data['reply_to'] : null,
            subject: isset($data['subject']) ? (string) $data['subject'] : null,
            text: isset($data['text']) ? (string) $data['text'] : null,
            html: isset($data['html']) ? (string) $data['html'] : null,
            attachments: is_array($data['attachments'] ?? null) ? $data['attachments'] : [],
            headers: is_array($data['headers'] ?? null) ? $data['headers'] : [],
            rawMime: isset($data['raw_mime']) ? (string) $data['raw_mime'] : null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'from' => $this->from,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'reply_to' => $this->replyTo,
            'subject' => $this->subject,
            'text' => $this->text,
            'html' => $this->html,
            'attachments' => $this->attachments,
            'headers' => $this->headers,
            'raw_mime' => $this->rawMime,
            'metadata' => $this->metadata,
        ];
    }

    public function toJson(mixed $options = 0): string
    {
        $encoded = json_encode($this->toArray(), JSON_THROW_ON_ERROR);

        return $encoded;
    }
}
