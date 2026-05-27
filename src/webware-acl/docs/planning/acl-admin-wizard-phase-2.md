# ACL Admin Wizard — Phase 2 Implementation Plan

**Branch:** `acl-ownership-assertion-aggregates`  
**Planned:** 2026-05-16  
**Status:** Ready to execute  
**Prerequisite:** Phase 1 (`route-resource-acl-implementation.md`) executed.

---

## Summary

Implements the 5-step wizard from `src/webware-acl/ui-mockup/acl-workflow.html` as a
server-rendered HTMX workflow. The wizard covers both "Protect a new route" and "Add a
rule to an already-protected route" in one atomic operation.

**Wizard steps (matching the mockup):**
1. Grant type — allow/deny + inherited/explicit
2. Role — hierarchy tree with propagation preview
3. Privilege — CRUD cards (pre-checked from route methods) + custom privilege toggle
4. Assertion — None (pre-selected), OwnershipAssertion, StoreOwnedResourceAssertion
5. Review & Confirm

---

## Architecture Decisions

- **Single composite command** (`SaveAccessControlEntryCommand`) atomically: protects the
  route (upsert — safe to call on already-protected routes), saves one rule per selected
  privilege, and optionally attaches an assertion to each rule.
- **`saveRule()` return type** changed from `void` to `int` (returns the rule PK). Required
  so the handler can pass the PK to `saveRuleAssertion()` without a second SELECT. Existing
  callers ignore the return value — no breaking change at call sites.
- **Wizard modal** is embedded in `admin-resources.phtml` and pre-populated on page load
  with the full role list, privilege list, and assertion options. Step navigation is pure
  JS (matching the mockup). Only the final submit hits the server.
- **Final submit endpoint:** `POST /admin/access/control` →
  `ProcessAccessControlEntryMiddleware` → `ResourceListHandler`. The existing
  `ResourceListHandler` re-renders the resources page; the modal closes via the existing
  `HX-Trigger: {"closeModal": null}` pattern already in place.
- **Route name:** `admin.acl.control.create`. Must be seeded in `acl_resource` with
  Developer-only access (see seed note at bottom).

---

## File List

| # | File | Action |
|---|---|---|
| 1 | `src/webware-acl/src/Repository/AclRepositoryInterface.php` | MODIFY — `saveRule()` return `int` |
| 2 | `src/webware-acl/src/Repository/AclRepository.php` | MODIFY — `saveRule()` return `int` |
| 3 | `src/webware-acl/src/Admin/Command/SaveAccessControlEntryCommand.php` | CREATE |
| 4 | `src/webware-acl/src/Admin/CommandHandler/SaveAccessControlEntryHandler.php` | CREATE |
| 5 | `src/webware-acl/src/Admin/CommandHandler/Container/SaveAccessControlEntryHandlerFactory.php` | CREATE |
| 6 | `src/webware-acl/src/Admin/Middleware/ProcessAccessControlEntryMiddleware.php` | CREATE |
| 7 | `src/webware-acl/src/Admin/Middleware/Container/ProcessAccessControlEntryMiddlewareFactory.php` | CREATE |
| 8 | `src/webware-acl/src/RouteProvider.php` | MODIFY — add POST route |
| 9 | `src/webware-acl/src/ConfigProvider.php` | MODIFY — register new services + command |
| 10 | `src/webware-acl/templates/acl/admin-resources.phtml` | MODIFY — embed wizard modal |

---

## File 1 — `AclRepositoryInterface.php` (MODIFY)

Change the `saveRule()` signature to return `int`:

```php
/**
 * Inserts an allow/deny rule for a role+resource+privilege triple.
 * Uses INSERT … ON DUPLICATE KEY UPDATE to upsert by the unique key
 * (role_pk, resource_pk, privilege_pk).
 * Returns the PK of the inserted or existing rule row.
 */
public function saveRule(int $rolePk, int $resourcePk, int $privilegePk, string $type): int;
```

---

## File 2 — `AclRepository.php` (MODIFY)

Change `saveRule()` return type from `void` to `int` and return the PK:

