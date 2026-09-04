# Triage Labels

The skills speak in terms of five canonical triage roles. Kanopi (this repo's tracker,
see `docs/agents/issue-tracker.md`) has no separate label state — there's no `add-label`
equivalent for tickets. Instead, each role maps to a ticket **status**, applied via
`mcp__Kanopi__update-ticket-status` with the machine name.

| Role in mattpocock/skills | Kanopi status (display) | Kanopi status (machine name) |
| --------------------------- | ------------------------- | ------------------------------ |
| `needs-triage`               | Backlog                   | `backlog`                       |
| `needs-info`                 | Client                    | `client`                        |
| `ready-for-agent`            | OnDeck                    | `on_deck` (confirmed live)      |
| `ready-for-human`            | ToDo                      | `to_do` (unconfirmed — verify before relying on it) |
| `wontfix`                    | Archived                  | `archived` (unconfirmed — verify before relying on it) |

`Client` is a semantic stretch — it normally means "waiting on the client," repurposed
here for "waiting on the ticket reporter," since Kanopi has no other "blocked on someone
outside the team" status. `ready-for-agent` vs `ready-for-human` is enforced by
convention (which status a skill sets), not by the tool itself.

When a skill mentions a role (e.g. "apply the AFK-ready triage label"), move the ticket
to the corresponding status from this table via `mcp__Kanopi__update-ticket-status`.
