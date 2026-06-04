<?php

declare(strict_types=1);

/**
 * This file is part of the Webware\Acl package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\Acl\Container;

use Mezzio\Router\RouteCollectorInterface;
use Psr\Container\ContainerInterface;
use Webware\Acl\Acl;
use Webware\Acl\Assertion\AssertionAggregateFactory;
use Webware\Acl\Repository\RoleRepository;
use Webware\Acl\Repository\RuleRepository;

final readonly class AclFactory
{
    public function __invoke(ContainerInterface $container): Acl
    {
        return new Acl(
            $container->get(RoleRepository::class),
            $container->get(RuleRepository::class),
            $container->get(AssertionAggregateFactory::class),
            $container->get(RouteCollectorInterface::class),
        );
    }
}