```php
#[Override]
public function saveRule(int $rolePk, int $resourcePk, int $privilegePk, string $type): int
{
    $sql    = new Sql($this->adapter, 'acl_rule');
    $select = $sql->select()
        ->columns(['id'])
        ->where(['role_pk' => $rolePk, 'resource_pk' => $resourcePk, 'privilege_pk' => $privilegePk]);

    $existing = $sql->prepareStatementForSqlObject($select)->execute()->current();

    if ($existing !== false && $existing !== null) {
        $id     = (int) $existing['id'];
        $update = $sql->update()->set(['type' => $type])->where(['id' => $id]);
        $sql->prepareStatementForSqlObject($update)->execute();

        return $id;
    }

    $insert = $sql->insert()->values([
        'role_pk'      => $rolePk,
        'resource_pk'  => $resourcePk,
        'privilege_pk' => $privilegePk,
        'type'         => $type,
    ]);
    $sql->prepareStatementForSqlObject($insert)->execute();

    return (int) $this->adapter->getDriver()->getLastGeneratedValue();
}
```

---

## File 3 — `SaveAccessControlEntryCommand.php` (CREATE)

```php
<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\Command;

use Webware\CommandBus\Command\NamedCommandInterface;
use Webware\CommandBus\Command\NamedCommandTrait;

/**
 * Atomically protects a route (upsert) + saves one rule per selected privilege
 * + optionally attaches an assertion to each rule.
 *
 * Safe to dispatch for already-protected routes — saveResource() and
 * insertPrivilege() are both upserts.
 */
final readonly class SaveAccessControlEntryCommand implements NamedCommandInterface
{
    use NamedCommandTrait;

    /**
     * @param string[]    $allowedMethods  e.g. ['GET', 'POST'] — resolved from RouteCollector
     * @param string[]    $privileges      privilege IDs selected in step 3, e.g. ['read', 'create']
     * @param string|null $assertionFqcn   null = no assertion; FQCN string otherwise
     */
    public function __construct(
        public string  $routeName,
        public array   $allowedMethods,
        public int     $rolePk,
        public array   $privileges,
        public string  $grantType,
        public ?string $assertionFqcn = null,
        public string  $assertionMode = 'all',
    ) {}
}
```

---

## File 4 — `SaveAccessControlEntryHandler.php` (CREATE)

```php
<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\CommandHandler;

use Override;
use Throwable;
use Webware\Acl\Admin\Command\SaveAccessControlEntryCommand;
use Webware\Acl\Cache\AclCacheInterface;
use Webware\Acl\PrivilegeInterface;
use Webware\Acl\Repository\AclRepositoryInterface;
use Webware\CommandBus\Command\CommandHandlerInterface;
use Webware\CommandBus\Command\CommandInterface;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandStatus;

use function array_filter;
use function array_map;
use function array_unique;
use function assert;
use function ucfirst;

final class SaveAccessControlEntryHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly AclRepositoryInterface $aclRepository,
        private readonly AclCacheInterface $aclCache,
    ) {}

    #[Override]
    public function handle(CommandInterface $command): CommandResultInterface
    {
        assert($command instanceof SaveAccessControlEntryCommand);

        $this->aclRepository->beginTransaction();

        try {
            // Upsert the route resource (no-op if already protected)
            $resourcePk = $this->aclRepository->saveResource(
                $command->routeName,
                $command->routeName,
            );

            // Upsert one privilege row per HTTP-method-derived privilege,
            // building a map of privilege_id → privilege_pk for rule creation.
            $methodPrivileges = array_unique(array_filter(array_map(
                static fn(string $m): string => PrivilegeInterface::METHOD_PRIVILEGE_MAP[$m] ?? '',
                $command->allowedMethods,
            )));

            $privilegePkMap = [];
            foreach ($methodPrivileges as $privilegeId) {
                $privilegePkMap[$privilegeId] = $this->aclRepository->insertPrivilege(
                    $resourcePk,
                    $privilegeId,
                    ucfirst($privilegeId),
                );
            }

            // Create one rule per user-selected privilege; attach assertion if provided.
            foreach ($command->privileges as $privilegeId) {
                $privilegePk = $privilegePkMap[$privilegeId] ?? null;

                if ($privilegePk === null) {
                    // Selected privilege not derivable from the route's allowed methods — skip.
                    continue;
                }

                $rulePk = $this->aclRepository->saveRule(
                    $command->rolePk,
                    $resourcePk,
                    $privilegePk,
                    $command->grantType,
                );

                if ($command->assertionFqcn !== null) {
                    $this->aclRepository->saveRuleAssertion(
                        $rulePk,
                        $command->assertionFqcn,
                        $command->assertionMode,
                        0,
                    );
                }
            }

            $this->aclRepository->incrementVersion();
            $this->aclCache->invalidate();
            $this->aclRepository->commit();
        } catch (Throwable $e) {
            $this->aclRepository->rollback();
            throw $e;
        }

        return new CommandResult($command, CommandStatus::Success, null);
    }
}
```

