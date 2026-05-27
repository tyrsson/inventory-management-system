# mezzio-async Project Guidelines

> **⚠ NOT THE ACTIVE RUNTIME (as of April 28, 2026)**
>
> TrueAsync (`php-async` extension) and `mezzio-async` have been **removed from the active
> application stack** due to current usability issues (SIGABRT crashes, devcontainer
> instability). The `src/mezzio-async/` package is **retained for future reintegration**.
>
> **Active runtime — verified from source files, not assumed:**
> - Web server: **PHP built-in** (`php -S 0.0.0.0:8080 -t public/`) — confirmed by `public/index.php` (`PHP_SAPI === 'cli-server'`)
> - Container image: `php:latest` (NOT `php:fpm`) — confirmed by `.devcontainer/docker/php/Dockerfile`
> - Container command: `sleep infinity` (VS Code attaches) — confirmed by `.devcontainer/docker-compose.yml`
> - **No PHP-FPM. No nginx.** Both absent from `.devcontainer/docker-compose.yml`.
> - Port 8080 forwarded — confirmed by `.devcontainer/devcontainer.json`
>
> **The guidelines below describe `src/mezzio-async/` — a retained package, not the running server.**

## What This Project Is

`mezzio-async` provides a Mezzio framework integration for **PHP TrueAsync** (`php-async`
extension, PHP 8.6+). It enables Mezzio applications to run as long-lived CLI processes
with a coroutine-per-connection, non-blocking HTTP server — no Swoole, no FrankenPHP.

TrueAsync API reference: `docs/planning/php-async-api.md`.

---

## Current Implementation Status

The core server is **working end-to-end**. The following components exist in
`src/mezzio-async/src/`:

```
ConfigProvider.php                 ← DI entry point (CLI-only dependencies)
Runner/
  AsyncRunner.php                  ← implements RequestHandlerRunnerInterface; handles connections
  AsyncRunnerFactory.php
Http/
  Server.php                       ← TCP socket, scheduler entry, Scope, accept loop, signals, hot-reload
  ServerFactory.php
  RequestParser.php                ← fread-based chunk accumulator → PSR-7
  RequestParserFactory.php
  ResponseEmitter.php              ← fwrite-based HTTP/1.1 serialiser
  ResponseEmitterFactory.php
  ServerRequestFactory.php         ← invokable; builds Laminas\Diactoros\ServerRequest
  ServerRequestFactoryFactory.php
  StaticFileHandler.php            ← serves public/ assets, path traversal protected
  StaticFileHandlerFactory.php
Log/
  LoggerDelegator.php              ← adds StreamHandlers to file + stderr
HotCodeReload/
  Watcher.php                      ← inotifywait subprocess; triggers restart on .php/.phtml change
  WatcherFactory.php
```

**Not yet implemented** (planned, do not fabricate):
- `Command/` (Start, Stop, Reload, Status)
- `Event/` (PSR-14 lifecycle events and listeners)
- `PidManager`

---

## Architecture

- **Single-process, single-thread, many coroutines.** No worker processes, no master/manager.
- **`Http\Server`** owns the TCP socket, TrueAsync scheduler entry, `Async\Scope` lifecycle,
  accept loop, and signal handling. It exposes a single `listen(callable $connectionHandler)` method.
- **`AsyncRunner`** is a thin Mezzio integration layer. `run()` calls `$this->server->listen($this->handleConnection(...))`. All connection logic (parse, dispatch, emit, log) lives in `handleConnection()`.
- **`Async\Scope`** owns all connection coroutines. Cancelling the scope shuts the server down.
- **No FrankenPHP**, no Swoole. The HTTP server uses `socket_create` with `SO_REUSEPORT`
  exported to a stream via `socket_export_stream`, then `stream_socket_accept` — automatically
  non-blocking inside TrueAsync coroutines. `SO_REUSEPORT` is required for hot-reload so the
  restarted process can bind the port while the old socket still exists in the kernel.
- **`await(spawn(...))`** is required to enter the TrueAsync scheduler from the CLI entry
  point. The entire server lifecycle runs inside `Http\Server::listen()`.

---

## Code Conventions

### Namespace and Package
```
Package:   webware/mezzio-async
Namespace: Mezzio\Async
Config key: mezzio-async
CLI prefix: mezzio:async:*
```

