<?php

use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmailAttachment;

it('transitions status via plain attribute assignment', function (): void {
    $inboundEmail = InboundEmail::factory()->create(['status' => InboundEmailStatus::Received]);

    $inboundEmail->update(['status' => InboundEmailStatus::Processing]);

    expect($inboundEmail->fresh()->status)->toBe(InboundEmailStatus::Processing);
});

it('sets error alongside a failed status', function (): void {
    $inboundEmail = InboundEmail::factory()->create(['status' => InboundEmailStatus::Processing]);

    $inboundEmail->update([
        'status' => InboundEmailStatus::Failed,
        'error' => 'something went wrong',
    ]);

    $fresh = $inboundEmail->fresh();

    expect($fresh->status)->toBe(InboundEmailStatus::Failed)
        ->and($fresh->error)->toBe('something went wrong');
});

it('cascades soft deletes to attachments', function (): void {
    $inboundEmail = InboundEmail::factory()->create();

    $attachment = $inboundEmail->attachments()->create([
        'filename' => 'invoice.pdf',
        'disk' => 'local',
        'path' => 'inbound-email-attachments/1/invoice.pdf',
        'content_type' => 'application/pdf',
        'size' => 1234,
    ]);

    $inboundEmail->delete();

    $this->assertSoftDeleted($inboundEmail);
    $this->assertSoftDeleted($attachment);

    expect(InboundEmailAttachment::find($attachment->id))->toBeNull()
        ->and(InboundEmailAttachment::withTrashed()->find($attachment->id)->deleted_at)->not->toBeNull();
});

it('does not run the soft delete cascade twice when force deleting', function (): void {
    $inboundEmail = InboundEmail::factory()->create();

    $inboundEmail->attachments()->create([
        'filename' => 'invoice.pdf',
        'disk' => 'local',
        'path' => 'inbound-email-attachments/1/invoice.pdf',
        'content_type' => 'application/pdf',
        'size' => 1234,
    ]);

    $inboundEmail->forceDelete();

    $this->assertDatabaseCount('inbound_emails', 0);
});
