<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\Middleware\Container;

use Mezzio\Router\RouteCollectorInterface;
use Psr\Container\ContainerInterface;
use Webware\Acl\Admin\Middleware\BuildAccessControlMiddleware;
use Webware\Acl\AssertionManager;
use Webware\Acl\Repository\RuleRepository;

final class BuildAccessControlMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): BuildAccessControlMiddleware
    {
        return new BuildAccessControlMiddleware(
            $container->get(RuleRepository::class),
            $container->get(RouteCollectorInterface::class),
            $container->get(AssertionManager::class),
        );
    }
}
