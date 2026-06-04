<?php

declare(strict_types=1);

namespace Webware\Acl\Assertion;

use Psr\Container\ContainerInterface;
use Webware\Acl\AssertionManager;

final readonly class AssertionAggregateFactoryFactory
{
    public function __invoke(ContainerInterface $container): AssertionAggregateFactory
    {
        $assertionManager = $container->get(AssertionManager::class);

        return new AssertionAggregateFactory($assertionManager);
    }
}
