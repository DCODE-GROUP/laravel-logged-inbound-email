<?php

namespace Touqeershafi\LaravelInboundEmail\Support;

use Touqeershafi\LaravelInboundEmail\Contracts\InboundProviderConfigResolver;

class NullInboundProviderConfigResolver implements InboundProviderConfigResolver
{
    public function resolve(?string $organizationAlias, string $provider): array
    {
        return [];
    }
}
