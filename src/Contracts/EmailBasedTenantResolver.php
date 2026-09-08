<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Contracts;

interface EmailBasedTenantResolver
{
    /**
     * @param  array<int, array{email: string, name: ?string}>  $recipients
     */
    public function resolve(array $recipients): ?string;
}
