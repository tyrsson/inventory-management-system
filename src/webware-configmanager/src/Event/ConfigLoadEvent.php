<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Event;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Stub — no listener registered in v1. Reserved for future use.
 */
final class ConfigLoadEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function __construct(
        public readonly string $path,
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
