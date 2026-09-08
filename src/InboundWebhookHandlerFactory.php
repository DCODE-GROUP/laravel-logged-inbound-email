<?php

namespace Dcodegroup\LaravelLoggedInboundEmail;

use Dcodegroup\LaravelLoggedInboundEmail\Contracts\InboundWebhookHandler;
use Dcodegroup\LaravelLoggedInboundEmail\Enums\Provider;
use Dcodegroup\LaravelLoggedInboundEmail\Handlers\MailgunHandler;
use Dcodegroup\LaravelLoggedInboundEmail\Handlers\MailpitHandler;
use Dcodegroup\LaravelLoggedInboundEmail\Handlers\PostmarkHandler;
use Dcodegroup\LaravelLoggedInboundEmail\Handlers\ResendHandler;
use Dcodegroup\LaravelLoggedInboundEmail\Handlers\SendGridHandler;
use Dcodegroup\LaravelLoggedInboundEmail\Handlers\SesSnsHandler;
use Illuminate\Contracts\Foundation\Application;

/**
 * Resolves the {@see InboundWebhookHandler} implementation responsible for
 * parsing and validating webhook payloads for a given inbound email {@see Provider}.
 */
class InboundWebhookHandlerFactory
{
    public function __construct(
        private readonly Application $app,
    ) {}

    public function make(Provider $provider): InboundWebhookHandler
    {
        return match ($provider) {
            Provider::Mailgun => $this->app->make(MailgunHandler::class),
            Provider::Postmark => $this->app->make(PostmarkHandler::class),
            Provider::SendGrid => $this->app->make(SendGridHandler::class),
            Provider::Ses => $this->app->make(SesSnsHandler::class),
            Provider::Mailpit => $this->app->make(MailpitHandler::class),
            Provider::Resend => $this->app->make(ResendHandler::class),
        };
    }
}
