<?php

namespace Touqeershafi\LaravelInboundEmail;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Touqeershafi\LaravelInboundEmail\Contracts\InboundWebhookHandler;
use Touqeershafi\LaravelInboundEmail\Handlers\MailgunHandler;
use Touqeershafi\LaravelInboundEmail\Handlers\MailpitHandler;
use Touqeershafi\LaravelInboundEmail\Handlers\PostmarkHandler;
use Touqeershafi\LaravelInboundEmail\Handlers\SendGridHandler;
use Touqeershafi\LaravelInboundEmail\Handlers\SesSnsHandler;

class InboundWebhookHandlerFactory
{
    public const ALLOWED_PROVIDERS = [
        'mailgun',
        'postmark',
        'sendgrid',
        'ses',
        'mailpit',
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
        };
    }
}
