<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Farmers Store Inventory package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\UserManager\Repository;

use PhpDb\Adapter\AdapterInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Webware\UserManager\Container\Configuration;
use Webware\ResultSet\WithRowDataPrototypeInterface;

final class UserRepositoryFactory
{
    /**
     * @todo Once phpdb readonly-clone support is merged, replace the manual
     *       hydrate() call in UserRepository with a HydratingResultSet using
     *       a constructor-aware hydrator and User::class as the row prototype.
     *       Pass the HydratingResultSet as the $resultSetPrototype argument to
     *       the TableGateway constructor here.
     */
    public function __invoke(ContainerInterface $container): UserRepository
    {
        $config = Configuration::getCredentialConfig($container, self::class);
        return new UserRepository(
            adapter: $container->get(AdapterInterface::class),
            dispatcher: $container->get(EventDispatcherInterface::class),
            userPrototype: $container->get(WithRowDataPrototypeInterface::class),
            credentialColumn: $config['username'],
        );
    }
}