### PHP Style
- `declare(strict_types=1)` in every file
- Constructor property promotion for readonly services
- `final` classes wherever inheritance is not explicitly designed for
- Typed properties throughout; no `mixed` unless required by interface contract

### Dependency Injection
- Every service has a corresponding `*Factory` class
- Factories receive `Psr\Container\ContainerInterface $container` as sole argument
- No service locator anti-pattern — inject all dependencies via constructor
- Register everything in `ConfigProvider::getDependencies()`
- `ConfigProvider::__invoke()` returns CLI dependencies only when `PHP_SAPI === 'cli'`

### Config Structure
```php
// mezzio-async.local.php
return [
    'mezzio-async' => [
        'http-server' => [
            'host' => '0.0.0.0',
            'port' => 80,
        ],
        'log_dir' => 'data/psr/log',   // optional; default is data/psr/log
    ],
];
```
`ServerFactory` reads `mezzio-async.http-server` first, then falls back to
the flat `mezzio-async` array. Default host `0.0.0.0`, port `8080`.

### Testing
- PHPUnit 11 (`phpunit.xml.dist`)
- Test namespace: `MezzioTest\Async\`
- Test directory: `test/MezzioTest/Async/`
- All new classes must have unit tests
- Integration tests requiring the extension must be skipped:
  `$this->markTestSkipped('true_async extension not available')`

---

## Critical Patterns

### Entering the Scheduler
The CLI entry point is synchronous PHP. Always wrap the entire server in `await(spawn(...))`:
```php
await(spawn(function () use ($server): void {
    // everything here runs inside TrueAsync
}));
```

### Accept Loop
```php
$scope->spawn(function () use ($server, $scope): void {
    try {
        while (true) {
            $peerName = '';
            $conn = @stream_socket_accept($server, -1, $peerName);
            if ($conn === false) {
                break; // scope cancelled
            }
            $scope->spawn($this->handleConnection(...), $conn, $peerName);
        }
    } finally {
        fclose($server);
    }
});
```
- `stream_socket_accept` takes **positional arguments only** — no named args
- `@` suppresses the warning emitted when accept is interrupted by cancellation
- The server socket is closed in `finally` of the accept coroutine

### Connection Handling — Parse Outside Logging try/finally
Browser speculative pre-connects open TCP without sending data; `parse()` returns `null`.
Parse must happen **outside** the logging `try/finally` so null connections are silently
dropped with no log entry:

```php
private function handleConnection(mixed $conn, string $peerName): void
{
    // Parse OUTSIDE logging try/finally — null = speculative pre-connect, no log
    try {
        $request = $this->parser->parse($conn, $peerName);
    } catch (Throwable $e) {
        $this->logger->warning('Parse error from ' . $peerName, ['exception' => $e]);
        fclose($conn);
        return;
    }

    if ($request === null) {
        fclose($conn);
        return;
    }

    // Real request from here — always log it
    $method  = $request->getMethod();
    $target  = $request->getRequestTarget();
    $startNs = hrtime(true);
    $status  = 500;

    try {
        // dispatch ...
    } catch (Throwable $e) {
        // error handling ...
    } finally {
        if (is_resource($conn)) fclose($conn);
        $ms = round((hrtime(true) - $startNs) / 1_000_000, 2);
        $this->logger->info(sprintf('%s %s %d %sms %s', $method, $target, $status, $ms, $peerName));
    }
}
```

### Shutdown
```php
await_any_or_fail([signal(Signal::SIGTERM), signal(Signal::SIGINT)]);
$scope->cancel();
$scope->awaitAfterCancellation(errorHandler: fn($e) => $logger->error(...));
```

### Hot-Reload Restart
`HotCodeReload\Watcher` watches `src/` and `config/` using an `inotifywait` subprocess
(not `Async\FileSystemWatcher` — recursive mode is broken in the current build).

A `Channel(1)` races the signal listener against the file watcher. On file change:
1. Scope is cancelled and drained
2. **After** `await()` returns (scheduler fully exited), `fclose($server)` is called
3. `pcntl_exec(PHP_BINARY, $argv)` replaces the process

`pcntl_exec` **must** be outside `await(spawn(...))`. TrueAsync's io_uring layer holds
internal kernel references to the server socket fd that survive PHP's `fclose()`. Calling
exec inside the scheduler causes `Address already in use` in the new process.

The server socket uses `SO_REUSEPORT` (via `socket_create` + `socket_export_stream`) so
the new process can bind the port immediately without waiting for the old socket to release.

---

## TrueAsync Usage Rules

1. **Never share I/O resources across concurrent coroutines.** Use `Async\Pool` for DB/Redis.
2. **All I/O is automatically non-blocking inside coroutines.** Do not use `stream_set_blocking`.
3. **`finally` blocks always run**, even on cancellation — close sockets there.
4. **`protect(callable)`** — use for critical sections (DB transactions).
5. **`Async\Scope`** — always scope coroutines. Avoid `spawn()` without an owning scope.
6. **Services are shared across all requests.** Keep them stateless.
7. **`Async\Context`** — use for request-scoped data (request ID, auth user).

---

## Frontend / Template Rules

- **No inline styles.** Never write `style="..."` in any `.phtml` template. Add a named `.ims-*` CSS class to `public/assets/css/custom.css` instead.
- **No hardcoded URLs or asset paths.** Always use `$this->url('route.name')` and `$this->basePath()`.

## What to Avoid

- Do **not** add Swoole-specific classes or type hints (`Swoole\*`)
- Do **not** use FrankenPHP APIs
- Do **not** use `async`/`await` keywords (this is not JavaScript)
- Do **not** use ReactPHP, Amp, or other userland async libraries
- Do **not** create multi-process architectures — there is no master/worker split
- Do **not** call `pcntl_fork()` — not compatible with TrueAsync
- Do **not** use named arguments for `stream_socket_accept` — pass positionally

---

## Docker / Runtime

> **⚠ The Dockerfile and compose config below are for the `mezzio-async` package (future use).
> They are NOT the active devcontainer configuration.**
>
> Active devcontainer verified from `.devcontainer/docker-compose.yml` and
> `.devcontainer/docker/php/Dockerfile`: `php:latest`, `command: sleep infinity`, port 8080,
> no nginx, no PHP-FPM, no TrueAsync extension installed.

```dockerfile
# mezzio-async future target image (NOT active)
FROM trueasync/php-true-async:latest
WORKDIR /var/www/app
CMD ["php", "bin/mezzio-async", "start"]
```

`docker-compose.yml` mounts `.:/var/www/app` and maps port `80:80`.  
Logs appear in `docker compose logs -f php` (via `php://stderr` handler).  
Local log file: `data/psr/log/async.log`.

