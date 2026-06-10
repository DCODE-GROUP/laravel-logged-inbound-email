<?php

namespace Touqeershafi\LaravelInboundEmail\Support;

use Touqeershafi\LaravelInboundEmail\Contracts\InboundWebhookTenantPolicy;

class AllowAllInboundWebhookTenantPolicy implements InboundWebhookTenantPolicy
{
    public function assertInboundAllowed(?string $organizationAlias, string $provider): void
    {
        // no-op — apps may bind a stricter policy (e.g. match URL provider to org settings).
    }
}
