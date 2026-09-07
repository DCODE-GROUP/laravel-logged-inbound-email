<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Enums;

/**
 * The inbound email providers supported by the package's webhook routes,
 * handler factory, and provider config. Values match the existing lowercase
 * strings used in config keys and webhook URLs, so those keep working
 * unchanged.
 */
enum Provider: string
{
    case Mailgun = 'mailgun';
    case Postmark = 'postmark';
    case SendGrid = 'sendgrid';
    case Ses = 'ses';
    case Mailpit = 'mailpit';
    case Resend = 'resend';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
