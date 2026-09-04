# Triage Labels

The skills speak in terms of five canonical triage roles. In this repo's tracker (Solo MCP
todos) these are recorded as entries in a todo's `tags` array — there's no separate
"label" concept, so labels and tags are the same thing here.

| Label in mattpocock/skills | Tag in our tracker | Meaning                                  |
| --------------------------- | ------------------- | ----------------------------------------- |
| `needs-triage`               | `needs-triage`       | Maintainer needs to evaluate this issue  |
| `needs-info`                 | `needs-info`         | Waiting on reporter for more information |
| `ready-for-agent`            | `ready-for-agent`    | Fully specified, ready for an AFK agent  |
| `ready-for-human`            | `ready-for-human`    | Requires human implementation            |
| `wontfix`                    | `wontfix`            | Will not be actioned                     |

When a skill mentions a role (e.g. "apply the AFK-ready triage label"), add the
corresponding tag string from this table via `todo_update`'s `tags` field.
