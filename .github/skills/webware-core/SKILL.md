---
name: "webware-core"
description: "Load when writing or reviewing any code that uses Webware\\Core primitives: HttpMethodProcessorTrait, WriteResult, or any webware-core utility. Also load when you need to understand how HTTP-method dispatch is handled in middleware across the webware ecosystem."
argument-hint: "<what you are implementing — e.g. 'POST middleware', 'method-dispatching middleware', 'WriteResult read in handler'>"
---

> ⚠ **SKILL INTEGRITY — NEVER REMOVE OR SHORTEN**
> Content in this file may only be **added to or updated**. Removing or shortening existing sections is not permitted without explicit user approval. If you are adding new knowledge, append it as a new section.

## Package Identity

| Item | Value |
|---|---|
| PHP namespace | `Webware\Core` |
| Package root | `src/webware-core/src/` |
| Future package name | `webware/core` |

---

## HttpMethodProcessorTrait

Provides a `process()` implementation for `MiddlewareInterface` that dispatches to a typed method per HTTP verb. Eliminates `if/elseif` chains and enforces the method-per-verb pattern.

### Usage

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Core\HttpMethodProcessorTrait;

final class ProcessManifestUploadMiddleware implements MiddlewareInterface
{
    use HttpMethodProcessorTrait;

    public function __construct(
        private readonly ManifestRepositoryInterface $manifests,
        private readonly ManifestCsvParser $parser,
    ) {}

    // Only override the verbs you handle.
    // GET, PATCH, DELETE fall through to $handler->handle($request) by default.

    public function processPost(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // … parse, persist, set WriteResult attribute …
        return $handler->handle($request->withAttribute(WriteResult::Success->value, $success));
    }
}
```

### Default method behaviour

| Method | Default behaviour |
|---|---|
| `processGet` | `return $handler->handle($request)` — pass through |
| `processPost` | `return $handler->handle($request)` — pass through |
| `processPatch` | `return $handler->handle($request)` — pass through |
| `processDelete` | `return $handler->handle($request)` — pass through |

Override only what you need. Unsupported verbs throw `DomainException`.

### Rules

- **Never implement `process()` directly** when using the trait — it is provided by the trait and will conflict.
- **Do not use `$request->getMethod()`** inside the middleware class body — the trait handles dispatch.
- `PUT` is aliased to `processPatch`.
- The trait implements `#[Override]` on `process()` — do not add your own `#[Override]` to `process()`.

---

## WriteResult Enum

`Webware\Acl\Admin\WriteResult` is the standard PSR-7 attribute contract for communicating write outcome from a `Process*Middleware` to its downstream handler.

```php
namespace Webware\Acl\Admin;

enum WriteResult: string
{
    case Success = 'webware_acl.write_result.success';
    case Failure = 'webware_acl.write_result.failure';
}
```

### Middleware sets it

```php
$success = false;

try {
    $this->repository->save($data);
    $success = true;
    $messenger?->success('Saved.');
} catch (Throwable $e) {
    $messenger?->error('Save failed: ' . $e->getMessage());
}

return $handler->handle(
    $request->withAttribute(WriteResult::Success->value, $success)
);
```

### Handler reads it

```php
public function handle(ServerRequestInterface $request): ResponseInterface
{
    $roles = $this->repository->fetchRoles();

    $response = new HtmlResponse($this->template->render('acl::admin-roles', [
        'roles' => $roles,
    ]));

    if ($request->getAttribute(WriteResult::Success->value) === true) {
        // Close modal, refresh table, etc.
        $response = $response->withHeader(
            Header::Trigger->value,
            json_encode(['closeModal' => null])
        );
    }

    return $response;
}
```

### Notes

- Always compare with `=== true` — the attribute may be absent (`null`) on GET requests where no write occurred.
- The handler always re-fetches data from the repository after a write — never pass entity state through the attribute; pass the scalar outcome only.
- `WriteResult::Failure` is set when a write was attempted and failed. Absence of the attribute means no write was attempted (e.g. a GET request).

---

## SystemMessengerInterface — Toast Notifications from Middleware

`Axleus\Message\SystemMessengerInterface` is the standard way for middleware to queue user-facing feedback without returning an HTML response.

```php
/** @var \Axleus\Message\SystemMessengerInterface|null $messenger */
$messenger = $request->getAttribute(SystemMessengerInterface::class);

$messenger?->success('Manifest imported — 38 items added.');
$messenger?->warning('3 unknown SKUs were added to the catalogue.');
$messenger?->danger('Could not parse the CSV file: ' . $e->getMessage());
```

Rules:
- Always null-safe (`?->`). The messenger is set by an upstream middleware; treat its absence as graceful degradation.
- Call from middleware only — handlers should not enqueue messages directly.
- Messages appear in the HTMX-persistent toast container in the layout (`#systemMessage`). They survive navigation swaps because the container is outside `<main>`.
