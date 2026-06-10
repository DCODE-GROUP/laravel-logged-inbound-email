<?php

namespace Touqeershafi\LaravelInboundEmail\Contracts;

use Symfony\Component\HttpKernel\Exception\HttpException;

interface InboundWebhookTenantPolicy
{
    /**
     * Run before signature verification (e.g. ensure URL provider matches org settings).
     *
     * @throws HttpException
     */
    public function assertInboundAllowed(?string $organizationAlias, string $provider): void;
}
