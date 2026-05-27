<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\Middleware\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\Admin\Middleware\ProcessRuleMiddleware;
use Webware\CommandBus\CommandBusInterface;

final class ProcessRuleMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): ProcessRuleMiddleware
    {
        return new ProcessRuleMiddleware(
            $container->get(CommandBusInterface::class),
        );
    }
}