> **Note:** `AclCacheInterface::invalidate()` — verify this method exists on the interface.
> If the cache exposes only `get()`/`set()`, call `$this->aclRepository->incrementVersion()`
> alone; the `FileAclCache` reads the version on the next request and rebuilds.

---

## File 5 — `SaveAccessControlEntryHandlerFactory.php` (CREATE)

```php
<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\CommandHandler\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\Admin\CommandHandler\SaveAccessControlEntryHandler;
use Webware\Acl\Cache\AclCacheInterface;
use Webware\Acl\Repository\AclRepositoryInterface;

final class SaveAccessControlEntryHandlerFactory
{
    public function __invoke(ContainerInterface $container): SaveAccessControlEntryHandler
    {
        return new SaveAccessControlEntryHandler(
            $container->get(AclRepositoryInterface::class),
            $container->get(AclCacheInterface::class),
        );
    }
}
```

---

## File 6 — `ProcessAccessControlEntryMiddleware.php` (CREATE)

Reads the wizard form submission and dispatches `SaveAccessControlEntryCommand`.

**Wizard form field names** (set by the template — see File 10):

| Field | Type | Description |
|---|---|---|
| `routeName` | string | Route being protected |
| `allowedMethods[]` | string[] | Resolved server-side from RouteCollector, not posted |
| `rolePk` | int | Selected role PK (step 2) |
| `privileges[]` | string[] | Selected privilege IDs, e.g. `['read','create']` (step 3) |
| `grantType` | string | `'allow'` or `'deny'` (step 1) |
| `assertionFqcn` | string | FQCN or empty string for None (step 4) |
| `assertionMode` | string | `'all'` (default; reserved for future multi-assertion support) |

```php
<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\Middleware;

use Axleus\Message\SystemMessengerInterface;
use Mezzio\Router\RouteCollectorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\Admin\Command\SaveAccessControlEntryCommand;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandBusInterface;
use Webware\Core\HttpMethodProcessorTrait;

use function array_filter;
use function array_map;
use function array_values;
use function is_array;
use function strval;

final class ProcessAccessControlEntryMiddleware implements MiddlewareInterface
{
    use HttpMethodProcessorTrait;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly RouteCollectorInterface $routeCollector,
    ) {}

    public function processPost(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $body      = (array) $request->getParsedBody();
        $messenger = $request->getAttribute(SystemMessengerInterface::class);

        $routeName    = (string)  ($body['routeName']    ?? '');
        $rolePk       = (int)     ($body['rolePk']       ?? 0);
        $grantType    = (string)  ($body['grantType']    ?? 'allow');
        $assertionFqcn = ($body['assertionFqcn'] ?? '') !== ''
            ? (string) $body['assertionFqcn']
            : null;
        $assertionMode = (string) ($body['assertionMode'] ?? 'all');

        $privileges = array_values(array_filter(array_map(
            strval(...),
            is_array($body['privileges'] ?? null) ? $body['privileges'] : [],
        )));

        // Resolve allowed methods from the registered route definition
        $allowedMethods = ['GET'];
        foreach ($this->routeCollector->getRoutes() as $route) {
            if ($route->getName() === $routeName) {
                $allowedMethods = $route->getAllowedMethods() ?? ['GET'];
                break;
            }
        }

        $result = $this->commandBus->handle(new SaveAccessControlEntryCommand(
            routeName:     $routeName,
            allowedMethods: $allowedMethods,
            rolePk:        $rolePk,
            privileges:    $privileges,
            grantType:     $grantType,
            assertionFqcn: $assertionFqcn,
            assertionMode: $assertionMode,
        ));

        if ($result->getStatus() === CommandStatus::Success) {
            $messenger?->success(
                "Access control entry saved for '{$routeName}'.",
                hops: 0,
                now: true,
            );
        } else {
            $messenger?->error(
                "Failed to save access control entry for '{$routeName}'.",
                hops: 0,
                now: true,
            );
        }

        return $handler->handle($request->withAttribute(CommandResult::class, $result));
    }
}
```

---

## File 7 — `ProcessAccessControlEntryMiddlewareFactory.php` (CREATE)

```php
<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\Middleware\Container;

use Mezzio\Router\RouteCollectorInterface;
use Psr\Container\ContainerInterface;
use Webware\Acl\Admin\Middleware\ProcessAccessControlEntryMiddleware;
use Webware\CommandBus\CommandBusInterface;

final class ProcessAccessControlEntryMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): ProcessAccessControlEntryMiddleware
    {
        return new ProcessAccessControlEntryMiddleware(
            $container->get(CommandBusInterface::class),
            $container->get(RouteCollectorInterface::class),
        );
    }
}
```

