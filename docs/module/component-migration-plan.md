# Component Migration Plan — Deep Audit

> Deep audit of the application + `vendor/` for component extraction.
> Records all points of friction and blockers found while preparing to move
> app-context packages into standalone repos under the webinertia org and to
> repoint the in-vendor packages to their new names.
>
> Audit date: 2026-08-16. `composer update` is currently broken; this audit is
> source-based, not lock-based.

---

## 1. Current State Snapshot

### In `vendor/` (old package names — will be repointed)

| Installed package | Namespace | Locked version | New target |
|---|---|---|---|
| `webware/command-bus` | `Webware\CommandBus\` | `0.5.x-dev` | `webware/message-bus` (`Webware\MessageBus\`) |
| `axleus/axleus-mailer` | `Axleus\Mailer\` | dev-master | `webware/webware-mailer` |
| `axleus/axleus-message` | `Axleus\Message\` | dev-master | `webware/webware-message` |
| `axleus/axleus-log` | `Axleus\Log\` | dev-master | `webware/webware-log` (`Webware\Log\`) |
| `webware/coding-standard` | — | — | stays |
| `webware/traccio` | — | — | stays |

`composer.json` **already declares** the new names (`webware/message-bus ^2.0.0-beta.1`,
`webware/messagebus-event ^2.0.0-beta.1`, `webware/webware-log ^1.0.0`) but
`composer.lock` still pins `webware/command-bus 0.5.x-dev` and the three `axleus/*`
packages. The vendor tree therefore still contains the old code.

### In app context (`src/` — will move out or be removed)

| Module | Namespace | Own `composer.json`? | Target package name |
|---|---|---|---|
| `src/webware-core` | `Webware\Core\` | yes | `webware/webware-core` (name in local `composer.json` is stale: `webware/core`) |
| `src/webware-event` | `Webware\Event\` | **no** | `webware/webware-event` |
| `src/webware-resultset` | `Webware\ResultSet\` | **no** | `webware/webware-resultset` |
| `src/webware-acl` | `Webware\Acl\` | yes | `webware/webware-acl` (local `composer.json` says `webware/acl`) |
| `src/webware-admin` | `Webware\Admin\` | yes | `webware/webware-admin` (local `composer.json` says `webware/admin`) |
| `src/webware-navigation` | `Webware\Navigation\` | yes | `webware/webware-navigation` (local `composer.json` says `webware/navigation`) |
| `src/webware-usermanager` | `Webware\UserManager\` | yes | `webware/webware-usermanager` (local `composer.json` says `webware/usermanager`) |
| `src/ims-manifest` | `Ims\Manifest\` | **no** | app-level (stays) |
| `src/ims-store` | `Ims\Store\` | **no** | app-level (stays) |
| `src/Htmx` | `Htmx\` | **no** | `webware/webware-htmx` |
| `src/phpdb-mezzio-session` | `PhpDb\Session\` | yes | `php-db/phpdb-mezzio-session` |
| `src/webware-configmanager` | `Webware\ConfigManager\` | yes | **REMOVE** |
| `src/mezzio-async`, `src/phpdb-async`, `src/ims-migration` | — | — | retained, out of scope |

---

## 2. Migration 1: `webware/command-bus` → `webware/message-bus` + `webware/messagebus-event`

This is the largest migration. The new package (checked out at
`/home/jsmith/github.com/webinertia/message-bus`, tag `2.0.0-beta.1`, branch `2.0.x`)
is **not a rename** — the pipeline contract changed.

### 2.1 Old → new API delta (verified from upstream source)

| Old (`Webware\CommandBus\`) | New (`Webware\MessageBus\`) | Notes |
|---|---|---|
| `CommandBusInterface::handle(CommandInterface): CommandResultInterface` | `MessageBusInterface extends PipelineHandlerInterface` → `handle(MessageInterface): ResultInterface` | `MessageBusInterface` is now the pipeline-handler contract |
| `CommandInterface` (root marker) | `MessageInterface` + compat `Command\CommandInterface extends MessageInterface` | `Command\` sub-namespace kept for compat |
| `CommandHandlerInterface::handle()` (typed method) | `MessageHandlerInterface` is an **empty marker**; `CommandHandlerInterface` compat marker also empty | Terminal middleware resolves the method name via `StrategyInterface` (`Strategy\HandleStrategy` → `'handle'`; `Strategy\ClassnameStrategy` → `lcfirst(class basename)`) and calls `$handler->{$method}($message)` |
| `CommandResult`, `CommandResultInterface` | `Command\CommandResult`, `Command\CommandResultInterface` (extends `CommandInterface, ResultInterface`) | Constructor shape unchanged |
| `CommandStatus`, `CommandStatusInterface` | **Gone.** Replaced by `MessageStatus` (enum) + `StatusInterface` | Every `CommandStatus::Success/Failure` call site must change |
| `MiddlewareInterface::process(CommandInterface, CommandHandlerInterface)` | `process(MessageInterface, PipelineHandlerInterface $next): ResultInterface` | Second param type changed; `$handler->handle($result)` becomes `$next->handle($result)` |
| `CommandHandlerMiddleware` (terminal) | `Middleware\MessageHandlerMiddleware` | Terminal now takes `(resolver, strategy)`; validates the return is `ResultInterface` and throws `HandlerMethodNotFoundException`/`TypeError` otherwise |
| `CommandHandlerResolverInterface` | `MessageHandlerResolverInterface` | Resolution unchanged (by `$message::class`) |
| `CommandBus` concrete class | `MessageBus` | |
| `ConfigProvider` key | `MessageBusInterface::class` (was `CommandBusInterface::class`) | `command_map` sub-key **unchanged**; new `query_map` sub-key added; `middleware_pipeline` unchanged |
| `Command\NamedCommandInterface` / `NamedCommandTrait` | Same names under `Webware\MessageBus\Command\` | Compat preserved |
| `MiddlewarePipe`, `Next`, `EmptyPipelineHandler` | Same concepts, renamed type hints | `Next` uses `SplQueue` clone |

New capabilities that do not exist today and may be adopted later: query bus
(`Query\QueryInterface`, `QueryHandlerInterface`, `query_map`, `Strategy\ClassnameStrategy`).

### 2.2 messagebus-event delta

| Old (`webware/commandbus-event`) | New (`Webware\MessageBus\Event\`) |
|---|---|
| `Middleware\PreHandleMiddleware` | `Middleware\CommandPreHandleMiddleware` (+ new `QueryPreHandleMiddleware`) |
| `Middleware\PostHandleMiddleware` | `Middleware\CommandPostHandleMiddleware` (+ new `QueryPostHandleMiddleware`) |
| `Event\PreHandleEvent`, `PostHandleEvent` | `Command\CommandPreHandleEvent`, `Command\CommandPostHandleEvent` |
| registered in `config['middleware_pipeline']` of the bus CP | registered under `MessageBusInterface::class => ['middleware_pipeline' => [pre @100, post @-100]]` |

**Important:** the app currently has **zero** usages of the old `webware/commandbus-event`
(`Webware\CommandBus\Event\*` classes appear nowhere in `src/`, `config/`, or tests).
`webware/usermanager`'s `composer.json` requires `webware/commandbus-event 0.1.x-dev`,
but nothing imports it. Adding `webware/messagebus-event` is therefore net-new wiring,
not a rename.

### 2.3 Usage volume (old namespace, app code only)

| Class/contract | Reference count |
|---|---|
| `Command\CommandStatus` | 29 files |
| `Command\CommandResult` | 30 |
| `CommandInterface` | 26 |
| `CommandBusInterface` | 19 |
| `CommandHandlerInterface` | 13 |
| `Command\CommandResultInterface` | 11 |
| `CommandHandlerResolverInterface` | 3 |
| bus `MiddlewareInterface` | 1 |

Distribution: `webware-acl` (27 files), `webware-usermanager` (19), `ims-manifest` (7),
`ims-store` (2), `App` (2), `config/config.php` (1).

### 2.4 App-specific CommandBus artifacts (blockers, not simple renames)

1. **`App\CommandBus\Middleware\CommandLoggingMiddleware`**
   (`src/App/src/CommandBus/Middleware/CommandLoggingMiddleware.php`, priority 0,
   registered in `App\ConfigProvider::getBusConfig()`).
   - Implements old `MiddlewareInterface::process(CommandInterface, CommandHandlerInterface)`.
   - Coupled to `Axleus\Log\Event\LogEvent`, `Axleus\Log\LogChannel` and `Monolog\Level` —
     triple-coupling: bus rename + log rename (`Axleus\Log` → `Webware\Log`) + messagebus
     result/status rename. Must migrate together.
   - New signature: `process(MessageInterface, PipelineHandlerInterface $next): ResultInterface`;
     `CommandStatus::Success` → `MessageStatus::Success`.
2. **Incomplete ACL-aware terminal middleware (dead code today):**
   - `Webware\Acl\Container\CommandHandlerMiddlewareFactory` constructs
     `Webware\Acl\CommandBus\Middleware\CommandHandlerMiddleware` — **the class file does
     not exist** anywhere in `src/` (only the factory and two tests reference it).
   - Tests reference further missing classes: `Webware\Acl\CommandBus\AuthorizableCommandInterface`
     and `Webware\Acl\CommandBus\CommandStatus`.
   - Decision needed during migration: port this to a `MessageHandlerMiddleware` subclass /
     custom terminal that asserts ACL before dispatch, or delete factory + tests.
3. **`ims-store` command-handler override is unwired:**
   - `Ims\Store\CommandHandler\UpdateUserCommandHandler` (uses old contracts) exists but
     `Ims\Store\ConfigProvider::getCommandMap()` is **empty** (commented out). The store
     override of `UpdateUserCommand` is currently dead; the usermanager handler runs.
     During migration, decide to wire or remove it (see `src/ims-store/docs/update-user-remaining-2026-07-02.md`).
4. **Entity-as-command legacy:** `Webware\UserManager\Entity\User` implements
   `NamedCommandInterface` (old) and appears in the usermanager command map comments.
   The codebase decision (2026-07-07) is that this pattern is broken — do not re-introduce
   it while migrating; keep pure DTO commands.

### 2.5 Config wiring changes for the bus migration

- `config/config.php`: `\Webware\CommandBus\ConfigProvider::class` → new package ConfigProvider.
- Every module `ConfigProvider` currently returning
  `CommandBusInterface::class => [BusProvider::COMMAND_MAP_KEY => ...]` must switch to
  `Webware\MessageBus\MessageBusInterface::class` with `ConfigProvider::COMMAND_MAP_KEY`
  (constant name unchanged). Affected: `App`, `webware-acl`, `webware-usermanager`,
  `ims-manifest`, `ims-store` (empty map).
- `App\ConfigProvider::getBusConfig()` middleware pipeline (`CommandLoggingMiddleware` @ 0)
  moves under `MessageBusInterface::class => ['middleware_pipeline' => ...]`.
- Handlers: keep `handle()` method names (default `HandleStrategy`). Update parameter
  (`MessageInterface`/concrete command class) and return type (`ResultInterface` or
  `Command\CommandResultInterface`).

### 2.6 Listener-provider collision — blocker between `webware-event` and `messagebus-event`

Both packages define a `ListenerProviderAggregateFactory` that reads the **same** merged
config keys (`listeners`, `listener_providers`) and both alias
`ListenerProviderInterface::class => ListenerProviderAggregate::class`:

- `Webware\Event\ConfigProvider` (`src/webware-event/`) — used by the app today (wires
  `EventDispatcherMiddleware` + aggregate).
- `Webware\MessageBus\Event\ConfigProvider` — same aliases + same factory shape
  (`phly/event-dispatcher`, `PrioritizedListenerProvider` + `AttachableListenerProvider`).

Registering both = duplicate service definition for `ListenerProviderAggregate::class` and
alias conflict; whichever ConfigProvider merges last wins the alias. Resolution must be
decided **before** adding messagebus-event (one package owns the aggregate + listener
config; the other contributes listeners only, or shares the provider).

---

## 3. Migration 2: `axleus/axleus-mailer` → `webware/webware-mailer`

### 3.1 Blockers inside the package itself

- **Undeclared dependencies:** `axleus/axleus-mailer/composer.json` requires only
  `phpmailer/phpmailer` but its source uses `Webware\CommandBus\*` classes in
  `CommandBus/` and `Event/`.
- **Broken/dead code in the installed package:**
  - `Axleus\Mailer\CommandBus\SendEmailCommand` implements
    `Webware\CommandBus\Event\EventAwareInterface` + `EventInterface` — these classes do
    **not exist** in the installed vendor tree (no `webware/commandbus-event` installed).
    Any instantiation would fatal.
  - `Axleus\Mailer\Event\MessageEvent extends Webware\CommandBus\Event\Event` — same
    missing parent (file carries a "todo: determine whether this event is still needed").
- The live path used by the app is `MailerInterface` + `AdapterInterface` fluent API
  (`getAdapter()`, `from()`, `to()`, `subject()`, `isHtml()`, `body()`, `altBody()`,
  `mailer->send()`) — migrate that, port or drop `CommandBus/` + `Event/` + `Middleware/`.
- New package must declare `webware/message-bus` (and `webware/messagebus-event` if the
  command flow survives) explicitly.

### 3.2 App usage (7 files)

- `webware-usermanager`: `Listener/SendVerificationEmailListener.php` +
  `Listener/Container/SendVerificationEmailListenerFactory.php`,
  `RequestHandler/ResendVerificationHandler.php` +
  `RequestHandler/Container/ResendVerificationHandlerFactory.php`.
- `config/autoload/app.global.php`: top-level config keys
  `Axleus\Mailer\Adapter\MessageInterface::class` and `Axleus\Mailer\MailerInterface::class`
  — **keys must be renamed to the new FQCNs** (config migration).
- `config/autoload/mail.local.php`: key `Axleus\Mailer\Adapter\AdapterInterface::class`
  (SMTP settings) — same rename.
- `config/config.php`: `\Axleus\Mailer\ConfigProvider::class` registration.
- `webware-usermanager/composer.json`: requires `axleus/axleus-mailer dev-master` — repoint.

---

## 4. Migration 3: `axleus/axleus-message` → `webware/webware-message`

### 4.1 Package facts

- Contents: `SystemMessenger`, `SystemMessengerInterface` (+ `SESSION_KEY`),
  `MessageLevel`, `MessageIcon`, `AbstractMessage`, `MessageInterface`,
  `Middleware\MessageMiddleware` (unused by app), `View\Helper\SystemMessenger`
  (+ factory), aware interfaces/traits, exceptions.
- `composer.json` requires `mezzio/mezzio-session-ext ^1.21` and `webware/command-bus ^0.5.0`
  (undeclared-in-code bus usage: its `ConfigProvider` imports
  `Webware\CommandBus\CommandBusInterface` + `ConfigProvider as BusProvider`).
- `mezzio/mezzio-session-ext` is currently **transitive-only** (pulled via this package)
  and is not actually used (`PhpDb\Session` overrides the ext alias; see
  `src/phpdb-mezzio-session/docs/phpdb-mezzio-session.md`). Decide whether
  `webware/webware-message` keeps or drops that dependency — dropping removes it from the
  tree entirely.

### 4.2 App usage (14 files)

- `App\Middleware\ImsMessengerMiddleware` (builds `SystemMessenger` from session,
  sets `SystemMessengerInterface::class` request attribute, injects into helper).
- `App\View\Helper\ImsMessenger` (renders `.ims-pending-toast` HTML; uses
  `MessageLevel::cases()`, `SystemMessenger::getMessage()`).
- `Htmx` body template `src/Htmx/templates/body/default.phtml` calls `$this->imsMessenger()`.
- `ims-manifest`: `Middleware/ProcessManifestUploadMiddleware.php`.
- `webware-acl`: `Admin/Middleware/ProcessRoleMiddleware.php`,
  `Admin/Middleware/ProcessRuleMiddleware.php`, `RequestHandler/ForbiddenHandler.php`
  + 2 integration tests.
- `webware-usermanager`: `Middleware/LoginMiddleware.php`,
  `Middleware/ProcessUpdateUserMiddleware.php`, `Middleware/RegistrationMiddleware.php`,
  `RequestHandler/LoginHandler.php`, `RequestHandler/VerifyEmailHandler.php`.
- `config/config.php`: `\Axleus\Message\ConfigProvider::class` registration.
- The toast container `#systemMessage` lives in `src/App/templates/layout/default.phtml`;
  the asset map in `config/autoload/app.global.php` maps `messenger.js` →
  `assets/js/system.messenger.js`. These are app-level and survive the move.

### 4.3 Friction

- Attribute key is `SystemMessengerInterface::class` — all middleware reads of
  `$request->getAttribute(SystemMessengerInterface::class)` must use the new FQCN.
- The package ConfigProvider registers the `systemMessenger` view helper — wiring must be
  preserved under the new namespace or the body template's helper call breaks.
- `webware-usermanager/composer.json` + `webware-acl/composer.json` require
  `axleus/axleus-message dev-master` — repoint both.

---

## 5. Migration 4: `src/Htmx` → `webware/webware-htmx`

### 5.1 Package facts

- No target repo/namespace exists yet. Current namespace is bare `Htmx\`
  (autoloaded from `src/Htmx/src/` in the root `composer.json`) — publishing requires a
  vendor prefix (e.g. `Webware\Htmx\`).
- Contents: `ConfigProvider` (aliases `TemplateRendererInterface` →
  `View\LaminasRenderer`; templates map `body::default` + `default_body`),
  `Middleware\DetectAjaxRequestMiddleware` (+ factory — receives the **entire merged
  `config` array**), `Middleware\DisableBodyMiddleware`,
  `Request\Header` + `Response\Header` enums (+ `EnumTrait`), `Request\ServerRequestFilter`
  (aliased to `Laminas\Diactoros\FilterServerRequestInterface`), `RequestHandlerTrait`,
  `ResponseTrait`, `TriggerTrait`, `View\LaminasRenderer` + `LaminasRendererFactory`.
- `View\LaminasRenderer` is a fork of the mezzio-laminasviewrenderer renderer implementing
  the 3-layer stack (layout / body / page). It depends on `Mezzio\LaminasView\LayoutHelper`,
  `Mezzio\Template\*`, `Laminas\View\*`.
- **Renderer alias ownership:** both `Mezzio\LaminasView\ConfigProvider` (from
  mezzio-laminasviewrenderer) and `Htmx\ConfigProvider` alias `TemplateRendererInterface`.
  Today the Htmx ConfigProvider is registered last in `config/config.php`, so Htmx wins.
  After the move, registration order must keep the htmx provider after
  mezzio-laminasviewrenderer, or the 3-layer rendering silently breaks.

### 5.2 App usage (8 files)

- `config/pipeline.php`: `Htmx\Middleware\DetectAjaxRequestMiddleware`.
- `webware-acl/src/RouteProvider.php` and `webware-usermanager/src/RouteProvider.php`:
  `Htmx\Middleware\DisableBodyMiddleware` on fragment routes.
- `Htmx\Response\Header` used by `webware-usermanager` (`LoginHandler`, `UserListHandler`)
  and `webware-acl` (`RoleListHandler`, `ResourceListHandler`).
- `test/AsyncTest/HotCodeReload/WatcherFactoryTest.php`: literal `'src/Htmx'` watch path.

### 5.3 Template config requirements (user requirement: config-driven template paths)

- `ConfigProvider::getTemplates()` hardcodes `__DIR__ . '/../templates/body/default.phtml'`.
  After moving to `vendor/`, the map entry must be resolvable and **overridable by the
  application** (app must be able to replace `body::default`).
- `LaminasRendererFactory` already reads the merged `config['templates']` array
  (`layout`, `body`, `default_layout`, `default_body`, `view_manager.default_layout`) —
  keep this contract; add an app-override key (see Section 8).

---

## 6. Other App-Context Modules Moving to Vendor

### 6.1 `webware-core` → `webware/core`

- Has `composer.json`; clean (psr/http-*, fig/http-message-util only).
- `Webware\Core\HttpMethodProcessorTrait` is used by 18 external files
  (acl 6, usermanager 5, ims-manifest 2, ims-store 1, webware-admin 1, App 1, config 2).
- `Middleware\AttachCoreServicesMiddleware` wired in `config/pipeline.php`.
- Low friction — publish + require.

### 6.2 `webware-resultset` → TBD package

- **No `composer.json`.** Contains `WithRowDataPrototypeInterface`,
  `WithRowDataResultSet` (no ConfigProvider — config-free).
- Used by usermanager (7 files, alias `WithRowDataPrototypeInterface::class =>
  Entity\User::class`) and webware-acl (4 files).
- Low friction — needs a new composer.json + packagist package name.

### 6.3 `webware-event` → TBD package

- **No `composer.json`.** Provides `EventDispatcherMiddleware`,
  `ListenerProviderAggregateFactory`, `EventAwareInterface`/`Trait` (trait/interface
  unused outside the module).
- Registered in `config/config.php` + used by `config/pipeline.php`.
- **Blocker:** `listeners`/`listener_providers` config-key and
  `ListenerProviderAggregate` ownership collide with `webware/messagebus-event`
  (Section 2.6). Decide ownership before publishing.

### 6.4 `webware-acl` → `webware/acl`

- Has `composer.json`. Deps to repoint: `axleus/axleus-log`, `axleus/axleus-message`,
  `webware/core`, `php-db/phpdb-mysql`; bus usage per Section 2.
- Templates `paths['acl']` — needs config-driven override (Section 8).
- `command_map` holds 5 commands + handlers.
- Cross-package consumers: webware-admin (5), webware-navigation (4),
  webware-usermanager (1), ims-manifest (2), ims-store (1), App (1).
- `Webware\Acl\Admin\WriteResult` — note: `WriteResult` is referenced in docs/skills but
  has **zero** references in app source; do not carry dead patterns over.

### 6.5 `webware-admin` → `webware/admin`

- Has `composer.json`. Deps: `webware/acl` (+ laminas/mezzio stack).
- Templates `paths['admin']` — config-driven override needed.
- Config keys `AdminInterface::class` (defaults) + `AclInterface::class` contribution;
  consumers read via `Webware\Admin\Container\Configuration` (usermanager, acl,
  ims-manifest widget listener).
- `adminUrl` view helper + `RegisterWidgetEvent` listener pattern used by acl,
  usermanager, ims-manifest.

### 6.6 `webware-navigation` → `webware/navigation`

- Has `composer.json`. Deps: `webware/acl` (ACL-aware middleware + `NavigationFilterIterator`).
- No templates. `NavigationMiddleware` in `config/pipeline.php`.
- Low friction beyond the `webware/acl` dep.

### 6.7 `webware-usermanager` → `webware/usermanager`

- Has `composer.json`. **Heaviest dependency list to repoint:**
  `axleus/axleus-log`, `axleus/axleus-mailer`, `axleus/axleus-message`, `webware/acl`,
  `webware/command-bus 0.5.x-dev`, `webware/commandbus-event 0.1.x-dev`,
  `php-db/phpdb-mysql`, monolog, ramsey/uuid, mezzio-auth + session stack.
- Templates `paths['user']` — config-driven override needed.
- `command_map` (3 commands) + `getListeners()` (`RegisterWidgetEvent`) +
  `config/autoload/user.global.php` registers `SendVerificationEmailListener` under
  `listeners` (app-level file, still works after move).
- **`ims-store` overrides usermanager services** via factory-map override
  (`UpdateUserModalHandler::class`, `Entity\User::class` factory → `Ims\Store\Entity\User`,
  `UserDataFilter` factory). This relies on ConfigProvider merge order
  (usermanager registered before ims-store in `config/config.php`). When usermanager
  moves to vendor, the app must keep its override modules registered **after** the vendor
  package ConfigProvider — service-manager "same key, later factory wins" must be
  preserved, or the overrides silently stop applying.
- `Entity\User` must not become `final` in the published package (ims-store subclasses it).

### 6.8 `ims-manifest` / `ims-store` — stay in the app

- Both need updated imports for the bus + `Axleus\Message` renames (Section 2, 4).
- `ims-manifest`: templates `paths['manifest']`; `getListeners()` for
  `RegisterWidgetEvent`; `getBusConfig()`.
- `ims-store`: templates `paths['ims-store']` (override namespace — see 8.2).
- No `composer.json` needed as long as they remain app modules.

### 6.9 `phpdb-mezzio-session` → `php-db/phpdb-mezzio-session`

- Has `composer.json` already. Registration-order constraint: `config/config.php`
  registers `Mezzio\Session\ConfigProvider` then `PhpDb\Session\ConfigProvider`
  (alias override for the session ext) — order must survive the move.

### 6.10 `webware-configmanager` — REMOVE

- Confirmed dead: only references are `config/config.php` (registration) and the leftover
  generated artifact `config/autoload/foo.global.php` (header: "generated by
  Webware\ConfigManager\ConfigWriter"). No imports anywhere in app code.
- Delete: `src/webware-configmanager/` (incl. its stray `composer.lock`),
  registration entry, `foo.global.php`, test suite entries, autoload(-dev) entries.

---

## 7. Composer / Tooling Blockers

1. **`composer update` broken state — concrete root causes (verified 2026-08-17):**
   - Root `composer.json` still requires `axleus/axleus-mailer dev-master` +
     `axleus/axleus-message dev-master`. Neither was ever on packagist (both were
     installed from `github.com/axleus/*` VCS via a `repositories` entry that no
     longer exists in the root `composer.json`) → unsatisfiable.
   - `webware/messagebus-event 2.0.0-beta.1` (published) requires
     `webware/message-bus: 2.0.x-dev` — packagist has **no** dev version of
     message-bus (only tags 0.1.0 … 2.0.0-beta.1) → unsatisfiable.
   - `webware/messagebus-event 2.0.0-beta.1` requires
     `webware/webware-psl-type: ^0.1.x-dev` — package page exists on packagist but
     has **zero releases** → unsatisfiable.
   - The lock still pins `webware/command-bus 0.5.x-dev` + `axleus/*`; vendor tree
     is old code. Code renames must land together with a successful lock update.
2. **Unpublished target packages:** `webware/webware-htmx` has no repo (nothing under
   `/home/jsmith/github.com/webinertia/`) and is not on packagist. Also unpublished:
   `webware/webware-acl`, `webware/webware-admin`, `webware/webware-navigation`,
   `webware/webware-usermanager`, `webware/webware-resultset`,
   `php-db/phpdb-mezzio-session`. Registered on packagist but with **zero releases**:
   `webware/webware-core`, `webware/webware-psl-type`. See Section 12 for the full matrix.
3. **PHP floor conflict:** `webware/message-bus` + `webware/messagebus-event` require
   `~8.4.1 || ~8.5.0`; `webware/webware-log` requires `~8.4.1 || ~8.5.0 || 8.6.0-dev`.
   Root `composer.json` allows `~8.2.0 || ~8.3.0 || ~8.4.0 || ~8.5.0 || ~8.6.0`.
   Either the app PHP floor is raised to 8.4.1+, or the new packages widen their
   constraints — decide before anything else.
4. **New transitive dependency:** `webware/messagebus-event` requires
   `webware/webware-psl-type` (uses `Psl\Type` in its factory). `ims-store` code already
   uses `Psl\Type` directly — confirm the app's `php-standard-library/type ^6.2` +
   webware-psl-type requirements are consistent.
5. **Autoload cleanup (root `composer.json`):** remove `psr-4` entries for every moved
   module (`Webware\Core\`, `Webware\Event\`, `Webware\ResultSet\`, `Webware\Acl\`,
   `Webware\Admin\`, `Webware\Navigation\`, `Webware\UserManager\`, `Htmx\`,
   `PhpDb\Session\`, `Webware\ConfigManager\`) and all matching `autoload-dev` test
   namespaces. Keep `App\`, `Ims\*`, `Mezzio\Async\`, `PhpDb\Async\`.
6. **`phpunit.xml.dist`:** per-module `<testsuite>` entries
   (`webware-core(-integration)`, `webware-acl(-integration)`, `webware-admin(-integration)`,
   `webware-navigation(-integration)`, `webware-usermanager(-integration)`,
   `webware-configmanager`, ...) must be removed when tests move with their packages.
7. **`phpstan.neon.dist` / `phpstan-baseline.neon`:** baseline contains 91 entries,
   including stale paths (`src/User/src` — 39 entries for a module that no longer exists,
   `src/Htmx/src` — 26, `src/App/src` — 26). Prune when modules move; baseline path
   cleanup is mandatory or analysis regresses.
8. **Stray files found during audit:** root-level empty file `adminRouteSegment`;
   `config/autoload/foo.global.php` (configmanager artifact);
   `src/webware-configmanager/composer.lock`; `public/info.php` + `public/test.php`
   (app-level strays, unrelated but worth cleaning).

---

## 8. Template Path Configuration — Cross-Cutting Requirement

Every template-bearing module hardcodes `__DIR__`-relative paths in
`ConfigProvider::getTemplates()`:

| Module | Current template config |
|---|---|
| `Htmx` | `map: body::default => __DIR__.'/../templates/body/default.phtml'`, `default_body` |
| `App` (stays) | `map: layout::default, app::home-page, error::404, error::error`, `paths: app, error`, `default_layout` |
| `ims-manifest` | `paths: manifest => __DIR__.'/../templates/manifest'` |
| `ims-store` | `paths: ims-store => __DIR__.'/../templates/user'` |
| `webware-acl` | `paths: acl => __DIR__.'/../templates/acl'` |
| `webware-admin` | `paths: admin => __DIR__.'/../templates/admin'` |
| `webware-usermanager` | `paths: user => __DIR__.'/../templates/user'` |

### 8.1 Why this breaks on move

- `__DIR__` moves from `src/{module}/src` to `vendor/{vendor}/{pkg}/src` — templates that
  ship inside the package still resolve, but any app-side override or customization of a
  package template has no supported mechanism.
- `Laminas\ConfigAggregator` merges `templates.paths` recursively: same-key `paths`
  entries **append** (numeric arrays) rather than replace. If an app module registers a
  path under the same namespace key (`user`, `acl`, `admin`), the package path stays in
  the list and resolution order decides the winner — fragile and implicit. Today this is
  avoided only because `ims-store` uses a distinct namespace (`ims-store::`) + a service
  override of the handler.

### 8.2 Required pattern (user requirement)

Each published module's ConfigProvider must consume configuration so the application can
override template locations:

1. Module declares a config key (per Webware convention: the module's primary interface
   FQCN, e.g. `UserManagerInterface::class` / package-specific config object) holding
   default template paths, with `__DIR__` defaults.
2. Module factory (or `getTemplates()` provider) merges: package defaults ← application
   overrides, where app overrides **replace**, not append.
3. App override files (e.g. `config/autoload/*.local.php`) set the override paths.
4. Verify the renderer's `NamespacedPathStackResolver` resolves app paths **before**
   package paths for overridden namespaces (order must be explicit, not merge-order luck).
5. `body::default` (Htmx) and `layout::default` (App) map entries must remain
   app-overridable for host-app re-skinning.

### 8.3 Template references that must keep working

- `user::*` (usermanager templates) referenced by handlers and by `ims-store`'s
  `UpdateUserHandler` (`'user::list-users'`).
- `ims-store::update-user-modal` (override namespace pattern).
- `acl::*`, `admin::*`, `manifest::*`, `app::*`, `error::*`, `body::default`,
  `layout::default`.

---

## 9. Config File Renames — Checklist

| File | Change |
|---|---|
| `config/config.php` | `\Axleus\Message\ConfigProvider` → new; `\Webware\CommandBus\ConfigProvider` → `Webware\MessageBus\ConfigProvider`; `\Axleus\Mailer\ConfigProvider` → new; `\Axleus\Log\ConfigProvider` → `Webware\Log\ConfigProvider`; `Htmx\ConfigProvider` import → new namespace; drop `Webware\ConfigManager\ConfigProvider` |
| `config/pipeline.php` | `Axleus\Log\Middleware\MonologMiddleware` → `Webware\Log\Middleware\MonologMiddleware`; `Htmx\Middleware\DetectAjaxRequestMiddleware` → new namespace |
| `config/autoload/app.global.php` | keys `Axleus\Mailer\Adapter\MessageInterface::class`, `Axleus\Mailer\MailerInterface::class` → new FQCNs |
| `config/autoload/mail.local.php` | key `Axleus\Mailer\Adapter\AdapterInterface::class` → new FQCN |
| `config/autoload/global.php` | key `Axleus\Log\ConfigProvider::class` (`log_errors`) → `Webware\Log\ConfigProvider::class` |
| `config/autoload/user.global.php` | `listeners` uses `Webware\UserManager\*` — unchanged namespaces, no edit needed |
| Module ConfigProviders (5×) | `CommandBusInterface::class => [command_map/middleware_pipeline]` → `MessageBusInterface::class` |
| `test/AsyncTest/HotCodeReload/WatcherFactoryTest.php` | watch path `'src/Htmx'` → new location |

---

## 10. Suggested Sequencing

1. **Decide PHP floor** (8.4.1+ vs widen package constraints) — gates everything.
2. **Publish prerequisites:** create + publish `webware/webware-mailer`,
   `webware/webware-message`, `webware/webware-htmx`, `webware/webware-psl-type` repos;
   settle listener-provider ownership between `webware-event` and `messagebus-event`.
3. **Template-config overridability** in `webware-acl`, `webware-admin`,
   `webware-usermanager`, `Htmx`, `ims-manifest` ConfigProviders (Section 8) **before**
   moving any template-bearing module.
4. **Log first:** `Axleus\Log` → `webware/webware-log` (5 usage files, no API rename —
   `LogEvent`/`LogChannel`/`MonologMiddleware` keep names under `Webware\Log\`).
5. **Bus migration as one atomic change:** namespace + contract rename
   (Section 2) across `App`, `webware-acl`, `webware-usermanager`, `ims-manifest`,
   `ims-store` + config + tests, with `composer update` succeeding in the same change.
   Resolve the dead ACL terminal-middleware code (2.4.2) at the same time.
6. **Mailer + message together** (both are axleus packages; usermanager uses both;
   mailer's dead bus/event code must be ported or deleted).
7. **Htmx move** (after message, because the body template + toast flow depends on the
   messenger helper).
8. **Extract remaining modules** in dependency order:
   `webware-core` → `webware-resultset` → `webware-event` → `webware-acl` →
   `webware-admin` → `webware-navigation` → `webware-usermanager` →
   `phpdb-mezzio-session`. Keep `ims-manifest`/`ims-store`/`App` in the app, with
   registration order after vendor ConfigProviders.
9. **Remove `webware-configmanager`** + strays (`foo.global.php`, `adminRouteSegment`,
   nested `composer.lock`).
10. **Tooling cleanup:** autoload entries, phpunit suites, phpstan baseline prune.

---

## 11. Open Questions

- Namespace for `webware/webware-htmx` (mailer + message settled:
  `Webware\Mailer\`, `Webware\Message\`) — config keys in `app.global.php` /
  `mail.local.php` / attributes depend on this.
- `mezzio-session-ext` dependency in `webware/webware-message`: **kept** in the
  published 1.0.0-beta.1 (`^1.21.0`) — resolved, no action.
- Mailer `CommandBus/` + `Event/` subsystems: **kept** in `webware/webware-mailer`
  1.0.x and already ported to `Webware\MessageBus` — BUT `composer.json` does not
  declare `webware/message-bus` / `webware/messagebus-event` (same undeclared-dep bug
  as the old axleus package). Must be fixed upstream before publish.
- Port or delete the incomplete ACL-aware terminal middleware (Section 2.4.2).
- Wire or delete `Ims\Store\CommandHandler\UpdateUserCommandHandler` (empty command map).

---

## 12. Release Availability Matrix (verified 2026-08-17 against packagist + local repos)

### Published with releases

| Package | Packagist versions | Root `composer.json` status |
|---|---|---|
| `webware/message-bus` | 0.1.0, 0.1.1, 1.0.0, 1.1.0, 2.0.0-beta.1 | `^2.0.0-beta.1` satisfiable ✅ |
| `webware/messagebus-event` | 1.0.0, 2.0.0-beta.1 | `^2.0.0-beta.1` nominally matches, but deps unsatisfiable ❌ (see below) |
| `webware/webware-log` | 0.1.0, 0.1.1, 0.1.2, 1.0.0 | `^1.0.0` satisfiable ✅ |
| `webware/webware-mailer` | 1.0.0-beta.1 | not yet required in root — available ✅ |
| `webware/webware-message` | 1.0.0-beta.1 | not yet required in root — available ✅ |
| `webware/webware-event` | 0.1.0 | available ✅ |
| `webware/webware-tools` | 0.1.0 | CI/CD tooling only |

### Registered on packagist, zero releases

| Package | Local repo state |
|---|---|
| `webware/webware-psl-type` | repo `0.1.x`, no tags — blocks messagebus-event 2.0.0-beta.1 (`^0.1.x-dev` required) |
| `webware/webware-core` | repo `0.1.x`, no tags |

### Not on packagist (404)

| Package | Local repo state |
|---|---|
| `webware/webware-htmx` | **no repo created yet** — blocks Htmx migration |
| `webware/webware-acl` | local repo `webware-acl`? No — still only in app (`src/webware-acl`); no standalone repo |
| `webware/webware-admin` | only in app (`src/webware-admin`) |
| `webware/webware-navigation` | standalone repo exists (`0.1.x`, no tags) — unpublished. **Will need a VCS `repositories` entry in root `composer.json` (github.com/webinertia/webware-navigation) since it will not be released on packagist for a while** |
| `webware/webware-usermanager` | only in app (`src/webware-usermanager`) |
| `webware/webware-resultset` | standalone repo exists (`update-mago-rules`, tag 0.1.0 local) — unpublished |
| `php-db/phpdb-mezzio-session` | only in app |

### Old packages (VCS-only, never on packagist)

| Package | Installed from |
|---|---|
| `webware/command-bus` 0.5.x-dev | `github.com/tyrsson/command-bus` (lock; root has no `repositories` entry anymore) |
| `webware/commandbus-event` | — (never installed in this tree) |
| `axleus/axleus-log` 0.1.2 | `github.com/axleus/axleus-log` |
| `axleus/axleus-mailer` dev-master | `github.com/axleus/axleus-mailer` — still required by root → **hard blocker** |
| `axleus/axleus-message` dev-master | `github.com/axleus/axleus-message` — still required by root → **hard blocker** |

### Composer-update blockers (ordered)

1. Swap root requires `axleus/axleus-mailer dev-master` → `webware/webware-mailer ^1.0.0-beta.1`
   and `axleus/axleus-message dev-master` → `webware/webware-message ^1.0.0-beta.1`.
2. Publish `webware/webware-psl-type` (tag `0.1.0` / publish `0.1.x` branch) —
   required by messagebus-event.
3. Publish `webware/message-bus` `2.0.x-dev` (or tag `2.0.0`) — messagebus-event
   2.0.0-beta.1 requires `2.0.x-dev`.
4. Fix `webware/webware-mailer` composer.json: declare `webware/message-bus` +
   `webware/messagebus-event` (code already imports `Webware\MessageBus\*`).
5. PHP floor is fine on this machine: container/host runs PHP **8.4.24** (satisfies
   `~8.4.1 || ~8.5.0`).
6. Remaining unpublished packages block their extraction phases only
   (htmx, acl, admin, navigation, usermanager, resultset, phpdb-mezzio-session) —
   no impact on the first composer update once 1–4 are done.
