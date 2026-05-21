<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Listener\Container;

use Psr\Container\ContainerInterface;
use Webware\ConfigManager\Listener\ConfigSaveListener;

final class ConfigSaveListenerFactory
{
    public function __invoke(ContainerInterface $container): ConfigSaveListener
    {
        return new ConfigSaveListener(
            config: $container->get('config'),
        );
    }
}
