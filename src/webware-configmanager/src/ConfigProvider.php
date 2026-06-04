<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use Webware\ConfigManager\Event\ConfigBustCacheEvent;
use Webware\ConfigManager\Event\ConfigSaveEvent;
use Webware\ConfigManager\Listener\CacheBustListener;
use Webware\ConfigManager\Listener\ConfigSaveListener;
use Webware\ConfigManager\Listener\Container\CacheBustListenerFactory;
use Webware\ConfigManager\Listener\Container\ConfigSaveListenerFactory;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'listeners'    => $this->getListeners(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'aliases'    => [],
            'delegators' => [],
            'factories'  => [
                ConfigSaveListener::class => ConfigSaveListenerFactory::class,
                CacheBustListener::class  => CacheBustListenerFactory::class,
            ],
        ];
    }

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
}
