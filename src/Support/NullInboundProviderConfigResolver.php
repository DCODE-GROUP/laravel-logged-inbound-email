<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Support;

use Dcodegroup\LaravelLoggedInboundEmail\Contracts\InboundProviderConfigResolver;

class NullInboundProviderConfigResolver implements InboundProviderConfigResolver
{
    public function resolve(?string $organizationAlias, string $provider): array
    {
        return [];
    }
}
