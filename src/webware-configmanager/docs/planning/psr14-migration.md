# PSR-14 Migration Plan — `webware/configmanager`

**Date:** 2026-05-18
**Status:** Planning
**Authority:** This document supersedes any prior design notes for this package.

---

## 1. Current State (Laminas Event Manager)

| Class | Laminas EM coupling |
|---|---|
| `ConfigManager` | Extends `AbstractListenerAggregate`; `attach(EventManagerInterface, $priority)` registers two listeners on string event names |
| `ConfigEvent` | Extends `Laminas\EventManager\Event`; uses `setParam`/`getParam` for all data; inherits `stopPropagation()` / `propagationIsStopped()` from Laminas |
| `ConfigProvider::getListeners()` | Returns `[ConfigManager::class]` — Laminas listener aggregate format |
| `ConfigManagerFactory` | No coupling — no change needed |
| `ConfigWriter` | No coupling — no change needed |
| `ConfigWriterInterface` | No coupling — no change needed |
| `ConfigCannotBeWrittenException` | No coupling — no change needed |

---

## 2. Target State (PSR-14)

### 2.1 Event Model — one class per event type

The single `ConfigEvent` with three string name constants maps to **three concrete event classes**. PSR-14 dispatches by type, not by string name.

```
src/Event/
    ConfigSaveEvent.php          # replaces EVENT_CONFIG_SAVE
    ConfigBustCacheEvent.php     # replaces EVENT_BUST_CACHE
    ConfigLoadEvent.php          # replaces EVENT_CONFIG_LOAD (future use)
```

Each event implements `Psr\EventDispatcher\StoppableEventInterface` and carries its own typed properties instead of Laminas `setParam`/`getParam`.

**`ConfigSaveEvent`** — carries everything `onSaveConfig` needs:
```php
final class ConfigSaveEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function __construct(
        public readonly string $target,          // config provider class name (was Event::$target)
        public readonly string $targetFile,      // filename under config/autoload/
        public readonly array  $updatedConfig,
        public readonly string $targetCache = ConfigBustCacheEvent::DEFAULT_CACHE,
    ) {}

    public function stopPropagation(): void { $this->propagationStopped = true; }
    public function isPropagationStopped(): bool { return $this->propagationStopped; }
}
```

**`ConfigBustCacheEvent`** — carries what `onBustCache` needs:
```php
final class ConfigBustCacheEvent implements StoppableEventInterface
{
    public const string DEFAULT_CACHE = __DIR__ . '/../../../../../data/cache/config-cache.php';
    private bool $propagationStopped  = false;

    public function __construct(
        public readonly string $targetCache = self::DEFAULT_CACHE,
    ) {}

    public function stopPropagation(): void { $this->propagationStopped = true; }
    public function isPropagationStopped(): bool { return $this->propagationStopped; }
}
```

**`ConfigLoadEvent`** — placeholder; no listener in v1:
```php
final class ConfigLoadEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;
    public function __construct(public readonly string $path) {}
    public function stopPropagation(): void { $this->propagationStopped = true; }
    public function isPropagationStopped(): bool { return $this->propagationStopped; }
}
```

> **Note on `DEFAULT_CACHE` constant:** Currently lives on `ConfigEvent`. Move it to
> `ConfigBustCacheEvent` since that is its only consumer. `ConfigSaveEvent` references it
> for its default `$targetCache` argument.

---

### 2.2 Listener Model — split `ConfigManager` into two focused listeners

`ConfigManager` currently handles two distinct responsibilities via `AbstractListenerAggregate`.
With PSR-14 each listener is a single-purpose invokable. Split into:

```
src/
    Listener/
        ConfigSaveListener.php         # handles ConfigSaveEvent — was onSaveConfig
        CacheBustListener.php          # handles ConfigBustCacheEvent — was onBustCache
    Container/
        ConfigSaveListenerFactory.php
        CacheBustListenerFactory.php
```

`ConfigManager.php` is **deleted** — its logic moves entirely into the two listeners.

**`ConfigSaveListener`:**
```php
final class ConfigSaveListener
{
    public function __construct(private readonly array $config) {}

    public function __invoke(ConfigSaveEvent $event): void
    {
        try {
            $configWriter = new ConfigWriter([
                new ArrayProvider([$event->target => $this->config[$event->target]]),
                new ArrayProvider([$event->target => $event->updatedConfig]),
            ]);
            $targetFile = getcwd() . '/config/autoload/' . basename($event->targetFile);
            $configWriter->writeConfig($targetFile);
        } catch (FileWriterException $e) {
            $event->stopPropagation();
            throw $e;
        }

        // In debug mode stop propagation so cache is NOT busted
        if ($this->config['debug']) {
            $event->stopPropagation();
        }
    }
}
```

