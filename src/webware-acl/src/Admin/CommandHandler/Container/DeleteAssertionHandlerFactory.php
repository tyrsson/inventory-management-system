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

namespace Webware\Acl\Admin\CommandHandler\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\Admin\CommandHandler\DeleteAssertionHandler;
use Webware\Acl\AclInterface;

final class DeleteAssertionHandlerFactory
{
    public function __invoke(ContainerInterface $container): DeleteAssertionHandler
    {
        $config = $container->get('config');

        return new DeleteAssertionHandler(
            $config[AclInterface::class] ?? [],
        );
    }
}
