<?php

declare(strict_types=1);

namespace Webware\Acl\Repository\Container;

use PhpDb\Adapter\AdapterInterface;
use Psr\Container\ContainerInterface;
use Webware\Acl\Repository\RoleRepository;

final class RoleRepositoryFactory
{
    public function __invoke(ContainerInterface $container): RoleRepository
    {
        return new RoleRepository(
            $container->get(AdapterInterface::class),
        );
    }
}
