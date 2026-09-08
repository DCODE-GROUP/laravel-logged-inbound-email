<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Support;

use Dcodegroup\LaravelLoggedInboundEmail\Contracts\EmailBasedTenantResolver;

/**
 * Extracts a tenant identifier from a plus-addressed recipient, e.g.
 * `{tenant_identifier}+{process}@domain`.
 */
class EmailAddressTenantResolver implements EmailBasedTenantResolver
{
    /**
     * @param  array<int, array{email: string, name: ?string}>  $recipients
     */
    public function resolve(array $recipients): ?string
    {
        $email = $recipients[0]['email'] ?? null;

        if (! is_string($email) || $email === '') {
            return null;
        }

        $localPart = strstr($email, '@', true);
        $localPart = $localPart === false ? $email : $localPart;

        $plusPosition = strpos($localPart, '+');

        if ($plusPosition === false || $plusPosition === 0) {
            return null;
        }

        return substr($localPart, 0, $plusPosition);
    }
}
