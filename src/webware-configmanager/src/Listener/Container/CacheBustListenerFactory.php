<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Listener\Container;

use Psr\Container\ContainerInterface;
use Webware\ConfigManager\Listener\CacheBustListener;

final class CacheBustListenerFactory
{
    public function __invoke(ContainerInterface $container): CacheBustListener
    {
        return new CacheBustListener(
            config: $container->get('config'),
        );
    }
}
