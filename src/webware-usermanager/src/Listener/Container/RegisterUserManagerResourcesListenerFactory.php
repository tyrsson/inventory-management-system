<?php

declare(strict_types=1);

namespace Webware\UserManager\Listener\Container;

use Psr\Container\ContainerInterface;
use Webware\UserManager\Container\Configuration;
use Webware\UserManager\Listener\RegisterUserManagerResourcesListener;

final readonly class RegisterUserManagerResourcesListenerFactory
{
    public function __invoke(ContainerInterface $container): RegisterUserManagerResourcesListener
    {
        $routeNamePrefix = Configuration::getRouteNamePrefix($container, self::class);

        return new RegisterUserManagerResourcesListener(
            $routeNamePrefix,
        );
    }
}
