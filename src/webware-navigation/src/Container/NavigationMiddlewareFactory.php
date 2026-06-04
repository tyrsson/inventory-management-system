<?php

declare(strict_types=1);

namespace Webware\Navigation\Container;

use Laminas\View\HelperPluginManager;
use Psr\Container\ContainerInterface;
use Webware\Navigation\Middleware\NavigationMiddleware;
use Webware\Navigation\View\Helper\Navigation;

final class NavigationMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): NavigationMiddleware
    {
        /** @var HelperPluginManager $helpers */
        $helpers = $container->get(HelperPluginManager::class);

        return new NavigationMiddleware(
            helper: $helpers->get(Navigation::class),
        );
    }
}
