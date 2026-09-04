<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Support;

use Dcodegroup\LaravelLoggedInboundEmail\Contracts\InboundWebhookTenantPolicy;

class AllowAllInboundWebhookTenantPolicy implements InboundWebhookTenantPolicy
{
    public function assertInboundAllowed(?string $organizationAlias, string $provider): void
    {
        // no-op — apps may bind a stricter policy (e.g. match URL provider to org settings).
    }
}
