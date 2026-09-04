# Issue tracker: Solo MCP Todos

Issues and specs for this repo live as todo items in the Solo MCP task system, scoped to
Solo project id `27` (`laravel-logged-inbound-email`, path
`/Users/jny986/Herd/laravel-logged-inbound-email`).

## Conventions

- Tools: `mcp__solo__todo_create`, `todo_list`, `todo_get`, `todo_update`,
  `todo_comment_create` / `todo_comment_list`, `todo_add_blocker` / `todo_set_blockers` /
  `todo_remove_blocker`.
- `project_id`: `27`. Can usually be omitted — `whoami`'s `effective_project_id` resolves
  to this repo when run from within it.
- **Title**: short summary, same role as a GitHub issue title.
- **Body**: the full issue description (spec, ticket details, etc).
- **Tags**: used to record triage labels (see `triage-labels.md`) plus any other freeform
  categorisation, e.g. `effort:<slug>` to group a feature's todos.
- **Priority**: `high` / `medium` / `low` — set as appropriate; independent of triage state.
- **Status**: Solo's native lifecycle (`open` / `in_progress` / `backlog` / `completed`) is
  separate from triage label tags — a todo can be `open` and tagged `needs-triage` at the
  same time.
- **Comments**: use `todo_comment_create` / `todo_comment_list` for conversation history.
- **Blocking**: use `todo_add_blocker` / `todo_set_blockers` / `todo_remove_blocker` to
  record dependencies; filter with `is_blocked` on `todo_list` to find unblocked work.

## When a skill says "publish to the issue tracker"

Call `todo_create` with `project_id: 27`, a descriptive title, the issue body, and tags
(e.g. `["needs-triage"]`).

## When a skill says "fetch the relevant ticket"

Call `todo_get` with the referenced `todo_id` (`include_comments: true` if history
matters). The user will normally pass the `todo_id` or its title directly.

## Wayfinding operations

Used by `/wayfinder`. Todos have no file-based numbering, so:

- **Map**: a parent todo per effort, title prefixed with the effort name; body holds
  Notes / Decisions-so-far / Fog.
- **Child ticket**: a todo tagged `effort:<slug>` for grouping, with the question in the
  body.
- **Blocking**: `todo_set_blockers` records dependencies; a ticket is unblocked when every
  todo it lists is `completed`.
- **Frontier**: `todo_list` filtered by `tags: ["effort:<slug>"]`, `completed: false`,
  `is_blocked: false`; first match wins.
- **Claim**: `todo_update` with `status: "in_progress"` before starting work.
- **Resolve**: `todo_comment_create` with the answer, `todo_update` with
  `status: "completed"`, then append a decision pointer (todo id + gist) to the map
  todo's body.
