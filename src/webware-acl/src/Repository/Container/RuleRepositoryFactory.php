<?php

declare(strict_types=1);

namespace Webware\Acl\Repository\Container;

use PhpDb\Adapter\AdapterInterface;
use Psr\Container\ContainerInterface;
use Webware\Acl\Repository\RuleRepository;

final class RuleRepositoryFactory
{
    public function __invoke(ContainerInterface $container): RuleRepository
    {
        return new RuleRepository(
            $container->get(AdapterInterface::class),
        );
    }
}
