# ims-manifest — Upload Write Path

## Overview

CSV manifest upload is the only write operation in this module. It uses
`webware/command-bus` to decouple the HTTP layer from the persistence logic.

```
ProcessManifestUploadMiddleware  →  UploadManifestCommand  →  UploadManifestHandler  →  ManifestRepository
```

---

## Routes

| Method | Path | Route name | Handler |
|---|---|---|---|
| `GET` | `/manifests` | `manifest.list` | `ManifestListHandler` |
| `GET` | `/manifest/upload` | `manifest.upload` | `ManifestUploadHandler` (no-JS fallback) |
| `POST` | `/manifest/upload` | `manifest.upload.store` | `ProcessManifestUploadMiddleware` → `ManifestUploadHandler` |
| `GET` | `/manifest/{id}` | `manifest.detail` | `ManifestDetailHandler` |

The upload form is presented in a Bootstrap modal on the list page (`GET /manifests`).
`GET /manifest/upload` is retained as a no-JS fallback pending IT policy confirmation.

---

## Classes

| Class | Namespace | Role |
|---|---|---|
| `ProcessManifestUploadMiddleware` | `Ims\Manifest\Middleware` | Validates upload, parses CSV, persists file, dispatches command |
| `UploadManifestCommand` | `Ims\Manifest\Command` | Immutable value object carrying `ParsedManifest`, `userId`, `csvPath` |
| `UploadManifestHandler` | `Ims\Manifest\CommandHandler` | Calls `ManifestRepository::insertFromCsv()`; returns `CommandResult` |
| `ManifestUploadHandler` | `Ims\Manifest\RequestHandler` | Render-only; redirects on success, renders form on failure |

---

## Write Flow

```mermaid
sequenceDiagram
    participant Browser
    participant AuthMW as AuthorizationMiddleware
    participant ProcMW as ProcessManifestUploadMiddleware
    participant Bus as CommandBus
    participant CmdHandler as UploadManifestHandler
    participant Repo as ManifestRepository
    participant Handler as ManifestUploadHandler

    Browser->>AuthMW: POST /manifest/upload (multipart/form-data)
    AuthMW->>ProcMW: allowed → delegate

    ProcMW->>ProcMW: validate uploaded file (UPLOAD_ERR_OK)
    ProcMW->>ProcMW: parse received_date override (optional; blank = use CSV date)
    ProcMW->>ProcMW: moveTo(data/manifest/<tmp>.csv)
    ProcMW->>ProcMW: ManifestCsvParser::parse() → ParsedManifest
    ProcMW->>ProcMW: rename to data/manifest/store{N}_<uniqid>.csv
    ProcMW->>ProcMW: guard: parsed.items === [] → cleanupFile() + messenger danger + delegate

    ProcMW->>Bus: handle(UploadManifestCommand(parsed, userId, csvPath))
    Bus->>CmdHandler: UploadManifestHandler::handle()
    CmdHandler->>Repo: insertFromCsv(parsed, userId, csvPath)
    Repo-->>CmdHandler: manifestId (int)
    CmdHandler-->>Bus: CommandResult(Success, manifestId)
    Bus-->>ProcMW: CommandResult

    ProcMW->>Handler: request.withAttribute(CommandResult::class, result)
    Handler->>Browser: RedirectResponse /manifest/{id}

    Note over ProcMW: On any Throwable: cleanupFile() + log via request logger + generic danger toast
```

---

## Middleware — Key Decisions

- **File is written before the command fires** — `moveTo()` then renamed to
  `data/manifest/store{N}_<uniqid>.csv` once the store ID is known from the CSV.
- **`csvPath` is intentionally persisted** — stored in `manifest.csv_path` in the DB.
  It is retained until the manifest is fully processed; `unlink()` + null happens at
  `POST /manifest/{id}/finish`.
- **Cleanup on all failure paths** — `cleanupFile()` is called on empty-CSV early return
  and in the `catch (Throwable)` block. The catch uses `$finalPath ?? $tmpPath` to clean
  whichever path exists at the point of failure.
- **Logger is pulled from the request attribute** — `$request->getAttribute(LoggerInterface::class)`
  set by `Axleus\Log\Middleware\MonologMiddleware`. No constructor injection needed; the user's
  email is already attached via Monolog processor.
- **User ID from session detail** — `(int) $user->getDetail('id')`. Mezzio session stores
  `DefaultUser` (not the concrete entity); `$user->id` is always `null`. The `id` is set in
  `UserRepository::hydrate()` under the details array.
- **Empty CSV guard is pre-bus** — If the parser returns no items the middleware cleans up
  the file and returns early without dispatching a command.
- **`received_date` defaults to blank** — If omitted, `ManifestCsvParser` uses the date from
  the consignment header row in the CSV (`$receivedDateOverride ?? $receivedDate ?? new DateTimeImmutable()`).

---

## Command

```php
final readonly class UploadManifestCommand implements NamedCommandInterface
{
    use NamedCommandTrait;

    public function __construct(
        public ParsedManifest $parsed,
        public int $userId,
        public string $csvPath,
    ) {}
}
```

`NamedCommandTrait::getName()` returns `static::class` by default, which is
how the bus resolves the handler via the `command_map` in `ConfigProvider`.

---

## CommandHandler

```php
public function handle(CommandInterface $command): CommandResult
{
    assert($command instanceof UploadManifestCommand);

    $manifestId = $this->manifests->insertFromCsv($command->parsed, $command->userId, $command->csvPath);

    return new CommandResult($command, CommandStatus::Success, $manifestId);
}
```

`CommandResult::getResult()` carries the new `manifest.id` PK. The handler
does not catch exceptions — they propagate to the middleware's `catch (Throwable $e)` block.

---

## Request Handler (render-only)

```php
$commandResult = $request->getAttribute(CommandResult::class);

if ($commandResult instanceof CommandResult && $commandResult->getStatus() === CommandStatus::Success) {
    $manifestId = (int) $commandResult->getResult();
    return new RedirectResponse('/manifest/' . $manifestId);
}

// GET or failed POST — render the upload form
return new HtmlResponse($this->template->render('manifest::upload'));
```

On `CommandStatus::Success` the handler redirects to the manifest detail page
using the PK returned in `CommandResult::getResult()`. Any other case (GET
request or caught exception in middleware) renders the upload form.

---

## Schema — `manifest` table additions

```sql
csv_path VARCHAR(255) NULL COMMENT 'Relative path to the uploaded CSV file; null once processing is complete',
```

Set on insert by `insertFromCsv()`. Nulled out by the finish endpoint after `unlink()`.

---

## `displayId()` format

```
{storeId}-{mmdd}   e.g. 207-0427
```

The received year is always visible in context (the `receivedDate` field on the detail/list
card) so it is not included in the ID itself. Uniqueness across years is enforced by the
DB unique constraint on `(store_id, received_date)` if added, or handled operationally.

---

## DI Registration

`ConfigProvider::getBusConfig()` registers the command → handler mapping:

```php
BusProvider::COMMAND_MAP_KEY => [
    UploadManifestCommand::class => UploadManifestHandler::class,
],
```

`getDependencies()` registers `UploadManifestHandler` via its factory:

```php
CommandHandler\UploadManifestHandler::class => CommandHandler\Container\UploadManifestHandlerFactory::class,
```

The factory injects `ManifestRepositoryInterface`.
