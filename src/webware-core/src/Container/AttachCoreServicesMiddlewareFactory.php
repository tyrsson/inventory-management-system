<?php

declare(strict_types=1);

namespace Webware\Core\Container;

use Laminas\InputFilter\InputFilterPluginManager;
use Psr\Container\ContainerInterface;
use Webware\Core\Middleware\AttachCoreServicesMiddleware;

final class AttachCoreServicesMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): AttachCoreServicesMiddleware
    {
        return new AttachCoreServicesMiddleware(
            $container->get(InputFilterPluginManager::class)
        );
    }
}
