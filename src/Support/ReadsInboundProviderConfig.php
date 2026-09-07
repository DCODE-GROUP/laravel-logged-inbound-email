<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Support;

use Illuminate\Http\Request;

trait ReadsInboundProviderConfig
{
    /**
     * Merged provider settings (env config + optional tenant resolver), set by InboundWebhookController.
     *
     * @return array<string, mixed>
     */
    protected function inboundProviderSettings(Request $request, string $provider): array
    {
        $merged = $request->attributes->get('inbound_email.merged_provider_config');
        if (is_array($merged)) {
            return $merged;
        }

        $base = config("inbound-email.providers.{$provider}");

        return is_array($base) ? $base : [];
    }
}
