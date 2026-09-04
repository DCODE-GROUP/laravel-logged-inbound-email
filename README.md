# Laravel Inbound Email

Multi-provider inbound email webhooks for Laravel. Incoming HTTP requests are verified per provider, normalized into an `InboundMessage` DTO, and handled asynchronously via **one** queued job class you configure.

**Supported providers:** Mailgun, Postmark, SendGrid, Amazon SES (via SNS), Mailpit, Resend.

**Requirements:** PHP `^8.4`, Laravel `12.x`+ (Illuminate `^12`–`^13` per `composer.json`).

---

## Install

```bash
composer require dcodegroup/laravel-logged-inbound-email
```

The package registers its service provider automatically (`extra.laravel.providers` in Composer).

### Publish configuration (recommended)

```bash
php artisan vendor:publish --tag=logged-inbound-email-config
```

This copies `config/inbound-email.php` into your app. Until you publish, the package merges the same defaults from the vendor file.

---

## Routes

Routes are registered under a configurable **prefix** (default `webhooks/inbound`). Middleware defaults to `api` (no session/CSRF). If you switch to `web`, add these paths to `VerifyCsrfToken` `$except` or use stateless verification.

### Single-tenant (default)

`organization_in_route` is `false`. Pattern:

```http
POST {prefix}/{provider}
```

Examples (default prefix):

| Provider  | Example path                      |
| --------- | --------------------------------- |
| Mailgun   | `POST /webhooks/inbound/mailgun`  |
| Postmark  | `POST /webhooks/inbound/postmark`   |
| SendGrid  | `POST /webhooks/inbound/sendgrid`   |
| SES (SNS) | `POST /webhooks/inbound/ses`        |
| Mailpit   | `POST /webhooks/inbound/mailpit`    |
| Resend    | `POST /webhooks/inbound/resend`     |

### Multi-tenant / SaaS

Set `INBOUND_EMAIL_ORG_IN_ROUTE=true` or `config(['inbound-email.organization_in_route' => true])`. Pattern:

```http
POST {prefix}/{orgAlias}/{provider}
```

Example: `POST https://your-app.test/webhooks/inbound/acme-corp/mailgun`

- **`{orgAlias}`** — Your tenant slug (per organization). It must match the regex in `organization_alias_pattern` (default: slug-like ASCII; see `config/inbound-email.php`).
- The job receives **`$orgAlias` as a separate constructor argument** from the normalized message array (see [Processing messages](#processing-messages)).

**Switching modes:** With multi-tenant routes enabled, old single-segment URLs such as `POST /webhooks/inbound/mailgun` are **not** registered. Point each provider’s webhook at the per-organization URL instead.

**Prefix:** `INBOUND_EMAIL_ROUTE_PREFIX` or `config('inbound-email.route_prefix')` (no leading/trailing slashes required in env; the package trims as needed).

---

## Configuration overview

| Env / concern | Purpose |
| ------------- | ------- |
| `INBOUND_EMAIL_ROUTE_PREFIX` | URL prefix for all inbound routes (default `webhooks/inbound`). |
| `INBOUND_EMAIL_ORG_IN_ROUTE` | `true` = `{orgAlias}/{provider}` URLs; `false` = `{provider}` only. |
| `INBOUND_EMAIL_ORG_ALIAS_PATTERN` | Regex (no delimiters) for `{orgAlias}` when org routing is on. |
| `INBOUND_EMAIL_JOB` | FQCN of your queued job (implements `ProcessesInboundEmail`). Default: package `DefaultProcessInboundEmailJob` (debug log only). |
| `INBOUND_EMAIL_QUEUE_CONNECTION` | Optional queue connection for the dispatch. |
| `INBOUND_EMAIL_QUEUE` | Optional queue name for the dispatch. |

Provider secrets and options (set only what you use):

| Env | Provider |
| --- | -------- |
| `INBOUND_EMAIL_MAILGUN_SIGNING_KEY` | Mailgun |
| `INBOUND_EMAIL_POSTMARK_WEBHOOK_SECRET` | Postmark |
| `INBOUND_EMAIL_SENDGRID_VERIFICATION_KEY` | SendGrid |
| `INBOUND_EMAIL_SES_ALLOW_UNSIGNED_SNS`, `INBOUND_EMAIL_SES_S3_DISK` | SES |
| `INBOUND_EMAIL_MAILPIT_BASE_URL`, `INBOUND_EMAIL_MAILPIT_API_TOKEN`, `INBOUND_EMAIL_MAILPIT_WEBHOOK_SECRET` | Mailpit |
| `INBOUND_EMAIL_RESEND_WEBHOOK_SECRET`, `INBOUND_EMAIL_RESEND_API_KEY`, `INBOUND_EMAIL_RESEND_API_BASE_URL` | Resend |

Full keys and comments live in the published `config/inbound-email.php`.

---

## Processing messages

The webhook controller verifies the request, builds an `InboundMessage`, and dispatches **your** job class from `config('inbound-email.job')`. There is no extra wrapper job.

### Job requirements

1. Implement `Dcodegroup\LaravelLoggedInboundEmail\Contracts\ProcessesInboundEmail` (extends `ShouldQueue`).
2. Use `Illuminate\Foundation\Bus\Dispatchable` (and typically `Queueable`, `InteractsWithQueue`, `SerializesModels`).
3. **`array $message`** — Serialized `InboundMessage` (`InboundMessage::toArray()` shape).
4. **Multi-tenant only:** second constructor parameter **`string $orgAlias`** — value of `{orgAlias}` from the URL. When `organization_in_route` is `false`, the package dispatches with **only** `$message`, so a one-argument constructor remains valid for single-tenant setups.

Rebuild the DTO in `handle()`:

```php
$inbound = \Dcodegroup\LaravelLoggedInboundEmail\InboundMessage::fromArray($this->message);
```

### Dispatch behavior

| `organization_in_route` | Call |
| ------------------------ | ---- |
| `false` | `YourJob::dispatch($messageArray)` |
| `true` | `YourJob::dispatch($messageArray, $orgAlias)` |

Queue connection and queue name from config are applied to the pending dispatch when set.

### Example job

```php
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Dcodegroup\LaravelLoggedInboundEmail\Contracts\ProcessesInboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\InboundMessage;

final class HandleInboundEmail implements ProcessesInboundEmail
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $message
     */
    public function __construct(
        public array $message,
        public string $orgAlias = '',
    ) {}

    public function handle(): void
    {
        $inbound = InboundMessage::fromArray($this->message);

        // When using organization_in_route, resolve the tenant from $this->orgAlias.
        // Use $inbound->provider, ->subject, ->text, ->metadata, etc.
    }
}
```

### Registering your job class

**Environment or published config:**

```env
INBOUND_EMAIL_JOB=App\Jobs\HandleInboundEmail
```

**Runtime (e.g. `AppServiceProvider`):**

```php
$this->app->boot(function (): void {
    config(['inbound-email.job' => \App\Jobs\HandleInboundEmail::class]);
});
```

---

## Development

```bash
composer test      # PHPUnit
composer analyse   # PHPStan
composer format    # Laravel Pint
```
