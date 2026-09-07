<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Support;

use Dcodegroup\LaravelLoggedInboundEmail\Contracts\InboundProviderConfigResolver;
use Dcodegroup\LaravelLoggedInboundEmail\Enums\Provider;

class NullInboundProviderConfigResolver implements InboundProviderConfigResolver
{
    public function resolve(?string $organizationAlias, Provider $provider): array
    {
        return [];
    }
}
