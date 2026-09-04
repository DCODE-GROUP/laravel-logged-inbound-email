<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Models;

use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Durable record of a received inbound email: both the raw webhook receipt
 * (`payload`) and its parsed, normalized fields, tracked through a status
 * lifecycle (see InboundEmailStatus).
 *
 * @property int $id
 * @property string $payload
 * @property string $provider
 * @property array<int, array{email: string, name: ?string}>|null $from
 * @property array<int, array{email: string, name: ?string}> $to
 * @property array<int, array{email: string, name: ?string}> $cc
 * @property array<int, array{email: string, name: ?string}> $bcc
 * @property array{email: string, name: ?string}|null $reply_to
 * @property string|null $subject
 * @property string|null $text_content
 * @property string|null $html_content
 * @property string|null $message_id
 * @property Carbon|null $received_at
 * @property InboundEmailStatus $status
 * @property string|null $error
 * @property string|null $organization_alias
 * @property int|null $tenant_id
 */
class InboundEmail extends Model
{
    protected $table = 'inbound_emails';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from' => 'array',
            'to' => 'array',
            'cc' => 'array',
            'bcc' => 'array',
            'reply_to' => 'array',
            'received_at' => 'datetime',
            'status' => InboundEmailStatus::class,
        ];
    }
}
