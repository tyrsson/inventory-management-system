<?php

declare(strict_types=1);


namespace Webware\Admin\Container;

use Psr\Container\ContainerInterface;
use Webware\Admin\Listener\RegisterAdminRulesListener;

final class RegisterAdminRulesListenerFactory
{
    public function __invoke(ContainerInterface $container): RegisterAdminRulesListener
    {
        return new RegisterAdminRulesListener(
            routeNamePrefix: Configuration::getAdminRouteNamePrefix($container, self::class),
        );
    }
}
