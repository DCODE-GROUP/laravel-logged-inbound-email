<?php

namespace Touqeershafi\LaravelInboundEmail\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Touqeershafi\LaravelInboundEmail\InboundMessage;

interface InboundWebhookHandler
{
    /**
     * @throws HttpException
     */
    public function verify(Request $request): void;

    /**
     * Return null when the webhook should acknowledge without queueing (e.g. SNS subscription confirmation).
     */
    public function toInboundMessage(Request $request): ?InboundMessage;
}
