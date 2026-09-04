# Laravel Logged Inbound Email

Receives inbound email webhooks from multiple providers, verifies and normalizes them, and hands the result to the consuming application for processing — while durably logging every received email as an Eloquent record.

## Language

**InboundMessage**:
The immutable, provider-agnostic value object built from a verified inbound webhook request within a single request cycle. Carries parsed envelope/body/attachment data for hand-off to the consuming app's queued job. Not persisted by itself.
_Avoid_: Email, message, payload

**InboundEmail**:
The Eloquent model that durably records a received inbound email: both the raw webhook receipt and its processing lifecycle. Combines what the upstream `trady` project splits into a generic `Webhook` model (raw payload + status) and a separate `InboundEmail` model (parsed fields + relations). Created by the package from an `InboundMessage`; the app advances its status and attaches it to its own domain records via `contactable`/`processable`.
_Avoid_: Webhook, log entry

**InboundEmail status**:
The processing lifecycle of an `InboundEmail` record: Pending → Receiving → Received → Processing → Processed, or Failed. One row is created per webhook and updated in place as it advances — never one row per transition. Webhook signature/tenant-policy verification failures are never recorded at all; the row only starts existing once verification has already passed. A failure *after* that point (e.g. malformed MIME during parsing, or the app's job calling `markFailed()`) lands on that same row as `Failed`, with an `error` column recording why.

**InboundEmailAttachment**:
A file attached to an inbound email, persisted to a configurable filesystem disk and linked to its parent `InboundEmail`. The attachment's content lives only on disk; `InboundEmail` never duplicates attachment bytes in its own columns. Soft-deleted in cascade with its parent `InboundEmail` — it has no independent audit value once the parent is gone.
_Avoid_: Media, file

**payload** (on InboundEmail):
The verbatim webhook body exactly as the provider sent it, stored unmodified for audit purposes. Distinct from the normalized columns (`from`, `to`, `subject`, etc.), which hold the parsed values extracted from that payload — mirroring the column layout of `trady`'s `InboundEmail` model.

**tenant_id / tenant_model** (on InboundEmail):
A nullable reference to the consuming app's own tenant record, resolved against a single model class the app declares via config (`tenant_model`). The package never populates this itself — the host app sets it after the row exists. Distinct from `organization_alias`, which is the raw `{orgAlias}` route segment string captured automatically when multi-tenant routing is enabled.