---

## File 8 — `RouteProvider.php` (MODIFY)

Add one route after the existing `admin.acl.resources.protect` route:

```php
// Access control wizard submit — POST saves rule (+ optional protect + assertion)
$routeCollector->post(
    '/admin/access/control',
    $middlewareFactory->prepare([
        BodyParamsMiddleware::class,
        ProcessAccessControlEntryMiddleware::class,
        ResourceListHandler::class,
    ]),
    'admin.acl.control.create'
);
```

Add import at top of file:

```php
use Webware\Acl\Admin\Middleware\ProcessAccessControlEntryMiddleware;
```

---

## File 9 — `ConfigProvider.php` (MODIFY)

**`getDependencies()` factories block** — add:

```php
ProcessAccessControlEntryMiddleware::class   => ProcessAccessControlEntryMiddlewareFactory::class,
SaveAccessControlEntryHandler::class         => SaveAccessControlEntryHandlerFactory::class,
```

**`getBusConfig()` command map** — add:

```php
SaveAccessControlEntryCommand::class => SaveAccessControlEntryHandler::class,
```

**New `use` statements** (add to the existing import block in alphabetical order within each group):

```php
use Webware\Acl\Admin\Command\SaveAccessControlEntryCommand;
use Webware\Acl\Admin\CommandHandler\Container\SaveAccessControlEntryHandlerFactory;
use Webware\Acl\Admin\CommandHandler\SaveAccessControlEntryHandler;
use Webware\Acl\Admin\Middleware\Container\ProcessAccessControlEntryMiddlewareFactory;
use Webware\Acl\Admin\Middleware\ProcessAccessControlEntryMiddleware;
```

---

## File 10 — `admin-resources.phtml` (MODIFY)

Add the wizard modal **at the very end of the template**, after all existing content.

The modal structure mirrors `acl-workflow.html` exactly. It contains all 5 steps, all
pre-populated with data from the template variables already available in
`ResourceListHandler::handle()` (`$roles`, `$roleParents`, `$resources`, `$privileges`).

Step navigation is pure Bootstrap + JS (matching the mockup). The modal is opened by
clicking either the existing "Protect" button (unprotected section) or a new "Add Rule"
button (protected section).

### Changes to existing Protect button

The existing Protect button in the unprotected section currently submits directly via HTMX
(`hx-post` to `admin.acl.resources.protect`). Replace it with a button that opens the
wizard modal instead, pre-filling the route name in a hidden field:

```php
<!-- REPLACE the existing hx-post Protect button with: -->
<button
    type="button"
    class="btn btn-sm btn-warning py-0 px-2 ims-wizard-open"
    data-route-name="<?= $this->escapeHtmlAttr($routeName) ?>"
    data-route-methods="<?= $this->escapeHtmlAttr(implode(',', $methods)) ?>"
    data-bs-toggle="modal"
    data-bs-target="#aclWizardModal"
>
    <i class="bi bi-shield-check me-1"></i>Protect
</button>
```

### Add Rule button (protected routes section)

Each protected route row already renders in the accordion. Add an "Add Rule" button to
each accordion panel footer:

```php
<button
    type="button"
    class="btn btn-sm btn-outline-primary py-0 px-2 ims-wizard-open"
    data-route-name="<?= $this->escapeHtmlAttr($resource->resourceId) ?>"
    data-route-methods="<?= $this->escapeHtmlAttr(implode(',', $routeMethods[$resource->resourceId] ?? ['GET'])) ?>"
    data-bs-toggle="modal"
    data-bs-target="#aclWizardModal"
>
    <i class="bi bi-plus-lg me-1"></i>Add Rule
</button>
```

> **Note:** `$routeMethods` is a map of `resourceId → methods[]` that the handler must
> build. Add this to `ResourceListHandler::handle()`:
>
> ```php
> $routeMethods = [];
> foreach ($this->routeCollector->getRoutes() as $route) {
>     $name = $route->getName();
>     if ($name !== null) {
>         $routeMethods[$name] = $route->getAllowedMethods() ?? ['GET'];
>     }
> }
> ```
>
> Pass `'routeMethods' => $routeMethods` into the template render call.

### Wizard modal HTML

