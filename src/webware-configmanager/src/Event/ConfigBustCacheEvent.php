<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Event;

use Psr\EventDispatcher\StoppableEventInterface;

final class ConfigBustCacheEvent implements StoppableEventInterface
{
    public const string DEFAULT_CACHE = __DIR__ . '/../../../../../data/cache/config-cache.php';

    private bool $propagationStopped = false;

    public function __construct(
        public readonly string $targetCache = self::DEFAULT_CACHE,
    ) {}

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
