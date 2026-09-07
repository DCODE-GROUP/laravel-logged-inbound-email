<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Support;

/**
 * Parses RFC-5321-style address strings ("Name <email@example.com>") into
 * the canonical array shape used by InboundMessage.
 */
final class AddressParser
{
    /**
     * Parse a single address string.
     *
     * @return array{email: string, name: ?string}|null null when the value is empty / unparseable
     */
    public static function parseOne(mixed $value): ?array
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^(?P<name>.*?)\s*<(?P<email>[^>]+)>$/', $value, $m)) {
            $name = trim($m['name'], " \t\"'");

            return [
                'email' => trim($m['email']),
                'name' => $name !== '' ? $name : null,
            ];
        }

        return ['email' => $value, 'name' => null];
    }

    /**
     * Parse a comma-separated list of address strings.
     *
     * @return array<int, array{email: string, name: ?string}>
     */
    public static function parseList(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $out = [];

        $parts = preg_split('/\s*,\s*/', $value);
        foreach ($parts !== false ? $parts : [] as $part) {
            $addr = self::parseOne($part);
            if ($addr !== null) {
                $out[] = $addr;
            }
        }

        return $out;
    }
}