```php
<!-- ACL Wizard Modal — appended after all existing template content -->
<?php
use Webware\Acl\PrivilegeInterface as Priv;

// Build role hierarchy tree data for the role step
$roleParentMap = [];
foreach ($this->roleParents as $childPk => $parentPks) {
    foreach ($parentPks as $parentPk) {
        $roleParentMap[$parentPk][] = $childPk;
    }
}

// Assertion options
$assertionOptions = [
    ''                                                        => ['label' => 'None',                        'icon' => 'bi-slash-circle',      'desc' => 'No ownership check required.'],
    'Webware\\Acl\\Assertion\\OwnershipAssertion'             => ['label' => 'Ownership Assertion',         'icon' => 'bi-person-lock',       'desc' => 'Passes only when the authenticated user\'s ID matches the resource\'s user_id route parameter.'],
    'Ims\\Store\\Acl\\StoreOwnedResourceAssertion'            => ['label' => 'Store Owned Resource',        'icon' => 'bi-building-lock',     'desc' => 'Passes only when the active store ID matches the resource\'s store_id.'],
];
?>

<div class="modal fade" id="aclWizardModal" tabindex="-1" aria-labelledby="aclWizardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <!-- Modal Header — route context bar (always visible) -->
            <div class="modal-header py-2 border-bottom">
                <div class="d-flex align-items-center gap-3 flex-wrap w-100">
                    <h5 class="modal-title mb-0" id="aclWizardModalLabel">
                        <i class="bi bi-shield-lock me-2 text-primary"></i>Access Control Wizard
                    </h5>
                    <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
                        <code class="small ims-wizard-route-display text-primary"></code>
                        <span class="ims-wizard-methods-display"></span>
                        <span class="ims-wizard-privs-display"></span>
                    </div>
                    <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <!-- Stepper -->
            <div class="modal-body pb-0">
                <div class="d-flex align-items-center justify-content-between mb-4 px-1">
                    <?php foreach ([1 => 'Grant Type', 2 => 'Role', 3 => 'Privilege', 4 => 'Assertion', 5 => 'Review'] as $n => $label): ?>
                    <div class="d-flex flex-column align-items-center ims-wizard-step-indicator <?= $n === 1 ? 'ims-wizard-step-active' : '' ?>" data-step="<?= $n ?>">
                        <div class="ims-wizard-step-circle"><?= $n ?></div>
                        <small class="mt-1 text-secondary"><?= $label ?></small>
                    </div>
                    <?php if ($n < 5): ?>
                    <div class="ims-wizard-step-connector flex-grow-1"></div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Wizard Form -->
            <form
                id="aclWizardForm"
                hx-post="<?= $this->url('admin.acl.control.create') ?>"
                hx-target="main"
                hx-swap="innerHTML"
            >
                <input type="hidden" name="routeName"     id="wizRouteName">
                <input type="hidden" name="assertionMode" value="all">

                <div class="modal-body pt-2">

                    <!-- Step 1: Grant Type -->
                    <div class="ims-wizard-panel" data-panel="1">
                        <p class="text-secondary small mb-3">Choose whether this rule allows or denies access, and whether the role is applied directly or inherited through the hierarchy.</p>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Access Type</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="grantType" id="grantAllow" value="allow" checked>
                                    <label class="form-check-label" for="grantAllow">
                                        <span class="badge bg-success-subtle border border-success-subtle text-success-emphasis me-1">Allow</span>
                                        Grant access
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="grantType" id="grantDeny" value="deny">
                                    <label class="form-check-label" for="grantDeny">
                                        <span class="badge bg-danger-subtle border border-danger-subtle text-danger-emphasis me-1">Deny</span>
                                        Explicitly deny
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info small py-2 mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Inherited:</strong> Setting a rule on a parent role automatically applies to all child roles unless overridden.
                        </div>
                    </div>

                    <!-- Step 2: Role -->
                    <div class="ims-wizard-panel d-none" data-panel="2">
                        <p class="text-secondary small mb-3">Select the role this rule applies to. Child roles inherit the grant unless they have an explicit override.</p>
                        <input type="hidden" name="rolePk" id="wizRolePk">
                        <div class="ims-role-tree border rounded p-3" style="max-height:320px;overflow-y:auto;">
                            <?php
                            // Recursive role tree render — starts from root roles (no parents)
                            $rootPks = [];
                            foreach ($this->roles as $pk => $role) {
                                if (empty($this->roleParents[$pk])) {
                                    $rootPks[] = $pk;
                                }
                            }
                            function renderRoleTree(array $roles, array $children, array $pks, int $depth = 0): void
                            {
                                foreach ($pks as $pk) {
                                    if (!isset($roles[$pk])) continue;
                                    $role = $roles[$pk];
                                    $indent = $depth * 16;
                                    echo '<div class="ims-role-item d-flex align-items-center gap-2 py-1 px-2 rounded mb-1 ims-role-selectable" '
                                        . 'data-role-pk="' . $pk . '" '
                                        . 'style="margin-left:' . $indent . 'px;cursor:pointer;">'
                                        . '<i class="bi bi-shield-' . ($depth === 0 ? 'fill' : 'half') . ' text-secondary small"></i>'
                                        . '<span class="small">' . htmlspecialchars($role->roleId) . '</span>'
                                        . '</div>';
                                    if (!empty($children[$pk])) {
                                        renderRoleTree($roles, $children, $children[$pk], $depth + 1);
                                    }
                                }
                            }
                            renderRoleTree($this->roles, $roleParentMap, $rootPks);
                            ?>
                        </div>
                        <div class="mt-2 small text-secondary">
                            Selected: <strong id="wizRoleDisplay" class="text-body">None</strong>
                        </div>
                    </div>

                    <!-- Step 3: Privilege -->
                    <div class="ims-wizard-panel d-none" data-panel="3">
                        <p class="text-secondary small mb-3">Select which privileges this rule covers. Cards are pre-checked based on the route's HTTP methods.</p>
                        <div class="row g-2" id="wizPrivCards">
                            <?php foreach (['read' => ['GET','bi-eye','primary'], 'create' => ['POST','bi-plus-circle','success'], 'update' => ['PUT/PATCH','bi-pencil','warning'], 'delete' => ['DELETE','bi-trash','danger']] as $privId => [$methods, $icon, $color]): ?>
                            <div class="col-6">
                                <div class="card border ims-priv-card" data-priv="<?= $privId ?>">
                                    <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
                                        <input class="form-check-input ims-priv-check" type="checkbox" name="privileges[]" value="<?= $privId ?>" id="wizPriv<?= ucfirst($privId) ?>">
                                        <label class="form-check-label d-flex align-items-center gap-2 w-100" for="wizPriv<?= ucfirst($privId) ?>">
                                            <i class="bi <?= $icon ?> text-<?= $color ?>"></i>
                                            <div>
                                                <div class="fw-semibold small"><?= ucfirst($privId) ?></div>
                                                <div class="text-secondary" style="font-size:.75rem;"><?= $methods ?></div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Step 4: Assertion -->
                    <div class="ims-wizard-panel d-none" data-panel="4">
                        <p class="text-secondary small mb-3">Optionally require an ownership check. Select None if no assertion is needed.</p>
                        <div class="row g-2">
                            <?php foreach ($assertionOptions as $fqcn => $opt): ?>
                            <div class="col-12">
                                <div class="card border ims-assertion-card <?= $fqcn === '' ? 'border-primary' : '' ?>"
                                     data-assertion-fqcn="<?= $this->escapeHtmlAttr($fqcn) ?>">
                                    <div class="card-body py-2 px-3 d-flex align-items-center gap-3">
                                        <i class="bi <?= $opt['icon'] ?> fs-5 text-secondary"></i>
                                        <div>
                                            <div class="fw-semibold small"><?= $opt['label'] ?></div>
                                            <div class="text-secondary" style="font-size:.75rem;"><?= $opt['desc'] ?></div>
                                        </div>
                                        <i class="bi bi-check-circle-fill text-primary ms-auto ims-assertion-check <?= $fqcn === '' ? '' : 'd-none' ?>"></i>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="assertionFqcn" id="wizAssertionFqcn" value="">
                    </div>

                    <!-- Step 5: Review -->
                    <div class="ims-wizard-panel d-none" data-panel="5">
                        <p class="text-secondary small mb-3">Review your selections before saving.</p>
                        <dl class="row small mb-0">
                            <dt class="col-4 text-secondary">Route</dt>
                            <dd class="col-8 ims-review-route"></dd>
                            <dt class="col-4 text-secondary">Grant Type</dt>
                            <dd class="col-8 ims-review-grant"></dd>
                            <dt class="col-4 text-secondary">Role</dt>
                            <dd class="col-8 ims-review-role"></dd>
                            <dt class="col-4 text-secondary">Privileges</dt>
                            <dd class="col-8 ims-review-privs"></dd>
                            <dt class="col-4 text-secondary">Assertion</dt>
                            <dd class="col-8 ims-review-assertion"></dd>
                        </dl>
                    </div>

                </div><!-- /.modal-body -->

                <!-- Modal Footer — navigation buttons -->
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary me-auto d-none" id="wizBtnBack">
                        <i class="bi bi-chevron-left me-1"></i>Back
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="wizBtnNext">
                        Next<i class="bi bi-chevron-right ms-1"></i>
                    </button>
                    <button type="submit" class="btn btn-sm btn-success d-none" id="wizBtnSave">
                        <i class="bi bi-check-lg me-1"></i>Save Rule
                    </button>
                </div>

            </form><!-- /#aclWizardForm -->
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /#aclWizardModal -->
```

