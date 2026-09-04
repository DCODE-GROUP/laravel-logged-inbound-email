<?php

namespace Dcodegroup\LaravelLoggedInboundEmail;

use Dcodegroup\LaravelLoggedInboundEmail\Contracts\InboundWebhookHandler;
use Dcodegroup\LaravelLoggedInboundEmail\Handlers\MailgunHandler;
use Dcodegroup\LaravelLoggedInboundEmail\Handlers\MailpitHandler;
use Dcodegroup\LaravelLoggedInboundEmail\Handlers\PostmarkHandler;
use Dcodegroup\LaravelLoggedInboundEmail\Handlers\ResendHandler;
use Dcodegroup\LaravelLoggedInboundEmail\Handlers\SendGridHandler;
use Dcodegroup\LaravelLoggedInboundEmail\Handlers\SesSnsHandler;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

class InboundWebhookHandlerFactory
{
    public const ALLOWED_PROVIDERS = [
        'mailgun',
        'postmark',
        'sendgrid',
        'ses',
        'mailpit',
        'resend',
    ];

    public function __construct(
        private readonly Application $app,
    ) {}

    public function make(string $provider): InboundWebhookHandler
    {
        if (! in_array($provider, self::ALLOWED_PROVIDERS, true)) {
            throw new InvalidArgumentException("Unknown inbound email provider [{$provider}].");
        }

        return match ($provider) {
            'mailgun' => $this->app->make(MailgunHandler::class),
            'postmark' => $this->app->make(PostmarkHandler::class),
            'sendgrid' => $this->app->make(SendGridHandler::class),
            'ses' => $this->app->make(SesSnsHandler::class),
            'mailpit' => $this->app->make(MailpitHandler::class),
            'resend' => $this->app->make(ResendHandler::class),
        };
    }
}
