<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Listener;

use Laminas\ConfigAggregator\ArrayProvider;
use Webimpress\SafeWriter\Exception\ExceptionInterface as FileWriterException;
use Webware\ConfigManager\ConfigWriter;
use Webware\ConfigManager\Event\ConfigSaveEvent;

final class ConfigSaveListener
{
    public function __construct(
        private readonly array $config,
    ) {}

    public function __invoke(ConfigSaveEvent $event): void
    {
        try {
            $configWriter = new ConfigWriter([
                new ArrayProvider([$event->target => $this->config[$event->target] ?? []]),
                new ArrayProvider([$event->target => $event->updatedConfig]),
            ]);
            $configWriter->writeConfig($event->targetFile);
        } catch (FileWriterException $e) {
            $event->stopPropagation();
            throw $e;
        }

        // In debug mode stop propagation — caller must not bust cache
        if ($this->config['debug'] ?? false) {
            $event->stopPropagation();
        }
    }
}