### Wizard JavaScript

Add the following to `public/assets/js/app.js` **inside the `htmx:afterSettle` listener**
(so it re-binds after every HTMX page swap):

```js
// ── ACL Wizard ────────────────────────────────────────────────────────────────
function initAclWizard() {
    var modal = document.getElementById('aclWizardModal');
    if (!modal) return;

    var currentStep = 1;
    var totalSteps  = 5;

    var privilegeMethodMap = {
        'GET':    'read',
        'POST':   'create',
        'PUT':    'update',
        'PATCH':  'update',
        'DELETE': 'delete'
    };

    // Populate route context from data attributes when modal opens
    modal.addEventListener('show.bs.modal', function (e) {
        var trigger = e.relatedTarget;
        if (!trigger) return;

        var routeName = trigger.getAttribute('data-route-name') || '';
        var methods   = (trigger.getAttribute('data-route-methods') || 'GET').split(',');

        // Set hidden field
        document.getElementById('wizRouteName').value = routeName;

        // Header context bar
        modal.querySelector('.ims-wizard-route-display').textContent = routeName;

        var methodBadges = methods.map(function(m) {
            return '<span class="badge bg-secondary-subtle border text-secondary-emphasis ims-badge-xs">' + m + '</span>';
        }).join(' ');
        modal.querySelector('.ims-wizard-methods-display').innerHTML = methodBadges;

        // Derive and pre-check privileges from methods
        var derivedPrivs = [...new Set(methods.map(function(m) { return privilegeMethodMap[m] || ''; }).filter(Boolean))];
        modal.querySelectorAll('.ims-priv-check').forEach(function(cb) {
            cb.checked = derivedPrivs.includes(cb.value);
        });

        var privColorMap = { read: 'primary', create: 'success', update: 'warning', delete: 'danger' };
        var privBadges = derivedPrivs.map(function(p) {
            return '<span class="badge bg-' + (privColorMap[p] || 'secondary') + '-subtle border text-' + (privColorMap[p] || 'secondary') + '-emphasis ims-badge-xs">' + p + '</span>';
        }).join(' ');
        modal.querySelector('.ims-wizard-privs-display').innerHTML = privBadges;

        // Reset to step 1
        goToStep(1);
    });

    // Clean up on close
    modal.addEventListener('hidden.bs.modal', function () {
        currentStep = 1;
        document.getElementById('wizRolePk').value = '';
        document.getElementById('wizRoleDisplay').textContent = 'None';
        document.getElementById('wizAssertionFqcn').value = '';
        modal.querySelectorAll('.ims-assertion-card').forEach(function(c) {
            c.classList.remove('border-primary');
            c.querySelector('.ims-assertion-check').classList.add('d-none');
        });
        // Pre-select None assertion
        var noneCard = modal.querySelector('.ims-assertion-card[data-assertion-fqcn=""]');
        if (noneCard) {
            noneCard.classList.add('border-primary');
            noneCard.querySelector('.ims-assertion-check').classList.remove('d-none');
        }
    });

    // Role selection
    modal.querySelectorAll('.ims-role-selectable').forEach(function(el) {
        el.addEventListener('click', function() {
            modal.querySelectorAll('.ims-role-selectable').forEach(function(r) {
                r.classList.remove('bg-primary-subtle');
            });
            el.classList.add('bg-primary-subtle');
            document.getElementById('wizRolePk').value = el.getAttribute('data-role-pk');
            document.getElementById('wizRoleDisplay').textContent = el.querySelector('span').textContent;
        });
    });

    // Assertion selection
    modal.querySelectorAll('.ims-assertion-card').forEach(function(card) {
        card.addEventListener('click', function() {
            modal.querySelectorAll('.ims-assertion-card').forEach(function(c) {
                c.classList.remove('border-primary');
                c.querySelector('.ims-assertion-check').classList.add('d-none');
            });
            card.classList.add('border-primary');
            card.querySelector('.ims-assertion-check').classList.remove('d-none');
            document.getElementById('wizAssertionFqcn').value = card.getAttribute('data-assertion-fqcn');
        });
    });

    // Step navigation
    document.getElementById('wizBtnNext').addEventListener('click', function() {
        if (currentStep < totalSteps) {
            goToStep(currentStep + 1);
        }
    });

    document.getElementById('wizBtnBack').addEventListener('click', function() {
        if (currentStep > 1) {
            goToStep(currentStep - 1);
        }
    });

    function goToStep(step) {
        currentStep = step;

        modal.querySelectorAll('.ims-wizard-panel').forEach(function(p) {
            p.classList.add('d-none');
        });
        modal.querySelector('.ims-wizard-panel[data-panel="' + step + '"]').classList.remove('d-none');

        modal.querySelectorAll('.ims-wizard-step-indicator').forEach(function(ind) {
            ind.classList.toggle('ims-wizard-step-active', parseInt(ind.getAttribute('data-step')) === step);
            ind.classList.toggle('ims-wizard-step-done',   parseInt(ind.getAttribute('data-step')) < step);
        });

        document.getElementById('wizBtnBack').classList.toggle('d-none', step === 1);
        document.getElementById('wizBtnNext').classList.toggle('d-none', step === totalSteps);
        document.getElementById('wizBtnSave').classList.toggle('d-none', step !== totalSteps);

        if (step === totalSteps) {
            populateReview();
        }
    }

    function populateReview() {
        var grantType = modal.querySelector('input[name="grantType"]:checked');
        var checkedPrivs = [...modal.querySelectorAll('.ims-priv-check:checked')].map(function(c) { return c.value; });
        var assertionCard = modal.querySelector('.ims-assertion-card.border-primary');

        modal.querySelector('.ims-review-route').textContent     = document.getElementById('wizRouteName').value;
        modal.querySelector('.ims-review-grant').textContent     = grantType ? grantType.value : '';
        modal.querySelector('.ims-review-role').textContent      = document.getElementById('wizRoleDisplay').textContent;
        modal.querySelector('.ims-review-privs').textContent     = checkedPrivs.join(', ') || 'None';
        modal.querySelector('.ims-review-assertion').textContent = assertionCard
            ? assertionCard.querySelector('.fw-semibold').textContent
            : 'None';
    }
}

initAclWizard();
```

