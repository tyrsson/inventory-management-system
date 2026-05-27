# webware-acl Deprecation Tracker

Components flagged for removal or refactor. Do not delete anything on this list
until the new UI covers the relevant functionality end-to-end and the replacement
component is confirmed working.

---

## Pattern Violations — Needs Refactor (data assembly in handler)

These handlers fetch data and build view models directly instead of reading from
a middleware-set request attribute. Each needs a corresponding `Build*Middleware`
extracted first; then the handler becomes render-only and the `AclRepositoryInterface`
constructor dependency is removed.

| File | Violation | Replacement |
|------|-----------|-------------|
| `src/Admin/RequestHandler/RuleManagerHandler.php` | Fetches roles, role parents, resources, privileges, rules, assertions; builds hierarchy rows, filter logic, and display maps inline. | Extract to `BuildRulesMiddleware`. Register before `RuleManagerHandler` in all rule-related pipelines. |
| `src/Admin/RequestHandler/RoleListHandler.php` | Fetches roles and role parents directly. | Extract to `BuildRolesMiddleware`. Register before `RoleListHandler` in all role-related pipelines. |
| `src/Admin/RequestHandler/ResourceListHandler.php` | Fetches resources and related data directly (verify). | Extract to `BuildResourcesMiddleware`. Register before `ResourceListHandler` in all resource-related pipelines. |

---

## Old UI Pages — Candidates for Removal (pending wizard coverage)

These templates and their associated routes/handlers exist from the pre-wizard UI.
Once `admin-acl.phtml` + the wizard covers all their functionality, these become
obsolete. Do not remove until feature parity is confirmed.

| Template | Handler(s) | Routes | Blocked on |
|----------|------------|--------|------------|
| `templates/acl/admin-rules.phtml` | `RuleManagerHandler` | `admin.acl.rules.*` | Wizard covering rule edit / toggle / hierarchy view |
| `templates/acl/admin-roles.phtml` | `RoleListHandler` | `admin.acl.roles.*` | Wizard or overview covering role CRUD |
| `templates/acl/admin-resources.phtml` | `ResourceListHandler` | `admin.acl.resources.*` | Wizard or overview covering resource CRUD |
| `templates/acl/admin-widget.phtml` | _(unknown — verify)_ | _(verify)_ | Verify if still referenced anywhere |

---

## JS — Dead Code After Modal Fix

| File | Code | Reason |
|------|------|--------|
| `public/assets/js/app.js` | `confirmBtn.setAttribute('hx-swap', 'none')` block and `htmx:afterRequest` listener added during delete-modal debugging | Replace with server-side `HX-Redirect` to `Referer` once `BuildRulesMiddleware` lands and pipelines are rewired |

---

## Notes

- `BuildAccessControlMiddleware` + `AclOverviewHandler` is the **reference implementation**
  of the correct pattern. All new middleware/handler pairs should follow it.
- The `HX-Redirect` to `Referer` on successful delete (in `RuleManagerHandler`) is a
  stop-gap until the pipeline is properly separated. Once `BuildRulesMiddleware` exists,
  the delete pipeline can terminate at `AclOverviewHandler` instead.
