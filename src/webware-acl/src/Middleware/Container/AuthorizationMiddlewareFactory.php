<?php

declare(strict_types=1);

namespace Webware\Acl\Middleware\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\AclInterface;
use Webware\Acl\Middleware\AuthorizationMiddleware;
use Webware\Acl\RequestHandler\ForbiddenHandlerInterface;

final class AuthorizationMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): AuthorizationMiddleware
    {
        $paramMap = $container->get('config')[AclInterface::class]['route_param_map'] ?? [];

        return new AuthorizationMiddleware(
            $container->get(AclInterface::class),
            $container->get(ForbiddenHandlerInterface::class),
            $paramMap,
        );
    }
}
