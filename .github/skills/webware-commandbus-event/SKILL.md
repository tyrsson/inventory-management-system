---
name: "webware-commandbus-event"
description: "Load when writing or reviewing any code that uses the webware/commandbus-event package: PreHandleMiddleware, PostHandleMiddleware, PreHandleEvent, PostHandleEvent, ListenerInterface, EventAwareInterface, EventDispatcherAwareInterface, or the listeners config. Also load when adding new CommandBus event listeners to this application."
argument-hint: "<what you are implementing — e.g. 'PreHandleEvent listener for ownership guard', 'PostHandleEvent listener for audit log', 'EventAwareInterface on a command', 'registering a listener in config'>"
---

> ⚠ **SKILL INTEGRITY — NEVER REMOVE OR SHORTEN**
> Content in this file may only be **added to or updated**. Removing or shortening existing sections is not permitted without explicit user approval. If you are adding new knowledge, append it as a new section.

## Package Identity

| Item | Value |
|---|---|
| Package | `webware/commandbus-event` |
| Vendor path | `vendor/webware/commandbus-event/src/` |
| PHP namespace | `Webware\CommandBus\Event` |
| Config keys | `'listeners'`, `'listener_providers'` (from `ConfigProvider`) |

---

## Architecture Overview

This package adds **PSR-14 event dispatching** to the CommandBus pipeline by inserting two middleware:

| Middleware | Priority | Event dispatched |
|---|---|---|
| `PreHandleMiddleware` | 100 (first) | `PreHandleEvent` — before the handler runs |
| `PostHandleMiddleware` | -100 (last) | `PostHandleEvent` — after the handler completes |

The event dispatcher (`phly/event-dispatcher`) is set via `EventDispatcherAwareDelegator` — it is injected automatically when the service is built from the container.

---

## Event Classes

### `PreHandleEvent`
Dispatched **before** the command handler executes. Carries the original command.

```php
$event->getCommand(); // CommandInterface — the command being handled
$event->stopPropagation(); // halt further listeners
$event->isPropagationStopped(); // bool
```

**Use for:** ownership/authorization checks, rate limiting, pre-validation that can abort processing.

### `PostHandleEvent`
Dispatched **after** the command handler completes. Carries the command.

```php
$event->getCommand(); // CommandInterface — the completed command
```

**Use for:** audit logging, sending emails, cache invalidation, side-effect triggers.

### `EventAwareInterface` + `EventAwareTrait`
Commands that need to carry a custom event for `PostHandleMiddleware` to dispatch implement this:

```php
use Webware\CommandBus\Event\EventAwareInterface;
use Webware\CommandBus\Event\EventAwareTrait;

final class RegisterUserCommand implements CommandInterface, EventAwareInterface
{
    use EventAwareTrait;
    // ...
}
```

`PostHandleMiddleware` checks `$command instanceof EventAwareInterface` and dispatches `$command->getEvent()` if set, in addition to the generic `PostHandleEvent`.

---

## `ListenerInterface`
All listeners in this application implement:

```php
interface ListenerInterface
{
    public function __invoke(EventInterface $event): void;
}
```

Listeners receive the specific event type they are registered for. Type-hint the concrete event class:

```php
use Webware\CommandBus\Event\ListenerInterface;
use Webware\CommandBus\Event\PreHandleEvent;

final class OwnershipGuardListener implements ListenerInterface
{
    public function __construct(
        private readonly IdentityContextInterface $identityContext,
    ) {}

    public function __invoke(EventInterface $event): void
    {
        assert($event instanceof PreHandleEvent);
        $command = $event->getCommand();

        if (! $command instanceof OwnedCommandInterface) {
            return;
        }

        if (! in_array($command->getOwnerId(), $this->identityContext->getOwnerIds(), true)) {
            $event->stopPropagation();
            throw new UnauthorizedCommandException($command);
        }
    }
}
```

Stopping propagation on a `PreHandleEvent` **does not** prevent the handler from running — `PreHandleMiddleware` does not check `isPropagationStopped()` before calling `$handler->handle()`. To abort execution you must **throw** from the listener.

---

## Registering Listeners in Config

Listeners are registered in `config/autoload/commandbus-event.global.php` (or any merged config file) under the `'listeners'` key.

```php
use Webware\CommandBus\Event\PreHandleEvent;
use Webware\CommandBus\Event\PostHandleEvent;

return [
    'listeners' => [
        PreHandleEvent::class => [
            ['listener' => OwnershipGuardListener::class, 'priority' => 10],
        ],
        PostHandleEvent::class => [
            ['listener' => AuditLogListener::class,       'priority' => 1],
            ['listener' => SendVerificationEmailListener::class, 'priority' => 1],
        ],
    ],
];
```

The `ListenerProviderAggregateFactory` reads this config and wires listeners onto either:
- `PrioritizedListenerProvider` — when `'priority'` is specified
- `AttachableListenerProvider` — when no priority (or priority omitted)

Both providers are part of `phly/event-dispatcher`.

Listeners must also be registered as **DI services** (factory or invokable):

```php
'factories' => [
    OwnershipGuardListener::class => OwnershipGuardListenerFactory::class,
],
```

---

## DI Wiring (automatic via delegators)

`PreHandleMiddleware` and `PostHandleMiddleware` both implement `EventDispatcherAwareInterface`. The `EventDispatcherAwareDelegator` is registered for both and injects `EventDispatcherInterface` automatically — no factory work needed for the middlewares themselves.

---

## `EventDispatcherAwareInterface` + `EventDispatcherAwareTrait`
Use when a custom CommandBus middleware or service needs access to the event dispatcher:

```php
use Webware\CommandBus\Event\EventDispatcherAwareInterface;
use Webware\CommandBus\Event\EventDispatcherAwareTrait;
use Webware\CommandBus\MiddlewareInterface;

final class MyMiddleware implements MiddlewareInterface, EventDispatcherAwareInterface
{
    use EventDispatcherAwareTrait;
    // setEventDispatcher() and getEventDispatcher() provided by the trait
}
```

Register the `EventDispatcherAwareDelegator` for your class in config:

```php
'delegators' => [
    MyMiddleware::class => [EventDispatcherAwareDelegator::class],
],
```

---

## Critical Rules

- **Listeners are shared services** — they must be stateless. No per-request state on a listener.
- **Throw to abort from `PreHandleEvent`** — stopping propagation alone does not halt pipeline execution.
- **`PostHandleEvent` listener exceptions bubble to the CommandBus caller** — handle them or let them propagate to the HTTP request handler.
- **Do not dispatch `PreHandleEvent` or `PostHandleEvent` manually** — they are dispatched automatically by the pipeline middleware.
- **Priority order**: higher number = runs first for `PrioritizedListenerProvider`.
- **One config file for all listener registrations** — keep `config/autoload/commandbus-event.global.php` as the single source of truth for listener wiring across all modules.
