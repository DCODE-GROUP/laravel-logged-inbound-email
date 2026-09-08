<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Contracts;

use Dcodegroup\LaravelLoggedInboundEmail\Enums\Provider;
use Symfony\Component\HttpKernel\Exception\HttpException;

interface InboundWebhookTenantPolicy
{
    /**
     * Run before signature verification (e.g. ensure URL provider matches org settings).
     *
     * @throws HttpException
     */
    public function assertInboundAllowed(?string $organizationAlias, Provider $provider): void;
}
