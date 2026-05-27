---
name: "webware-command-bus"
description: "Load when writing or reviewing any code that uses the webware/command-bus package: CommandBus, CommandInterface, CommandHandlerInterface, CommandHandlerResolver, MiddlewarePipe, MiddlewareInterface, CommandResult, CommandStatus, or the command_map config. Also load when adding new commands, handlers, or CommandBus middleware to this application."
argument-hint: "<what you are implementing — e.g. 'new command + handler', 'CommandBus middleware', 'command_map registration', 'CommandResult handling in a request handler'>"
---

> ⚠ **SKILL INTEGRITY — NEVER REMOVE OR SHORTEN**
> Content in this file may only be **added to or updated**. Removing or shortening existing sections is not permitted without explicit user approval. If you are adding new knowledge, append it as a new section.

## Package Identity

| Item | Value |
|---|---|
| Package | `webware/command-bus` |
| Vendor path | `vendor/webware/command-bus/src/` |
| PHP namespace | `Webware\CommandBus` |
| Config key | `Webware\CommandBus\CommandBusInterface::class` |

---

## Architecture Overview

The CommandBus is a **middleware pipeline** (structurally identical to Mezzio's HTTP pipeline). A command enters `CommandBus::handle()`, traverses each `MiddlewareInterface` in priority order, and the terminal middleware (`CommandHandlerMiddleware`) resolves and invokes the mapped handler. The result propagates back through the pipeline as a `CommandResultInterface`.

```
CommandBus::handle($command)
  → MiddlewarePipe::process($command, EmptyPipelineHandler)
    → PreHandleMiddleware (priority 100, from commandbus-event)
    → ... custom middleware ...
    → CommandHandlerMiddleware (priority 1, terminal)
      → CommandHandlerResolver::resolve($command)
      → $handler->handle($command) → CommandResult
    → PostHandleMiddleware (priority -100, from commandbus-event)
```

---

## Core Contracts

### `CommandInterface`
Marker interface. Every command implements it — no methods required.

```php
final readonly class UploadManifestCommand implements CommandInterface
{
    public function __construct(
        public ParsedManifest $parsed,
        public int $userId,
        public string $csvPath,
    ) {}
}
```

### `CommandHandlerInterface`
```php
interface CommandHandlerInterface
{
    public function handle(CommandInterface $command): CommandResultInterface;
}
```
Every handler is **1:1** with a command. One command, one handler — no exceptions.

### `CommandResult` + `CommandStatus`
```php
enum CommandStatus { case Success; case Failure; }

final readonly class CommandResult implements CommandResultInterface
{
    public function __construct(
        private CommandInterface $command,
        private CommandStatus $status,
        private mixed $result,          // any payload — int ID, entity, null, etc.
    ) {}
}
```
Handlers **always** return a `CommandResult`. Never throw instead of returning `Failure`.

### `MiddlewareInterface`
```php
interface MiddlewareInterface
{
    public function process(
        CommandInterface $command,
        CommandHandlerInterface $handler,
    ): CommandResultInterface;
}
```
Identical pattern to PSR-15. Call `$handler->handle($command)` to continue the pipeline. Return without calling it to short-circuit.

---

## Command Map Registration

Commands are mapped to handlers in config. The `CommandHandlerResolver` reads `config[CommandBusInterface::class]['command_map']`.

**Pattern — register in your module's `ConfigProvider`:**
```php
use Webware\CommandBus\CommandBusInterface;
use Webware\CommandBus\ConfigProvider as BusProvider;

public function __invoke(): array
{
    return [
        'dependencies'             => $this->getDependencies(),
        CommandBusInterface::class => [
            BusProvider::COMMAND_MAP_KEY => $this->getCommandMap(),
        ],
    ];
}

private function getCommandMap(): array
{
    return [
        UploadManifestCommand::class   => UploadManifestHandler::class,
        ProcessManifestCommand::class  => ProcessManifestHandler::class,
    ];
}
```

The `command_map` config is **merged** across all `ConfigProvider` instances. Each module registers only its own commands.

---

## Adding CommandBus Middleware

Middleware is registered in config under `middleware_pipeline`. Priority controls order — higher runs earlier.

```php
use Webware\CommandBus\CommandBusInterface;
use Webware\CommandBus\ConfigProvider as BusProvider;

CommandBusInterface::class => [
    BusProvider::MIDDLEWARE_PIPELINE_KEY => [
        [
            'middleware' => OwnershipGuardMiddleware::class,
            'priority'   => 50,  // after PreHandleEvent (100), before handler (1)
        ],
    ],
],
```

`CommandHandlerMiddleware` (priority 1) is always the terminal middleware — do not replace it.

---

## DI Wiring

Handlers must be registered as services. Use `InvokableFactory` or a dedicated factory:

```php
'factories' => [
    UploadManifestHandler::class  => UploadManifestHandlerFactory::class,
    ProcessManifestHandler::class => InvokableFactory::class,
],
```

Custom `MiddlewareInterface` implementations also need factory registration:

```php
'factories' => [
    OwnershipGuardMiddleware::class => OwnershipGuardMiddlewareFactory::class,
],
```

And a pipeline entry (see above).

---

## Consuming `CommandResultInterface` in Request Handlers

```php
$result = $this->commandBus->handle($command);

if ($result->getStatus() === CommandStatus::Failure) {
    // set failure WriteResult attribute, return $handler->handle($request)
}

$id = $result->getResult(); // e.g. newly inserted int ID
```

---

## Critical Rules

- **1:1 command/handler** — one command class maps to exactly one handler. No handler handles multiple commands.
- **Handlers are stateless** — they are shared services. Never store per-request state on a handler.
- **Commands are value objects** — use `readonly` properties. No setters.
- **Always return `CommandResult`** — never throw from a handler to signal business failure; return `CommandStatus::Failure` with a meaningful `$result` payload instead.
- **`CommandHandlerMiddleware` is terminal** — it does not call `$handler->handle()` after executing the real handler. Custom middleware placed after it (lower priority) will only see the result on the way back.
- **Do not call `CommandBus::handle()` from within a handler** — no nested bus dispatching.
