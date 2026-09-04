<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Models;

use Dcodegroup\LaravelLoggedInboundEmail\Database\Factories\InboundEmailFactory;
use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
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
 * @property int|null $contactable_id
 * @property string|null $contactable_type
 * @property int|null $processable_id
 * @property string|null $processable_type
 * @property Carbon|null $deleted_at
 */
class InboundEmail extends Model
{
    /** @use HasFactory<InboundEmailFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'inbound_emails';

    protected $guarded = [];

    protected static function newFactory(): InboundEmailFactory
    {
        return InboundEmailFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $inboundEmail): void {
            if ($inboundEmail->isForceDeleting()) {
                return;
            }

            $inboundEmail->attachments->each->delete();
        });
    }

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

    /**
     * @return HasMany<InboundEmailAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(InboundEmailAttachment::class);
    }

    /**
     * Whichever of the consuming app's own models represents "who this came
     * from" (a Contact, a Customer, etc.). Never populated by the package.
     *
     * @return MorphTo<Model, $this>
     */
    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whatever business record this email produced (an Order, a Job, a
     * Ticket, etc.). Never populated by the package.
     *
     * @return MorphTo<Model, $this>
     */
    public function processable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * For the consuming app's own job to call once it starts acting on this
     * email. Never called by the package itself.
     */
    public function markProcessing(): bool
    {
        return $this->update(['status' => InboundEmailStatus::Processing]);
    }

    /**
     * For the consuming app's own job to call once it has finished acting on
     * this email successfully. Never called by the package itself.
     */
    public function markProcessed(): bool
    {
        return $this->update(['status' => InboundEmailStatus::Processed]);
    }

    /**
     * For the consuming app's own job to call when its own processing fails.
     * Never called by the package itself.
     */
    public function markFailed(?string $error = null): bool
    {
        return $this->update([
            'status' => InboundEmailStatus::Failed,
            'error' => $error,
        ]);
    }
}
