<?php

namespace Touqeershafi\LaravelInboundEmail\Contracts;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Marker for your inbound-email queue job. Implement this on a class that uses
 * Laravel's job traits (`Dispatchable`, `Queueable`, etc.) and accepts the
 * serialized message array in its constructor (see package README).
 *
 * When `config('inbound-email.organization_in_route')` is true, the package also
 * passes a second constructor argument: the org alias string from the URL.
 */
interface ProcessesInboundEmail extends ShouldQueue {}
