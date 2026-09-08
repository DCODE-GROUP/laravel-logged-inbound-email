<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Unit\Models;

use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmailAttachment;
use Dcodegroup\LaravelLoggedInboundEmail\Tests\TestCase;

class InboundEmailTest extends TestCase
{
    public function test_status_can_be_transitioned_via_plain_attribute_assignment(): void
    {
        $inboundEmail = InboundEmail::factory()->create(['status' => InboundEmailStatus::Received]);

        $inboundEmail->update(['status' => InboundEmailStatus::Processing]);

        self::assertSame(InboundEmailStatus::Processing, $inboundEmail->fresh()->status);
    }

    public function test_error_can_be_set_alongside_a_failed_status(): void
    {
        $inboundEmail = InboundEmail::factory()->create(['status' => InboundEmailStatus::Processing]);

        $inboundEmail->update([
            'status' => InboundEmailStatus::Failed,
            'error' => 'something went wrong',
        ]);

        $fresh = $inboundEmail->fresh();

        self::assertSame(InboundEmailStatus::Failed, $fresh->status);
        self::assertSame('something went wrong', $fresh->error);
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
