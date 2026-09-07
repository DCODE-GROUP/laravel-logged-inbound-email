<?php

use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmailAttachment;

it('transitions status when marked processing', function (): void {
    $inboundEmail = InboundEmail::factory()->create(['status' => InboundEmailStatus::Received]);

    $inboundEmail->markProcessing();

    expect($inboundEmail->fresh()->status)->toBe(InboundEmailStatus::Processing);
});

it('transitions status when marked processed', function (): void {
    $inboundEmail = InboundEmail::factory()->create(['status' => InboundEmailStatus::Processing]);

    $inboundEmail->markProcessed();

    expect($inboundEmail->fresh()->status)->toBe(InboundEmailStatus::Processed);
});

it('transitions status and sets error when marked failed', function (): void {
    $inboundEmail = InboundEmail::factory()->create(['status' => InboundEmailStatus::Processing]);

    $inboundEmail->markFailed('something went wrong');

    $fresh = $inboundEmail->fresh();

    expect($fresh->status)->toBe(InboundEmailStatus::Failed)
        ->and($fresh->error)->toBe('something went wrong');
});

it('clears the error column when marked failed without an error', function (): void {
    $inboundEmail = InboundEmail::factory()->create(['status' => InboundEmailStatus::Processing]);

    $inboundEmail->markFailed();

    expect($inboundEmail->fresh()->error)->toBeNull();
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
