<?php

use Dcodegroup\LaravelLoggedInboundEmail\Jobs\ProcessInboundEmailJob;
use Dcodegroup\LaravelLoggedInboundEmail\Support\EmailAddressTenantResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Route prefix
    |--------------------------------------------------------------------------
    |
    | Incoming webhooks are registered as POST {prefix}/{provider}, for example:
    | POST /webhooks/inbound/mailgun
    |
    | When organization_in_route is true, the pattern is:
    | POST {prefix}/{orgAlias}/{provider}  e.g. POST /webhooks/inbound/acme-corp/mailgun
    |
    */
    'route_prefix' => env('INBOUND_EMAIL_ROUTE_PREFIX', 'webhooks/inbound'),

    /*
    |--------------------------------------------------------------------------
    | Organization (tenant) in URL
    |--------------------------------------------------------------------------
    |
    | Enable SaaS-style URLs so each organization uses its own webhook path. The
    | orgAlias is passed to your inbound job as a separate constructor argument
    | (only when this is true — see README).
    |
    */
    'organization_in_route' => env('INBOUND_EMAIL_ORG_IN_ROUTE', false),

    /*
    | Regex for the {orgAlias} route segment (no delimiters). Override if your
    | slugs use other characters.
    |
    */
    'organization_alias_pattern' => env('INBOUND_EMAIL_ORG_ALIAS_PATTERN', '[a-zA-Z0-9][a-zA-Z0-9._-]*'),

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy
    |--------------------------------------------------------------------------
    |
    | When true, the `tenant_id` column (and its index) is added to the
    | inbound_emails table at migration time, and InboundEmail::tenant()
    | becomes available. Evaluated when the host app runs `php artisan
    | migrate`, after publishing and configuring the package — not at
    | vendor:publish time. Flipping this after the initial migration does
    | not retroactively add or drop the column; add your own follow-up
    | migration if you enable this later.
    |
    */
    'multi_tenant_enabled' => env('INBOUND_EMAIL_MULTI_TENANT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Email-based tenant discovery
    |--------------------------------------------------------------------------
    |
    | Enable identifying the tenant from the recipient address itself. The
    | package's default strategy is plus-addressing, e.g.
    | `{tenant_identifier}+{process}@domain`. Unlike organization_in_route,
    | this is not mutually exclusive with route-based discovery — both may be
    | enabled at once. When both produce a tenant identifier and they
    | disagree, the route-derived value wins.
    |
    */
    'email_based_tenancy_enabled' => env('INBOUND_EMAIL_EMAIL_BASED_TENANCY_ENABLED', false),

    /*
    | Fully-qualified class name of the resolver used to parse the tenant
    | identifier from the recipient address when email_based_tenancy_enabled
    | is true. Must implement
    | Dcodegroup\LaravelLoggedInboundEmail\Contracts\EmailBasedTenantResolver.
    | Override with your own class to use a different email-based tenancy
    | scheme (e.g. a lookup table or a different delimiter) instead of the
    | package's default plus-addressing.
    |
    */
    'tenant_resolver' => env('INBOUND_EMAIL_TENANT_RESOLVER', EmailAddressTenantResolver::class),

    /*
    |--------------------------------------------------------------------------
    | Tenant model
    |--------------------------------------------------------------------------
    |
    | Fully-qualified class name of your own tenant model. Declared here so
    | there is a single, unambiguous model class that InboundEmail::tenant_id
    | refers to. The package never resolves or sets tenant_id itself — you
    | are responsible for populating it on the InboundEmail row after it
    | exists (e.g. from organization_alias, or however your app maps
    | webhooks to tenants).
    |
    */
    'tenant_model' => env('INBOUND_EMAIL_TENANT_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Route middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Inbound email job (queued)
    |--------------------------------------------------------------------------
    |
    | Fully-qualified class name. Must implement ProcessesInboundEmail, use
    | Laravel's Dispatchable trait, and accept array $message (InboundMessage::toArray()).
    |
    */
    'job' => env('INBOUND_EMAIL_JOB', ProcessInboundEmailJob::class),

    /*
    |--------------------------------------------------------------------------
    | Queue connection & queue name for the inbound job dispatch
    |--------------------------------------------------------------------------
    */
    'queue_connection' => env('INBOUND_EMAIL_QUEUE_CONNECTION'),
    'queue' => env('INBOUND_EMAIL_QUEUE'),

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Attachments extracted from InboundMessage are written to this filesystem
    | disk, and one InboundEmailAttachment row is created per file. Defaults
    | to the application's default disk.
    |
    */
    'attachments' => [
        'disk' => env('INBOUND_EMAIL_ATTACHMENTS_DISK', config('filesystems.default')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider-specific settings
    |--------------------------------------------------------------------------
    */
    'providers' => [

        'mailgun' => [
            'signing_key' => env('INBOUND_EMAIL_MAILGUN_SIGNING_KEY'),
        ],

        'postmark' => [
            'webhook_secret' => env('INBOUND_EMAIL_POSTMARK_WEBHOOK_SECRET'),
        ],

        'sendgrid' => [
            'verification_key' => env('INBOUND_EMAIL_SENDGRID_VERIFICATION_KEY'),
        ],

        'ses' => [
            'allow_sns_message_without_signature' => env('INBOUND_EMAIL_SES_ALLOW_UNSIGNED_SNS', false),
            's3_disk' => env('INBOUND_EMAIL_SES_S3_DISK'),
        ],

        'mailpit' => [
            'base_url' => env('INBOUND_EMAIL_MAILPIT_BASE_URL', 'http://127.0.0.1:8025'),
            'api_token' => env('INBOUND_EMAIL_MAILPIT_API_TOKEN'),
            'webhook_secret' => env('INBOUND_EMAIL_MAILPIT_WEBHOOK_SECRET'),
        ],

        'resend' => [
            'webhook_secret' => env('INBOUND_EMAIL_RESEND_WEBHOOK_SECRET'),
            'api_key' => env('INBOUND_EMAIL_RESEND_API_KEY'),
            'api_base_url' => env('INBOUND_EMAIL_RESEND_API_BASE_URL', 'https://api.resend.com'),
        ],
    ],

];
