<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Support;

use Dcodegroup\LaravelLoggedInboundEmail\Contracts\InboundWebhookHandler;
use Dcodegroup\LaravelLoggedInboundEmail\Enums\InboundEmailStatus;
use Dcodegroup\LaravelLoggedInboundEmail\InboundMessage;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmailAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Persists the InboundEmail row for a webhook that has already passed
 * verification, and drives it through the package-owned portion of its
 * status lifecycle: Pending -> Receiving -> Received, or Failed.
 *
 * One row is created per webhook and updated in place; nothing here ever
 * runs before InboundWebhookTenantPolicy::assertInboundAllowed() and the
 * provider handler's verify() have both already succeeded.
 */
class InboundEmailRecorder
{
    /**
     * Create the Pending row, parse the request via the given handler, and
     * advance the row through Receiving to Received/Failed accordingly.
     *
     * Returns the parsed InboundMessage, or null when the handler
     * legitimately determined the webhook was never an email (e.g. an SNS
     * SubscriptionConfirmation) — in which case no InboundEmail row is left
     * behind at all.
     *
     * The given $organizationAlias should already be resolved by the caller
     * to null unless multi-tenant routing (`organization_in_route`) is
     * enabled and the `{orgAlias}` route segment was present; this method
     * simply stores whatever it is given.
     *
     * @throws Throwable re-thrown after marking the row Failed
     */
    public function record(Request $request, string $provider, InboundWebhookHandler $handler, ?string $organizationAlias = null): ?InboundMessage
    {
        $inboundEmail = $this->createPending($request, $provider, $organizationAlias);

        $inboundEmail->update(['status' => InboundEmailStatus::Receiving]);

        try {
            $message = $handler->toInboundMessage($request);
        } catch (Throwable $e) {
            $inboundEmail->update([
                'status' => InboundEmailStatus::Failed,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        if ($message === null) {
            $inboundEmail->delete();

            return null;
        }

        $this->markReceived($inboundEmail, $message);

        return $message;
    }

    private function createPending(Request $request, string $provider, ?string $organizationAlias): InboundEmail
    {
        return InboundEmail::create([
            'payload' => $this->rawPayload($request),
            'provider' => $provider,
            'organization_alias' => $organizationAlias,
            'status' => InboundEmailStatus::Pending,
        ]);
    }

    private function markReceived(InboundEmail $inboundEmail, InboundMessage $message): void
    {
        $inboundEmail->update([
            'provider' => $message->provider,
            'from' => $message->from,
            'to' => $message->to,
            'cc' => $message->cc,
            'bcc' => $message->bcc,
            'reply_to' => $message->replyTo,
            'subject' => $message->subject,
            'text_content' => $message->text,
            'html_content' => $message->html,
            'message_id' => $this->extractMessageId($message),
            'received_at' => Carbon::now(),
            'status' => InboundEmailStatus::Received,
        ]);

        $this->storeAttachments($inboundEmail, $message);
    }

    /**
     * Write each InboundMessage attachment's (base64-encoded) content to the
     * configured disk, and create one InboundEmailAttachment row per file.
     */
    private function storeAttachments(InboundEmail $inboundEmail, InboundMessage $message): void
    {
        if ($message->attachments === []) {
            return;
        }

        $disk = (string) config('inbound-email.attachments.disk');

        foreach ($message->attachments as $attachment) {
            $filename = $attachment['filename'];
            $contentType = $attachment['content_type'];
            $content = base64_decode($attachment['content_base64'], true);
            $content = $content === false ? '' : $content;

            $path = sprintf(
                'inbound-email-attachments/%d/%s-%s',
                $inboundEmail->id,
                Str::random(20),
                $filename,
            );

            Storage::disk($disk)->put($path, $content);

            InboundEmailAttachment::create([
                'inbound_email_id' => $inboundEmail->id,
                'filename' => $filename,
                'disk' => $disk,
                'path' => $path,
                'content_type' => $contentType,
                'size' => strlen($content),
            ]);
        }
    }

    /**
     * The verbatim webhook body, exactly as received. Falls back to
     * reconstructing a form-encoded body from the parsed request parameters
     * for requests whose raw content is unavailable (e.g. non-JSON POSTs in
     * the test HTTP client, where Symfony's Request::create() never
     * populates php://input).
     */
    private function rawPayload(Request $request): string
    {
        $content = $request->getContent();

        if ($content !== '') {
            return $content;
        }

        return http_build_query($request->all());
    }

    /**
     * Best-effort dedup reference (not enforced unique at this stage). Every
     * handler that has a real provider message ID stores it in metadata
     * under a `*_message_id` key; fall back to a `Message-Id` header parsed
     * from raw MIME when present.
     */
    private function extractMessageId(InboundMessage $message): ?string
    {
        foreach ($message->metadata as $key => $value) {
            if (is_string($value) && $value !== '' && str_ends_with($key, '_message_id')) {
                return $value;
            }
        }

        foreach ($message->headers as $key => $value) {
            if ($value !== '' && strtolower($key) === 'message-id') {
                return $value;
            }
        }

        return null;
    }
}
