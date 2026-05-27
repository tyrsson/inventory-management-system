# Skill File Audit Log

Use this log to detect unauthorised modifications to skill files between sessions.
If a skill is missing its mandatory banner or has weakened language, compare against the last verified date below.

---

## 2026-05-15

**Session:** May 15, 2026 (branch: `acl-ownership-assertion-aggregates`)

**Skills verified and corrected:**

| Skill | Issue found | Fix applied |
|---|---|---|
| `webware-coding-standard` | Frontmatter used weak "Load when..." language | Updated to "ALWAYS load… No exceptions." |
| `webware-module-architecture` | Weak frontmatter; no mandatory banner | Frontmatter updated; mandatory banner added |
| `phpdb` | Mandatory banner **removed** — was present in a prior session | Banner restored; frontmatter updated to "ALWAYS load…" |

**Check for drift:**
```bash
git log --oneline .github/skills/
git diff <last-known-good-commit> -- .github/skills/
```

**Other changes recorded this session:**
- `UserInterface::isGuest()` added; `GuestUser` → `true`, `User` → `false`
- `$baseRole` config string removed from `ForbiddenHandler` and its factory
- `IdentityMiddlewareFactory` config key fixed: `'webware-acl'` → `AclInterface::class`
- `.php-cs-fixer.dist.php` excludes added: `.github`, `docs`, `data`, `resources`, `stubs`
- Webware component config key convention added to `webware-coding-standard` skill
