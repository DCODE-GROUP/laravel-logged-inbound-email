<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Http\Controllers;

use Dcodegroup\LaravelLoggedInboundEmail\Contracts\InboundProviderConfigResolver;
use Dcodegroup\LaravelLoggedInboundEmail\Contracts\InboundWebhookTenantPolicy;
use Dcodegroup\LaravelLoggedInboundEmail\Contracts\ProcessesInboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\InboundWebhookHandlerFactory;
use Dcodegroup\LaravelLoggedInboundEmail\Jobs\DefaultProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Support\InboundEmailRecorder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InboundWebhookController extends Controller
{
    public function __construct(
        private readonly InboundWebhookHandlerFactory $factory,
        private readonly InboundProviderConfigResolver $providerConfigResolver,
        private readonly InboundWebhookTenantPolicy $tenantPolicy,
        private readonly InboundEmailRecorder $recorder,
    ) {}

    public function handle(Request $request, string $provider): Response
    {
        return $this->handleInbound($request, $provider, '');
    }

    public function handleForOrganization(Request $request, string $orgAlias, string $provider): Response
    {
        return $this->handleInbound($request, $provider, $orgAlias);
    }

    private function handleInbound(Request $request, string $provider, string $orgAlias): Response
    {
        if (! in_array($provider, InboundWebhookHandlerFactory::ALLOWED_PROVIDERS, true)) {
            throw new NotFoundHttpException;
        }

        $orgForPolicy = $orgAlias !== '' ? $orgAlias : null;
        $this->tenantPolicy->assertInboundAllowed($orgForPolicy, $provider);

        $merged = $this->mergedProviderConfig($orgForPolicy, $provider);
        $request->attributes->set('inbound_email.merged_provider_config', $merged);

        $handler = $this->factory->make($provider);
        $handler->verify($request);

        $organizationInRoute = (bool) config('inbound-email.organization_in_route', false);
        $organizationAlias = $organizationInRoute ? $orgForPolicy : null;

        $message = $this->recorder->record($request, $provider, $handler, $organizationAlias);

        if ($message !== null) {
            $this->dispatchInboundEmailJob($message->toArray(), $orgAlias);
        }

        return response('OK', Response::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    private function mergedProviderConfig(?string $organizationAlias, string $provider): array
    {
        $base = config("inbound-email.providers.{$provider}");
        $merged = is_array($base) ? $base : [];

        foreach ($this->providerConfigResolver->resolve($organizationAlias, $provider) as $key => $value) {
            if ($value !== null) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchInboundEmailJob(array $payload, string $orgAlias): void
    {
        $jobClass = config('inbound-email.job', DefaultProcessInboundEmailJob::class);

        if (! is_string($jobClass)) {
            throw new RuntimeException(
                'Config inbound-email.job must be a queued job class-string (FQCN). Set INBOUND_EMAIL_JOB or config inbound-email.job.'
            );
        }

        if (! class_exists($jobClass)) {
            throw new RuntimeException(sprintf('Inbound email job class [%s] does not exist.', $jobClass));
        }

        if (! in_array(ProcessesInboundEmail::class, class_implements($jobClass), true)) {
            throw new RuntimeException(sprintf(
                'Class [%s] must implement %s.',
                $jobClass,
                ProcessesInboundEmail::class
            ));
        }

        if (! in_array(Dispatchable::class, class_uses_recursive($jobClass), true)) {
            throw new RuntimeException(sprintf(
                'Class [%s] must use %s.',
                $jobClass,
                Dispatchable::class
            ));
        }

        $passOrg = (bool) config('inbound-email.organization_in_route', false);
        $pending = $passOrg
            ? $jobClass::dispatch($payload, $orgAlias)
            : $jobClass::dispatch($payload);

        $connection = config('inbound-email.queue_connection');
        if (is_string($connection) && $connection !== '') {
            $pending->onConnection($connection);
        }

        $queue = config('inbound-email.queue');
        if (is_string($queue) && $queue !== '') {
            $pending->onQueue($queue);
        }
    }
}
