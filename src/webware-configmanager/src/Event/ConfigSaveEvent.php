<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Event;

use Psr\EventDispatcher\StoppableEventInterface;

use function basename;

final class ConfigSaveEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function __construct(
        public readonly string $target,
        public readonly string $targetFile,
        public readonly array $updatedConfig,
        public readonly string $targetCache = ConfigBustCacheEvent::DEFAULT_CACHE,
        public readonly bool $replace = false,
    ) {}

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function getTargetFileName(): string
    {
        return basename($this->targetFile);
    }
}
