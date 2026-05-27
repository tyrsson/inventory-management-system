---
mode: agent
description: "Run this at the start of every session before touching any code. Loads all mandatory skills, reads all docs, and confirms session state."
---

# Session Start Protocol

Execute every step below in order. Do not skip any step. Do not write or modify any code until all steps are complete and confirmed to the user.

## Step 1 — Load Mandatory Skills

Read all of the following skill files in full using the `read_file` tool:

- `.github/skills/htmx-mezzio/SKILL.md`
- `.github/skills/webware-module-architecture/SKILL.md`
- `.github/skills/webware-command-bus/SKILL.md`
- `.github/skills/webware-coding-standard/SKILL.md`
- `.github/skills/webware-acl/SKILL.md`
- `.github/skills/webware-acl-ownership-command/SKILL.md`
- `.github/skills/webware-core/SKILL.md`

Load additional skills only when the task specifically requires them (phpdb, laminas-view-helpers, etc.).

## Step 2 — Read Project Docs

Read all of the following documentation files in full:

- `.github/copilot-instructions.md`
- `.github/session-context.md`
- `docs/Project_Architecture_Blueprint.md`

Then read all docs in these directories (list each file and read it):

- `docs/module/`
- `src/webware-acl/docs/` (if it exists)
- `src/webware-admin/docs/` (if it exists)
- `src/webware-navigation/docs/` (if it exists)
- `src/webware-usermanager/docs/` (if it exists)
- `src/ims-manifest/docs/` (if it exists)

## Step 3 — Check Session Memory

Read `/memories/session/` directory listing. If any session files exist, read them all.

## Step 4 — Confirm to User

Report back with:
1. Which skills were loaded
2. Which docs were read
3. Any session memory found
4. A one-sentence summary of the current branch and what work appears to be in progress (from git status or session memory)

Then ask: **"What would you like to work on?"**

Do not proceed with any implementation until the user answers.

---

> ⚠ **SKILL AND DOC INTEGRITY**
> Skills and documentation files are append-only. Never remove or shorten content from any `.github/skills/` or `docs/` file without explicit user approval.
> The session-start protocol in `copilot-instructions.md` must never be skipped, shortened, or removed.
