<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Listener;

use Webware\ConfigManager\Event\ConfigBustCacheEvent;

use function realpath;
use function unlink;

final class CacheBustListener
{
    public function __construct(
        private readonly array $config,
    ) {}

    public function __invoke(ConfigBustCacheEvent $event): void
    {
        if ($this->config['debug'] ?? false) {
            return;
        }

        @unlink(realpath($event->targetCache));
    }
}
