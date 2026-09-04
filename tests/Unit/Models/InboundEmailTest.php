<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Unit\Models;

use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmailAttachment;
use Dcodegroup\LaravelLoggedInboundEmail\Tests\TestCase;

class InboundEmailTest extends TestCase
{
    public function test_mark_processing_transitions_status(): void
    {
        $inboundEmail = InboundEmail::factory()->create(['status' => InboundEmailStatus::Received]);

        $inboundEmail->markProcessing();

        self::assertSame(InboundEmailStatus::Processing, $inboundEmail->fresh()->status);
    }

    public function test_mark_processed_transitions_status(): void
    {
        $inboundEmail = InboundEmail::factory()->create(['status' => InboundEmailStatus::Processing]);

        $inboundEmail->markProcessed();

        self::assertSame(InboundEmailStatus::Processed, $inboundEmail->fresh()->status);
    }

    public function test_mark_failed_transitions_status_and_sets_error(): void
    {
        $inboundEmail = InboundEmail::factory()->create(['status' => InboundEmailStatus::Processing]);

        $inboundEmail->markFailed('something went wrong');

        $fresh = $inboundEmail->fresh();

        self::assertSame(InboundEmailStatus::Failed, $fresh->status);
        self::assertSame('something went wrong', $fresh->error);
    }

    public function test_mark_failed_without_error_clears_error_column(): void
    {
        $inboundEmail = InboundEmail::factory()->create(['status' => InboundEmailStatus::Processing]);

        $inboundEmail->markFailed();

        self::assertNull($inboundEmail->fresh()->error);
    }

    public function test_soft_deleting_inbound_email_cascades_to_attachments(): void
    {
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

        self::assertNull(InboundEmailAttachment::find($attachment->id));
        self::assertNotNull(InboundEmailAttachment::withTrashed()->find($attachment->id)->deleted_at);
    }

    public function test_force_deleting_inbound_email_does_not_run_the_soft_delete_cascade_twice(): void
    {
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
    }
}