> **Design note on cache busting after save:** The old implementation registered
> `onBustCache` on the *same* `EVENT_CONFIG_SAVE` string event at priority 0 (runs after
> save). With PSR-14 and two distinct event types this self-chaining is gone. The caller
> (wizard / CLI seeder) is responsible for dispatching `ConfigBustCacheEvent` after
> dispatching `ConfigSaveEvent`. This is cleaner — the listener does not dispatch secondary
> events internally.

**`CacheBustListener`:**
```php
final class CacheBustListener
{
    public function __construct(private readonly array $config) {}

    public function __invoke(ConfigBustCacheEvent $event): void
    {
        if ($this->config['debug']) {
            return; // never bust cache in development mode
        }
        @unlink(realpath($event->targetCache));
    }
}
```

---

### 2.3 `ConfigProvider` — updated listener registration

`getListeners()` returns the `webware-event` PSR-14 format (matching existing modules):

```php
public function getListeners(): array
{
    return [
        ConfigSaveEvent::class      => [
            ['listener' => ConfigSaveListener::class, 'priority' => 1],
        ],
        ConfigBustCacheEvent::class => [
            ['listener' => CacheBustListener::class, 'priority' => 1],
        ],
    ];
}
```

`getDependencies()` factories updated:
```php
'factories' => [
    ConfigSaveListener::class  => Container\ConfigSaveListenerFactory::class,
    CacheBustListener::class   => Container\CacheBustListenerFactory::class,
],
```

`ConfigManager::class` factory entry removed.

---

### 2.4 `composer.json` dependency changes

| Action | Package |
|---|---|
| **Remove** | `laminas/laminas-eventmanager` |
| **Add** | `psr/event-dispatcher:^1.0` |
| **Add** | `webware/event` (our PSR-14 dispatcher — provides `EventDispatcherMiddleware`, listener wiring) |

---

## 3. Test Migration

### 3.1 `ConfigEventTest` → `ConfigSaveEventTest` + `ConfigBustCacheEventTest`

- No more `setParam`/`getParam` — test public readonly properties directly
- `isPropagationStopped()` replaces `propagationIsStopped()`
- Remove any Laminas Event Manager setup/mocking

### 3.2 `ConfigManagerTest` → `ConfigSaveListenerTest` + `CacheBustListenerTest`

Key changes:

| Old | New |
|---|---|
| `new ConfigManager($config)` | `new ConfigSaveListener($config)` / `new CacheBustListener($config)` |
| `$configManager->onSaveConfig($event)` | `($listener)($event)` |
| `$configManager->onBustCache($event)` | `($listener)($event)` |
| `new ConfigEvent(null, FooConfigProvider::class)` | `new ConfigSaveEvent(target: FooConfigProvider::class, ...)` |
| `$configEvent->propagationIsStopped()` | `$configEvent->isPropagationStopped()` |
| `$configEvent->setTargetFile(...)` | Constructor arg `targetFile:` |
| `$configEvent->setUpdatedConfig(...)` | Constructor arg `updatedConfig:` |
| `$configEvent->setTargetCache(...)` | Constructor arg `targetCache:` |

### 3.3 `ConfigWriterTest`

No changes needed — `ConfigWriter` has no Laminas EM coupling.

---

## 4. File Inventory — Before vs After

```
BEFORE                                  AFTER
------                                  -----
src/
  ConfigManager.php              →      DELETED
  ConfigManagerFactory.php       →      DELETED
  ConfigCannotBeWrittenException →      unchanged
  ConfigWriter.php               →      unchanged
  ConfigWriterInterface.php      →      unchanged
  ConfigProvider.php             →      updated (listeners, factories)
  Event/
    ConfigEvent.php              →      DELETED
                                        Event/ConfigSaveEvent.php       (new)
                                        Event/ConfigBustCacheEvent.php  (new)
                                        Event/ConfigLoadEvent.php       (new, stub)
                                        Listener/ConfigSaveListener.php (new)
                                        Listener/CacheBustListener.php  (new)
                                        Container/ConfigSaveListenerFactory.php (new)
                                        Container/CacheBustListenerFactory.php  (new)

test/
  unit/Event/ConfigEventTest.php →      unit/Event/ConfigSaveEventTest.php
                                        unit/Event/ConfigBustCacheEventTest.php
  integration/ConfigManagerTest  →      integration/ConfigSaveListenerTest.php
                                        integration/CacheBustListenerTest.php
  unit/ConfigWriterTest.php      →      unchanged
```

---

## 5. Decisions

1. **`ConfigLoadEvent`** — no listener implementation exists or is planned for v1. The event class is a stub only; no listener is registered. Revisit in v2.
2. **Caller responsibility for cache bust** — `ConfigSaveListener` remains pure (no injected dispatcher, no secondary dispatch). The caller (wizard or CLI seeder) is responsible for dispatching `ConfigBustCacheEvent` after a successful save.
3. **`webware/event` dependency** — will be declared as a published Composer package dependency (`webware/event`) once published. During development it is path-loaded via the workspace `composer.json` repositories block.
