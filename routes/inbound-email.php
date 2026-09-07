<?php

use Dcodegroup\LaravelLoggedInboundEmail\Http\Controllers\InboundWebhookController;
use Illuminate\Support\Facades\Route;

$orgInRoute = (bool) config('inbound-email.organization_in_route', false);
$orgPattern = (string) config('inbound-email.organization_alias_pattern', '[a-zA-Z0-9][a-zA-Z0-9._-]*');

if ($orgInRoute) {
    Route::post('{orgAlias}/{provider}', [InboundWebhookController::class, 'handleForOrganization'])
        ->where('orgAlias', $orgPattern)
        ->whereIn('provider', ['mailgun', 'postmark', 'sendgrid', 'ses', 'mailpit', 'resend']);
} else {
    Route::post('{provider}', [InboundWebhookController::class, 'handle'])
        ->whereIn('provider', ['mailgun', 'postmark', 'sendgrid', 'ses', 'mailpit', 'resend']);
}