---

## Build and Test

```bash
composer install
./vendor/bin/phpunit
./vendor/bin/phpstan analyse
./vendor/bin/php-cs-fixer fix --dry-run
```

## Key References

- `docs/planning/php-async-api.md` — TrueAsync API quick reference
- https://true-async.github.io/en/docs.html — canonical TrueAsync documentation
- https://true-async.github.io/en/docs.html — canonical TrueAsync documentation
- Attached `mezzio-swoole/` workspace — the reference implementation to mirror

---

## ⚠ MANDATORY SESSION-START PROTOCOL — NOT OPTIONAL

At the start of **every session**, before writing or modifying any code, you **MUST**:

1. Read **all** documentation files in the following directories:
   - `docs/` (project-level planning and architecture docs)
   - `src/webware-acl/docs/`
   - `src/webware-admin/docs/`
   - `src/webware-navigation/docs/`
   - `src/webware-usermanager/docs/`
   - `src/ims-manifest/docs/`

2. Load **all** applicable skills from `.github/skills/` before starting any task. At minimum load:
   - `htmx-mezzio` — required for ANY handler, template, or HTMX interaction
   - `webware-module-architecture` — required for ANY handler, middleware, or route work
   - `webware-command-bus` — required for ANY command or handler work
   - `webware-coding-standard` — required for ALL PHP files

3. Check `/memories/session/` for any saved session state from a prior conversation.

**Skipping this protocol wastes the user's request budget and forces them to repeat context they have already provided. This is unacceptable.**

> ⚠ **THIS SECTION MUST NEVER BE REMOVED, SHORTENED, OR MODIFIED FOR ANY REASON.**
> It exists because it was removed or lost in a prior session, costing significant user request budget.
> Any agent or process that removes or alters this section is in violation of the project's instructions.
