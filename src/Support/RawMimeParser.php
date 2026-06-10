<?php

namespace Touqeershafi\LaravelInboundEmail\Support;

/**
 * Lightweight raw-MIME-string parser for the common inbound email cases.
 *
 * Covers single-part and multipart/alternative|mixed messages.
 * Binary attachments and nested multipart structures are passed through as
 * base64 blobs without content decoding.
 *
 * For production workloads that require full MIME support (S/MIME, PGP,
 * complex nested multipart), install the `mailparse` PECL extension.
 */
final class RawMimeParser
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

    private function __construct(string $raw)
    {
        [$headerBlock, $bodyBlock] = self::splitHeadersBody($raw);

        $this->headers = self::parseHeaderBlock($headerBlock);

        $this->from = AddressParser::parseOne($this->headers['From'] ?? '');
        $this->to = AddressParser::parseList($this->headers['To'] ?? '');
        $this->cc = AddressParser::parseList($this->headers['Cc'] ?? '');
        $this->bcc = AddressParser::parseList($this->headers['Bcc'] ?? '');
        $this->subject = $this->headers['Subject'] ?? null;

        $contentType = $this->headers['Content-Type'] ?? 'text/plain';

        if (preg_match('/boundary="?([^";\s]+)"?/i', $contentType, $m)) {
            ['text' => $text, 'html' => $html, 'attachments' => $attachments] =
                self::parseMultipart($bodyBlock, $m[1]);

            $this->text = $text;
            $this->html = $html;
            $this->attachments = $attachments;
        } else {
            $decoded = self::decodeBody($bodyBlock, $this->headers['Content-Transfer-Encoding'] ?? null);

            $this->text = stripos($contentType, 'text/html') !== false ? null : $decoded;
            $this->html = stripos($contentType, 'text/html') !== false ? $decoded : null;
            $this->attachments = [];
        }
    }

    public static function parse(string $raw): self
    {
        return new self($raw);
    }

    // -------------------------------------------------------------------------

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitHeadersBody(string $raw): array
    {
        foreach (["\r\n\r\n", "\n\n"] as $sep) {
            $pos = strpos($raw, $sep);
            if ($pos !== false) {
                return [substr($raw, 0, $pos), substr($raw, $pos + strlen($sep))];
            }
        }

        return [$raw, ''];
    }

    /**
     * @return array<string, string>
     */
    private static function parseHeaderBlock(string $block): array
    {
        // Unfold multi-line headers per RFC 5322
        $block = preg_replace('/\r?\n[ \t]+/', ' ', $block) ?? $block;

        $headers = [];

        $lines = preg_split('/\r?\n/', $block);
        foreach ($lines !== false ? $lines : [] as $line) {
            $colon = strpos($line, ':');
            if ($colon === false) {
                continue;
            }

            $name = trim(substr($line, 0, $colon));
            $value = trim(substr($line, $colon + 1));

            if ($name !== '') {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * @return array{text: ?string, html: ?string, attachments: array<int, array{filename: string, content_type: ?string, content_base64: string}>}
     */
    private static function parseMultipart(string $body, string $boundary): array
    {
        $text = null;
        $html = null;
        $attachments = [];

        $delimiter = '--'.$boundary;
        $rawParts = explode($delimiter, $body);

        foreach ($rawParts as $part) {
            $part = ltrim($part, "\r\n");
            if ($part === '' || $part === '--' || $part === "--\r\n" || $part === "--\n") {
                continue;
            }

            [$partHeaders, $partBody] = self::splitHeadersBody($part);
            $ph = self::parseHeaderBlock($partHeaders);
            $partCT = $ph['Content-Type'] ?? 'text/plain';
            $encoding = $ph['Content-Transfer-Encoding'] ?? null;
            $disposition = $ph['Content-Disposition'] ?? '';

            // Recurse into nested multipart
            if (preg_match('/boundary="?([^";\s]+)"?/i', $partCT, $m)) {
                $nested = self::parseMultipart($partBody, $m[1]);
                $text = $text ?? $nested['text'];
                $html = $html ?? $nested['html'];
                $attachments = array_merge($attachments, $nested['attachments']);

                continue;
            }

            $decoded = self::decodeBody($partBody, $encoding);

            if (stripos($disposition, 'attachment') !== false) {
                preg_match('/filename\*?="?([^";\s]+)"?/i', $disposition, $fn);
                $filename = $fn[1] ?? 'attachment';

                $attachments[] = [
                    'filename' => $filename,
                    'content_type' => trim(explode(';', $partCT)[0]),
                    'content_base64' => base64_encode($decoded),
                ];

                continue;
            }

            if (stripos($partCT, 'text/html') !== false) {
                $html = $html ?? $decoded;
            } elseif (stripos($partCT, 'text/plain') !== false) {
                $text = $text ?? $decoded;
            }
        }

        return ['text' => $text, 'html' => $html, 'attachments' => $attachments];
    }

    private static function decodeBody(string $body, ?string $encoding): string
    {
        $enc = strtolower(trim($encoding ?? ''));

        return match ($enc) {
            'base64' => (string) base64_decode(str_replace(["\r", "\n"], '', $body), true),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }
}
