<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Jobs;

use Dcodegroup\LaravelLoggedInboundEmail\Contracts\ProcessesInboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\InboundMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Default no-op handler. Replace via config `inbound-email.job` or by rebinding
 * what `ProcessesInboundEmail` resolves to (must be a class-string of your job).
 */
final class DefaultProcessInboundEmailJob implements ProcessesInboundEmail
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $message  InboundMessage::toArray()
     */
    public function __construct(
        public array $message,
        public string $orgAlias = '',
    ) {}

    public function handle(): void
    {
        $inbound = InboundMessage::fromArray($this->message);

        Log::debug('Inbound email received (set config inbound-email.job or bind ProcessesInboundEmail to your job class-string).', [
            'provider' => $inbound->provider,
            'subject' => $inbound->subject,
            'org_alias' => $this->orgAlias !== '' ? $this->orgAlias : null,
        ]);
    }
}
