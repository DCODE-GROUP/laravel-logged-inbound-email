<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Contracts;

interface InboundProviderConfigResolver
{
    /**
     * Per-tenant overrides merged on top of config("inbound-email.providers.{provider}").
     * Non-null values replace env-backed defaults (empty strings are ignored by the merger).
     *
     * @return array<string, mixed>
     */
    public function resolve(?string $organizationAlias, string $provider): array;
}
