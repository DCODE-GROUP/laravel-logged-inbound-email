<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Support;

use Dcodegroup\LaravelLoggedInboundEmail\Contracts\InboundWebhookTenantPolicy;
use Dcodegroup\LaravelLoggedInboundEmail\Enums\Provider;

class AllowAllInboundWebhookTenantPolicy implements InboundWebhookTenantPolicy
{
    public function assertInboundAllowed(?string $organizationAlias, Provider $provider): void
    {
        // no-op — apps may bind a stricter policy (e.g. match URL provider to org settings).
    }
}
