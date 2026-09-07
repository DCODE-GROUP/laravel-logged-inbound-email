<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Observers;

use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;

class InboundEmailObserver
{
    public function deleting(InboundEmail $inboundEmail): void
    {
        if ($inboundEmail->isForceDeleting()) {
            return;
        }

        $inboundEmail->attachments->each->delete();
    }
}
