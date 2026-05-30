<?php

declare(strict_types=1);

namespace Webware\Acl\Middleware\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\AclInterface;
use Webware\Acl\Middleware\AclMiddleware;

final class AclMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): AclMiddleware
    {
        return new AclMiddleware(
            $container->get(AclInterface::class)
        );
    }
}
