<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single attachment extracted from an inbound email, with its content
 * written to a configurable filesystem disk (see `inbound-email.attachments.disk`).
 * The attachment's bytes live only on disk; this row just remembers where.
 *
 * @property int $id
 * @property int $inbound_email_id
 * @property string $filename
 * @property string $disk
 * @property string $path
 * @property string|null $content_type
 * @property int|null $size
 */
class InboundEmailAttachment extends Model
{
    protected $table = 'inbound_email_attachments';

    protected $guarded = [];

    /**
     * @return BelongsTo<InboundEmail, $this>
     */
    public function inboundEmail(): BelongsTo
    {
        return $this->belongsTo(InboundEmail::class);
    }
}
