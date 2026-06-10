<?php

namespace Touqeershafi\LaravelInboundEmail\Support;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Extracts address/attachment/header data from an already-structured
 * Symfony Email object into the arrays used by InboundMessage.
 */
final class MimeEmailParser
{
    /** @var array{email: string, name: ?string}|null */
    public readonly ?array $from;

    /** @var array<int, array{email: string, name: ?string}> */
    public readonly array $to;

    /** @var array<int, array{email: string, name: ?string}> */
    public readonly array $cc;

    /** @var array<int, array{email: string, name: ?string}> */
    public readonly array $bcc;

    /** @var array{email: string, name: ?string}|null */
    public readonly ?array $replyTo;

    public readonly ?string $subject;

    public readonly ?string $text;

    public readonly ?string $html;

    /** @var array<int, array{filename: string, content_type: ?string, content_base64: string}> */
    public readonly array $attachments;

    /** @var array<string, string> */
    public readonly array $headers;

    private function __construct(Email $email)
    {
        $this->from = $this->firstAddress($email->getFrom());
        $this->to = $this->addressList($email->getTo());
        $this->cc = $this->addressList($email->getCc());
        $this->bcc = $this->addressList($email->getBcc());
        $this->replyTo = $this->firstAddress($email->getReplyTo());
        $this->subject = $email->getSubject();
        $this->text = $email->getTextBody();
        $this->html = $email->getHtmlBody();

        $this->attachments = $this->parseAttachments($email);
        $this->headers = $this->parseHeaders($email);
    }

    public static function parse(Email $email): self
    {
        return new self($email);
    }

    // -------------------------------------------------------------------------

    /**
     * @param  Address[]  $addresses
     * @return array{email: string, name: ?string}|null
     */
    private function firstAddress(array $addresses): ?array
    {
        if ($addresses === []) {
            return null;
        }

        $a = $addresses[0];
        $rawName = $a->getName();

        return ['email' => $a->getAddress(), 'name' => $rawName !== '' ? $rawName : null];
    }

    /**
     * @param  Address[]  $addresses
     * @return array<int, array{email: string, name: ?string}>
     */
    private function addressList(array $addresses): array
    {
        $out = [];
        foreach ($addresses as $a) {
            $rawName = $a->getName();
            $out[] = ['email' => $a->getAddress(), 'name' => $rawName !== '' ? $rawName : null];
        }

        return $out;
    }

    /**
     * @return array<int, array{filename: string, content_type: ?string, content_base64: string}>
     */
    private function parseAttachments(Email $email): array
    {
        $out = [];

        foreach ($email->getAttachments() as $part) {
            $out[] = [
                'filename' => $part->getFilename() ?? 'attachment',
                'content_type' => $part->getMediaType().'/'.$part->getMediaSubtype(),
                'content_base64' => base64_encode($part->getBody()),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(Email $email): array
    {
        $out = [];

        foreach ($email->getHeaders()->all() as $headerList) {
            foreach ($headerList as $h) {
                $out[$h->getName()] = $h->getBodyAsString();
            }
        }

        return $out;
    }
}
