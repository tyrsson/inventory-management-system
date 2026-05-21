<?php

declare(strict_types=1);

namespace Webware\UserManager\Listener\Container;

use Psr\Container\ContainerInterface;
use Webware\UserManager\Container\Configuration;
use Webware\UserManager\Listener\RegisterUserManagerRulesListener;

final readonly class RegisterUserManagerRulesListenerFactory
{
    public function __invoke(ContainerInterface $container): RegisterUserManagerRulesListener
    {
        $routeNamePrefix = Configuration::getRouteNamePrefix($container, self::class);

        return new RegisterUserManagerRulesListener(
            $routeNamePrefix,
        );
    }
}
