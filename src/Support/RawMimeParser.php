<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Support;

use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\Header\Part\AddressPart;
use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\Message;

/**
 * Parses a raw MIME string (headers + body) into the canonical array shape
 * used by InboundMessage, via zbateson/mail-mime-parser.
 */
class RawMimeParser
{
    /** @var array{email: string, name: ?string}|null */
    public readonly ?array $from;

    /** @var array<int, array{email: string, name: ?string}> */
    public readonly array $to;

    /** @var array<int, array{email: string, name: ?string}> */
    public readonly array $cc;

    /** @var array<int, array{email: string, name: ?string}> */
    public readonly array $bcc;

    public readonly ?string $subject;

    public readonly ?string $text;

    public readonly ?string $html;

    /** @var array<int, array{filename: string, content_type: ?string, content_base64: string}> */
    public readonly array $attachments;

    /** @var array<string, string> */
    public readonly array $headers;

    protected function __construct(string $raw)
    {
        $message = Message::from($raw, false);

        $this->from = $this->firstAddress($message);
        $this->to = $this->addressList($message, HeaderConsts::TO);
        $this->cc = $this->addressList($message, HeaderConsts::CC);
        $this->bcc = $this->addressList($message, HeaderConsts::BCC);
        $this->subject = $message->getSubject();
        $this->text = $message->getTextContent();
        $this->html = $message->getHtmlContent();

        $this->attachments = $this->parseAttachments($message);
        $this->headers = $this->parseHeaders($message);
    }

    public static function parse(string $raw): self
    {
        return new self($raw);
    }

    // -------------------------------------------------------------------------

    /**
     * @return array{email: string, name: ?string}|null
     */
    protected function firstAddress(IMessage $message): ?array
    {
        $header = $message->getHeader(HeaderConsts::FROM);
        if (! $header instanceof AddressHeader) {
            return null;
        }

        $addresses = $header->getAddresses();
        if ($addresses === []) {
            return null;
        }

        return $this->addressPartToArray($addresses[0]);
    }

    /**
     * @return array<int, array{email: string, name: ?string}>
     */
    protected function addressList(IMessage $message, string $headerName): array
    {
        $header = $message->getHeader($headerName);
        if (! $header instanceof AddressHeader) {
            return [];
        }

        return array_map(
            fn (AddressPart $address): array => $this->addressPartToArray($address),
            $header->getAddresses(),
        );
    }

    /**
     * @return array{email: string, name: ?string}
     */
    protected function addressPartToArray(AddressPart $address): array
    {
        $name = $address->getName();

        return ['email' => $address->getEmail(), 'name' => $name !== '' ? $name : null];
    }

    /**
     * @return array<int, array{filename: string, content_type: ?string, content_base64: string}>
     */
    protected function parseAttachments(IMessage $message): array
    {
        $out = [];

        foreach ($message->getAllAttachmentParts() as $part) {
            $out[] = [
                'filename' => $part->getFilename() ?? 'attachment',
                'content_type' => $part->getContentType(),
                'content_base64' => base64_encode((string) $part->getBinaryContentStream()),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    protected function parseHeaders(IMessage $message): array
    {
        $out = [];

        foreach ($message->getRawHeaders() as [$name, $value]) {
            $out[$name] = $value;
        }

        return $out;
    }
}
