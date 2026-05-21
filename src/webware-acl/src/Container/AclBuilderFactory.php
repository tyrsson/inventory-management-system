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

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Webware\Acl\AclBuilder;
use Webware\Acl\AclInterface;

final class AclBuilderFactory
{
    public function __invoke(ContainerInterface $container): AclBuilder
    {
        $config = $container->get('config');

        $events = $container->has(EventDispatcherInterface::class)
            ? $container->get(EventDispatcherInterface::class)
            : null;

        return new AclBuilder(
            config: $config[AclInterface::class] ?? [],
            events: $events,
        );
    }
}
