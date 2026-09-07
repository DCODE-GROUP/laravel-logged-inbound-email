<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Enums;

/**
 * The processing lifecycle of an InboundEmail record.
 *
 * Pending, Receiving, Received, and Failed are set exclusively by the
 * package itself (see InboundEmailRecorder). Processing and Processed are
 * set exclusively by the consuming app's own job, via markProcessing()/
 * markProcessed() (introduced in a later ticket) — the package never sets
 * them.
 */
enum InboundEmailStatus: string
{
    case Pending = 'pending';
    case Receiving = 'receiving';
    case Received = 'received';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
}