Also add to `htmx:afterSettle` listener (after the existing `initBootstrapComponents()` call):

```js
initAclWizard();
```

### CSS additions to `public/assets/css/custom.css`

```css
/* ACL Wizard stepper */
.ims-wizard-step-indicator         { flex: 0 0 auto; text-align: center; }
.ims-wizard-step-circle            { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .85rem; font-weight: 600; background: var(--bs-secondary-bg); border: 2px solid var(--bs-border-color); color: var(--bs-secondary-color); }
.ims-wizard-step-active .ims-wizard-step-circle  { background: var(--bs-primary); border-color: var(--bs-primary); color: #fff; }
.ims-wizard-step-done   .ims-wizard-step-circle  { background: var(--bs-success-bg-subtle); border-color: var(--bs-success); color: var(--bs-success); }
.ims-wizard-step-connector         { height: 2px; background: var(--bs-border-color); margin: 0 4px; margin-bottom: 20px; }

/* Role tree */
.ims-role-selectable:hover         { background: var(--bs-secondary-bg); }

/* Assertion / privilege cards */
.ims-assertion-card                { cursor: pointer; transition: border-color .15s; }
.ims-assertion-card:hover          { border-color: var(--bs-primary) !important; }
.ims-priv-card                     { cursor: pointer; }

/* Misc */
.ims-badge-xs                      { font-size: .7rem; padding: .2em .45em; }
```

---

## Seed Note

Add the new `admin.acl.control.create` route to the seed's `acl_resource`,
`acl_privilege` (create), `acl_rule` (Developer allow), and `acl_route_privilege`
(or route-resource mapping depending on Phase 1 execution state) blocks alongside the
existing `admin.acl.*` rows. Follow the exact same pattern already used for those 14
routes in `999_seed.sql`.

---

## Tests Required

| File | Type | What to test |
|---|---|---|
| `test/AsyncTest/Admin/CommandHandler/SaveAccessControlEntryHandlerTest.php` | Unit | Happy path (new route), happy path (existing route), assertion attached, transaction rollback on exception |
| `test/AsyncTest/Admin/Middleware/ProcessAccessControlEntryMiddlewareTest.php` | Unit | POST dispatches command, success messenger, failure messenger, non-POST passes through |

Both test files follow the same pattern as existing handler/middleware tests in
`test/AsyncTest/`.
