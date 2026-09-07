<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Support;

use Dcodegroup\LaravelLoggedInboundEmail\Enums\Provider;
use Illuminate\Http\Request;

trait ReadsInboundProviderConfig
{
    /**
     * Merged provider settings (env config + optional tenant resolver), set by InboundWebhookController.
     *
     * @return array<string, mixed>
     */
    protected function inboundProviderSettings(Request $request, Provider $provider): array
    {
        $merged = $request->attributes->get('inbound_email.merged_provider_config');
        if (is_array($merged)) {
            return $merged;
        }

        $base = config("inbound-email.providers.{$provider->value}");

        return is_array($base) ? $base : [];
    }
}
