# Issue tracker: Kanopi

Issues and specs for this repo live as Kanopi tickets under the **Laravel Logged Inbound
Email** project (project ID `1598`), client **DCODE GROUP** (client ID `70`). Use the
`mcp__Kanopi__*` tools for all operations — there is no CLI equivalent.

## Conventions

- **Create a ticket**: `mcp__Kanopi__create-ticket` with `client_id: 70`, `project_id: 1598`, `name`, and `description`. Set `ticket_type` (`feature`, `bug`, `customer request`, `opportunity`, `warranty`, `maintenance`, `qa`) to match the work.
- **Read a ticket**: `mcp__Kanopi__show-ticket` with the ticket ID (accepts an array to batch-fetch). `mcp__Kanopi__list-ticket-comments` for its discussion.
- **List tickets**: `mcp__Kanopi__list-tickets` with `project_id: 1598`, optionally filtered by `status`, `ticket_type`, `assigned_user_id`, or date range.
- **Find a ticket by text**: `mcp__Kanopi__search-tickets` with a `query` string (full-text, ranked by relevance, not scoped to a project).
- **Comment on a ticket**: `mcp__Kanopi__add-ticket-comment`. Use `@[Full Name]` in the comment body to notify a user.
- **Change status**: `mcp__Kanopi__update-ticket-status` with the target status's machine name — do not set status via `update-ticket`. Discover valid machine names for this project by checking the `status` field on existing tickets (e.g. `Backlog`, `Client`, `Done`) via `list-tickets`.
- **Edit other fields** (name, description, assignee, priority, tags, milestone, stage, due date): `mcp__Kanopi__update-ticket`.
- **Tags**: look up tag IDs with `mcp__Kanopi__list-tags` (search by name) before passing them to `create-ticket`/`update-ticket`; Kanopi tags are org-wide, not project-scoped, so search before assuming a tag doesn't exist.

## Pull requests as a triage surface

**PRs as a request surface: no.** Kanopi has no concept of pull requests; PR review still happens on GitHub, but PRs are not pulled into the ticket triage queue.

## When a skill says "publish to the issue tracker"

Create a Kanopi ticket with `mcp__Kanopi__create-ticket` (`client_id: 70`, `project_id: 1598`).

## When a skill says "fetch the relevant ticket"

Run `mcp__Kanopi__show-ticket` with the ticket ID, and `mcp__Kanopi__list-ticket-comments` for its history.

## Triage-role → status mapping

Kanopi has no separate label state for triage roles — the five canonical roles map onto
this project's ticket **status** instead (set via `mcp__Kanopi__update-ticket-status`,
using the machine name, not the display name):

| Triage role | Status (display) | Status (machine name) |
| --- | --- | --- |
| `needs-triage` | Backlog | `backlog` |
| `needs-info` | Client | `client` |
| `ready-for-agent` | OnDeck | `on_deck` (confirmed live) |
| `ready-for-human` | ToDo | `to_do` (unconfirmed — verify before relying on it) |
| `wontfix` | Archived | `archived` (unconfirmed — verify before relying on it) |

`Client` is a semantic stretch — it normally means "waiting on the client," repurposed
here for "waiting on the ticket reporter" since there's no other "blocked on someone
outside the team" status. `ready-for-agent` vs `ready-for-human` is enforced by
convention (which status you set), not by the tool itself.
