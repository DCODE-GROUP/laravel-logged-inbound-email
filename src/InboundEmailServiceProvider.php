<?php

namespace Dcodegroup\LaravelLoggedInboundEmail;

use Dcodegroup\LaravelLoggedInboundEmail\Contracts\InboundProviderConfigResolver;
use Dcodegroup\LaravelLoggedInboundEmail\Contracts\InboundWebhookTenantPolicy;
use Dcodegroup\LaravelLoggedInboundEmail\Support\AllowAllInboundWebhookTenantPolicy;
use Dcodegroup\LaravelLoggedInboundEmail\Support\NullInboundProviderConfigResolver;
use Dcodegroup\LaravelLoggedInboundEmail\Support\SnsMessageVerifier;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class InboundEmailServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-logged-inbound-email')
            ->hasConfigFile('inbound-email');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SnsMessageVerifier::class);

        $this->app->singleton(InboundProviderConfigResolver::class, NullInboundProviderConfigResolver::class);
        $this->app->singleton(InboundWebhookTenantPolicy::class, AllowAllInboundWebhookTenantPolicy::class);
    }

    public function packageBooted(): void
    {
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
