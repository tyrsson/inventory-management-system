<?php

declare(strict_types=1);


namespace Webware\Acl\Admin\Middleware\Container;

use Mezzio\Router\RouteCollectorInterface;
use Psr\Container\ContainerInterface;
use Webware\Acl\Admin\Middleware\BuildAccessControlMiddleware;
use Webware\Acl\AclInterface;
use Webware\Acl\AssertionManager;

final class BuildAccessControlMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): BuildAccessControlMiddleware
    {
        $config = $container->get('config');

        return new BuildAccessControlMiddleware(
            $config[AclInterface::class] ?? [],
            $container->get(RouteCollectorInterface::class),
            $container->get(AssertionManager::class),
        );
    }
}
