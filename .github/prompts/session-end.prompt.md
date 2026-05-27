---
mode: agent
description: "Run this at the END of every session to update session-context.md with decisions made, work completed, and next steps. Always run before closing the conversation."
---

# Session End Protocol

Execute every step below in order. Do not skip any step.

## Step 1 — Collect Session Summary

Review the current conversation and identify:

1. **Decisions confirmed** — design choices the user explicitly approved (not proposals, not assumptions from summaries)
2. **Work completed** — files created or modified, with their paths
3. **Work in progress** — anything started but not finished
4. **Pending next steps** — what the user indicated should happen next
5. **Patterns established** — any new conventions, rules, or architectural decisions that must carry forward
6. **Skills or docs updated** — any `.github/skills/*.md` or `docs/` files that were modified

## Step 2 — Update `session-context.md`

Read `.github/session-context.md` in full first.

Then update it using `replace_string_in_file` or `multi_replace_string_in_file`:

- Update the `_Last updated:` date at the top to today's date
- Add a new dated entry under **## Key Design Decisions** for anything confirmed this session
- Update **## Application Layer Status** for any files created or modified
- Update **## Next Steps** — mark completed items with ~~strikethrough~~, add new items
- **Never remove existing content** — only add or update

## Step 3 — Update Session Memory

Create or update `/memories/session/current.md` with:
- Branch name
- What was completed this session (bullet list)
- What is in progress (bullet list)  
- The single most important thing to remember at the start of the next session
- Any traps or decisions that the next session agent must not second-guess

## Step 4 — Confirm to User

Report:
1. What was added to `session-context.md`
2. What was written to session memory
3. Remind the user to invoke `/session-start` at the beginning of the next conversation

---

> ⚠ **INTEGRITY RULE**
> `session-context.md` is append-only for decisions and status. Never delete or shorten existing sections. Add new dated entries rather than overwriting old ones.
