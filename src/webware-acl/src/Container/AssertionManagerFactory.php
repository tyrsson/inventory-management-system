<?php

declare(strict_types=1);

namespace Webware\Acl\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\AssertionManager;

final readonly class AssertionManagerFactory
{
    public function __invoke(ContainerInterface $container): AssertionManager
    {
        $config = Configuration::getAssertionManagerConfig($container, self::class);

        return new AssertionManager($container, $config);
    }
}
