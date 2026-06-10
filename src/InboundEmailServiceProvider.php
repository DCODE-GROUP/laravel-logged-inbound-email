<?php

namespace Touqeershafi\LaravelInboundEmail;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Touqeershafi\LaravelInboundEmail\Contracts\InboundProviderConfigResolver;
use Touqeershafi\LaravelInboundEmail\Contracts\InboundWebhookTenantPolicy;
use Touqeershafi\LaravelInboundEmail\Support\AllowAllInboundWebhookTenantPolicy;
use Touqeershafi\LaravelInboundEmail\Support\NullInboundProviderConfigResolver;
use Touqeershafi\LaravelInboundEmail\Support\SnsMessageVerifier;

class InboundEmailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/inbound-email.php', 'inbound-email');

        $this->app->singleton(SnsMessageVerifier::class);

        $this->app->singleton(InboundProviderConfigResolver::class, NullInboundProviderConfigResolver::class);
        $this->app->singleton(InboundWebhookTenantPolicy::class, AllowAllInboundWebhookTenantPolicy::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/inbound-email.php' => config_path('inbound-email.php'),
            ], 'inbound-email-config');
        }

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        $prefix = trim((string) config('inbound-email.route_prefix', 'webhooks/inbound'), '/');
        $middleware = config('inbound-email.middleware', ['api']);

        Route::prefix($prefix)
            ->middleware($middleware)
            ->group(__DIR__.'/../routes/inbound-email.php');
    }
}
