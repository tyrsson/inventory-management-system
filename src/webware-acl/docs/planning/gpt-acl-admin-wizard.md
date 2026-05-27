---
goal: Implement the ACL admin wizard from the route-centric mockup in the current webware-acl module
version: 1.0
date_created: 2026-05-16
last_updated: 2026-05-16
owner: GitHub Copilot
status: Planned
tags: [feature, acl, admin-ui, htmx, wizard]
---

# Introduction

![Status: Planned](https://img.shields.io/badge/status-Planned-blue)

This plan translates `src/webware-acl/ui-mockup/acl-workflow.html` into the current `webware-acl` implementation. The live code already has a basic route-protection path via `ProtectRouteCommand`, `ProtectRouteHandler`, `ProcessProtectRouteMiddleware`, and the unprotected-route list in `src/webware-acl/templates/acl/admin-resources.phtml`, but it does not yet provide the mockup's route-centric Access Control page, protected-route summaries, or the 5-step wizard for grant type, role, privilege, assertion, and review.

## 1. Requirements & Constraints

- **REQ-001**: Implement the mockup as a route-centric Access Control workflow, not as another resource-centric form. The resulting page must expose both unprotected routes and already protected routes in one screen.
- **REQ-002**: Preserve the current ACL write architecture: request handlers remain render-only; write operations remain in `Process*Middleware` classes and command handlers.
- **REQ-003**: Keep all ACL admin writes on the command bus. The wizard submit must dispatch a typed command instead of writing directly from middleware or the handler.
- **REQ-004**: The wizard must support both entry points shown by the mockup: protecting an unprotected route and adding a new rule to an already protected route.
- **REQ-005**: The wizard must support the five mockup steps: grant mode, role selection, privilege selection, assertion selection, and review/confirm.
- **REQ-006**: The Access Control page must support search and status filters for `all`, `unprotected`, and `protected` routes.
- **REQ-007**: Protected route rows must display at least route name, allowed methods, derived privileges, rule count, and whether any assertion exists on that route's rules.
- **REQ-008**: Expanding a protected route row must reveal concrete rule rows and a fast path to add another rule without leaving the page.
- **REQ-009**: Reuse the existing HTMX success flow: `SystemMessengerInterface` for feedback and `HX-Trigger: closeModal` to close the modal after a successful submit.
- **REQ-010**: Support `allow` and `deny` rule types from the wizard. The current protect flow only creates `allow` rules and must be extended.
- **REQ-011**: Support inherited versus explicit grants without materializing duplicate descendant rules. Inherited behavior must continue to come from the existing role hierarchy in the ACL, not from denormalized copies.
- **REQ-012**: Support privilege subsets per route and one optional custom privilege in the wizard, as shown by the mockup.
- **REQ-013**: Support optional assertion attachment during the same submit, at minimum `OwnershipAssertion` and `StoreOwnedResourceAssertion` when available in the built ACL.
- **CON-001**: The live route-based ACL implementation is the source of truth. Do not reintroduce the superseded route-mapping model described in older documentation.
- **CON-002**: ACL admin access remains Developer-only through existing built-in rules. The plan must not widen ACL administration to Administrator.
- **CON-003**: No inline styles belong in templates. Visual rules from the mockup must move into `public/assets/css/custom.css` under `.ims-*` classes.
- **CON-004**: Interactive behavior must live in `public/assets/js/app.js` and must survive HTMX swaps by using the established page-init patterns.
- **CON-005**: URLs and assets must use view helpers such as `$this->url()` and `$this->basePath()`; no hardcoded links belong in the template.
- **PAT-001**: The existing `ProtectRouteCommand` and `ProtectRouteHandler` are already the local write slice for route protection. Prefer extending that slice over creating a second overlapping route-protection workflow.
- **PAT-002**: If assertions are attached in the same submit, `AclRepositoryInterface::saveRule()` must return the rule PK so the handler can call `saveRuleAssertion()` without an additional lookup.
- **PAT-003**: Role hierarchy data must come from `AclRepositoryInterface::fetchRoles()` and `AclRepositoryInterface::fetchRoleParents()`. The mockup's inline role tree is illustrative only and must not be copied literally.

## 2. Implementation Steps

### Implementation Phase 1

- **GOAL-001**: Replace the current overview page with a route-centric Access Control screen backed by real ACL and route data.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-001 | Modify `src/webware-acl/src/Admin/RequestHandler/AclOverviewHandler.php` so `admin.acl.read` becomes the primary route-centric Access Control page instead of a stats dashboard. The handler must prepare route inventory, protected/unprotected grouping, rule counts, role hierarchy data, and assertion summaries. |  |  |
| TASK-002 | Create `src/webware-acl/src/Admin/Middleware/BuildAccessControlMiddleware.php` plus `src/webware-acl/src/Admin/Middleware/Container/BuildAccessControlMiddlewareFactory.php`. The middleware runs on the GET pipeline before `AclOverviewHandler`, assembles route inventory from `AclRepositoryInterface` and `RouteCollectorInterface`, and attaches the result to the request as `$request->withAttribute(BuildAccessControlMiddleware::class, $viewModel)`. The view model array must contain `unprotectedRoutes`, `protectedRoutes`, `roleTree`, `roleChildren`, `routeFilters`, `roles`, and `roleParents`. This follows the same pattern as `RouteMiddleware` — data assembly is separated from rendering. |  |  |
| TASK-003 | Modify `src/webware-acl/src/Admin/RequestHandler/AclOverviewHandler.php` and its factory to read the view model from `$request->getAttribute(BuildAccessControlMiddleware::class)` instead of querying `AclRepositoryInterface` directly. The handler becomes a thin render-only class. Update `RouteProvider` to prepend `BuildAccessControlMiddleware` to the `admin.acl.read` GET pipeline. |  |  |
| TASK-004 | Keep `src/webware-acl/src/Admin/RequestHandler/ResourceListHandler.php` and `src/webware-acl/templates/acl/admin-resources.phtml` focused on low-level resource management. Remove the duplicate unprotected-route UX from that page or replace it with a link to `admin.acl.read` so there is only one canonical route-protection workflow. **Execution note:** TASK-002 and TASK-004 are tightly coupled — TASK-002 must be completed (route-inventory logic extracted into the builder) before TASK-004 removes the existing UX from `ResourceListHandler`, or the only live implementation of that logic will be lost. Do not execute these two tasks in parallel. |  |  |
| TASK-005 | Modify `src/webware-acl/src/RouteProvider.php` navigation labels and route comments as needed so the route-centric page is clearly presented as the main ACL management screen while preserving existing route names unless a rename is strictly required. |  |  |

### Implementation Phase 2

- **GOAL-002**: Implement the mockup layout, modal structure, and client-side interaction model on the Access Control page without violating the project's HTMX and template rules.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-006 | Rewrite `src/webware-acl/templates/acl/admin-acl.phtml` to match the mockup's route-centric layout: breadcrumb, header, search/filter controls, unprotected-routes section, protected-routes section, and expandable rules panels. |  |  |
| TASK-007 | Create `src/webware-acl/templates/acl/partials/protect-route-wizard.phtml` and render it from `admin-acl.phtml` so the five-step modal stays maintainable. The partial must receive route context, role hierarchy data, and assertion options from the handler. |  |  |
| TASK-008 | Move all wizard, route-list, role-tree, privilege-card, and summary styling from the mockup into `public/assets/css/custom.css` under new `.ims-acl-*` classes. Remove any inline `style="..."` usage introduced by the mockup translation. |  |  |
| TASK-009 | Extend `public/assets/js/app.js` with a dedicated ACL wizard controller that handles `openWizard`, step navigation, route search/filter, role filtering, privilege toggles, assertion selection, review population, and protected-route panel expansion. The controller must reinitialize after HTMX swaps using the existing event patterns. |  |  |
| TASK-010 | Reuse the existing `closeModal` cleanup behavior in `public/assets/js/app.js` and remove any mockup-only direct toast code. Success and failure feedback must come from server-side `SystemMessengerInterface` and HTMX response headers. |  |  |

### Implementation Phase 3

- **GOAL-003**: Extend the existing route-protection write path so one wizard submit can create or update the complete ACL entry represented by the mockup.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-011 | Modify `src/webware-acl/src/Admin/Command/ProtectRouteCommand.php` to carry the full wizard payload: `grantMode`, `ruleType`, selected `roleId`, `selectedPrivileges`, optional `customPrivilege`, optional `assertionFqcn`, and `assertionMode`, while preserving `routeName` and resolved `allowedMethods`. |  |  |
| TASK-012 | Modify `src/webware-acl/src/Admin/Middleware/ProcessProtectRouteMiddleware.php` to parse the wizard submit, validate the payload, resolve allowed methods from `RouteCollectorInterface`, dispatch the expanded `ProtectRouteCommand`, and attach `CommandResult::class` plus messenger feedback for the downstream render handler. |  |  |
| TASK-013 | Modify `src/webware-acl/src/Admin/CommandHandler/ProtectRouteHandler.php` so a single transaction can upsert the ACL resource, upsert method-derived privileges, optionally upsert one custom privilege, save `allow` or `deny` rules for the selected role, and attach an assertion when requested. The handler must rely on existing ACL inheritance instead of creating descendant copies for inherited grants. |  |  |
| TASK-014 | Modify `src/webware-acl/src/Repository/AclRepositoryInterface.php` and `src/webware-acl/src/Repository/AclRepository.php` so `saveRule()` returns the rule PK. The implementation must return the existing PK on update and the inserted PK on create. There are exactly two callers outside the interface: `ProtectRouteHandler` (FILE-009, already updated by TASK-013) and `SaveRuleHandler` (FILE-021). Both currently discard the return value so the change is safe, but `SaveRuleHandler` must be explicitly updated so its signature matches the new interface contract. |  |  |
| TASK-015 | Modify `src/webware-acl/src/Admin/RequestHandler/AclOverviewHandler.php` so it mirrors the success handling already used by `ResourceListHandler` and `RuleManagerHandler`: when a successful `CommandResult` is present, respond with `HX-Trigger: closeModal` while re-rendering the updated Access Control page. |  |  |
| TASK-016 | Add or repoint a POST endpoint in `src/webware-acl/src/RouteProvider.php` so the wizard can submit directly back to the Access Control page. If a new route such as `admin.acl.control.create` is introduced, keep `admin.acl.resources.protect` as a temporary compatibility alias until no template still posts to it. |  |  |

### Implementation Phase 4

- **GOAL-004**: Surface protected-route management on the Access Control page without replacing the dedicated Rules page for advanced editing.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-017 | Extend the Access Control read model so each protected route exposes a stable summary payload: `methods`, `derivedPrivileges`, `ruleCount`, `roles`, `hasAssertions`, and `rules[]`. Each `rules[]` entry must include `ruleId`, `type`, `roleId`, inheritance metadata, and attached assertions. |  |  |
| TASK-018 | Render delete actions for individual rules in `src/webware-acl/templates/acl/admin-acl.phtml` by reusing the existing `admin.acl.rules.delete` route. Render an `Add rule` action per protected route that reopens the wizard preloaded with that route's context. |  |  |
| TASK-019 | Use the existing role hierarchy to display inherited-versus-explicit context in the protected-route rules panel. The Access Control page should provide summary-level visibility only; the full hierarchy diagnostics remain on `admin.acl.rules.read`. |  |  |
| TASK-020 | Keep the dedicated pages for Roles, Resources, and Rules in place. The new Access Control page becomes the fast route-centric workflow, not a wholesale replacement for the deeper CRUD pages. |  |  |

### Implementation Phase 5

- **GOAL-005**: Add executable validation for the new read model, wizard write path, and HTMX integration.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-021 | Create `test/WebwareTest/Acl/Admin/ViewModel/AccessControlViewModelBuilderTest.php` to verify deterministic grouping of protected and unprotected routes, role-tree output, assertion summaries, and route filtering inputs. |  |  |
| TASK-022 | Create `test/WebwareTest/Acl/Admin/Middleware/ProcessProtectRouteMiddlewareTest.php` to verify wizard payload parsing, invalid-input handling, route-method resolution, messenger usage, and `CommandResult` propagation. |  |  |
| TASK-023 | Create `test/WebwareTest/Acl/Admin/CommandHandler/ProtectRouteHandlerTest.php` to verify transactional writes for `allow` and `deny`, custom privilege creation, returned rule PK usage, and optional assertion persistence. |  |  |
| TASK-024 | Create `test/WebwareTest/Acl/Admin/RequestHandler/AclOverviewHandlerTest.php` to verify Access Control page rendering and `HX-Trigger: closeModal` behavior after a successful wizard submit. |  |  |
| TASK-025 | Run a manual HTMX validation pass against the live page to confirm modal close behavior, backdrop cleanup, search/filter behavior, route panel expansion, and toast rendering on success and failure. |  |  |

## 3. Alternatives

- **ALT-001**: Keep the mockup inside `src/webware-acl/templates/acl/admin-resources.phtml` and make the resources page do double duty. Rejected because the mockup is explicitly route-centric and the current `admin.acl.read` page is the better ownership point for an Access Control workflow.
- **ALT-002**: Dispatch existing `SaveRuleCommand` and `SaveAssertionCommand` sequentially from middleware after a separate protect-route step. Rejected because one wizard submit needs one atomic transaction covering resource, privilege, rule, and assertion writes.
- **ALT-003**: Copy the mockup's inline CSS and inline JavaScript into the template for speed. Rejected because project rules require styles in `public/assets/css/custom.css`, behavior in `public/assets/js/app.js`, and HTMX-safe reinitialization.
- **ALT-004**: Materialize inherited grants as explicit rules on every descendant role. Rejected because the existing ACL hierarchy already provides inheritance semantics and denormalizing descendant rules would create maintenance and correctness problems.

## 4. Dependencies

- **DEP-001**: The current route-based ACL implementation already present in `src/webware-acl/src/Http/RouteResource.php`, `src/webware-acl/src/Middleware/RouteMiddleware.php`, and `src/webware-acl/src/Middleware/AuthorizingDispatchMiddleware.php`.
- **DEP-002**: Existing route-protection write slice in `src/webware-acl/src/Admin/Command/ProtectRouteCommand.php`, `src/webware-acl/src/Admin/Middleware/ProcessProtectRouteMiddleware.php`, and `src/webware-acl/src/Admin/CommandHandler/ProtectRouteHandler.php`.
- **DEP-003**: Existing role and rule read APIs in `src/webware-acl/src/Repository/AclRepositoryInterface.php` and `src/webware-acl/src/Repository/AclRepository.php`.
- **DEP-004**: Existing modal cleanup and HTMX integration in `public/assets/js/app.js`.
- **DEP-005**: Existing assertion implementations and listener wiring, especially `src/webware-acl/src/Assertion/OwnershipAssertion.php` and `src/webware-acl/src/Listener/RegisterOwnershipAssertionListener.php`.

## 5. Files

- **FILE-001**: `src/webware-acl/src/Admin/RequestHandler/AclOverviewHandler.php` — replace stats-only behavior with route-centric Access Control view composition and success-trigger handling.
- **FILE-002**: `src/webware-acl/src/Admin/RequestHandler/Container/AclOverviewHandlerFactory.php` — inject the new view-model builder.
- **FILE-003**: `src/webware-acl/src/Admin/Middleware/BuildAccessControlMiddleware.php` — new middleware that assembles the Access Control view model and attaches it as a request attribute before `AclOverviewHandler`.
- **FILE-004**: `src/webware-acl/src/Admin/Middleware/Container/BuildAccessControlMiddlewareFactory.php` — new factory for the middleware.
- **FILE-005**: `src/webware-acl/templates/acl/admin-acl.phtml` — rewrite to render the route-centric Access Control page.
- **FILE-006**: `src/webware-acl/templates/acl/partials/protect-route-wizard.phtml` — new wizard partial for the five-step modal.
- **FILE-007**: `src/webware-acl/src/Admin/Command/ProtectRouteCommand.php` — extend payload to represent the wizard submit.
- **FILE-008**: `src/webware-acl/src/Admin/Middleware/ProcessProtectRouteMiddleware.php` — parse and validate the wizard form.
- **FILE-009**: `src/webware-acl/src/Admin/CommandHandler/ProtectRouteHandler.php` — extend the transactional write path for rule type, privilege selection, custom privilege, and assertions.
- **FILE-010**: `src/webware-acl/src/Repository/AclRepositoryInterface.php` — change `saveRule()` contract to return the rule PK.
- **FILE-011**: `src/webware-acl/src/Repository/AclRepository.php` — return rule PK and persist assertions in the same transaction flow.
- **FILE-012**: `src/webware-acl/src/RouteProvider.php` — wire the wizard submit endpoint to the route-centric page.
- **FILE-013**: `src/webware-acl/src/Admin/RequestHandler/ResourceListHandler.php` — remove duplicated route-protection UI or replace it with a link to the canonical Access Control page.
- **FILE-014**: `src/webware-acl/templates/acl/admin-resources.phtml` — remove the old checkbox-based protect flow once the wizard is live.
- **FILE-015**: `public/assets/css/custom.css` — add `.ims-acl-*` classes for the mockup styling.
- **FILE-016**: `public/assets/js/app.js` — add ACL wizard and route-list interaction logic.
- **FILE-017**: `test/WebwareTest/Acl/Admin/ViewModel/AccessControlViewModelBuilderTest.php` — new builder tests.
- **FILE-018**: `test/WebwareTest/Acl/Admin/Middleware/ProcessProtectRouteMiddlewareTest.php` — new middleware tests.
- **FILE-019**: `test/WebwareTest/Acl/Admin/CommandHandler/ProtectRouteHandlerTest.php` — new command-handler tests.
- **FILE-020**: `test/WebwareTest/Acl/Admin/RequestHandler/AclOverviewHandlerTest.php` — new request-handler tests.
- **FILE-021**: `src/webware-acl/src/Admin/CommandHandler/SaveRuleHandler.php` — update to satisfy the `saveRule()` int return type introduced by TASK-014; the existing call site discards the return value and requires no logic change, only a signature-compatible call.

## 6. Testing

- **TEST-001**: Verify that the Access Control read model groups named routes into protected and unprotected buckets using the actual route collector and current ACL resources.
- **TEST-002**: Verify that unnamed routes, duplicate method-derived privileges, and routes already protected in `acl_resource` are handled deterministically.
- **TEST-003**: Verify that the wizard submit rejects missing route name, missing role, and invalid assertion identifiers before dispatching the command.
- **TEST-004**: Verify that `ProtectRouteHandler` creates `allow` and `deny` rules correctly and does not create descendant copies for inherited grants.
- **TEST-005**: Verify that `saveRule()` returns a rule PK usable for immediate `saveRuleAssertion()` calls on both create and update paths.
- **TEST-006**: Verify that a selected custom privilege is inserted once per resource and reused on subsequent submits.
- **TEST-007**: Verify that `AclOverviewHandler` emits `HX-Trigger: closeModal` only when a successful `CommandResult` is attached to the request.
- **TEST-008**: Manually verify route search/filter behavior, protected-route expansion, modal navigation, closeModal cleanup, and toast display in the browser.

## 7. Risks & Assumptions

- **RISK-001**: `RouteCollectorInterface` may expose framework or utility routes that should not appear in the Access Control page. The builder must define deterministic filtering rules for unnamed, duplicate, and internal routes.
- **RISK-002**: Changing `saveRule()` from `void` to `int` touches every caller. Existing callers must be checked so they continue to work when the method begins returning a value.
- **RISK-003**: The mockup combines route-centric management with rule-level detail. If the protected-route panel becomes too dense, the page can duplicate concerns already served by `admin.acl.rules.read`.
- **RISK-004**: Custom privileges can diverge from the route's HTTP-method-derived privileges if validation is too loose. The handler must define deterministic rules for when custom privileges are allowed.
- **RISK-005**: Documentation in `src/webware-acl/docs/authorization-middleware.md` contains historical duplication around old middleware naming. Implementation decisions must follow the live code, not the stale section.
- **ASSUMPTION-001**: The current route-based ACL implementation is already the active model and remains authoritative for this feature.
- **ASSUMPTION-002**: `OwnershipAssertion` and store-owned assertions remain valid options for wizard selection and stay registered during ACL build.
- **ASSUMPTION-003**: `admin.acl.read` remains the canonical Developer-only entry point for ACL management.

## 8. Related Specifications / Further Reading

- `src/webware-acl/ui-mockup/acl-workflow.html`
- `src/webware-acl/docs/admin-ui-workflows.md`
- `src/webware-acl/docs/architecture/blueprint.md`
- `src/webware-acl/docs/planning/route-resource-acl-implementation.md`
- `src/webware-acl/docs/planning/acl-admin-wizard-phase-2.md`
- `src/webware-acl/templates/acl/admin-resources.phtml`
- `src/webware-acl/src/Admin/Middleware/ProcessProtectRouteMiddleware.php`
- `src/webware-acl/src/Admin/CommandHandler/ProtectRouteHandler.php`