<?php

declare(strict_types=1);

namespace Webware\Acl\Http\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\AclInterface;
use Webware\Acl\Http\RouteResourceFactory;

final class RouteResourceFactoryFactory
{
    public function __invoke(ContainerInterface $container): RouteResourceFactory
    {
        $paramMap = $container->get('config')[AclInterface::class]['route_param_map'] ?? [];

        return new RouteResourceFactory($paramMap);
    }
}
