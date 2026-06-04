<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\Dashboard\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\AclInterface;
use Webware\Acl\Admin\Dashboard\RegisterWidgetListener;
use Webware\Acl\Container\Configuration;
use Webware\Admin\Container\Configuration as AdminConfiguration;

use function rtrim;

final readonly class RegisterWidgetListenerFactory
{
    public function __invoke(ContainerInterface $container): RegisterWidgetListener
    {
        $resourceId = rtrim(
            AdminConfiguration::getAdminRouteNamePrefix($container, self::class)
            . Configuration::getAdminRouteNamePrefix($container, self::class),
            '.'
        );

        $config = $container->get('config');

        return new RegisterWidgetListener(
            $resourceId,
            $config[AclInterface::class] ?? [],
        );
    }
}
